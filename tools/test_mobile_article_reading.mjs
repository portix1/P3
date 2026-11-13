import { chromium } from 'playwright';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const stylesheet = resolve(root, 'var/templates/six-cordes-ux-clair/style.min.css');
const mobile = resolve(root, 'var/modules/articles/reading-mobile-lot2.css');
const desktop = resolve(root, 'var/editor/reading-desktop-p2.css');
const template = readFileSync(resolve(root, 'var/modules/articles/templates/index.tpl'), 'utf8');
if (!template.includes('reading-mobile-lot2.css?v=')) {
  throw new Error('Module Articles does not load the mobile reading stylesheet.');
}

function svg(width, height, fill) {
  const payload = '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height
    + '" viewBox="0 0 ' + width + ' ' + height + '"><rect width="' + width + '" height="' + height
    + '" fill="' + fill + '"/></svg>';
  return 'data:image/svg+xml,' + encodeURIComponent(payload);
}
const html = [
  '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>',
  '<body class="theme-six-cordes theme-six-cordes-clair"><div class="site-shell six-cordes-shell"><main class="content-card six-cordes-content">',
  '<article class="news-detail article-reading"><div class="news-body news-rich-text">',
  '<p class="ql-align-justify">Paragraphe de lecture justifié servant à mesurer les espaces entre les mots sur téléphone.</p>',
  '<p style="text-align: justify">Autre paragraphe justifié directement dans le contenu éditorial.</p>',
  '<figure id="f25" class="portix-image-figure portix-image-size-25 portix-image-align-left" data-width="25">',
  '<img class="portix-content-image p3-mobile-figure-expand" src="' + svg(900,1448,'#a0b8ca') + '" width="900" height="1448" alt="Manche">',
  '<figcaption>Manche de guitare — figure 25 %</figcaption></figure>',
  '<p>Le paragraphe suivant vérifie que le texte reprend toute la largeur.</p>',
  '<figure id="f33" class="portix-image-figure portix-image-size-33 portix-image-align-right" data-width="33">',
  '<img class="portix-content-image p3-mobile-figure-expand" src="' + svg(1448,900,'#cca18a') + '" width="1448" height="900" alt="Cavité">',
  '<figcaption>Fraise et cavité — figure 33 %</figcaption></figure>',
  '<figure id="f50" class="portix-image-figure portix-image-size-50" data-width="50">',
  '<img class="portix-content-image p3-mobile-figure-expand" src="' + svg(1448,900,'#b2caa1') + '" width="1448" height="900" alt="Chevalet">',
  '<figcaption>Chevalet — figure 50 %</figcaption></figure>',
  '<img id="icon" class="portix-content-image portix-image-size-25" src="' + svg(48,48,'#b1b1b1') + '" width="48" height="48" alt="Icône">',
  '<div class="article-table-scroll"><table><tbody><tr><td>',
  '<figure id="tableFigure" class="portix-image-figure portix-image-size-25" data-width="25">',
  '<img class="portix-content-image" src="' + svg(80,80,'#a9a9a9') + '" width="80" height="80" alt="Symbole"><figcaption>Miniature</figcaption></figure>',
  '</td></tr></tbody></table></div></div></article></main></div></body></html>',
].join('');

const browser = await chromium.launch({ headless: true });
try {
  for (const width of [320, 360, 390, 414, 768, 1024, 1280, 1440]) {
    const page = await browser.newPage({ viewport: { width, height: 844 }, deviceScaleFactor: 2 });
    try {
      await page.setContent(html);
      await page.addStyleTag({ path: stylesheet });
      await page.addStyleTag({ path: mobile });
      await page.addStyleTag({ path: desktop });
      await page.waitForFunction(() => Array.from(document.images).every(img => img.complete));
      const measured = await page.evaluate(() => {
        const rect = sel => document.querySelector(sel).getBoundingClientRect();
        const style = sel => getComputedStyle(document.querySelector(sel));
        return {
          scroll: document.documentElement.scrollWidth,
          reading: rect('.article-reading > .news-body.news-rich-text').width,
          readingAlign: style('.article-reading > .news-body.news-rich-text').textAlign,
          readingHyphens: style('.article-reading > .news-body.news-rich-text').hyphens,
          figures: ['#f25','#f33','#f50'].map(sel => rect(sel).width),
          figureImages: ['#f25 > img','#f33 > img','#f50 > img'].map(sel => rect(sel).width),
          float: style('#f25').float,
          textAlign: style('.ql-align-justify').textAlign,
          inlineAlign: style('p[style]').textAlign,
          icon: rect('#icon').width,
          iconHeight: rect('#icon').height,
          tableFigure: rect('#tableFigure img').width,
          captions: Array.from(document.querySelectorAll('figcaption')).map(el => el.textContent.trim()),
        };
      });
      if (measured.scroll > width + 2) {
        throw new Error('Horizontal overflow at ' + width + 'px: ' + measured.scroll);
      }
      if (width <= 640) {
        const threshold = Math.min(measured.reading * .70, 270);
        if (measured.figures.some(x => x < threshold)) {
          throw new Error('Small mobile figure at ' + width + 'px: ' + JSON.stringify(measured));
        }
        if (measured.figureImages.some(x => x < Math.min(measured.reading*.70, 270))) {
          throw new Error('Large image still rendered as a thumbnail at ' + width + ': ' + JSON.stringify(measured));
        }
        if (measured.float !== 'none') throw new Error('Figure float at ' + width);
        if (!['start','left'].includes(measured.textAlign) || !['start','left'].includes(measured.inlineAlign)) {
          throw new Error('Justification on mobile at ' + width);
        }
        if (measured.icon > 55 || measured.iconHeight > 55 || measured.tableFigure > 100) {
          throw new Error('Small icon enlarged at ' + width + ': ' + JSON.stringify(measured));
        }
      } else if (width <= 1024) {
        if (measured.reading > 660) throw new Error('Tablet line too wide at ' + width + ': ' + measured.reading);
      } else {
        if (measured.reading > 660 || measured.reading < 400) {
          throw new Error('Desktop reading column outside 400–660px at ' + width + ': ' + JSON.stringify(measured));
        }
        if (!['start','left'].includes(measured.readingAlign)
            || !['start','left'].includes(measured.textAlign)
            || !['start','left'].includes(measured.inlineAlign)) {
          throw new Error('Desktop text remains justified at ' + width + ': ' + JSON.stringify(measured));
        }
        if (measured.readingHyphens !== 'auto') {
          throw new Error('Desktop hyphenation not enabled: ' + JSON.stringify(measured));
        }
        if (measured.figures[0] > measured.reading * .36
            || measured.figures[1] > measured.reading * .46
            || measured.figures[2] > measured.reading * .63) {
          throw new Error('Desktop percentage figure enlarged: ' + JSON.stringify(measured));
        }
      }
      if (!measured.captions.includes('Manche de guitare — figure 25 %')) {
        throw new Error('Figure caption lost.');
      }
      if ([320,390,768,1280,1440].includes(width)) {
        mkdirSync(resolve(root, 'home-browser-previews'), { recursive: true });
        await page.screenshot({
          path: resolve(root, 'home-browser-previews/article-reading-' + width + '.png'),
          fullPage: true,
        });
      }
      process.stdout.write('Article ' + width + 'px: figures ' + measured.figures.map(Math.round).join('/')
        + ', line ' + Math.round(measured.reading) + 'px, overflow 0\n');
    } finally {
      await page.close();
    }
  }
} finally {
  await browser.close();
}
