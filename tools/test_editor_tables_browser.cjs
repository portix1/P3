const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict'),{spawnSync}=require('child_process');
const root=path.resolve(__dirname,'..'),project=path.dirname(root);
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const image='var/uploads/images/0123456789abcdef0123456789abcdef.png';
const fixtureDb=path.join(require('os').tmpdir(),'p3-table-editor-test-'+process.pid+'.sqlite');
const initial='<p>Avant</p><table><caption>Mesures utiles</caption><thead><tr><th scope="col">Paramètre</th><th scope="col">Valeur</th></tr></thead><tbody><tr><th scope="row">Référence</th><td>Texte <a href="https://example.org/guide" target="_self">guide</a><figure class="portix-image-figure portix-image-size-25"><img src="'+image+'" alt="Repère"><figcaption>Une légende</figcaption></figure>Suite</td></tr><tr><td>C</td><td>D</td></tr></tbody></table><p>Après</p>';
let saved=initial;const errors=[];
function store(html){const r=spawnSync(process.env.PHP_EXECUTABLE||path.join(project,'runtime/node_modules/.bin/php-wasm-cli'),[path.join(root,'tools/table_roundtrip.php'),fixtureDb],{input:JSON.stringify(html===undefined?{}:{html}),encoding:'utf8',cwd:project});if(r.status!==0)throw new Error(r.stdout+r.stderr);return JSON.parse(r.stdout);}
function fixture(){return '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:20px} main{max-width:950px;margin:auto}.portix-editor{position:relative} .ql-editor{min-height:500px}</style></head><body><main><form><div class="portix-editor" data-portix-editor><textarea class="portix-editor-source" name="content">'+saved.replace(/&/g,'&amp;').replace(/</g,'&lt;')+'</textarea><div class="portix-editor-surface"></div></div><button id="save">Enregistrer</button></form></main><script>window.katex={render:(x,n)=>n.textContent=x};document.querySelector("form").addEventListener("submit",async e=>{e.preventDefault();const r=await fetch("/save",{method:"POST",body:document.querySelector("textarea").value});document.body.dataset.saved=await r.text();});</script><script src="/var/editor/portix-editor.js?v=fixture"></script></body></html>';}
const server=http.createServer((req,res)=>{
 const u=new URL(req.url,'http://localhost');if(u.pathname==='/article'){res.setHeader('Content-Type','text/html; charset=utf-8');return res.end(fixture());}
 if(u.pathname==='/save'){let data='';req.on('data',b=>data+=b);req.on('end',()=>{try{saved=store(data).html;res.end('ok');}catch(e){res.statusCode=500;res.end(e.message);}});return;}
 if(u.pathname==='/'+image){res.setHeader('Content-Type','image/svg+xml');return res.end('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="60"><rect width="100" height="60" fill="blue"/></svg>');}
 if(!u.pathname.startsWith('/var/editor/')||u.pathname.includes('..')){res.statusCode=404;return res.end();}
 const p=path.join(root,u.pathname);if(!fs.existsSync(p)){res.statusCode=404;return res.end();}res.setHeader('Content-Type',p.endsWith('.css')?'text/css':'text/javascript');res.end(fs.readFileSync(p));
});
(async()=>{await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin='http://127.0.0.1:'+server.address().port;const browser=await chromium.launch({executablePath:process.env.CHROMIUM_EXECUTABLE,args:['--no-sandbox','--disable-dev-shm-usage','--no-zygote','--single-process','--use-gl=angle','--use-angle=swiftshader','--enable-unsafe-swiftshader']});
try{const page=await browser.newPage({viewport:{width:1280,height:1000}});page.on('pageerror',e=>errors.push(e.message));
async function open(){await page.goto(origin+'/article');await page.locator('.is-enhanced').waitFor();await page.waitForFunction(()=>document.querySelector('link[data-portix-editor-style="portix"]')?.sheet);}
async function save(){await page.locator('#save').click();await page.waitForFunction(()=>document.body.dataset.saved);assert.equal(await page.locator('body').getAttribute('data-saved'),'ok');assert.equal(store().html,saved);}
async function command(name){const group=name.startsWith('insert')?'Insérer':'Supprimer';await page.locator('.portix-table-tools summary').filter({hasText:group}).click();await page.locator('[data-table-command="'+name+'"]').click();}
await open();
const cell=page.locator('.ql-editor tr').nth(2).locator('td').first();await cell.click();await page.keyboard.press('End');await page.keyboard.type(' texte saisi');assert.match(await cell.innerText(),/texte saisi/);
// Enter and paste stay in the cell; no accidental new rows.
await page.keyboard.press('Enter');await page.keyboard.type('deuxième ligne');assert.match(await cell.innerText(),/deuxième ligne/);assert.equal(await page.locator('.ql-editor tr').count(),3);
await page.evaluate(()=>{const cell=document.querySelector('.ql-editor tr:last-child td');const data=new DataTransfer();data.setData('text/html','<p><b>Collage riche</b> <a href="https://example.net/details">lien ajouté</a></p><p>Fin collage</p>');data.setData('text/plain','Collage riche lien ajouté\nFin collage');cell.dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true,clipboardData:data}));});
assert.match(await cell.innerText(),/Collage riche/);assert.equal(await page.locator('.ql-editor tr').count(),3);
await page.keyboard.press('Tab');assert.equal(await page.evaluate(()=>document.getSelection().anchorNode.parentElement.closest('td').textContent),'D');
await page.keyboard.press('Shift+Tab');assert.match(await page.evaluate(()=>document.getSelection().anchorNode.parentElement.closest('td').textContent),/texte saisi/);
await page.locator('.ql-editor tr').nth(1).locator('td').first().click();
let handle=page.locator('.portix-column-resizer').first();await handle.waitFor({state:'visible'});let b=await handle.boundingBox();const before=await page.locator('.ql-editor tr').first().locator('td').first().evaluate(el=>el.getBoundingClientRect().width);
await page.mouse.move(b.x+5,b.y+35);await page.mouse.down();await page.mouse.move(b.x+80,b.y+35,{steps:8});await page.mouse.up();
const after=await page.locator('.ql-editor tr').first().locator('td').first().evaluate(el=>el.getBoundingClientRect().width);assert(after>before+40,`column grew ${before} -> ${after}`);
// Native history must undo and restore the full resize in one step.
await page.locator('[data-command="undo"]').click();assert.equal(await page.locator('.ql-editor td[style*="width"]').count(),0);
await page.locator('[data-command="redo"]').click();assert(await page.locator('.ql-editor td[style*="width"]').count()>0);
await page.locator('.portix-column-resizer').first().focus();await page.keyboard.press('ArrowRight');await page.waitForTimeout(40);assert.equal(await page.locator(':focus').getAttribute('data-column-boundary'),'1');await page.keyboard.press('ArrowLeft');
await page.locator('.ql-editor img').click();await page.locator('[data-image-properties]').click();await page.locator('input[maxlength="500"]').fill('Légende révisée');await page.getByRole('button',{name:'Fermer',exact:true}).click();
await save();assert.match(saved,/width: [\d.]+%/);assert.match(saved,/<caption>Mesures utiles<\/caption>/);assert.match(saved,/scope="row"/);assert.match(saved,/Légende révisée/);assert.match(saved,/https:\/\/example.org\/guide/);assert(!/contenteditable|data-row|column-resizer|data-portix/.test(saved));
assert.match(saved,/Collage riche/);assert(!/href="https:\/\/example.org\/guide"[^>]*target="_blank"/.test(saved),'original same-window link preserved');assert.match(saved,/example.net\/details/);
const once=saved;await open();await save();assert.equal(saved,once,'save -> reopen -> save is stable');
await page.locator('.ql-editor tr').nth(2).locator('td').first().click();await command('insertRowAbove');assert.equal(await page.locator('.ql-editor tr').count(),4);
await command('insertColumnRight');assert.equal(await page.locator('.ql-editor tr').first().locator('td').count(),3);
await command('deleteColumn');assert.equal(await page.locator('.ql-editor tr').first().locator('td').count(),2);
await command('deleteRow');assert.equal(await page.locator('.ql-editor tr').count(),3);
await save();await open();assert.equal(await page.locator('.ql-editor img').count(),1);assert.equal(await page.locator('.ql-editor a[href="https://example.org/guide"]').count(),1);
console.log('PASS browser: click/type, Enter, drag column, row/column operations, SQLite save/reopen stable, image/caption/link preserved');
// Existing merged cells must survive editing/reopening without Quill adding cells.
saved='<table><tbody><tr><th colspan="2" scope="col">Titre fusionné</th></tr><tr><td rowspan="2">Vertical</td><td>A</td></tr><tr><td>B</td></tr></tbody></table><p>Fin</p>';
await open();assert.equal(await page.locator('.ql-editor td').count(),4);assert.equal(await page.locator('.ql-editor [colspan="2"]').count(),1);assert.equal(await page.locator('.ql-editor [rowspan="2"]').count(),1);
await page.locator('.ql-editor tr').nth(2).locator('td').click();await page.keyboard.type(' suite');await save();const merged=saved;await open();await save();assert.equal(saved,merged);assert.match(saved,/colspan="2"/);assert.match(saved,/rowspan="2"/);
await page.locator('.ql-editor tr').nth(2).locator('td').click();await command('insertColumnLeft');assert.equal(await page.locator('.ql-editor tr').first().locator('td').getAttribute('colspan'),'3');await save();await open();assert.match(saved,/colspan="3"/);
await page.locator('.ql-editor tr').nth(2).locator('td').last().click();await command('deleteColumn');await save();await open();assert.match(saved,/colspan="2"/);
await page.locator('.ql-editor tr').nth(2).locator('td').last().click();await command('insertRowBelow');assert.equal(await page.locator('.ql-editor tr').count(),4);
await command('insertColumnRight');await save();await open();
await page.locator('.ql-editor tr').nth(1).locator('td').first().click();await command('deleteRow');await save();await open();
assert.match(saved,/Vertical/);assert(!saved.includes('rowspan="2"')); // spanning content moved to the surviving row
console.log('PASS browser: existing colspan/rowspan, direct editing, logical column insertion and SQLite roundtrip');
await page.setViewportSize({width:390,height:844});await page.locator('.ql-editor tr').nth(2).locator('td').first().click();assert(await page.locator('.portix-table-tools').isVisible());
assert.deepEqual(errors,[]);console.log('PASS mobile ribbon and no JavaScript errors');
// A one-cell table can be removed through the normal row/column actions, then undone.
for(const action of ['deleteRow','deleteColumn']){saved='<table><tbody><tr><td>Unique</td></tr></tbody></table><p>Suite</p>';await open();await page.locator('.ql-editor td').click();await command(action);assert.equal(await page.locator('.ql-editor table').count(),0);await page.locator('[data-command="undo"]').click();assert.equal(await page.locator('.ql-editor td').innerText(),'Unique');await save();}
console.log('PASS last row/column deletion and native undo');
}finally{await browser.close();server.close();try{fs.unlinkSync(fixtureDb);}catch{}}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
