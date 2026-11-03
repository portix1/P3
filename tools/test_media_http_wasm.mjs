// Isolated integration fixture. Requires @php-wasm/node and @php-wasm/universal.
// No production configuration/database is loaded; authentication context belongs to this fixture only.
import fs from 'node:fs';import os from 'node:os';import path from 'node:path';import assert from 'node:assert/strict';import {pathToFileURL} from 'node:url';
const runtimeRoot=process.env.PHP_WASM_NODE_MODULES;
if(!runtimeRoot)throw new Error('Set PHP_WASM_NODE_MODULES to a directory containing @php-wasm packages.');
const {PHP,setPhpIniEntries}=await import(pathToFileURL(path.join(runtimeRoot,'@php-wasm/universal/index.js')).href);
const {loadNodeRuntime,createNodeFsMountHandler}=await import(pathToFileURL(path.join(runtimeRoot,'@php-wasm/node/index.js')).href);
const source=path.resolve(path.dirname(new URL(import.meta.url).pathname),'..');
const fixture=fs.mkdtempSync(path.join(os.tmpdir(),'p3-media-http-'));
fs.mkdirSync(path.join(fixture,'var/modules'),{recursive:true});fs.cpSync(path.join(source,'var/modules/media'),path.join(fixture,'var/modules/media'),{recursive:true});
fs.mkdirSync(path.join(fixture,'includes/class'),{recursive:true});fs.copyFileSync(path.join(source,'includes/class/image_storage.class.php'),path.join(fixture,'includes/class/image_storage.class.php'));
const php=new PHP(await loadNodeRuntime(process.env.TEST_PHP_VERSION||'8.2',{emscriptenOptions:{processId:process.pid}}));
await setPhpIniEntries(php,{upload_max_filesize:'288M',post_max_size:'288M',memory_limit:'256M'});
await php.mount('/app',createNodeFsMountHandler(source));await php.mount('/fixture',createNodeFsMountHandler(fixture));
const setup=`require '/app/bootstrap/autoload.php';
$root='/fixture';$database=new Portix\\Infrastructure\\Database($root.'/database/test.sqlite');$pdo=$database->connection();
`;
async function code(snippet){const r=await php.run({code:'<?php '+setup+snippet});if(r.errors)throw new Error(r.errors);return r;}
function multipart(fields,file){const boundary='P3'+Math.random().toString(16).slice(2);let parts=[];
for(const [name,value] of Object.entries(fields))parts.push(Buffer.from('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"\r\n\r\n'+value+'\r\n'));
if(file)parts.push(Buffer.concat([Buffer.from('--'+boundary+'\r\nContent-Disposition: form-data; name="file"; filename="'+file.name+'"\r\nContent-Type: '+file.type+'\r\n\r\n'),file.bytes,Buffer.from('\r\n')]));
parts.push(Buffer.from('--'+boundary+'--\r\n'));return {body:Buffer.concat(parts),headers:{'Content-Type':'multipart/form-data; boundary='+boundary}};}
async function request(operation,fields={},file=null,role='admin',token='test'){
const input=multipart({operation,csrf_token:token,...fields},file);
const body=setup+`require_once '/app/includes/class/security.class.php';
$_SESSION=['csrf_token'=>'test'];$user=new Portix\\Domain\\UserAccount(['pseudo'=>'fixture','is_admin'=>${role==='admin'?'true':'false'},'is_redactor'=>${role==='redactor'?'true':'false'}]);
Portix\\Media\\MediaBridge::configure($pdo,$root,true);$argument=['Admin','media','api'];$requestId='fixture';require '/fixture/var/modules/media/config.php';`;
const r=await php.run({method:'POST',...input,code:'<?php '+body});
if(r.errors)throw new Error(r.errors);try{return {status:r.httpStatusCode,...JSON.parse(r.text)};}catch{throw new Error(r.text);}}
try{
await code(`foreach(['var/uploads/images','var/uploads/media','var/sys','var/templates','tmp'] as $d){if(!is_dir($root.'/'.$d))mkdir($root.'/'.$d,0755,true);}
$pdo->exec("CREATE TABLE modules(prefix TEXT,path TEXT,enabled INTEGER)");$pdo->exec("INSERT INTO modules VALUES('media','media',1)");
$pdo->exec('CREATE TABLE articles(id INTEGER PRIMARY KEY,body TEXT,status TEXT)');$pdo->exec('CREATE TABLE content_revisions(id INTEGER PRIMARY KEY,snapshot_json TEXT)');
(new Portix\\Module\\ModuleSchemaInstaller($database))->install('media','/fixture/var/modules/media/database');echo 'setup';`);
let response=await request('upload',{}, {name:'Plan.pdf',type:'application/pdf',bytes:Buffer.from('%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n')},'redactor');
assert(response.ok,JSON.stringify(response));const pdf=response.result;assert.equal(pdf.family,'pdf');assert(fs.existsSync(path.join(fixture,pdf.public_path)));console.log('PASS: real multipart upload by redactor');
response=await request('delete',{id:pdf.id},null,'redactor');assert.equal(response.ok,false);assert(fs.existsSync(path.join(fixture,pdf.public_path)));console.log('PASS: redactor deletion forbidden');
response=await request('delete',{id:pdf.id},null,'admin','wrong');assert.equal(response.ok,false);console.log('PASS: CSRF prevents mutation');
response=await request('upload',{}, {name:'Fake.pdf',type:'application/pdf',bytes:Buffer.from('<?php echo "bad";')});assert.equal(response.ok,false);console.log('PASS: real upload rejects fake PDF');
response=await request('replace',{id:pdf.id},{name:'Different.zip',type:'application/zip',bytes:Buffer.from('PK fake')});assert.equal(response.ok,false);console.log('PASS: replacement family mismatch rejected');
const old=fs.readFileSync(path.join(fixture,pdf.public_path));
response=await request('replace',{id:pdf.id},{name:'Plan2.pdf',type:'application/pdf',bytes:Buffer.from('%PDF-1.4\nUpdated\n%%EOF\n')});assert(response.ok,JSON.stringify(response));assert.notDeepEqual(fs.readFileSync(path.join(fixture,pdf.public_path)),old);console.log('PASS: replacement retains public path');
response=await request('restore',{id:pdf.id});assert(response.ok,JSON.stringify(response));assert.deepEqual(fs.readFileSync(path.join(fixture,pdf.public_path)),old);console.log('PASS: restore previous bytes');
await code(`$q=$pdo->prepare('INSERT INTO articles VALUES(1,:body,:status)');$q->execute(['body'=>${JSON.stringify('<a href="'+pdf.public_path+'">PDF</a>')},'status'=>'draft']);`);
response=await request('delete',{id:pdf.id,confirm_history:'1'});assert.equal(response.ok,false);console.log('PASS: final server scan blocks current use');
await code(`$pdo->exec('DELETE FROM articles');$q=$pdo->prepare('INSERT INTO content_revisions VALUES(1,:json)');$q->execute(['json'=>${JSON.stringify(JSON.stringify({body:'<a href="'+pdf.public_path+'">PDF</a>'}))}]);`);
response=await request('delete',{id:pdf.id});assert.equal(response.ok,false);response=await request('delete',{id:pdf.id,confirm_history:'1'});assert(response.ok,JSON.stringify(response));assert(!fs.existsSync(path.join(fixture,pdf.public_path)));console.log('PASS: historic deletion requires explicit confirmation');
// Real image upload and dimension-changing replacement.
await code(`$im=imagecreatetruecolor(12,8);imagepng($im,'/fixture/initial.png');imagedestroy($im);`);
const png=fs.readFileSync(path.join(fixture,'initial.png'));
response=await request('upload',{}, {name:'Photo.png',type:'image/png',bytes:png},'redactor');assert(response.ok,JSON.stringify(response));const photo=response.result;assert.equal(photo.family,'image');assert.equal(photo.created_by,'fixture');
await code(`$im=imagecreatetruecolor(20,10);imagepng($im,'/fixture/replacement.png');imagedestroy($im);`);
const newImage=fs.readFileSync(path.join(fixture,'replacement.png'));
response=await request('replace',{id:photo.id},{name:'Photo2.png',type:'image/png',bytes:newImage});assert.equal(response.ok,false);assert.match(response.error,/Dimensions différentes/);
response=await request('replace',{id:photo.id,confirm_dimensions:'1'},{name:'Photo2.png',type:'image/png',bytes:newImage});assert(response.ok,JSON.stringify(response));console.log('PASS: image replacement needs dimension confirmation');
response=await request('upload',{}, {name:'Huge.pdf',type:'application/pdf',bytes:Buffer.concat([Buffer.from('%PDF-'),Buffer.alloc(20000000)])});assert.equal(response.ok,false);assert.match(response.error,/20 Mo/);console.log('PASS: application rejects document over 20,000,000 bytes');
console.log('Real PHP multipart API fixture complete.');
}finally{php.exit();fs.rmSync(fixture,{recursive:true,force:true});}
