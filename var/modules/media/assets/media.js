(function () {
    'use strict';
    var section = document.querySelector('[data-media-manager]');
    if (!section) { return; }
    var grid = section.querySelector('[data-media-grid]');
    var status = section.querySelector('[data-media-status]');
    var admin = section.dataset.mediaAdmin === '1';
    var token = document.querySelector('input[name="csrf_token"]');
    var csrf = token ? token.value : document.body.dataset.mediaCsrf;
    var rows = [], analysis = null, generation = 0, busy = false;
    var selected = new Set(), bulkDelete = null, bulkCount = null;
    var bulk = document.createElement('div'); bulk.className='media-actions'; bulk.hidden=true;
    grid.before(bulk);
    function candidates() { return rows.filter(function(row){var uses=analysis && analysis[row.id];return uses && !uses.current.length && !uses.errors.length;}); }
    function updateSelection() {
        if(bulkCount){bulkCount.textContent=selected.size+' fichier(s) sélectionné(s)';}
        if(bulkDelete){bulkDelete.textContent='Supprimer la sélection ('+selected.size+')';bulkDelete.disabled=busy || !selected.size;}
    }
    function report(text) { status.textContent = text; }
    async function api(operation, fields, file) {
        var data = new FormData(); data.set('csrf_token', csrf || ''); data.set('operation', operation);
        Object.keys(fields || {}).forEach(function (key) { data.set(key, fields[key]); });
        if (file) { data.set('file', file, file.name); }
        var response = await fetch(new URL('Admin-media-api.html', document.baseURI), {method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});
        var payload = await response.json();
        if (!response.ok || !payload.ok) { throw new Error(payload.error || 'Opération impossible.'); }
        return payload.result;
    }
    function button(label, action) {
        var node = document.createElement('button'); node.type = 'button'; node.textContent = label; node.title = label;
        node.addEventListener('click', function () { if(!busy) { run(action); } }); return node;
    }
    async function run(action) {
        if(busy){return;}
        busy = true;
        section.querySelectorAll('button').forEach(function (node) { node.disabled = true; });
        section.querySelectorAll('[data-media-select]').forEach(function(node){node.disabled=true;});
        try { await action(); } catch (error) { report(error.message); }
        finally { busy = false; section.querySelectorAll('button').forEach(function (node) { node.disabled = false; }); section.querySelectorAll('[data-media-select]').forEach(function(node){node.disabled=false;}); updateSelection(); }
    }
    function pickFile(action) {
        var input = document.createElement('input'); input.type='file'; input.accept='.jpg,.jpeg,.png,.webp,.pdf,.zip';
        input.addEventListener('change',function(){if(input.files.length){run(function(){return action(input.files[0]);});}}, {once:true});input.click();
    }
    function showUsage(card, uses) {
        var output = card.querySelector('.media-usage');
        if(!output){output=document.createElement('p');output.className='media-usage';card.appendChild(output);}
        output.textContent = uses.errors.length ? 'Analyse incomplète : '+uses.errors.join('; ') : uses.current.length ? 'Utilisé :\n'+uses.current.join('\n') : uses.history.length ? 'Historique uniquement :\n'+uses.history.join('\n') : 'Aucune utilisation détectée.';
        (uses.identical_current || []).forEach(function (copy) {
            output.textContent += '\nCopie identique utilisée : ' + copy.path.split('/').pop() + '\n' + copy.uses.join('\n');
        });
    }
    async function deleteSelection() {
        var ids=Array.from(selected), allowed=[], blocked=[], failed=[], historical=[], skipped=0;
        report('Vérification de '+ids.length+' fichier(s)…');
        for(var id of ids){
            try {
                var uses=await api('usages',{id:id});
                if(uses.current.length || uses.errors.length){blocked.push(id);continue;}
                allowed.push({id:id,history:uses.history.length>0});
                if(uses.history.length){var row=rows.find(function(item){return String(item.id)===id;});historical.push((row?row.public_path:id)+'\n'+uses.history.join('\n'));}
            } catch(error){failed.push(id+' : '+error.message);}
        }
        if(!allowed.length){report('Aucune suppression possible. Bloqués : '+blocked.length+'. Erreurs : '+failed.length+'. '+failed.join('; '));return;}
        var message='Supprimer définitivement '+allowed.length+' fichier(s) sélectionné(s) ?';
        if(blocked.length || failed.length){message+='\nIgnorés : '+blocked.length+' bloqué(s), '+failed.length+' erreur(s).';}
        if(!confirm(message)){report('Suppression annulée.');return;}
        if(historical.length && !confirm('Confirmation spécifique : ces fichiers sont utilisés uniquement dans l’historique. Leur suppression peut casser les illustrations ou liens lors d’une restauration.\n'+historical.join('\n\n')+'\nSupprimer aussi ces fichiers historiques ?')){
            skipped=allowed.filter(function(item){return item.history;}).length;
            allowed=allowed.filter(function(item){return !item.history;});
        }
        var removed=0;
        for(var item of allowed){
            try { await api('delete',{id:item.id,confirm_history:item.history?'1':'0'});selected.delete(item.id);removed++; }
            catch(error){
                if(/bloqu|interd|historique|confirmation/i.test(error.message)){blocked.push(item.id);}else{failed.push(item.id+' : '+error.message);}
            }
        }
        // Refresh uses after mutation; selection is restricted to the refreshed candidates.
        var refreshError='';
        try {analysis=await api('analyse');await load();}
        catch(error){refreshError=' Actualisation impossible : '+error.message+' Rechargez la page avant une autre opération.';selected.clear();grid.replaceChildren();bulk.hidden=true;}
        report('Supprimés : '+removed+'. Bloqués : '+blocked.length+'. Erreurs : '+failed.length+'.'+(skipped?' Conservés à votre demande : '+skipped+'.':'')+(failed.length?' '+failed.join('; '):'')+refreshError);
    }
    function render() {
        grid.replaceChildren();bulk.replaceChildren();bulk.hidden=!analysis || !admin;
        var visible=new Set(candidates().map(function(row){return String(row.id);}));
        selected.forEach(function(id){if(!visible.has(id)){selected.delete(id);}});
        if(analysis && admin){
            bulk.appendChild(button('Tout sélectionner',async function(){visible.forEach(function(id){selected.add(id);});render();}));
            bulk.appendChild(button('Tout désélectionner',async function(){selected.clear();render();}));
            bulk.appendChild(button('Inverser la sélection',async function(){visible.forEach(function(id){if(selected.has(id)){selected.delete(id);}else{selected.add(id);}});render();}));
            bulkDelete=button('Supprimer la sélection',deleteSelection);bulk.appendChild(bulkDelete);
            bulkCount=document.createElement('span');bulk.appendChild(bulkCount);updateSelection();
        }else{selected.clear();bulkDelete=null;bulkCount=null;}
        rows.forEach(function (row) {
            var uses=analysis && analysis[row.id];
            if(analysis && (!uses || uses.current.length || uses.errors.length)){return;}
            var card=document.createElement('article');card.className='media-card';
            if(analysis && admin){
                var label=document.createElement('label'),checkbox=document.createElement('input');
                checkbox.type='checkbox';checkbox.dataset.mediaSelect=String(row.id);checkbox.checked=selected.has(String(row.id));
                checkbox.title='Sélectionner '+row.public_path.split('/').pop();
                checkbox.addEventListener('change',function(){if(this.checked){selected.add(this.dataset.mediaSelect);}else{selected.delete(this.dataset.mediaSelect);}updateSelection();});
                label.append(checkbox,document.createTextNode(' Sélectionner'));card.appendChild(label);
            }
            var preview=document.createElement(row.family==='image'?'img':'div');
            if(row.family==='image'){preview.src=new URL(row.public_path+'?p3media='+row.sha256.slice(0,16),document.baseURI);preview.alt='';preview.loading='lazy';}
            else {preview.className='media-file-icon';preview.textContent=row.family.toUpperCase();}
            var name=document.createElement('strong');name.textContent=row.original_name;
            var filename=document.createElement('small');filename.textContent=row.public_path.split('/').pop();filename.title=row.public_path;
            var size=document.createElement('small');size.textContent=(row.size_bytes<1000000?(row.size_bytes/1000).toFixed(1)+' ko':(row.size_bytes/1000000).toFixed(2)+' Mo')+(row.width?' · '+row.width+' × '+row.height:'');
            var actions=document.createElement('div');actions.className='media-card-actions';
            actions.appendChild(button('Voir les utilisations',async function(){showUsage(card,await api('usages',{id:row.id}));}));
            if(row.family==='image'){
                var a11y=button('Accessibilité',async function(){
                    var editor=document.createElement('div');editor.className='media-a11y';
                    var alt=document.createElement('input');alt.type='text';alt.maxLength=250;
                    alt.value=row.alt_text || '';alt.title='Description utile à une personne qui ne voit pas l’image';
                    var label=document.createElement('label');label.textContent='Texte alternatif';label.appendChild(alt);
                    var decorative=document.createElement('input');decorative.type='checkbox';
                    decorative.checked=row.is_decorative===1 || row.is_decorative==='1';
                    decorative.title='Cochez seulement si cette image est purement décorative';
                    alt.disabled=decorative.checked;
                    var decorativeLabel=document.createElement('label');
                    decorativeLabel.append(decorative,document.createTextNode(' Image décorative'));
                    decorative.addEventListener('change',function(){
                        alt.disabled=decorative.checked;
                        if(decorative.checked){alt.value='';}
                    });
                    var help=document.createElement('small');
                    help.textContent='Le texte enregistré sera proposé à la prochaine insertion. Chaque article peut le personnaliser.';
                    var save=document.createElement('button');save.type='button';save.textContent='Enregistrer';
                    save.title='Enregistrer le texte alternatif de ce média';
                    save.addEventListener('click',function(){run(async function(){
                        if(!decorative.checked && !alt.value.trim()){report('Renseignez une description ou cochez « Image décorative ».');return;}
                        var updated=await api('set_accessibility',{id:row.id,alt_text:alt.value.trim(),is_decorative:decorative.checked?'1':'0'});
                        row.alt_text=updated.alt_text;row.is_decorative=updated.is_decorative;
                        report('Accessibilité du média enregistrée. Les articles existants ne sont pas modifiés.');
                        render();
                    });});
                    editor.append(label,decorativeLabel,help,save);
                    card.appendChild(editor);alt.focus();
                });
                actions.appendChild(a11y);
            }

            if(admin){
                actions.appendChild(button('Remplacer',function(){pickFile(async function(file){
                    var current=await api('usages',{id:row.id});
                    if(current.errors.length){throw new Error('Analyse incomplète : remplacement non engagé.');}
                    if(!confirm('Remplacer ce média dans toutes ses utilisations ?\n'+current.current.concat(current.history).join('\n'))){return;}
                    try {await api('replace',{id:row.id},file);}
                    catch(error){if(!error.message.includes('Dimensions différentes') || !confirm(error.message+'\nConfirmer ?')){throw error;}await api('replace',{id:row.id,confirm_dimensions:'1'},file);}
                    analysis=null;await load();report('Média remplacé. La version précédente est conservée.');
                });}));
                actions.appendChild(button('Version précédente',async function(){if(confirm('Revenir à la version précédente pour toutes les utilisations ?')){await api('restore',{id:row.id});analysis=null;await load();report('Version précédente rétablie.');}}));
                actions.appendChild(button('Supprimer',async function(){
                    var checked=await api('usages',{id:row.id});showUsage(card,checked);
                    if(checked.errors.length || checked.current.length){throw new Error('Suppression interdite : usages actuels ou analyse incomplète.');}
                    var message=checked.history.length?'Attention : ce média est utilisé dans les contenus historiques suivants :\n'+checked.history.join('\n')+'\nLeur restauration pourrait laisser des illustrations ou liens manquants. Supprimer quand même ?':'Supprimer définitivement ce média inutilisé ?';
                    if(confirm(message)){await api('delete',{id:row.id,confirm_history:checked.history.length?'1':'0'});analysis=null;await load();report('Média supprimé.');}
                }));
            }
            card.append(preview,name,filename,size,actions);if(uses){showUsage(card,uses);}grid.appendChild(card);
        });
    }
    async function load(){var current=++generation;var result=await api('list',{family:section.querySelector('[data-media-family]').value,search:section.querySelector('[data-media-search]').value});if(current===generation){rows=result;render();}}
    section.querySelector('[data-media-add]').addEventListener('click',function(){pickFile(async function(file){await api('upload',{},file);analysis=null;await load();report('Fichier ajouté.');});});
    var auditButton=section.querySelector('[data-media-alt-audit]');
    if(auditButton){
        auditButton.addEventListener('click',function(){run(async function(){
            report('Analyse des articles en cours…');
            var result=await api('alt_audit');
            var output=section.querySelector('[data-media-alt-results]');
            var summary=output.querySelector('[data-media-alt-summary]');
            var list=output.querySelector('[data-media-alt-list]');
            list.replaceChildren();
            summary.textContent=result.articles+' article(s) examinés, '+result.images
                +' image(s) : '+result.described+' décrite(s), '+result.decorative
                +' décorative(s) déclarée(s), '+result.issues.length+' à vérifier.';
            result.issues.forEach(function(row){
                var entry=document.createElement('article');entry.className='media-alt-issue';
                var heading=document.createElement('h3');heading.textContent=row.title+' — image '+row.position;
                var meta=document.createElement('p');
                meta.textContent=(row.status==='published'?'Publié':'Brouillon')
                    +' · attribut alt '+row.reason+' · '+row.src;
                var caption=document.createElement('p');
                caption.textContent=row.caption ? 'Légende : '+row.caption : 'Sans légende.';
                var link=document.createElement('a');
                link.href='Admin-articles-edit-'+row.id+'.html';
                link.textContent='Ouvrir cet article';
                link.title='Modifier les propriétés de l’image dans le WYSIWYG';
                entry.append(heading,meta,caption,link);list.appendChild(entry);
            });
            (result.errors || []).forEach(function(error){
                var line=document.createElement('p');line.textContent=error;list.appendChild(line);
            });
            output.hidden=false;
            report('Audit terminé. Aucun article n’a été modifié.');
        });});
    }
    section.querySelector('[data-media-family]').addEventListener('change',function(){run(load);});
    var timer;section.querySelector('[data-media-search]').addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(function(){run(load);},250);});
    if(admin){
        section.querySelector('[data-media-webp]').addEventListener('click',function(){run(async function(){
            var pending=await api('webp_candidates');
            if(!pending.length){report('Aucun PNG à migrer.');return;}
            if(!confirm('Convertir '+pending.length+' PNG sans perte et mettre à jour leurs utilisations ?\nLes originaux et leurs anciennes URL seront conservés.')){return;}
            var done=0, errors=[];
            for(var item of pending){
                report('Migration '+(done+errors.length+1)+'/'+pending.length+'…');
                try {await api('webp_migrate',{id:item.id});done++;}
                catch(error){errors.push(item.public_path.split('/').pop()+' : '+error.message);}
            }
            analysis=null;await load();report(done+' PNG migré(s). '+errors.length+' échec(s). '+errors.join('; '));
        });});
        section.querySelector('[data-media-inventory]').addEventListener('click',function(){run(async function(){var result=await api('inventory');analysis=null;await load();report(result.added+' fichier(s) répertorié(s). Non reconnus : '+result.unknown.join(', '));});});
        section.querySelector('[data-media-analyse]').addEventListener('click',function(){run(async function(){report('Analyse en cours…');analysis=await api('analyse');await load();report('Candidats affichés ; aucune suppression effectuée. Une analyse incomplète bloque la suppression.');});});
        section.querySelector('[data-media-recover]').addEventListener('click',function(){run(async function(){await api('recover');await load();report('Récupération terminée.');});});
    }
    run(load);
})();
