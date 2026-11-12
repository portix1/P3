import {chromium} from 'playwright';
import {mkdirSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
mkdirSync('audit-mobile-layout',{recursive:true});
const browser=await chromium.launch();
try {
 const context=await browser.newContext({viewport:{width:390,height:844},deviceScaleFactor:3,isMobile:true,hasTouch:true,locale:'fr-BE'});
 const page=await context.newPage();
 const r=await page.goto('https://www.universguitare.be/la-forme-du-corps.html',{waitUntil:'domcontentloaded',timeout:60000});
 await page.waitForTimeout(3000);
 const obj=await page.evaluate(()=>{
  const links=[...document.querySelectorAll('link[rel="stylesheet"]')].map(x=>x.href);
  const images=[...document.querySelectorAll('.article-reading img')].map((img,i)=>{
   const ancestors=[];let el=img;
   for(let n=0;n<5&&el;n++,el=el.parentElement){
    const cs=getComputedStyle(el);
    ancestors.push({tag:el.tagName,classes:el.className||'',dataWidth:el.getAttribute('data-width'),
      inlineStyle:el.getAttribute('style'),width:Math.round(el.getBoundingClientRect().width),
      float:cs.float,display:cs.display,maxWidth:cs.maxWidth,
      cssWidth:cs.width,position:cs.position});
   }
   return {i,src:img.getAttribute('src')?.slice(0,145),srcset:!!img.getAttribute('srcset'),
    sizes:img.getAttribute('sizes'),w:img.getAttribute('width'),h:img.getAttribute('height'),ancestors};
  });
  const b=el=>({selector:el?.className||'',bg:getComputedStyle(el).backgroundImage,
    height:Math.round(el.getBoundingClientRect().height)});
  return {stylesheetLinks:links,images,header:b(document.querySelector('.site-header.six-cordes-masthead')),
    footer:b(document.querySelector('.site-footer.six-cordes-footer')),
    body:document.body.className,
    viewport:window.innerWidth,status:document.readyState};
 });
 writeFileSync(resolve('audit-mobile-layout','inspect.json'),JSON.stringify({status:r?.status(),...obj},null,2));
 console.log('HTTP',r?.status(),'IMAGES',obj.images.length,'CSS',obj.stylesheetLinks.length);
 for(const img of obj.images)console.log(img.i,img.ancestors.slice(0,3).map(x=>x.tag+'.'+x.classes+'['+x.width+'px]').join(' > '));
 await context.close();
}finally{await browser.close();}
