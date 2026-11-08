// Run against a disposable seeded installation, never production.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const base = process.env.HOME_TEST_URL;
if (!base || !/^http:\/\/(127\.0\.0\.1|localhost):\d+\/?$/.test(base)) throw new Error('HOME_TEST_URL must identify a disposable localhost installation');
(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM_EXECUTABLE, args: ['--no-sandbox']});
  try {
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const response = await page.goto(base);
    assert.equal(response.status(), 200);
    await page.locator('.home-zone').first().waitFor();
    assert((await page.locator('.home-zone').count()) >= 2);
    for (const width of [1280, 375, 320]) {
      await page.setViewportSize({width, height: 900});
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `No horizontal overflow at ${width}`);
    }
    await page.waitForTimeout(1500);
    assert.equal((await context.cookies()).some(cookie => cookie.name === 'PHPSESSID'), false, 'Measurement must not create a PHP session');
    const direct = await page.locator('[data-home-slot] h3 a').first().getAttribute('href');
    assert(!direct.includes('home-events'), 'Article links remain direct');
    const ticket = await page.locator('[data-home-page]').getAttribute('data-home-ticket');
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
      const ctx = await browser.newContext(); const tab = await ctx.newPage();
      await tab.goto(base + '/login.html');
      const csrf = await tab.locator('input[name="csrf_token"]').first().inputValue();
      await ctx.request.post(base + '/login.html', {form: {pseudo: actor, pass: 'test-home-password', csrf_token: csrf}});
      const adminResponse = await tab.goto(base + '/Admin-home.html');
      assert.equal(adminResponse.status(), 200, `${actor} can compose sections`);
      const before = await tab.locator('details').count();
      const rejected = await ctx.request.post(base + '/Admin-home.html', {form: {home_action:'delete',id:'1',confirm_delete:'1',csrf_token:'wrong'}});
      assert.equal(rejected.status(), 403);
      await tab.reload(); assert.equal(await tab.locator('details').count(), before);
      for (const route of ['Admin-home-settings.html', 'Admin-home-report.html']) {
        const result = await ctx.request.get(base + '/' + route);
        assert.equal(result.status(), actor === 'admin' ? 200 : 403, `${actor}: ${route}`);
      }
      await tab.goto(base);
      assert.equal(await tab.locator('script[src*="home/assets/home.js"]').count(), 0, 'Staff excluded from measurement');
      if (actor === 'admin') {
        await tab.goto(base + '/Admin-home-report.html');
        assert((await tab.locator('main').innerText()).includes('Pages d’accueil observées'));
      }
      await ctx.close();
    }
    console.log('Home browser: desktop/mobile, direct links, anonymous/staff, roles, CSRF and telemetry endpoint OK');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
