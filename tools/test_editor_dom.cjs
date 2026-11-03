const {setup,assert}=require('./editor_test_setup.cjs');
const image='var/uploads/images/0123456789abcdef0123456789abcdef.png';
(async()=>{
 let t=await setup('<p>Avant</p><figure class="portix-image-figure portix-image-size-50 portix-image-align-left"><img src="'+image+'" alt="Vue"><figcaption>Légende précise</figcaption></figure><p>Après</p>');
 
 assert.equal(t.q.root.querySelectorAll('img').length,1);
 assert.match(t.save(),/Légende précise/);assert.match(t.save(),/portix-image-align-left/);
 const format=t.q.getFormat(6,1);
 const img=t.q.root.querySelector('img');img.dispatchEvent(new t.w.MouseEvent('click',{bubbles:true}));
 const caption=t.w.document.querySelector('input[maxlength="500"]');caption.value='Nouvelle';caption.dispatchEvent(new t.w.Event('input',{bubbles:true}));
 assert.match(t.save(),/Nouvelle/);
 t.q.getModule('history').undo();assert.match(t.save(),/Légende précise/);
 const divider=t.w.document.querySelector('[data-command="divider"]');divider.click();assert.match(t.save(),/<hr>/);
 assert.equal(Array.from(t.w.document.querySelectorAll('[data-portix-editor] button,.ql-picker-label,.ql-picker-item')).filter(b=>!b.title).length,0);
 const reload=await setup(t.save());assert.equal(reload.q.root.querySelectorAll('img').length,1);assert.match(reload.save(),/Légende précise/);
 t.dom.window.close();reload.dom.window.close();
 t=await setup('<table><thead><tr><th scope="col">A</th><th scope="col">B</th></tr></thead><tbody><tr><th scope="row">Row</th><td>Début<figure class="portix-image-figure portix-image-size-50"><img src="'+image+'"><figcaption>Cellule</figcaption></figure>Fin</td></tr></tbody></table><p>Fin document</p>');
 
 assert.equal(t.q.root.querySelectorAll('td').length,4);assert.match(t.save(),/<th[^>]*scope="row"/);assert.match(t.save(),/<td[^>]*>Début[\s\S]*<figure[\s\S]*Cellule[\s\S]*Fin<\/td>/);
 const tableImage=t.q.root.querySelector('img');tableImage.dispatchEvent(new t.w.MouseEvent('click',{bubbles:true}));
 const tableCaption=t.w.document.querySelector('input[maxlength="500"]');tableCaption.value='Cellule modifiée';tableCaption.dispatchEvent(new t.w.Event('input',{bubbles:true}));assert.match(t.save(),/Cellule modifiée/);
 const data=t.q.getContents();t.q.setContents(data);assert.equal(t.q.root.querySelectorAll('img').length,1);assert.match(t.save(),/Cellule modifiée/);
 const tableReload=await setup(t.save());assert.equal(tableReload.q.root.querySelectorAll('td').length,4);assert.match(tableReload.save(),/scope="row"/);
 t.dom.window.close();tableReload.dom.window.close();
 t=await setup('<p><a href="https://example.org">Externe</a><span class="ql-size-huge ql-font-serif" style="background-color: rgb(255, 255, 0)">Grand</span><sup>2</sup></p>');
 t.q.clipboard.dangerouslyPasteHTML(t.q.getLength()-1, '<a href="https://example.net">Nouveau lien</a>', 'user');assert.match(t.save(),/target="_blank"/);assert.match(t.save(),/ql-size-huge/);assert.match(t.save(),/background-color/);
 t.dom.window.close();console.log('Editor integration: OK');
})().catch(e=>{console.error(e);process.exit(1);});
