// Isolated real PHP SAPI requests; no production database or settings are used.
import fs from 'node:fs';import os from 'node:os';import path from 'node:path';import assert from 'node:assert/strict';import {pathToFileURL,fileURLToPath} from 'node:url';
const runtimeRoot=process.env.PHP_WASM_NODE_MODULES;if(!runtimeRoot)throw new Error('Set PHP_WASM_NODE_MODULES');
const {PHP}=await import(pathToFileURL(path.join(runtimeRoot,'@php-wasm/universal/index.js')).href);
const {loadNodeRuntime,createNodeFsMountHandler}=await import(pathToFileURL(path.join(runtimeRoot,'@php-wasm/node/index.js')).href);
const source=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../../../..');
const fixture=fs.mkdtempSync(path.join(os.tmpdir(),'p3-audience-http-'));fs.mkdirSync(path.join(fixture,'var/sys'),{recursive:true});
fs.writeFileSync(path.join(fixture,'var/sys/insights.json'),JSON.stringify({mode:'estimated',secret:'a'.repeat(64),last_purge_day:new Date().toISOString().slice(0,10)}));
const php=new PHP(await loadNodeRuntime(process.env.TEST_PHP_VERSION||'8.2',{emscriptenOptions:{processId:process.pid}}));
await php.mount('/app',createNodeFsMountHandler(source));await php.mount('/fixture',createNodeFsMountHandler(fixture));
const setup=`require '/app/bootstrap/autoload.php';require '/app/includes/class/settings.class.php';
chdir('/fixture');$database=new Portix\\Infrastructure\\Database('/fixture/database/test.sqlite');
(new Portix\\Infrastructure\\Migrator($database))->migrate();
$repositories=new Portix\\Repository\\RepositoryRegistry($database);
$_SERVER['SCRIPT_NAME']='/test/index.php';$_SERVER['HTTPS']='on';
$request=Portix\\Http\\Request::fromGlobals();$route='news';
$pageMetadata=new Portix\\Seo\\PageMetadata('Fixture','','https://example.test/test');$pageMetadata->define('Fixture');
$user=new class {public function isAdmin():bool{return ($_SERVER['HTTP_X_ROLE']??'')==='admin';}public function isRedactor():bool{return false;}};
`;
async function request(headers={},method='GET'){
 const r=await php.run({code:'<?php '+setup+`include '/app/var/modules/insights/index.php';$insightsTracker();echo json_encode(['audience'=>$insightsAudience->summary(30),'totals'=>$repositories->insights()->totals(30)]);`,relativeUri:'/test/news.html',method,headers:{'User-Agent':'Mozilla/5.0 Firefox/153',...headers}});
 if(r.errors)throw new Error(r.errors);return {headers:r.headers,...JSON.parse(r.text)};
}
async function dashboard(){
 const code=`<?php ${setup}
 require '/app/includes/class/security.class.php';
 include '/app/var/modules/insights/index.php';
 $_LANG=[];require '/app/var/modules/insights/lang/fr.php';$translator=new Portix\\Support\\Translator($_LANG);
 $requestId='fixture';$argument=['Admin','insights','dashboard'];
 $template=new class {public array $values=[];public function set(string $key,mixed $value):void{$this->values[$key]=$value;}public function setSlot(string $key,string $value):void{}public function addRow(string $key,array $value):void{}};
 include '/app/var/modules/insights/config.php';echo json_encode($template->values);`;
 const r=await php.run({code,relativeUri:'/test/Admin-insights.html',method:'GET',headers:{'X-Role':'admin'}});if(r.errors)throw new Error(r.errors);return JSON.parse(r.text);
}
const cookieOf=r=>{const h=Object.entries(r.headers).find(([k])=>k.toLowerCase()==='set-cookie');return h?(Array.isArray(h[1])?h[1][0]:h[1]):'';};
try{
 const empty=await dashboard();assert.equal(empty.insights_unique_visitors,'Non mesuré');assert.equal(empty.insights_pages_per_visitor,'Non mesuré');console.log('PASS real dashboard: absent data is not shown as zero');
 let r=await request();let cookie=cookieOf(r);assert.match(cookie,/portix_visitor=[a-f0-9]{32}/);assert.match(cookie,/path=\/test\//i);assert.match(cookie,/httponly/i);assert.match(cookie,/secure/i);assert.match(cookie,/samesite=lax/i);assert.equal(r.audience.unique,1);assert.equal(r.audience.pages,1);console.log('PASS real PHP: first cookie, scoped path, Secure/HttpOnly/SameSite, first detailed measurement');
 const pair=cookie.split(';')[0];r=await request({Cookie:pair});assert.equal(cookieOf(r),'');assert.equal(r.audience.unique,1);assert.equal(r.audience.pages,2);assert.equal(r.audience.visitor_days,1);console.log('PASS real PHP: same cookie is one browser on two page views');
 r=await request({Cookie:'portix_visitor=invalid'});assert.match(cookieOf(r),/portix_visitor=[a-f0-9]{32}/);assert.equal(r.audience.unique,2);console.log('PASS invalid cookie replaced');
 const before=r;for(const headers of [{'User-Agent':'Googlebot'}, {'DNT':'1'}, {'X-Role':'admin'}]){r=await request(headers);assert.equal(cookieOf(r),'');assert.equal(r.audience.pages,before.audience.pages);}
 assert.equal(r.totals.bot_views,1);console.log('PASS bots, DNT and signed-in admin excluded from cookie audience');
 r=await request({},'POST');assert.equal(r.audience.pages,3);console.log('PASS POST excluded');
 const settings=JSON.parse(fs.readFileSync(path.join(fixture,'var/sys/insights.json')));settings.mode='minimal';fs.writeFileSync(path.join(fixture,'var/sys/insights.json'),JSON.stringify(settings));
 r=await request();assert.equal(cookieOf(r),'');assert.equal(r.audience.pages,3);assert.equal(r.totals.page_views,4);console.log('PASS minimal mode preserves existing details and collects page totals without visitor cookie');
 const screen=await dashboard();assert.equal(screen.insights_unique_visitors,'2');assert.equal(screen.insights_visits,'2');assert.equal(screen.insights_pages_per_visitor,'1,50');assert.match(screen.insights_measurement_note,/désactivée/);console.log('PASS real dashboard: consistent detailed counters and disabled-collection notice');
 console.log('AUDIENCE HTTP COMPLETE PHP '+(process.env.TEST_PHP_VERSION||'8.2'));
}finally{php.exit();fs.rmSync(fixture,{recursive:true,force:true});}
