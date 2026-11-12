import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const css = resolve(root, 'var/templates/six-cordes-ux-clair/style.min.css');
const override = resolve(root, 'var/editor/mobile-header-p1.css');
const readingScript = resolve(root, 'var/templates/six-cordes-ux-clair/assets/reading-mobile.js');
const mobileScript = resolve(root, 'var/editor/mobile-header-p1.js');
const body = [
  '<!doctype html><html lang="fr"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>',
  '<body class="theme-six-cordes theme-six-cordes-clair"><div class="site-shell six-cordes-shell">',
  '<header class="site-header six-cordes-masthead">',
  '<a class="brand six-cordes-brand" href="./"><span class="clair-brand-mark">▥</span>',
  '<span class="brand-copy"><strong>Univers Guitare</strong><small>ATELIER &amp; LUTHERIE</small></span></a>',
  '<div class="site-header-tools"><div class="site-header-search"><form class="search-nav-form" action="search.html">',
  '<input name="q" type="search" placeholder="Rechercher..." aria-label="Recherche"><button type="submit">OK</button></form></div>',
  '<div class="site-header-actions"><button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">',
  '<span aria-hidden="true">☰</span> Menu</button></div></div></header>',
  '<nav class="main-navigation six-cordes-top-navigation" id="main-navigation" aria-label="Navigation principale">',
  '<ul><li class="navigation-item"><a href="./">Accueil</a></li>',
  '<li class="navigation-item has-submenu"><div class="navigation-link-row"><a href="articles.html">Articles</a>',
  '<button data-submenu-toggle type="button" aria-expanded="false" aria-label="Rubriques">▾</button></div>',
  '<ul class="navigation-submenu">',
  ...Array.from({length: 22}, (_, i) => '<li><a href="articles-' + i + '.html">Rubrique test numéro ' + (i + 1) + '</a></li>'),
  '</ul></li></ul></nav>',
  '<main class="content-card"><h1>Univers Guitare</h1><p>Article de lecture et recherche</p></main>',
  '<footer class="site-footer"><a href="contact.html">Contact</a></footer>',
  '</div></body></html>'
].join('');

const browser = await chromium.launch();
try {
  for (const [width, height] of [[320,568],[360,800],[390,844],[414,896],[844,390],[768,1024],[1280,900]]) {
    const page = await browser.newPage({ viewport: {width,height}, deviceScaleFactor:2 });
    try {
      await page.setContent(body);
      await page.addStyleTag({ path: css });
      await page.addStyleTag({ path: override });
      await page.addScriptTag({ path: readingScript });
      await page.addScriptTag({ path: mobileScript });
      const compact = width <= 640 || (width <= 900 && height <= 500);
      const measures = await page.evaluate(() => {
        const header = document.querySelector('header.site-header');
        const toggle = document.querySelector('.p3-mobile-search-toggle');
        const field = document.querySelector('.search-nav-form input');
        const brand = document.querySelector('.six-cordes-brand');
        return {
          headerHeight: header.getBoundingClientRect().height,
          brandHeight: brand.getBoundingClientRect().height,
          searchVisible: toggle && getComputedStyle(toggle).display !== 'none',
          fieldSize: parseFloat(getComputedStyle(field).fontSize),
          scrollWidth: document.documentElement.scrollWidth,
        };
      });
      if (measures.scrollWidth > width + 2) throw Error('Horizontal overflow at ' + width + ': ' + JSON.stringify(measures));
      if (compact) {
        const maxHeader = height <= 500 ? 66 : 74;
        if (measures.headerHeight > maxHeader || measures.brandHeight < 44 || !measures.searchVisible) {
          throw Error('Header targets or height at ' + width + ': ' + JSON.stringify(measures));
        }
        await page.getByRole('button',{name:'Rechercher sur le site'}).click();
        const searchOpen = await page.evaluate(() => ({
          open: document.body.classList.contains('p3-mobile-search-open'),
          expanded: document.querySelector('.p3-mobile-search-toggle').getAttribute('aria-expanded'),
          field: document.querySelector('.search-nav-form input').getBoundingClientRect().height,
          font: parseFloat(getComputedStyle(document.querySelector('.search-nav-form input')).fontSize),
          focused: document.activeElement.matches('.search-nav-form input'),
        }));
        if (!searchOpen.open || searchOpen.expanded !== 'true' || searchOpen.font < 16 ||
            searchOpen.field < 44 || !searchOpen.focused) {
          throw Error('Search not usable at ' + width + ': ' + JSON.stringify(searchOpen));
        }
        await page.keyboard.press('Escape');
        if (await page.evaluate(() => document.body.classList.contains('p3-mobile-search-open'))) {
          throw Error('Search does not close on Escape');
        }
        await page.locator('.nav-toggle').click();
        await page.waitForFunction(() => document.body.classList.contains('p3-mobile-nav-open'));
        const menu = await page.evaluate(() => {
          const nav = document.getElementById('main-navigation');
          const rect = nav.getBoundingClientRect();
          return {
            top:rect.top,bottom:rect.bottom,height:rect.height,
            overflow:getComputedStyle(nav).overflowY,
            bodyOverflow:getComputedStyle(document.body).overflow,
            expanded:document.querySelector('.nav-toggle').getAttribute('aria-expanded'),
            focused: nav.contains(document.activeElement),
          };
        });
        if (menu.bottom > height + 2 || menu.overflow !== 'auto' || menu.expanded !== 'true' ||
            menu.bodyOverflow !== 'hidden' || !menu.focused) {
          throw Error('Menu open not accessible at '+width+': '+JSON.stringify(menu));
        }
        await page.locator('[data-submenu-toggle]').click();
        await page.locator('#main-navigation').evaluate(el => { el.scrollTop = el.scrollHeight; });
        const bottom = await page.locator('#main-navigation').evaluate(el => ({
          scroll: el.scrollTop,
          available:el.scrollHeight > el.clientHeight
        }));
        if (!bottom.available && width === 320) throw Error('Long menu should scroll at 320px');
        await page.keyboard.press('Escape');
        await page.locator('.nav-toggle').click();
      } else if (measures.searchVisible) {
        throw Error('Search button should not appear on desktop');
      }
      process.stdout.write('Mobile header '+width+'×'+height+' — '+Math.round(measures.headerHeight)+' px, search/menu '+(compact?'OK':'desktop unaffected')+'\n');
    } finally { await page.close(); }
  }
} finally { await browser.close(); }
