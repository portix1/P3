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
function fixture() {
    return '<!doctype html><html lang="fr"><meta charset="utf-8"><link rel="stylesheet" href="/p3/var/admin/style.css"><style>body{padding:20px}main{max-width:950px;margin:auto}</style><body><main><form><input name="csrf_token" value="test"><div class="portix-editor" data-portix-editor><textarea class="portix-editor-source">' + escape(saved) + '</textarea><div class="portix-editor-surface"></div></div></form></main><script>window.katex={render:function(value,node){node.textContent=value;}};</script><script src="/p3/var/editor/portix-editor.js?v=' + version + '"></script></body></html>';
}
const server = http.createServer((req, res) => {
    const url = new URL(req.url, 'http://localhost');
    if (url.pathname === '/p3/Admin.html') {
        res.setHeader('Content-Type', 'text/html'); res.setHeader('Cache-Control', 'no-store'); return res.end(fixture());
    }
    if (url.pathname === '/p3/' + imageUrl) { res.setHeader('Content-Type', 'image/png'); return res.end(png); }
    const rel = url.pathname.replace(/^\/p3\//, '');
    if (!/^(var\/editor\/|var\/admin\/style\.css$)/.test(rel) || rel.includes('..')) { res.statusCode = 404; return res.end(); }
    const file = path.join(root, rel);
    if (!fs.existsSync(file)) { res.statusCode = 404; return res.end(); }
    res.setHeader('Content-Type', file.endsWith('.css') ? 'text/css' : 'text/javascript');
    res.setHeader('Cache-Control', 'public, max-age=31536000, immutable');
    // An unversioned stylesheet represents a stale RC7 file after an update.
    if (rel === 'var/editor/portix-editor.css' && !url.searchParams.has('v')) return res.end('/* stale RC7 style */');
    res.end(fs.readFileSync(file));
});
async function wrapGeometry(page) {
    return page.evaluate(() => {
        const obj = document.querySelector('.ql-editor .portix-image-object');
        const paragraph = obj.closest('p').nextElementSibling;
        const range = document.createRange(); range.setStart(paragraph.firstChild, 0); range.setEnd(paragraph.firstChild, 8);
        const b = obj.getBoundingClientRect(), t = range.getBoundingClientRect(), p = paragraph.getBoundingClientRect();
        return {left:b.left, right:b.right, top:b.top, bottom:b.bottom, textLeft:t.left, textRight:t.right, textTop:t.top, paragraphLeft:p.left, float:getComputedStyle(obj).float};
    });
}
async function run(engine, origin) {
    saved = initial;
    const options = {headless:true};
    if (engine === 'chromium' && process.env.CHROMIUM_EXECUTABLE) { options.executablePath=process.env.CHROMIUM_EXECUTABLE; options.args=['--no-sandbox']; }
    if (engine === 'firefox' && process.env.FIREFOX_TEST_SANDBOX === '0') {
        options.env={...process.env,MOZ_DISABLE_CONTENT_SANDBOX:'1',MOZ_DISABLE_GMP_SANDBOX:'1',MOZ_DISABLE_RDD_SANDBOX:'1'};
        options.firefoxUserPrefs={'security.sandbox.content.level':0};
    }
    const browser = await playwright[engine].launch(options);
    try {
        const page = await browser.newPage({viewport:{width:1280,height:950}});
        const errors=[]; page.on('pageerror', e=>errors.push(e.message));
        // Local CSS uses the real browser cache, without Playwright interception.
        await page.goto(origin+'/p3/Admin.html',{waitUntil:'domcontentloaded'});
        await page.locator('.is-enhanced').waitFor();
        await page.waitForFunction(() => [...document.querySelectorAll('link[data-portix-editor-style]')].filter(e=>e.dataset.portixEditorStyle!=='katex').every(e=>e.sheet));
        assert.match(await page.locator('link[data-portix-editor-style="portix"]').getAttribute('href'), /\?v=image-fix-1$/);
        await page.evaluate(()=>{const q=Quill.find(document.querySelector('.ql-container'));q.setSelection(q.getLength()-1,0);});
        await page.locator('[data-command="divider"]').click();
        assert.equal(await page.locator('.ql-editor hr').count(),1,'divider command inserts hr');
        await page.locator('[data-command="undo"]').click();
        assert.equal(await page.locator('.ql-editor hr').count(),0,'native undo removes hr');
        await page.locator('[data-command="redo"]').click();
        assert.equal(await page.locator('.ql-editor hr').count(),1,'native redo restores hr');
        await page.locator('[data-command="undo"]').click();
        let g=await wrapGeometry(page); assert.equal(g.float,'left'); assert(g.textLeft>=g.right-2 && g.textTop<g.bottom,'left wrap: text is beside image');
        await page.locator('.ql-editor img').click();
        const align={selectOption: value=>page.locator('[data-image-align="'+value+'"]').click()};
        let g2;
        await align.selectOption('right'); g2=await wrapGeometry(page); assert.equal(g2.float,'right'); assert(g2.textRight<=g2.left+2 && g2.textTop<g2.bottom,'right wrap');
        await align.selectOption('center'); g2=await wrapGeometry(page); assert.equal(g2.float,'none'); assert(g2.textTop>=g2.bottom,'center on own line');
        await align.selectOption('left');
        assert.equal(await page.locator('.portix-rich-tools:not(.ql-toolbar .portix-rich-tools)').count(),0,'extensions integrated in native toolbar');
        for(const name of ['undo','redo','divider','table']) {
            const button=page.locator('.ql-toolbar [data-command="'+name+'"]');
            assert.equal(await button.innerText(),'','icon command without text'); assert.equal(await button.locator('svg').count(),1);
        }
        assert.equal(await page.locator('button:not([title])').count(),0,'all buttons have tooltips');
        assert.equal(await page.locator('.portix-image-tools button').count(),4,'image palette has only three positions and properties');
        assert.equal(await page.locator('.portix-image-tools input,.portix-image-tools select').count(),0,'no image settings panel');
        await page.screenshot({path:'/tmp/p3-rc15-image.png',fullPage:true});
        await page.locator('[data-image-properties]').click();
        const properties=page.locator('dialog[open]');
        assert.equal(await properties.locator('input[type="checkbox"]').count(),0,'no additional wrap choice');
        const caption=properties.locator('input[maxlength="500"]'); await caption.fill('Légende test'); await caption.dispatchEvent('input');
        const number=properties.locator('input[type="number"]'); await number.fill('40'); await number.press('Tab');
        await properties.getByRole('button',{name:'Fermer',exact:true}).click();
        assert.equal(await page.locator('.portix-image-resize').count(),4);
        for (const corner of ['se','sw','ne','nw']) {
            const resize=page.locator('.portix-image-resize[data-corner="'+corner+'"]');
            await resize.scrollIntoViewIfNeeded(); const box=await resize.boundingBox(); assert(box,'handle remains available');
            const before=await page.locator('.ql-editor .portix-image-object').boundingBox();
            const direction=corner.endsWith('w')?-1:1;
            await page.mouse.move(box.x+7,box.y+7); await page.mouse.down(); await page.mouse.move(box.x+7+direction*45,box.y+7,{steps:8}); await page.mouse.up();
            const after=await page.locator('.ql-editor .portix-image-object').boundingBox(); assert(after.width>before.width+20,'resize '+corner+' changes visible width: '+before.width+' -> '+after.width);
            assert(await resize.isVisible(),'resize keeps the selection');
        }
        const width=await page.locator('.ql-editor img').getAttribute('data-width');
        await page.locator('[data-image-properties]').click();
        assert.equal(await page.locator('dialog[open] input[type="number"]').inputValue(),width,'handle resize updates width property');
        await page.locator('dialog[open]').getByRole('button',{name:'Fermer',exact:true}).click();
        assert.match(await page.locator('.portix-editor-source').inputValue(),new RegExp('data-width="'+width+'"'));
        // Native gestures, including an image that has not been selected first.
        await page.locator('.ql-editor p').last().click();
        await page.locator('.ql-editor img').dragTo(page.locator('.ql-editor td').last());
        assert.equal(await page.locator('.ql-editor td img').count(),1,'native image drag reaches a cell');
        assert.equal(await page.locator('.ql-editor .portix-image-caption').textContent(),'Légende test');
        assert.equal(await page.locator('.ql-editor img').getAttribute('data-width'),width);
        await page.evaluate(()=>Quill.find(document.querySelector('.ql-container')).getModule('history').undo());
        assert.equal(await page.locator('.ql-editor td img').count(),0,'single undo restores image');
        await page.locator('.ql-editor img').dragTo(page.locator('.ql-editor p').first());
        assert.equal(await page.locator('.ql-editor > p').first().locator('img').count(),1,'native vertical drag reaches paragraph');
        saved=await page.locator('.portix-editor-source').inputValue();
        version='image-fix-2';
        await page.reload({waitUntil:'domcontentloaded'}); await page.locator('.is-enhanced').waitFor();
        await page.waitForFunction(()=>document.querySelector('link[data-portix-editor-style="portix"]').sheet);
        assert.match(await page.locator('link[data-portix-editor-style="portix"]').getAttribute('href'),/\?v=image-fix-2$/,'update changes CSS URL in a warm cache');
        assert.equal(await page.locator('.ql-editor img').count(),1); assert.equal(await page.locator('.ql-editor img').getAttribute('data-width'),width);
        assert.equal(await page.locator('.ql-editor .portix-image-caption').textContent(),'Légende test');
        await page.locator('.ql-editor td').first().click();
        await page.locator('.portix-table-tools').waitFor({state:'visible'});
        const rowBefore=await page.locator('.ql-editor tr').count();
        await page.locator('.portix-table-tools summary').filter({hasText:'Insérer'}).click();
        await page.screenshot({path:'/tmp/p3-rc15-table.png',fullPage:true});
        await page.locator('.portix-table-tools button[data-table-command="insertRowBelow"]').click();
        assert.equal(await page.locator('.ql-editor tr').count(),rowBefore+1,'insert row command');
        await page.evaluate(()=>Quill.find(document.querySelector('.ql-container')).getModule('history').undo());
        assert.equal(await page.locator('.ql-editor tr').count(),rowBefore,'undo table insertion');
        await page.locator('.ql-editor td').first().click();
        await page.locator('.portix-table-tools summary').filter({hasText:'Insérer'}).click();
        const columnsBefore=await page.locator('.ql-editor tr').first().locator('td').count();
        await page.locator('[data-table-command="insertColumnRight"]').click();
        assert.equal(await page.locator('.ql-editor tr').first().locator('td').count(),columnsBefore+1,'insert column');
        await page.locator('[data-command="undo"]').click();
        assert.equal(await page.locator('.ql-editor tr').first().locator('td').count(),columnsBefore,'undo column');
        await page.locator('.ql-editor td').first().click();
        await page.locator('.portix-table-tools summary').filter({hasText:'Propriétés'}).click();
        await page.locator('.portix-table-tools button[title="En-tête"]').click();
        assert.match(await page.locator('.portix-editor-source').inputValue(),/<th[ >]/,'header exports semantic th');
        assert.equal(errors.length,0,JSON.stringify(errors));
        console.log(engine+': Snow icons, hr/undo/redo, compact palettes, three image positions, four corners, properties, native drag, persistence, table rows/columns/headers and tooltips: OK');
    } finally {version='image-fix-1'; await browser.close();}
}
(async()=>{
    await new Promise(r=>server.listen(0,'127.0.0.1',r));
    const origin='http://127.0.0.1:'+server.address().port;
    try {for(const engine of (process.argv.slice(2).length?process.argv.slice(2):['firefox','chromium'])) await run(engine,origin);}
    finally {server.closeAllConnections();await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exitCode=1;});
