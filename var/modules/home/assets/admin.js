(() => {
'use strict';
const editor=document.querySelector('[data-home-editor]'); if(!editor)return;
editor.querySelectorAll('button').forEach(button=>{if(!button.title)button.title=button.textContent.trim();});
const list=editor.querySelector('[data-home-sections]'), feedback=editor.querySelector('[data-home-feedback]');
let dirty=false, busy=false;
const say=text=>{feedback.textContent=text;};
const mark=()=>{dirty=true;say('Modifications non enregistrées.');};
editor.addEventListener('input',mark); editor.addEventListener('change',mark);
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
editor.querySelectorAll('form').forEach(form=>form.addEventListener('submit',()=>{dirty=false;}));
const zones=()=>Array.from(list.querySelectorAll('[data-home-section]'));
function refreshOrder(){zones().forEach((zone,i)=>{zone.querySelector('[name=position]').value=i*10;});mark();}
function imagePreview(form){const value=form.elements.image_path.value,img=form.querySelector('[data-home-image-preview]');img.hidden=!value;if(value)img.src=new URL(value,document.baseURI);else img.removeAttribute('src');}
function dialog(title){const d=document.createElement('dialog');d.className='home-dialog';const h=document.createElement('h2');h.textContent=title;const close=document.createElement('button');close.type='button';close.textContent='Fermer';close.title='Fermer';close.addEventListener('click',()=>d.close());d.append(h,close);document.body.append(d);d.addEventListener('close',()=>d.remove(),{once:true});d.showModal();return d;}
async function pick(form){
 const d=dialog('Choisir une image'), search=document.createElement('input'), status=document.createElement('p'),grid=document.createElement('div');
 search.type='search';search.placeholder='Rechercher une image';search.setAttribute('aria-label','Rechercher une image');status.setAttribute('role','status');grid.className='home-media-grid';d.append(search,status,grid);search.focus();let seq=0,timer;
 async function load(){const serial=++seq;status.textContent='Chargement…';try{
  const data=new FormData();data.set('operation','list');data.set('family','image');data.set('search',search.value);data.set('csrf_token',form.elements.csrf_token.value);
  const response=await fetch('Admin-media-api.html',{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}}),payload=await response.json();
  if(!response.ok||!payload.ok)throw Error(payload.error||'Médiathèque indisponible.');if(serial!==seq||!d.open)return;
  grid.replaceChildren();payload.result.filter(row=>row.family==='image'&&/^var\/uploads\/images\/[^?#]+\.(?:jpe?g|png|webp)$/i.test(row.public_path)).forEach(row=>{
   const button=document.createElement('button'),img=document.createElement('img'),label=document.createElement('span');button.type='button';button.title='Choisir '+row.original_name;button.setAttribute('aria-label',button.title);img.src=new URL(row.public_path,document.baseURI);img.alt='';img.loading='lazy';label.textContent=row.original_name;button.append(img,label);
   button.addEventListener('click',()=>{const select=form.elements.image_path;let option=Array.from(select.options).find(o=>o.value===row.public_path);if(!option){option=new Option(row.original_name,row.public_path);select.add(option);}select.value=row.public_path;imagePreview(form);mark();d.close();});grid.append(button);
  });status.textContent=grid.children.length?'Sélectionnez une image.':'Aucune image trouvée.';
 }catch(error){if(d.open&&serial===seq)status.textContent=error.message;}}
 search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(load,350);});d.addEventListener('close',()=>{clearTimeout(timer);seq++;});await load();
}
zones().forEach(zone=>{
 const form=zone.querySelector('form');form.elements.image_path.addEventListener('change',()=>imagePreview(form));
 zone.querySelector('[data-home-pick]').addEventListener('click',()=>pick(form));
 zone.querySelector('[data-home-clear]').addEventListener('click',()=>{form.elements.image_path.value='';imagePreview(form);mark();});
 const kind=form.elements.kind;
 function fields(){const category=kind.value==='articles',text=kind.value==='text';['category_id','descendants','weighted'].forEach(name=>{form.elements[name].closest('label').hidden=!category;});form.elements.body.closest('label').hidden=!text;form.elements.item_count.closest('label').hidden=text;}
 fields();kind.addEventListener('change',()=>{if(kind.value==='latest')form.elements.item_count.value=3;fields();});
 const up=zone.querySelector('[data-home-up]'),down=zone.querySelector('[data-home-down]');
 if(up)up.addEventListener('click',()=>{const previous=zone.previousElementSibling;if(previous){list.insertBefore(zone,previous);refreshOrder();up.focus();}});
 if(down)down.addEventListener('click',()=>{const next=zone.nextElementSibling;if(next&&next.dataset.homeSection!=='0'){list.insertBefore(next,zone);refreshOrder();down.focus();}});
});
let dragged=null;
list.addEventListener('dragstart',e=>{if(!e.target.matches('[data-home-drag]'))return;dragged=e.target.closest('[data-home-section]');e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',dragged.dataset.homeSection);});
list.addEventListener('dragover',e=>{if(dragged){e.preventDefault();e.dataTransfer.dropEffect='move';}});
list.addEventListener('drop',e=>{e.preventDefault();const target=e.target.closest('[data-home-section]');if(dragged&&target&&target!==dragged){list.insertBefore(dragged,target);refreshOrder();}dragged=null;});list.addEventListener('dragend',()=>dragged=null);
async function submit(action){
 if(busy)return;busy=true;
 try{
  const sections=[];
  for(const zone of zones()){
   const form=zone.querySelector('form');
   if(zone.dataset.homeSection==='0'&&!form.elements.title.value.trim()&&!form.elements.body.value.trim()&&!form.elements.category_id.value.match(/^[1-9]/)&&form.elements.kind.value==='articles')continue;
   if(form.elements.home_image?.files.length)throw Error('Enregistrez la zone pour ajouter son fichier à la médiathèque, puis prévisualisez la composition.');
   const data=Object.fromEntries(new FormData(form));delete data.home_image;sections.push(data);
  }
  say(action==='preview'?'Préparation de l’aperçu…':'Enregistrement…');
  const data=new FormData();data.set('csrf_token',editor.querySelector('[name=csrf_token]').value);data.set('home_action',action);data.set('sections',JSON.stringify(sections));
  const response=await fetch('Admin-home.html',{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}}),result=await response.json();
  if(!response.ok||!result.ok)throw Error(result.error||'Impossible de traiter la composition.');
  if(action==='publish'){dirty=false;location.reload();return;}
  say('Aperçu prêt. Aucune modification publiée.');const d=dialog('Aperçu de la composition'),note=document.createElement('p');note.textContent='Aperçu des contenus et des colonnes, sans l’habillage du thème. Le tirage peut varier à la publication.';d.append(note);
  const frame=document.createElement('iframe');frame.title='Aperçu de l’accueil';frame.setAttribute('sandbox','');frame.className='home-preview-frame';frame.srcdoc=result.html;
  const controls=document.createElement('div');[['Ordinateur','1100px'],['Tablette','768px'],['Téléphone','375px']].forEach(([label,width])=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.title='Aperçu '+label.toLowerCase();b.addEventListener('click',()=>{frame.style.width=width;});controls.append(b);});d.append(controls,frame);
 }catch(error){say(error.message);}finally{busy=false;}
}
editor.querySelector('[data-home-preview]').addEventListener('click',()=>submit('preview'));
editor.querySelector('[data-home-publish]').addEventListener('click',()=>submit('publish'));
})();
