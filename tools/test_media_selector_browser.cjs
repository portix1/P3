// Optional browser regression suite. Requires Playwright with Firefox/Chromium.
// No site, account, upload or database is used: all fixtures are served in memory.
const fs = require('fs');
const path = require('path');
const http = require('http');
const assert = require('node:assert/strict');
const playwright = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const imageUrl = 'var/uploads/images/0123456789abcdef0123456789abcdef.png';
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAKAAAABkCAIAAACO1KzYAAABAUlEQVR4nO3RAQkAIBDAwI9tJIMYzBQijIMLMNisfQib7wU8ZXCcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEGxxkcZ3CcwXEXeVYw6zBi8l8AAAAASUVORK5CYII=', 'base64');
const escape = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
const initial = '<p>Avant</p><figure class="portix-image-figure portix-image-size-50 portix-image-align-left"><img src="' + imageUrl + '" alt="Vue"><figcaption>Légende test</figcaption></figure><p>' + 'Texte destiné à vérifier le passage à côté de la photographie. '.repeat(22) + '</p><table><tbody><tr><td>A</td><td>B</td></tr></tbody></table><p>Fin</p>';
let saved = initial;
let version = 'image-fix-1';
let mediaOn=true, failList=false;
const mediaRows=[{id:1,family:'image',public_path:imageUrl,original_name:'Illustration existante',size_bytes:1234,sha256:'a'.repeat(64)},{id:2,family:'pdf',public_path:'var/uploads/media/'+'b'.repeat(32)+'.pdf',original_name:'Plan.pdf',size_bytes:123,sha256:'b'.repeat(64)},{id:3,family:'zip',public_path:'var/uploads/media/'+'c'.repeat(32)+'.zip',original_name:'Archive.zip',size_bytes:321,sha256:'c'.repeat(64)}];
function fixture() {
    return '<!doctype html><html lang="fr"><meta charset="utf-8"><link rel="stylesheet" href="/p3/var/admin/style.css"><style>body{padding:20px}main{max-width:950px;margin:auto}</style><body data-portix-media="' + (mediaOn?'1':'0') + '"><main><form><input name="csrf_token" value="test"><div class="portix-editor" data-portix-editor><textarea class="portix-editor-source">' + escape(saved) + '</textarea><div class="portix-editor-surface"></div></div></form></main><script>window.katex={render:function(value,node){node.textContent=value;}};</script><script src="/p3/var/editor/portix-editor.js?v=' + version + '"></script></body></html>';
}
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if(url.pathname==='/p3/Admin-media-api.html'){
        let body='';req.on('data',chunk=>body+=chunk.toString());req.on('end',()=>{
            const field=name=>{const m=body.match(new RegExp('name="'+name+'"\\r?\\n\\r?\\n([^\\r\\n]*)'));return m?m[1]:'';};
            const operation=field('operation');res.setHeader('Content-Type','application/json');
            if(operation==='list' && failList){res.statusCode=422;return res.end(JSON.stringify({ok:false,error:'Test : catalogue indisponible'}));}
            let result=mediaRows;
            if(operation==='upload'){result={...mediaRows[1],id:4,original_name:'Ajout.pdf'};mediaRows.push(result);}
            else {result=mediaRows.filter(row=>(!field('family')||row.family===field('family'))&&row.original_name.toLowerCase().includes(field('search').toLowerCase()));}
            res.end(JSON.stringify({ok:true,result}));
        });return;
    }
    if (url.pathname === '/p3/Admin.html') {
        res.setHeader('Content-Type', 'text/html'); res.setHeader('Cache-Control', 'no-store'); return res.end(fixture());
    }
    if (url.pathname === '/p3/' + imageUrl) { res.setHeader('Content-Type', 'image/png'); return res.end(png); }
    const rel = url.pathname.replace(/^\/p3\//, '');
    if (!/^(var\/editor\/|var\/modules\/media\/assets\/|var\/admin\/style\.css$)/.test(rel) || rel.includes('..')) { res.statusCode = 404; return res.end(); }
    const file = path.join(root, rel);
    if (!fs.existsSync(file)) { res.statusCode = 404; return res.end(); }
    res.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : 'text/javascript');
    res.setHeader('Cache-Control', 'public, max-age=31536000, immutable');
    // An unversioned stylesheet represents a stale RC7 file after an update.
    if (rel === 'var/editor/portix-editor.css' && !url.searchParams.has('v')) return res.end('/* stale RC7 style */');
    res.end(fs.readFileSync(file));
});

(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const browser=await playwright.firefox.launch({headless:true,env:{...process.env,MOZ_DISABLE_CONTENT_SANDBOX:'1',MOZ_DISABLE_GMP_SANDBOX:'1',MOZ_DISABLE_RDD_SANDBOX:'1'},firefoxUserPrefs:{'security.sandbox.content.level':0}});
    try{
        const page=await browser.newPage({viewport:{width:1280,height:950}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
        const origin='http://127.0.0.1:'+server.address().port;
        await page.goto(origin+'/p3/Admin.html');await page.locator('.is-enhanced').waitFor();
        assert.equal(await page.locator('.ql-toolbar [data-command="file"]').count(),1);
        const initialDelta=await page.evaluate(()=>JSON.stringify(Quill.find(document.querySelector('.ql-container')).getContents()));
        await page.evaluate(()=>Quill.find(document.querySelector('.ql-container')).setSelection(0,5));
        await page.locator('[data-command="file"]').click();
        let modal=page.locator('dialog[open].media-selector');await modal.locator('.media-card').first().waitFor();
        assert.equal(await modal.locator('.media-card').count(),2,'document selector excludes images');
        await modal.getByRole('button',{name:'Sélectionner Plan.pdf',exact:true}).click();
        const label=modal.locator('input[title="Texte du lien"]');
        assert.equal(await label.inputValue(),'Avant','selected text is proposed');await label.fill('Le plan');
        await modal.getByRole('button',{name:'Insérer',exact:true}).click();
        const source=await page.locator('.portix-editor-source').inputValue();assert.match(source,/<a[^>]+href="var\/uploads\/media\/b{32}\.pdf"[^>]*>Le plan<\/a>/);
        await page.locator('[data-command="undo"]').click();assert.equal(await page.evaluate(()=>JSON.stringify(Quill.find(document.querySelector('.ql-container')).getContents())),initialDelta,'one undo restores document');
        const beforeCancel=await page.locator('.portix-editor-source').inputValue();
        await page.locator('[data-command="file"]').click();modal=page.locator('dialog[open].media-selector');await modal.locator('.media-card').first().waitFor();
        await modal.getByRole('button',{name:'Annuler',exact:true}).click();assert.equal(await page.locator('.portix-editor-source').inputValue(),beforeCancel,'cancel preserves contents');
        await page.locator('.ql-image').click();modal=page.locator('dialog[open].media-selector');await modal.locator('.media-card').first().waitFor();
        assert.equal(await modal.locator('.media-card').count(),1,'image selector excludes PDF/ZIP');
        assert.equal(await modal.locator('.media-document-label').isVisible(),false,'link-label field hidden for images');
        await page.screenshot({path:'/tmp/p3-media-selector.png',fullPage:true});
        await modal.locator('.media-card').first().dblclick();assert.equal(await page.locator('.ql-editor img').count(),2,'double click inserts existing image');
        await page.locator('[data-command="undo"]').click();
        await page.evaluate(()=>{const q=Quill.find(document.querySelector('.ql-container'));const cell=document.querySelector('td');q.setSelection(q.getIndex(Quill.find(cell)),0);});
        await page.locator('.ql-image').click();modal=page.locator('dialog[open].media-selector');await modal.locator('.media-card').first().waitFor();await modal.locator('.media-card').first().dblclick();
        assert.equal(await page.locator('.ql-editor td img').count(),1,'existing image inserted in table');
        await page.locator('[data-command="undo"]').click();
        failList=true;await page.locator('[data-command="file"]').click();modal=page.locator('dialog[open].media-selector');await modal.getByText('Test : catalogue indisponible',{exact:true}).waitFor();
        assert.equal(await modal.getByRole('button',{name:'Insérer',exact:true}).isDisabled(),true,'failure does not insert');await modal.getByRole('button',{name:'Annuler',exact:true}).click();failList=false;
        await page.locator('[data-command="file"]').click();modal=page.locator('dialog[open].media-selector');await modal.locator('.media-card').first().waitFor();
        const chooser=page.waitForEvent('filechooser');await modal.getByRole('button',{name:'Ajouter un fichier',exact:true}).click();await (await chooser).setFiles({name:'Ajout.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.4\nTest')});
        await modal.getByRole('button',{name:'Sélectionner Ajout.pdf',exact:true}).waitFor();await modal.getByRole('button',{name:'Annuler',exact:true}).click();
        for(let i=10;i<40;i++){mediaRows.push({...mediaRows[0],id:i,original_name:'Image '+i});}
        await page.locator('.ql-image').click();modal=page.locator('dialog[open].media-selector');
        await modal.getByRole('button',{name:'Sélectionner Image 39',exact:true}).waitFor();
        for(const width of [1280,600,390]){
            await page.setViewportSize({width,height:950});
            const layout=await modal.locator('.media-grid').evaluate(grid=>({scroll:grid.scrollHeight>grid.clientHeight,cards:Array.from(grid.children).map(card=>{
                const r=card.getBoundingClientRect(),i=card.querySelector('img').getBoundingClientRect();
                return {card:[r.left,r.top,r.right,r.bottom],image:[i.left,i.top,i.right,i.bottom]};
            })}));
            assert.equal(layout.cards.length,31);assert.ok(layout.scroll,'many thumbnails scroll');
            for(const box of layout.cards){assert.ok(box.image[1]>=box.card[1] && box.image[3]<=box.card[3],'image stays in its own card');}
            for(let i=0;i<layout.cards.length;i++)for(let j=i+1;j<layout.cards.length;j++){
                const a=layout.cards[i].image,b=layout.cards[j].image;
                assert.ok(a[2]<=b[0] || b[2]<=a[0] || a[3]<=b[1] || b[3]<=a[1],'thumbnail rectangles never overlap');
            }
        }
        await modal.getByRole('button',{name:'Annuler',exact:true}).click();await page.setViewportSize({width:1280,height:950});
        mediaOn=false;await page.reload();await page.locator('.is-enhanced').waitFor();assert.equal(await page.locator('[data-command="file"]').count(),0,'disabled module hides file button');
        const fallback=page.waitForEvent('filechooser');await page.locator('.ql-image').click();await(await fallback).setFiles([]);assert.equal(await page.locator('dialog[open].media-selector').count(),0,'native upload fallback');
        assert.deepEqual(errors,[]);console.log('Firefox media selector: type filters, selected text, one-step undo, cancel, double-click, table, upload, error and disabled fallback: OK (mock API).');
    }finally{await browser.close();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});
