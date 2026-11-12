/*
 * Audit léger, en lecture seule, des performances mobiles de la production.
 * Une page à la fois. Aucune authentification, POST, soumission ou scan.
 * Les chargements de documents sont espacés de plusieurs secondes ;
 * les ressources d'une page se chargent comme dans un navigateur réel.
 */
import { chromium } from 'playwright';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
const out = resolve('audit-mobile-production');
mkdirSync(out, {recursive: true});

const targets = [
  {key: 'accueil', title: 'Accueil', path: '/'},
  {key: 'article-texte', title: 'Article texte — Le diapason', path: '/le-diapason.html'},
  {key: 'article-illustre', title: 'Article illustré — La forme du corps', path: '/la-forme-du-corps.html'},
];
const base = 'https://www.universguitare.be';
const runtime = {
  date: new Date().toISOString(),
  browser: 'Chromium via Playwright',
  viewport: '390 × 844',
  deviceScaleFactor: 3,
  network: 'Slow 4G : 1.6 Mb/s descendant, 0.75 Mb/s montant, 150 ms RTT simulée',
  cpu: 'x4',
  note: 'Mesures de laboratoire sur GitHub Actions : une visite à cache froid par page. Défilement après capture des métriques de premier écran pour déclencher les médias différés. Échantillon trop faible pour estimer la variabilité.'
};
const results = [];
let browser;
function round(v, precision=0) { return Number.isFinite(v) ? Number(v.toFixed(precision)) : null; }
function delay(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }
try {
  browser = await chromium.launch({
    headless: true,
    args: ['--disable-background-networking', '--no-sandbox'],
  });
  for (const item of targets) {
    const result = {key:item.key, title:item.title, url:base+item.path, status:'non_mesure', errors:[]};
    const context = await browser.newContext({
      viewport: {width:390,height:844},
      deviceScaleFactor:3, isMobile:true, hasTouch:true,
      locale:'fr-BE', colorScheme:'light', serviceWorkers:'block',
    });
    const page = await context.newPage();
    const cdp = await context.newCDPSession(page);
    const resources = new Map();
    const requestsFailed = [];
    try {
      await cdp.send('Network.enable');
      await cdp.send('Network.setCacheDisabled', {cacheDisabled:true});
      await cdp.send('Network.emulateNetworkConditions', {
        offline:false, latency:150,
        downloadThroughput: 200000, uploadThroughput:93750,
        connectionType:'cellular4g',
      });
      await cdp.send('Emulation.setCPUThrottlingRate',{rate:4});
      cdp.on('Network.responseReceived', e => {
        if (!e.response || !e.requestId) return;
        const url = String(e.response.url);
        const prior = resources.get(e.requestId) || {};
        resources.set(e.requestId, {...prior,
          url, type:e.type || '', mime:e.response.mimeType || '',
          status:e.response.status || 0,
        });
      });
      cdp.on('Network.loadingFinished', e => {
        const prior = resources.get(e.requestId);
        if (prior) resources.set(e.requestId,{
          ...prior, bytes:e.encodedDataLength || 0, finished:true,
        });
      });
      cdp.on('Network.loadingFailed', e => {
        const prior = resources.get(e.requestId);
        if (prior) resources.set(e.requestId,{
          ...prior, finished:false, failed:true, error:e.errorText,
        });
      });
      page.on('requestfailed', req => requestsFailed.push({
        url:req.url(), failure:req.failure() || 'error',
      }));
      await page.addInitScript(() => {
        window.__mobileAudit = {lcp:null, shifts:[]};
        try {
          new PerformanceObserver(list => {
            for (const entry of list.getEntries()) {
              window.__mobileAudit.lcp = {
                time:entry.startTime, size:entry.size,
                url:entry.url||null,
                element:entry.element?.outerHTML?.slice(0,350)||null,
              };
            }
          }).observe({type:'largest-contentful-paint', buffered:true});
          new PerformanceObserver(list => {
            for(const entry of list.getEntries()) {
              if (!entry.hadRecentInput) {
                window.__mobileAudit.shifts.push({
                  time:entry.startTime,
                  value:entry.value,
                  sources:entry.sources?.map(s=>({
                    node:s.node?.outerHTML?.slice(0,140)||null,
                    previous:s.previousRect, current:s.currentRect
                  })) || [],
                });
              }
            }
          }).observe({type:'layout-shift', buffered:true});
        } catch(e) { window.__mobileAudit.observerError=String(e); }
      });
      // Une seule navigation à la fois pour éviter de solliciter LWS.
      const start = Date.now();
      const navResponse = await page.goto(result.url,{
        waitUntil:'domcontentloaded',timeout:65000
      });
      result.httpStatus = navResponse?.status() ?? null;
      result.contentType = navResponse?.headers()?.['content-type'] || '';
      result.navigationWallMs = Date.now() - start;
      const text = await page.locator('body').innerText().catch(()=> '');
      if (result.httpStatus !== 200 ||
          /just a moment|forbidden|access denied|challenge|erreur 503|service unavailable/i.test(text.slice(0,160))) {
        result.status='bloque';
        result.errors.push('Réponse HTTP inattendue ou filtrage anti-robot. Aucune métrique interprétable.');
      } else {
        await delay(5200);
        const snapshot = await page.evaluate(() => {
          const nav = performance.getEntriesByType('navigation')[0];
          const fcp = performance.getEntriesByName('first-contentful-paint')[0];
          const webfonts = document.fonts?.status || null;
          const shifts = window.__mobileAudit.shifts || [];
          const lcp = window.__mobileAudit.lcp;
          const vw=window.innerWidth;
          const images=Array.from(document.querySelectorAll('img')).slice(0,30).map(img=>{
            const rect=img.getBoundingClientRect();
            return {src:img.getAttribute('src'),selected:img.currentSrc,srcset:img.getAttribute('srcset')?.slice(0,400)||null,
              natural:[img.naturalWidth,img.naturalHeight],
              displayed:[Math.round(rect.width),Math.round(rect.height)],
              loading:img.loading,
              visible:rect.bottom>0 && rect.top < window.innerHeight};
          });
          return {
            ttfb:nav ? nav.responseStart-nav.requestStart : null,
            fcp:fcp?.startTime??null,
            domContentLoaded:nav?.domContentLoadedEventEnd||null,
            lcp,
            cls:shifts.reduce((a,x)=>a+x.value,0),
            topShifts:shifts.sort((a,b)=>b.value-a.value).slice(0,6),
            horizontalOverflow:Math.max(0,document.documentElement.scrollWidth-vw),
            viewportWidth:vw,
            fullHeight:document.documentElement.scrollHeight,
            fonts:webfonts,
            themeColor:document.querySelector('meta[name="theme-color"]')?.content||null,
            preloadedFonts:Array.from(document.querySelectorAll('link[rel="preload"][as="font"]')).map(x=>x.getAttribute('href')),
            images,
          };
        });
        const first = Array.from(resources.values()).filter(x=>x.finished);
        const sum = a => a.reduce((v,x)=>v+(x.bytes||0),0);
        const group = a => a.reduce((g,x)=>{
          const type = x.type || 'Other';
          g[type]=(g[type]||0)+(x.bytes||0);
          return g;
        },{});
        result.firstScreen = {
          ttfbMs:round(snapshot.ttfb),
          fcpMs:round(snapshot.fcp),
          lcpMs:round(snapshot.lcp?.time),
          lcpElement:snapshot.lcp?.element||null,
          lcpUrl:snapshot.lcp?.url||null,
          cls:round(snapshot.cls,4),
          topShifts:snapshot.topShifts,
          transferredBytes:sum(first),
          requestCount:first.length,
          bytesByType:group(first),
          horizontalOverflowPx:snapshot.horizontalOverflow,
          viewportWidth:snapshot.viewportWidth,
          fullHeight:snapshot.fullHeight,
          fonts:snapshot.fonts,
          themeColor:snapshot.themeColor,
          preloadedFonts:snapshot.preloadedFonts,
          images:snapshot.images,
        };
        await page.screenshot({path:resolve(out,item.key+'-390x844.png'),timeout:20000});
        // Descendre progressivement pour déclencher les images lazy sans rafale.
        const height=snapshot.fullHeight;
        const step= Math.max(600,Math.floor(844*.85));
        for(let top=step; top < Math.min(height,17000); top+=step) {
          await page.evaluate(y=>window.scrollTo(0,y),top);
          await delay(650);
        }
        await delay(2300);
        const all = Array.from(resources.values()).filter(x=>x.finished);
        const imageDetails=all.filter(x=>x.type==='Image').map(x=>({
          url:x.url.replace(base,''),
          bytes:x.bytes||0, mime:x.mime, status:x.status
        })).sort((a,b)=>b.bytes-a.bytes).slice(0,22);
        result.fullScroll={
          transferredBytes:sum(all),
          requestCount:all.length,
          bytesByType:group(all),
          images:imageDetails,
        };
        const later=await page.evaluate(()=>({
          scrollWidth:document.documentElement.scrollWidth,
          width:window.innerWidth,
          cls:(window.__mobileAudit.shifts||[]).reduce((a,x)=>a+x.value,0),
        }));
        result.fullScroll.horizontalOverflowPx=Math.max(0,later.scrollWidth-later.width);
        result.fullScroll.cls=round(later.cls,4);
        result.status='ok';
      }
    } catch(e) {
      result.status='erreur';
      result.errors.push(String(e?.stack||e).slice(0,1800));
    }
    result.failures=requestsFailed.slice(0,25).map(r=>({
      url:r.url.replace(base,''),error:r.failure
    }));
    results.push(result);
    writeFileSync(resolve(out,'mobile-metrics.json'),
      JSON.stringify({runtime,results},null,2)+'\n');
    console.log(item.key, result.status, JSON.stringify({
      http:result.httpStatus,lcp:result.firstScreen?.lcpMs,
      cls:result.firstScreen?.cls,
      kb:round((result.fullScroll?.transferredBytes||result.firstScreen?.transferredBytes||0)/1024),
      requestFailures:result.failures.length,
    }));
    await context.close();
    // Au moins 1,5 s de repos avant d'ouvrir une autre page.
    await delay(1800);
  }
} catch (e) {
  writeFileSync(resolve(out,'fatal.txt'),String(e?.stack||e));
  console.error('Audit error',e);
} finally {
  if(browser) await browser.close();
}
const baseline={
  accueil:{lcpMs:1192,cls:0.095,bytesKb:362},
  'article-texte':{lcpMs:2092,cls:0.265,bytesKb:322},
  'article-illustre':{lcpMs:3756,cls:0.018,bytesKb:1829,imageKb:1430},
};
const rows=['| Page | HTTP | LCP (ms) | CLS | Premier écran (Ko) | Page défilée (Ko) | Requêtes défilées | Avant LCP (ms) |',
'|---|---:|---:|---:|---:|---:|---:|---:|'];
for(const r of results){
  const first=r.firstScreen||{}, full=r.fullScroll||{}, old=baseline[r.key]||{};
  rows.push('| '+r.title+' | '+(r.httpStatus||'—')+' | '+(first.lcpMs??'—')+' | '+(first.cls??'—')
    +' | '+(first.transferredBytes?round(first.transferredBytes/1024):'—')
    +' | '+(full.transferredBytes?round(full.transferredBytes/1024):'—')
    +' | '+(full.requestCount??'—')+' | '+(old.lcpMs??'—')+' |');
}
const notes=[
'# Audit mobile de production — Univers Guitare',
'',
'Conditions : '+runtime.viewport+' / '+runtime.network+' / CPU '+runtime.cpu+'.',
'Mesures de laboratoire isolées, sans données de terrain. Une visite par URL, cache désactivé.',
'Les visites sont séquentielles (pas de crawl parallèle).',
'',
...rows,
'',
'**Important :** les tailles du premier écran et de la page défilée ne portent pas sur le même périmètre.',
'Ne pas comparer directement la somme des ressources différées avec un ancien bilan de chargement sans vérifier sa méthode.',
'Les temps de réponse peuvent varier selon la charge LWS, le réseau du runner GitHub et le navigateur.',
'',
'## Plus grosses images de la page illustrée',
'',
...(results.find(x=>x.key==='article-illustre')?.fullScroll?.images||[]).map(x=>'- '+round(x.bytes/1024)+' Ko — '+x.url),
'',
'## Incidents',
...(results.flatMap(x=>x.errors.map(e=>'- '+x.key+' : '+e)).length
 ? results.flatMap(x=>x.errors.map(e=>'- '+x.key+' : '+e)) : ['Aucun incident fatal observé.']),
'',
'Pour les détails techniques (ressources, variations de mise en page, images, timing), voir mobile-metrics.json.',
];
writeFileSync(resolve(out,'rapport-mobile.md'),notes.join('\n')+'\n');
