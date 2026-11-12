// Run against a disposable seeded installation, never production.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const visitorOptions = {userAgent: 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'};
const base = process.env.HOME_TEST_URL;
if (!base || !/^http:\/\/(127\.0\.0\.1|localhost):\d+\/?$/.test(base)) throw new Error('HOME_TEST_URL must identify a disposable localhost installation');
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_EXECUTABLE, args: ['--no-sandbox']});
  try {
    const context = await browser.newContext(visitorOptions);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto(base);
    assert.equal(response.status(), 200);
    await page.locator('.home-zone').first().waitFor();
    assert((await page.locator('.home-zone').count()) >= 2);
    assert.equal(await page.locator('.p3-home h1').count(), 0, 'No general title above home zones');
    for (const width of [1280, 1920, 375, 320]) {
      await page.setViewportSize({width, height: 900});
      await page.waitForTimeout(150);
      if (process.env.HOME_SCREENSHOT_DIR) {
        fs.mkdirSync(process.env.HOME_SCREENSHOT_DIR, {recursive:true});
        await page.screenshot({path: path.join(process.env.HOME_SCREENSHOT_DIR, `home-${width}.png`), fullPage:true});
      }
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `No horizontal overflow at ${width}`);
      await page.locator('.home-thumbnail img').first().evaluate(image => image.decode());
      const thumbnail = await page.locator('.home-thumbnail').first().evaluate(node => {
        const box=node.getBoundingClientRect();
        return {height:box.height,lineHeight:parseFloat(getComputedStyle(node).lineHeight),float:getComputedStyle(node).cssFloat,fit:getComputedStyle(node.querySelector('img')).objectFit,ratio:box.width/box.height,naturalRatio:node.querySelector('img').naturalWidth/node.querySelector('img').naturalHeight};
      });
      assert.equal(thumbnail.float, 'left', 'Thumbnail floats left');
      assert(Math.abs(thumbnail.height-thumbnail.lineHeight*3)<1, `Thumbnail height follows three text lines at ${width}`);
      assert.equal(thumbnail.fit, 'contain', 'Image proportions preserved');
      assert(Math.abs(thumbnail.ratio-thumbnail.naturalRatio)<.025, 'Thumbnail width follows its natural aspect ratio, not a square box');
      const wraps = await page.locator('.home-items').first().evaluate(node => {
        const probe=document.createElement('article');probe.className='home-item';
        const thumb=document.createElement('span');thumb.className='home-thumbnail';
        const image=document.createElement('img');image.src=document.querySelector('.home-thumbnail img').src;thumb.append(image);
        const paragraph=document.createElement('p');paragraph.textContent='Readable text flows beside the picture then returns below it. '.repeat(15);
        probe.append(thumb,paragraph);node.append(probe);
        try {
          const bounds=probe.getBoundingClientRect(), picture=thumb.getBoundingClientRect();
          const range=document.createRange();range.setStart(paragraph.firstChild,0);range.setEnd(paragraph.firstChild,1);const first=range.getBoundingClientRect();
          let below=false;
          for(let i=0;i<paragraph.textContent.length;i++) {
            range.setStart(paragraph.firstChild,i);range.setEnd(paragraph.firstChild,i+1);const box=range.getBoundingClientRect();
            if(box.y>=picture.bottom && Math.abs(box.x-bounds.x)<2){below=true;break;}
          }
          return first.x>=picture.right && Math.abs(picture.x-bounds.x)<1 && below;
        } finally {probe.remove();}
      });
      assert(wraps, `Text wraps beside and below the left thumbnail at ${width}`);
      const grid = await page.locator('.home-zones').evaluate(node => {
        const zones = [...node.querySelectorAll(':scope > .home-zone:not(.home-kind-text)')];
        return {columns:getComputedStyle(node).gridTemplateColumns.split(' ').length,
          boxes:zones.slice(0,3).map(zone => ({x:zone.getBoundingClientRect().x,y:zone.getBoundingClientRect().y})),
          width:node.getBoundingClientRect().width};
      });
      if (width <= 640) {
        assert.equal(grid.columns, 1, `Single mobile column at ${width}`);
        assert(Math.abs(grid.boxes[0].x-grid.boxes[1].x)<1, 'Mobile zones align in one column');
        assert(grid.boxes[1].y>grid.boxes[0].y, 'Mobile zones retain reading order');
      } else {
        assert(grid.columns>=2 && grid.columns<=3, `${width}: two or three desktop columns (content width ${grid.width})`);
        assert(grid.boxes[1].x>grid.boxes[0].x, 'Desktop zones sit next to one another');
        assert(Math.abs(grid.boxes[1].y-grid.boxes[0].y)<1, 'First desktop row aligns');
      }
    }
    for (const [imageWidth,imageHeight] of [[200,100],[100,200],[100,100]]) {
      const dimensions=await page.locator('.home-items').first().evaluate(async (node,size) => {
        const item=document.createElement('article');item.className='home-item';
        const frame=document.createElement('span');frame.className='home-thumbnail';
        const image=document.createElement('img');image.src='data:image/svg+xml,'+encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${size[0]}" height="${size[1]}"></svg>`);
        frame.append(image);item.append(frame);node.append(item);
        try {await image.decode();const box=image.getBoundingClientRect();return {width:box.width,height:box.height,line:parseFloat(getComputedStyle(image).lineHeight)};}
        finally {item.remove();}
      },[imageWidth,imageHeight]);
      assert(Math.abs(dimensions.height-3*dimensions.line)<1, 'Height follows three lines for portrait, landscape and square images');
      assert(Math.abs(dimensions.width/dimensions.height-imageWidth/imageHeight)<.025, 'Natural image ratio sets thumbnail width');
    }
    await page.setViewportSize({width:1920,height:900});
    for (const [contentWidth,expected] of [[700,2],[1040,3]]) {
      const previous = await page.locator('.p3-home').getAttribute('style');
      await page.locator('.p3-home').evaluate((node,width) => {node.style.width=width+'px';node.style.maxWidth='none';},contentWidth);
      await page.waitForTimeout(50);
      assert.equal(await page.locator('.home-zones').evaluate(node => getComputedStyle(node).gridTemplateColumns.split(' ').length),expected, `${contentWidth}px content: ${expected} columns`);
      await page.locator('.p3-home').evaluate((node,style) => {if(style===null)node.removeAttribute('style');else node.setAttribute('style',style);},previous);
    }
    await page.waitForTimeout(1500);
    assert.equal((await context.cookies()).some(cookie => cookie.name === 'PHPSESSID'), false, 'Measurement must not create a PHP session');
    const direct = await page.locator('[data-home-slot] h3 a').first().getAttribute('href');
    assert(!direct.includes('home-events'), 'Article links remain direct');
    const ticket = await page.locator('[data-home-page]').getAttribute('data-home-ticket');
    assert(ticket.length > 64, 'Human-like visitor receives a measurement ticket');
    const invalid = await context.request.post(base + '/home-events.html', {data: {ticket: ticket + 'bad', page: 'a'.repeat(32), events: [{kind: 'page'}]}});
    assert.equal(invalid.status(), 422);
    const methods = await context.request.get(base + '/home-events.html');
    assert.equal(methods.status(), 405);
    await page.locator('[data-home-slot] h3 a').first().click();
    await page.waitForURL(url => url.pathname !== '/');
    const denied = await context.request.get(base + '/Admin-home.html');
    assert([200,403].includes(denied.status()));
    assert(!(await denied.text()).includes('home_action'), 'Anonymous sees no composition controls');
    assert.deepEqual(errors, []);
    await context.close();
    for (const actor of ['editor', 'admin']) {
      const ctx = await browser.newContext(visitorOptions); const tab = await ctx.newPage();
      await tab.goto(base + '/login.html');
      const csrf = await tab.locator('input[name="csrf_token"]').first().inputValue();
      await ctx.request.post(base + '/login.html', {form: {pseudo: actor, pass: 'test-home-password', csrf_token: csrf}});
      const adminResponse = await tab.goto(base + '/Admin-home.html');
      assert.equal(adminResponse.status(), actor === 'admin' ? 200 : 403, `${actor}: Home composition permissions`);
      if (actor === 'admin') {
        const before = await tab.locator('details').count();
        const rejected = await ctx.request.post(base + '/Admin-home.html', {form: {home_action:'delete',id:'1',confirm_delete:'1',csrf_token:'wrong'}});
        assert.equal(rejected.status(), 403);
        await tab.reload(); assert.equal(await tab.locator('details').count(), before);
        const zones=tab.locator('[data-home-section]');
        await zones.first().locator('summary').click();
        await zones.first().locator('[name=title]').fill('Draft browser preview');
        await zones.first().locator('[data-home-down]').click();
        assert.equal(await zones.nth(1).locator('[name=title]').inputValue(),'Draft browser preview','Keyboard order changes the draft');
        await tab.locator('[data-home-preview]').click();
        await tab.locator('.home-preview-frame').waitFor();
        assert((await tab.locator('.home-preview-frame').getAttribute('srcdoc')).includes('Draft browser preview'));
        const unchanged=await ctx.request.get(base+'/Admin-home.html');
        assert(!(await unchanged.text()).includes('Draft browser preview'),'Browser preview is not published');
        await tab.getByRole('button',{name:'Téléphone',exact:true}).click();
        assert.equal(await tab.locator('.home-preview-frame').evaluate(el=>el.getBoundingClientRect().width),375);
        await tab.getByRole('button',{name:'Fermer',exact:true}).click();
        const chosen=zones.nth(1);
        await tab.route('**/Admin-media-api.html',route=>route.fulfill({json:{ok:true,result:[{family:'image',public_path:'var/uploads/images/home-test.webp',original_name:'Home test image'}]}}));
        await chosen.locator('[data-home-pick]').click();
        await tab.getByRole('button',{name:'Choisir Home test image',exact:true}).click();
        assert.equal(await chosen.locator('[name=image_path]').inputValue(),'var/uploads/images/home-test.webp');
        await chosen.locator('[data-home-clear]').click();
        assert.equal(await chosen.locator('[name=image_path]').inputValue(),'');
        const fresh=tab.locator('[data-home-section="0"]');
        await fresh.locator('[name=kind]').selectOption('latest');
        assert.equal(await fresh.locator('[name=item_count]').inputValue(),'3');
        await fresh.locator('[name=title]').fill('Latest browser articles');
        await Promise.all([tab.waitForNavigation({waitUntil:'networkidle'}),tab.locator('[data-home-publish]').click()]);
        assert.equal(await tab.locator('[name=title]').evaluateAll(nodes=>nodes.filter(n=>n.value==='Latest browser articles').length),1);
        for(const width of [1280,375,320]) {
          await tab.setViewportSize({width,height:900});
          assert(await tab.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Composer fits narrow viewport');
          if(process.env.HOME_SCREENSHOT_DIR)await tab.screenshot({path:path.join(process.env.HOME_SCREENSHOT_DIR,`composer-${width}.png`),fullPage:true});
        }

      } else {
        assert.equal(await tab.locator('form[action="Admin-home.html"]').count(), 0, 'Editor cannot see structural forms');
        const rejected = await ctx.request.post(base + '/Admin-home.html', {form: {home_action:'save',title:'Denied',kind:'text',csrf_token:'wrong'}});
        assert.equal(rejected.status(), 403, 'Editor cannot submit structural changes');
      }
      for (const route of ['Admin-home-settings.html', 'Admin-home-report.html', 'Admin-menu.html', 'Admin-footer.html']) {
        const result = await ctx.request.get(base + '/' + route);
        assert.equal(result.status(), actor === 'admin' ? 200 : 403, `${actor}: ${route}`);
      }
      await tab.goto(base);
      assert.equal(await tab.locator('script[src*="home/assets/home.js"]').count(), 0, 'Staff excluded from measurement');
      if (actor === 'admin') {
        await tab.goto(base + '/Admin-home-settings.html');
        const toggle = tab.locator('label').filter({has: tab.locator('input[name="tracking"]')});
        for (const width of [1280, 320]) {
          await tab.setViewportSize({width, height:900});
          assert(await toggle.evaluate(label => {
            const checkbox = label.querySelector('input').getBoundingClientRect();
            const text = [...label.childNodes].find(node => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
            const range = document.createRange();
            range.selectNodeContents(text);
            const firstLine = range.getClientRects()[0];
            return firstLine.left >= checkbox.right && firstLine.top < checkbox.bottom && checkbox.top < firstLine.bottom;
          }), `Checkbox precedes its label at ${width}px`);
        }
        await tab.goto(base + '/Admin-home-report.html');
        assert((await tab.locator('main').innerText()).includes('Pages d’accueil observées'));
      }
      await ctx.close();
    }
    console.log('Home browser: desktop/mobile, direct links, anonymous/staff, roles, CSRF and telemetry endpoint OK');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
