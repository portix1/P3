import { chromium } from 'playwright';
import {mkdirSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';

const dest=resolve('audit-mobile-followup');
mkdirSync(dest,{recursive:true});
const target='https://www.universguitare.be/la-forme-du-corps.html';
const browser=await chromium.launch({headless:true,args:['--disable-background-networking','--no-sandbox']});
const runs=[];
try {
  const context=await browser.newContext({viewport:{width:390,height:844},deviceScaleFactor:3,isMobile:true,hasTouch:true,locale:'fr-BE',serviceWorkers:'block'});
  // Document-only request: no parallel asset requests or image downloads.
  await context.route('**/*',route => route.request().resourceType()==='document' ? route.continue() : route.abort());
  for(let i=0;i<6;i++){
    const page=await context.newPage();
    let status=null;let markup=[];
    try{
      const r=await page.goto(target,{waitUntil:'domcontentloaded',timeout:70000});
      status=r?.status()??null;
      markup=await page.evaluate(()=>Array.from(document.querySelectorAll('.article-reading img')).map((img,j)=>({
        index:j,
        src:img.getAttribute('src'),
        width:img.getAttribute('width'),
        height:img.getAttribute('height'),
        srcset:img.getAttribute('srcset'),
        sizes:img.getAttribute('sizes'),
        loading:img.getAttribute('loading'),
      })));
    }catch(e){markup=[{error:String(e)}];}
    const run={iteration:i+1,time:new Date().toISOString(),http:status,images:markup,
      total:markup.length,withSrcset:markup.filter(x=>!!x.srcset).length,
      withDimensions:markup.filter(x=>!!x.width&&!!x.height).length};
    runs.push(run);
    writeFileSync(resolve(dest,'warm-image-variants.json'),JSON.stringify({url:target,runs},null,2)+'\n');
    console.log('Document',run.iteration,'HTTP',run.http,'images',run.total,'srcset',run.withSrcset,'dimensions',run.withDimensions);
    await page.close();
    if(status!==200)break;
    await new Promise(resolve=>setTimeout(resolve,2200));
  }
  await context.close();
}finally{await browser.close();}
