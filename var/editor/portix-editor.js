(function () {
    'use strict';
    var labels = { equipment: 'Information', advice: 'Conseil', warning: 'Avertissement', note: 'Note' };
    function typeOf(node) {
        return Object.keys(labels).find(function (type) { return node.classList.contains('portix-callout-' + type); }) || '';
    }
    function register() {
        var P = Quill.import('parchment');
        Quill.register(new P.ClassAttributor('callout', 'portix-callout', { scope: P.Scope.BLOCK, whitelist: Object.keys(labels) }), true);
        Quill.register(new P.ClassAttributor('callout-title', 'portix-box-title', { scope: P.Scope.BLOCK, whitelist: ['yes'] }), true);
    }
    function editable(root) {
        root.querySelectorAll('div.portix-callout').forEach(function (box) {
            var type = typeOf(box);
            box.querySelectorAll('.portix-callout-label').forEach(function (label) { label.remove(); });
            Array.from(box.children).forEach(function (node) {
                var lines = /^(UL|OL)$/.test(node.tagName) ? node.querySelectorAll('li') : [node];
                Array.from(lines).forEach(function (line) { if (type) { line.classList.add('portix-callout-' + type); } });
            });
            box.querySelectorAll('.portix-callout-title').forEach(function (title) { title.classList.replace('portix-callout-title', 'portix-box-title-yes'); });
            box.replaceWith.apply(box, Array.from(box.childNodes));
        });
    }
    function semantic(root, quill) {
        // Quill serializes list items without block attributes. Restore only our
        // attributes from the corresponding live item, including nested lists.
        var originals = Array.from(quill.root.querySelectorAll('li'));
        var cursor = 0;
        function ownText(node) { var copy = node.cloneNode(true); copy.querySelectorAll('ul,ol,.ql-ui').forEach(function (n) { n.remove(); }); return copy.textContent.trim(); }
        root.querySelectorAll('li').forEach(function (li) {
            var text = ownText(li), original = originals[cursor];
            if (!original || ownText(original) !== text) { return; }
            cursor++;
            var type = typeOf(original); if (type) { li.classList.add('portix-callout-' + type); }
            if (original.classList.contains('portix-box-title-yes')) { li.classList.add('portix-box-title-yes'); }
        });
        // An image is a Quill inline embed, but its public HTML is a <figure>.
        // The browser reparses <p><figure>...</figure></p> as sibling nodes.
        // Recover the enclosing line's callout format from the live editor
        // BEFORE grouping the public HTML. Match in document order, never by URL.
        var liveImages = Array.from(quill.root.querySelectorAll('.portix-image-object'));
        Array.from(root.querySelectorAll('figure.portix-image-figure')).forEach(function (figure, index) {
            var live = liveImages[index];
            if (!live) { return; }
            var parent = live.closest('p,li,h1,h2,h3,h4,h5,h6,blockquote');
            var type = parent ? typeOf(parent) : '';
            if (type) { figure.classList.add('portix-callout-' + type); }
        });
        // Split lists into runs before grouping so a partially selected list stays a list.
        Array.from(root.querySelectorAll('ul,ol')).reverse().forEach(function (list) {
            var run = null, previous = null;
            Array.from(list.children).forEach(function (li) {
                var type = typeOf(li);
                if (!run || type !== previous) { run = list.cloneNode(false); list.before(run); previous = type; if (type) { run.classList.add('portix-callout-' + type); } }
                if (type) { li.classList.remove('portix-callout-' + type); }
                run.appendChild(li);
            });
            list.remove();
        });
        function group(parent) {
            var box = null, current = '';
            Array.from(parent.children).forEach(function (node) {
                var type = typeOf(node);
                if (!type) { box = null; current = ''; if (!node.matches('div.portix-video,figure,table')) { group(node); } return; }
                if (!box || current !== type) {
                    box = document.createElement('div'); box.className = 'portix-callout portix-callout-' + type;
                    var label = document.createElement('p'); label.className = 'portix-callout-label'; label.textContent = labels[type]; box.appendChild(label);
                    node.before(box); current = type;
                }
                node.classList.remove('portix-callout-' + type);
                if (node.classList.contains('portix-box-title-yes')) { node.classList.replace('portix-box-title-yes', 'portix-callout-title'); }
                if (!node.className) { node.removeAttribute('class'); }
                box.appendChild(node);
            });
        }
        group(root);
    }
    function tools(quill, wrapper, toolbar) {
        function button(label, command) {
            var b = document.createElement('button'); b.type = 'button'; b.textContent = label; b.title = label; b.setAttribute('aria-label', label); b.dataset.calloutCommand = command;
            b.addEventListener('mousedown', function (e) { e.preventDefault(); }); return b;
        }
        var toggle = button('Insérer ou modifier un encadré', 'toggle'); toggle.className = 'portix-rich-tool portix-callout-toggle'; toggle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect class="ql-stroke" x="3" y="4" width="18" height="16" rx="1" fill="none"/><path class="ql-stroke" d="M7 4v16M11 9h6M11 13h6M11 17h4" fill="none"/></svg>'; toggle.setAttribute('aria-expanded', 'false'); toolbar.appendChild(toggle);
        var panel = document.createElement('fieldset'); panel.className = 'portix-callout-tools'; panel.hidden = true;
        var legend = document.createElement('legend'); legend.textContent = 'Encadré éditorial'; panel.appendChild(legend);
        var label = document.createElement('label'); label.textContent = 'Type d’encadré ';
        var select = document.createElement('select'); select.title = 'Type d’encadré'; select.setAttribute('aria-label', select.title);
        Object.keys(labels).forEach(function (type) { var o = document.createElement('option'); o.value = type; o.textContent = labels[type]; select.appendChild(o); }); label.appendChild(select); panel.appendChild(label);
        var apply = button('Appliquer', 'apply'), title = button('Titre d’encadré', 'title'), remove = button('Retirer l’encadré', 'remove'), exit = button('Continuer après', 'exit'), close = button('Fermer', 'close');
        title.setAttribute('aria-pressed', 'false'); panel.append(apply, title, remove, exit, close); wrapper.querySelector('.ql-toolbar').after(panel);
        var saved = null;
        function range() { return saved || quill.selection.savedRange || { index: quill.getLength() - 1, length: 0 }; }
        function linesInBox() {
            var r = range(), line = quill.getLine(r.index)[0], type = line && line.formats().callout;
            if (!type || r.length) { return quill.getLines(r.index, Math.max(r.length, 1)); }
            var all = quill.getLines(), i = all.indexOf(line), start = i, end = i;
            while (start > 0 && all[start - 1].formats().callout === type) { start--; }
            while (end + 1 < all.length && all[end + 1].formats().callout === type) { end++; }
            return all.slice(start, end + 1);
        }
        function hide() { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); }
        function restore() { var r = range(); quill.setSelection(r.index, r.length, 'silent'); }
        toggle.addEventListener('click', function () {
            if (!panel.hidden) { hide(); return; }
            var r = quill.getSelection() || quill.selection.savedRange; saved = r ? { index: r.index, length: r.length } : null;
            var format = quill.getFormat(range().index, range().length); select.value = typeof format.callout === 'string' ? format.callout : 'advice'; title.setAttribute('aria-pressed', format['callout-title'] === 'yes' ? 'true' : 'false');
            panel.hidden = false; toggle.setAttribute('aria-expanded', 'true'); select.focus();
        });
        function mutate(fn) { restore(); quill.getModule('history').cutoff(); fn(); quill.getModule('history').cutoff(); hide(); quill.focus(); }
        apply.addEventListener('click', function () { mutate(function () { linesInBox().forEach(function (line) { if (line.domNode.closest('td,th')) { return; } quill.formatLine(quill.getIndex(line), 1, 'callout', select.value, 'user'); }); }); });
        title.addEventListener('click', function () { mutate(function () { var r = range(), formats = quill.getFormat(r.index, 0); if (quill.getLine(r.index)[0].domNode.closest('td,th')) { return; } if (!formats['callout-title']) { quill.formatLine(r.index, 1, { header: false, list: false, blockquote: false }, 'user'); } if (!formats.callout) { quill.formatLine(r.index, 1, 'callout', select.value, 'user'); } quill.formatLine(r.index, 1, 'callout-title', formats['callout-title'] === 'yes' ? false : 'yes', 'user'); }); });
        remove.addEventListener('click', function () { mutate(function () { linesInBox().forEach(function (line) { quill.formatLine(quill.getIndex(line), 1, { callout: false, 'callout-title': false }, 'user'); }); }); });
        exit.addEventListener('click', function () { mutate(function () { var lines = linesInBox(), last = lines[lines.length - 1]; if (!last) { return; } var index = quill.getIndex(last) + last.length(); quill.insertText(index, '\n', {}, 'user'); quill.formatLine(index, 1, { callout: false, 'callout-title': false, list: false, header: false, blockquote: false }, 'user'); quill.setSelection(index, 0, 'silent'); }); });
        close.addEventListener('click', function () { hide(); restore(); quill.focus(); });
        panel.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); hide(); toggle.focus(); } });
    }
    window.PortixCallouts = { register: register, editable: editable, semantic: semantic, tools: tools };
}());

(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) { return; }
    var base = new URL('.', script.src);
    var editorAssetVersion = (new URL(script.src).searchParams.get('v') || 'rc15-media-0.1.0-rc1') + '-tables-0.1.0';

    function editorAsset(path) {
        var url = new URL(path, base);
        url.searchParams.set('v', editorAssetVersion);
        return url.href;
    }
    var maximumImageSize = 8 * 1024 * 1024;
    var allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    var imageSizes = ['25', '33', '50', '66', '75', '100'];
    var imageAlignments = ['left', 'center', 'right'];
    var katexBase = 'https://cdn.jsdelivr.net/npm/katex@0.18.9/dist/';
    var katexCssIntegrity = 'sha384-lPx0C4zIUZLpveABMwOFcFeGZwsvKBJfhJ85FN1PYOV7xApBcFMhcAEMVKF8loOI';
    var katexJsIntegrity = 'sha384-19KE2cFb3U+RUWmyhBz7aLOGDG8WrRC6hE3oY/HTZZlAAVWYTdmvLC//+TIV3zUx';

    function message(name, fallback) {
        return document.body ? (document.body.getAttribute('data-' + name) || fallback) : fallback;
    }

    function loadStyle(url, name, integrity) {
        if (document.querySelector('link[data-portix-editor-style="' + name + '"]')) { return; }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        link.setAttribute('data-portix-editor-style', name);
        if (integrity) {
            link.integrity = integrity;
            link.crossOrigin = 'anonymous';
        }
        document.head.appendChild(link);
    }

    function loadExternalScript(url, marker, ready, callback, errorCallback, integrity) {
        if (ready()) { callback(); return; }
        var existing = document.querySelector('script[' + marker + ']');
        if (existing) {
            existing.addEventListener('load', callback, { once: true });
            if (errorCallback) { existing.addEventListener('error', errorCallback, { once: true }); }
            return;
        }
        var externalScript = document.createElement('script');
        externalScript.src = url;
        externalScript.setAttribute(marker, '1');
        if (integrity) {
            externalScript.integrity = integrity;
            externalScript.crossOrigin = 'anonymous';
        }
        externalScript.addEventListener('load', callback, { once: true });
        if (errorCallback) { externalScript.addEventListener('error', errorCallback, { once: true }); }
        document.head.appendChild(externalScript);
    }

    function loadQuill(callback) {
        loadExternalScript(
            new URL('quill/quill.js', base).href,
            'data-portix-quill',
            function () { return Boolean(window.Quill); },
            callback
        );
    }

    function loadKatex(callback) {
        var done = false;
        var timer = window.setTimeout(function () { finish(false); }, 8000);
        function finish(ready) { if (done) { return; } done = true; window.clearTimeout(timer); callback(ready); }
        loadStyle(katexBase + 'katex.min.css', 'katex', katexCssIntegrity);
        loadExternalScript(katexBase + 'katex.min.js', 'data-portix-katex',
            function () { return Boolean(window.katex); }, function () { finish(true); }, function () { finish(false); }, katexJsIntegrity);
    }

    function statusElement(wrapper) {
        var status = wrapper.nextElementSibling;
        if (status && status.classList.contains('portix-editor-status')) { return status; }
        status = document.createElement('p');
        status.className = 'portix-editor-status';
        status.setAttribute('aria-live', 'polite');
        wrapper.insertAdjacentElement('afterend', status);
        return status;
    }

    function showStatus(wrapper, message, type) {
        var status = statusElement(wrapper);
        status.textContent = message || '';
        status.classList.toggle('is-error', type === 'error');
        status.classList.toggle('is-success', type === 'success');
    }

    function setFormBusy(form, busy) {
        var pending = Number(form.dataset.portixPendingUploads || 0);
        pending = Math.max(0, pending + (busy ? 1 : -1));
        form.dataset.portixPendingUploads = String(pending);
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
            button.disabled = pending > 0;
        });
    }

    function validateImage(file) {
        if (!file || allowedImageTypes.indexOf(file.type) === -1) {
            throw new Error(message('editor-invalid-image-format', 'Unsupported image format.'));
        }
        if (file.size > maximumImageSize) {
            throw new Error(message('editor-image-too-large', 'Image too large.'));
        }
    }

    function uploadImage(file, wrapper) {
        validateImage(file);
        var form = wrapper.closest('form');
        var token = form ? form.querySelector('input[name="csrf_token"]') : null;
        if (!form || !token) {
            return Promise.reject(new Error(message('editor-incomplete-form', 'Incomplete security form.')));
        }

        var data = new FormData();
        data.append('csrf_token', token.value);
        data.append('image', file, file.name || 'image');
        setFormBusy(form, true);
        showStatus(wrapper, message('editor-uploading-image', 'Uploading image…'));

        var uploadUrl = wrapper.getAttribute('data-upload-url') || 'Admin-news-upload-image.html';
        return fetch(uploadUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (response) {
            return response.json().catch(function () {
                throw new Error(message('editor-invalid-response', 'Invalid server response.'));
            }).then(function (payload) {
                if (!response.ok || !payload.ok || !payload.url) {
                    throw new Error(payload.error || message('editor-upload-failed', 'Unable to store image.'));
                }
                return payload.url;
            });
        }).finally(function () {
            setFormBusy(form, false);
        });
    }

    // Quill stores callout membership on the newline ending each block.
    // Images are inline embeds; newlines inserted around them must retain the
    // enclosing callout or the public <figure> is serialized outside the box.
    function imageCalloutAttributes(quill, index) {
        var line = quill.getLine(Math.max(0, Math.min(index, quill.getLength() - 1)))[0];
        var type = line && line.formats().callout;
        return ['advice', 'warning', 'equipment', 'note'].indexOf(type) !== -1
            ? { callout: type } : {};
    }

    function insertImages(files, quill, wrapper) {
        var range = quill.getSelection(true);
        var position = range ? range.index : Math.max(0, quill.getLength() - 1);
        var sequence = Promise.resolve();
        var tableModule = null;
        var insideTable = false;

        try { tableModule = quill.getModule('table'); } catch (error) { tableModule = null; }
        if (tableModule && typeof tableModule.getTable === 'function') {
            var tableInfo = tableModule.getTable({ index: position, length: 0 });
            insideTable = Boolean(tableInfo && tableInfo[0]);
        }

        if (range && range.length) {
            quill.deleteText(range.index, range.length, 'user');
        }

        var lineAndOffset = quill.getLine(position);
        var imageCallout = !insideTable ? imageCalloutAttributes(quill, position) : {};
        if (!insideTable && lineAndOffset[0] && lineAndOffset[1] > 0) {
            quill.insertText(position, '\n', imageCallout, 'user');
            position += 1;
        }

        files.forEach(function (file) {
            sequence = sequence.then(function () {
                return uploadImage(file, wrapper);
            }).then(function (url) {
                quill.insertEmbed(position, 'portix-image', { src: url }, 'user');
                // P3 image-callout 0.1.4: set the image line explicitly, not just the inserted newline.
                if (!insideTable && imageCallout.callout) {
                    quill.formatLine(position, 1, 'callout', imageCallout.callout, 'user');
                }
                if (insideTable) {
                    position += 1;
                    quill.insertText(position, ' ', 'user');
                    position += 1;
                } else {
                    quill.insertText(position + 1, '\n', imageCallout, 'user');
                    position += 2;
                }
                quill.setSelection(position, 0, 'silent');
            });
        });

        sequence.then(function () {
            showStatus(
                wrapper,
                files.length > 1
                    ? message('editor-images-added', 'Images added.')
                    : message('editor-image-added', 'Image added.'),
                'success'
            );
        }).catch(function (error) {
            showStatus(wrapper, error.message || message('editor-image-failed', 'Unable to add image.'), 'error');
        });
    }

    function mediaEnabled() {
        return document.body && document.body.getAttribute('data-portix-media') === '1';
    }

    function selectMedia(quill, wrapper, family) {
        loadStyle(new URL('../modules/media/assets/media.css?v=0.1.0-rc3', base).href, 'media');
        var dialog = makeEditorDialog(family === 'image' ? 'Insérer une image' : 'Insérer un fichier');
        dialog.dialog.classList.add('media-selector');
        dialog.dialog.querySelector('form').addEventListener('submit', function (event) { event.preventDefault(); });
        rememberEditorRange(quill, dialog);
        var savedText = dialog.range && dialog.range.length ? quill.getText(dialog.range.index, dialog.range.length) : '';
        var actions = document.createElement('div'); actions.className = 'media-actions';
        var search = document.createElement('input'); search.type = 'search'; search.placeholder = 'Rechercher'; search.title = 'Rechercher dans la médiathèque'; search.setAttribute('aria-label', search.title);
        var add = makeRichButton('Ajouter un fichier');
        var grid = document.createElement('div'); grid.className = 'media-grid';
        var status = document.createElement('p'); status.className = 'media-status'; status.setAttribute('role', 'status');
        var label = document.createElement('input'); label.type = 'text'; label.maxLength = 250; label.title = 'Texte du lien'; label.value = savedText;
        var labelField = labelledField('Texte du lien', label); labelField.classList.add('media-document-label'); labelField.hidden = family === 'image';
        var insert = makeRichButton('Insérer'); insert.disabled = true;
        var cancel = makeRichButton('Annuler');
        actions.append(search, add); dialog.body.append(actions,status,grid,labelField); dialog.actions.append(cancel,insert);
        var selected = null, serial = 0, closed = false, busy = false, timer;
        function close() { closed=true;clearTimeout(timer);dialog.close();dialog.dialog.remove();quill.focus(); }
        cancel.addEventListener('click', close);
        dialog.dialog.addEventListener('cancel', function(event){event.preventDefault();close();});
        dialog.dialog.addEventListener('close',function(){closed=true;clearTimeout(timer);dialog.dialog.remove();},{once:true});
        async function api(operation, fields, file) {
            var form = wrapper.closest('form'); var token = form && form.querySelector('input[name="csrf_token"]');
            if (!token) { throw new Error('Jeton de sécurité absent. Rechargez la page.'); }
            var data = new FormData();data.set('csrf_token',token.value);data.set('operation',operation);
            Object.keys(fields || {}).forEach(function(key){data.set(key,fields[key]);});
            if(file){data.set('file',file,file.name);}
            var response=await fetch(new URL('Admin-media-api.html',document.baseURI),{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});
            var payload=await response.json();
            if(!response.ok || !payload.ok){throw new Error(payload.error || 'Médiathèque indisponible.');}
            return payload.result;
        }
        function choose(row, card) {
            selected=row;insert.disabled=false;
            grid.querySelectorAll('.media-card').forEach(function(node){node.classList.toggle('is-selected',node===card);node.setAttribute('aria-pressed',node===card?'true':'false');});
            if(family!=='image' && !savedText){label.value=row.original_name;}
        }
        async function load() {
            var current=++serial;status.textContent='Chargement…';
            try {
                var rows=await api('list',{family:family==='image'?'image':'',search:search.value});
                if(closed || current!==serial){return;}
                selected=null;insert.disabled=true;grid.replaceChildren();
                rows.filter(function(row){return family==='image'?row.family==='image':row.family==='pdf'||row.family==='zip';}).forEach(function(row){
                    var card=document.createElement('button');card.type='button';card.className='media-card';card.title='Sélectionner '+row.original_name;card.setAttribute('aria-label',card.title);card.setAttribute('aria-pressed','false');
                    var preview=document.createElement(row.family==='image'?'img':'span');
                    if(row.family==='image'){preview.src=new URL(row.public_path+'?p3media='+row.sha256.slice(0,16),document.baseURI);preview.alt='';preview.loading='lazy';}
                    else {preview.className='media-file-icon';preview.textContent=row.family.toUpperCase();}
                    var name=document.createElement('strong');name.textContent=row.original_name;
                    var size=document.createElement('small');size.textContent=(row.size_bytes<1000000?(row.size_bytes/1000).toFixed(1)+' ko':(row.size_bytes/1000000).toFixed(2)+' Mo');
                    card.append(preview,name,size);
                    card.addEventListener('click',function(){choose(row,card);});
                    card.addEventListener('dblclick',function(){choose(row,card);insert.click();});grid.appendChild(card);
                });
                status.textContent=grid.children.length?'Sélectionnez un fichier.':'Aucun média trouvé. Vous pouvez ajouter un fichier.';
            } catch(error){if(!closed && current===serial){status.textContent=error.message;}}
        }
        search.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(load,250);});
        add.addEventListener('click',function(){
            var input=document.createElement('input');input.type='file';input.accept=family==='image'?'.jpg,.jpeg,.png,.webp':'.pdf,.zip';
            input.addEventListener('change',async function(){
                if(!input.files.length || busy){return;}
                busy=true;add.disabled=true;cancel.disabled=true;insert.disabled=true;
                var form=wrapper.closest('form');setFormBusy(form,true);status.textContent='Envoi en cours…';
                try {await api('upload',{},input.files[0]);await load();}
                catch(error){status.textContent=error.message;}
                finally{busy=false;add.disabled=false;cancel.disabled=false;setFormBusy(form,false);}
            },{once:true});input.click();
        });
        insert.addEventListener('click',function(){
            if(!selected || busy){return;}
            var row=selected;var text=label.value.trim();
            if(family!=='image' && !text){status.textContent='Indiquez le texte du lien.';label.focus();return;}
            // Validate against the same local allow-list as the editor, even for server responses.
            if(family==='image' ? !safeImageSource(row.public_path) : !/^var\/uploads\/media\/[a-f0-9]{32}\.(?:pdf|zip)$/.test(row.public_path)){
                status.textContent='Adresse de média refusée.';return;
            }
            var range=restoredEditorRange(quill,dialog);var history=quill.getModule('history');history.cutoff();
            var Delta=Quill.import('delta');
            if(family==='image'){
                var insideTable=Boolean(quill.root.querySelector('td,th') && quill.getModule('table').getTable(range)[0]);
                var line=quill.getLine(range.index);var prefix=!insideTable && line[1]>0?'\n':'';
                var imageCallout=!insideTable?imageCalloutAttributes(quill,range.index):{};
                var delta=new Delta().retain(range.index).delete(range.length);
                if(prefix){delta.insert(prefix,imageCallout);}
                delta.insert({'portix-image':{src:row.public_path}});
                if(!insideTable){delta.insert('\n',imageCallout);}
                quill.updateContents(delta,'user');
                // Keep the image inside the selected callout on save and reopen.
                if (!insideTable && imageCallout.callout) {
                    quill.formatLine(range.index + prefix.length, 1, 'callout', imageCallout.callout, 'user');
                }
                quill.setSelection(range.index+prefix.length+1,0,'silent');
            } else {
                quill.updateContents(new Delta().retain(range.index).delete(range.length).insert(text,{link:row.public_path,'link-target':'self'}),'user');
                quill.setSelection(range.index+text.length,0,'silent');
            }
            history.cutoff();close();
        });
        dialog.open();search.focus();load();
    }

    function selectImage(quill, wrapper) {
        if (mediaEnabled()) { selectMedia(quill, wrapper, 'image'); return; }
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = allowedImageTypes.join(',');
        input.addEventListener('change', function () {
            if (input.files && input.files.length) {
                insertImages(Array.from(input.files), quill, wrapper);
            }
        }, { once: true });
        input.click();
    }

    function clipboardImages(event) {
        var files = [];
        var items = event.clipboardData ? event.clipboardData.items : [];
        Array.from(items || []).forEach(function (item) {
            if (item.kind === 'file' && item.type.indexOf('image/') === 0) {
                var file = item.getAsFile();
                if (file) { files.push(file); }
            }
        });
        return files;
    }

    function normaliseBreakableSpaces(html) {
        return String(html || '')
            .replace(/&nbsp;|&#160;|&#x0*a0;/gi, ' ')
            .replace(/[\u00a0\u202f]/g, ' ');
    }

    function escapeHtmlText(value) {
        var node = document.createElement('div');
        node.textContent = String(value == null ? '' : value);
        return node.innerHTML;
    }

    function escapeHtmlAttribute(value) {
        return escapeHtmlText(value).replace(/"/g, '&quot;');
    }

    function registerFormulaFormat() {
        if (window.PortixFormulaRegistered || !window.katex) { return; }
        var BaseFormula = Quill.import('formats/formula');
        class PortixFormula extends BaseFormula {
            static create(value) {
                var data = (value && typeof value === 'object') ? value : { latex: String(value || ''), display: false };
                var latex = String(data.latex || data.value || '');
                var display = Boolean(data.display);
                var node = super.create(latex);
                node.setAttribute('data-value', latex);
                if (display) { node.setAttribute('data-display', '1'); }
                else { node.removeAttribute('data-display'); }
                if (window.katex) {
                    window.katex.render(latex, node, { throwOnError: false, displayMode: display });
                }
                return node;
            }

            html() {
                var latex = this.domNode.getAttribute('data-value') || '';
                var display = this.domNode.getAttribute('data-display') === '1';
                return '<span class="portix-formula" data-latex="'
                    + escapeHtmlAttribute(latex) + '"'
                    + (display ? ' data-display="1"' : '') + '>'
                    + escapeHtmlText(latex) + '</span>';
            }
        }
        PortixFormula.blotName = BaseFormula.blotName;
        PortixFormula.tagName = BaseFormula.tagName;
        PortixFormula.className = BaseFormula.className;
        Quill.register(PortixFormula, true);
        window.PortixFormulaRegistered = true;
    }

    function tokeniseInlineMarkdown(text, tokens) {
        function token(html) {
            var marker = '\uE000' + tokens.length + '\uE001';
            tokens.push(html);
            return marker;
        }

        text = String(text || '');
        text = text.replace(/`([^`\n]+)`/g, function (_, code) {
            return token('<code>' + escapeHtmlText(code) + '</code>');
        });
        text = text.replace(/\$([^$\n]+)\$/g, function (_, latex) {
            latex = latex.trim();
            if (!latex) { return _; }
            if (!window.katex) { return token('<code>' + escapeHtmlText(latex) + '</code>'); }
            return token('<span class="ql-formula" data-value="'
                + escapeHtmlAttribute(latex) + '"></span>');
        });
        text = text.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (_, label, url) {
            return token('<a href="' + escapeHtmlAttribute(url) + '">'
                + escapeHtmlText(label) + '</a>');
        });
        return text;
    }

    function inlineMarkdown(text) {
        var tokens = [];
        var value = tokeniseInlineMarkdown(text, tokens);
        value = escapeHtmlText(value);
        value = value.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
        value = value.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
        value = value.replace(/~~([^~\n]+)~~/g, '<s>$1</s>');
        value = value.replace(/(^|[\s(])\*([^*\n]+)\*(?=$|[\s).,;:!?])/g, '$1<em>$2</em>');
        value = value.replace(/(^|[\s(])_([^_\n]+)_(?=$|[\s).,;:!?])/g, '$1<em>$2</em>');
        value = value.replace(/\uE000(\d+)\uE001/g, function (_, index) {
            return tokens[Number(index)] || '';
        });
        return value;
    }

    function splitMarkdownTableRow(line) {
        var value = String(line || '').trim();
        if (value.charAt(0) === '|') { value = value.slice(1); }
        if (value.charAt(value.length - 1) === '|') { value = value.slice(0, -1); }
        var cells = [];
        var current = '';
        var escaped = false;
        for (var index = 0; index < value.length; index += 1) {
            var character = value.charAt(index);
            if (escaped) {
                current += character;
                escaped = false;
            } else if (character === '\\') {
                escaped = true;
            } else if (character === '|') {
                cells.push(current.trim());
                current = '';
            } else {
                current += character;
            }
        }
        cells.push(current.trim());
        return cells;
    }

    function isMarkdownTableSeparator(line) {
        var cells = splitMarkdownTableRow(line);
        return cells.length > 0 && cells.every(function (cell) {
            return /^:?-{3,}:?$/.test(cell.replace(/\s+/g, ''));
        });
    }

    function markdownToHtml(markdown) {
        var lines = String(markdown || '').replace(/\r\n?/g, '\n').split('\n');
        var html = [];
        var index = 0;

        function isSpecialStart(position) {
            var line = lines[position] || '';
            var next = lines[position + 1] || '';
            return /^\s*$/.test(line)
                || /^\s*```/.test(line)
                || /^\s*#{1,6}\s+/.test(line)
                || /^\s*>\s?/.test(line)
                || /^\s*[-+*]\s+/.test(line)
                || /^\s*\d+[.)]\s+/.test(line)
                || /^\s*\$\$/.test(line)
                || (line.indexOf('|') !== -1 && isMarkdownTableSeparator(next));
        }

        while (index < lines.length) {
            var line = lines[index];
            if (/^\s*$/.test(line)) { index += 1; continue; }

            if (/^\s*```(?:\w+)?\s*$/.test(line)) {
                index += 1;
                var code = [];
                while (index < lines.length && !/^\s*```\s*$/.test(lines[index])) {
                    code.push(lines[index]);
                    index += 1;
                }
                if (index < lines.length) { index += 1; }
                html.push('<pre><code>' + escapeHtmlText(code.join('\n')) + '</code></pre>');
                continue;
            }

            if (/^\s*\$\$/.test(line)) {
                var formula = line.replace(/^\s*\$\$/, '');
                if (/\$\$\s*$/.test(formula)) {
                    formula = formula.replace(/\$\$\s*$/, '');
                    index += 1;
                } else {
                    index += 1;
                    var formulaLines = [formula];
                    while (index < lines.length && !/\$\$\s*$/.test(lines[index])) {
                        formulaLines.push(lines[index]);
                        index += 1;
                    }
                    if (index < lines.length) {
                        formulaLines.push(lines[index].replace(/\$\$\s*$/, ''));
                        index += 1;
                    }
                    formula = formulaLines.join('\n');
                }
                formula = formula.trim();
                if (formula) {
                    if (window.katex) {
                        html.push('<p class="ql-align-center"><span class="ql-formula" data-value="'
                            + escapeHtmlAttribute(formula) + '"></span></p>');
                    } else {
                        html.push('<p><code>' + escapeHtmlText(formula) + '</code></p>');
                    }
                }
                continue;
            }

            if (line.indexOf('|') !== -1 && index + 1 < lines.length
                && isMarkdownTableSeparator(lines[index + 1])) {
                var header = splitMarkdownTableRow(line);
                index += 2;
                var bodyRows = [];
                while (index < lines.length && lines[index].indexOf('|') !== -1
                    && !/^\s*$/.test(lines[index])) {
                    bodyRows.push(splitMarkdownTableRow(lines[index]));
                    index += 1;
                }
                html.push('<table><thead>');
                html.push('<tr>' + header.map(function (cell) {
                    return '<th scope="col">' + inlineMarkdown(cell) + '</th>';
                }).join('') + '</tr></thead><tbody>');
                bodyRows.forEach(function (row) {
                    html.push('<tr>' + header.map(function (_, cellIndex) {
                        return '<td>' + inlineMarkdown(row[cellIndex] || '') + '</td>';
                    }).join('') + '</tr>');
                });
                html.push('</tbody></table>');
                continue;
            }

            var heading = line.match(/^\s*(#{1,6})\s+(.+)$/);
            if (heading) {
                var level = Math.min(4, Math.max(2, heading[1].length));
                html.push('<h' + level + '>' + inlineMarkdown(heading[2]) + '</h' + level + '>');
                index += 1;
                continue;
            }

            if (/^\s*>\s?/.test(line)) {
                var quote = [];
                while (index < lines.length && /^\s*>\s?/.test(lines[index])) {
                    quote.push(lines[index].replace(/^\s*>\s?/, ''));
                    index += 1;
                }
                html.push('<blockquote>' + inlineMarkdown(quote.join(' ')) + '</blockquote>');
                continue;
            }

            if (/^\s*[-+*]\s+/.test(line)) {
                var unorderedItems = [];
                while (index < lines.length) {
                    var unorderedMatch = lines[index].match(/^\s*[-+*]\s+(.+)$/);
                    if (!unorderedMatch) { break; }
                    unorderedItems.push('<li>' + inlineMarkdown(unorderedMatch[1]) + '</li>');
                    index += 1;
                }
                html.push('<ul>' + unorderedItems.join('') + '</ul>');
                continue;
            }

            if (/^\s*\d+[.)]\s+/.test(line)) {
                var orderedItems = [];
                while (index < lines.length) {
                    var orderedMatch = lines[index].match(/^\s*\d+[.)]\s+(.+)$/);
                    if (!orderedMatch) { break; }
                    orderedItems.push('<li>' + inlineMarkdown(orderedMatch[1]) + '</li>');
                    index += 1;
                }
                html.push('<ol>' + orderedItems.join('') + '</ol>');
                continue;
            }

            var paragraph = [line.trim()];
            index += 1;
            while (index < lines.length && !isSpecialStart(index)) {
                paragraph.push(lines[index].trim());
                index += 1;
            }
            html.push('<p>' + inlineMarkdown(paragraph.join(' ')) + '</p>');
        }
        return html.join('');
    }


    function makeRichButton(label, className) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className || 'portix-rich-tool';
        button.textContent = label;
        button.title = label;
        button.setAttribute('aria-label', label);
        button.addEventListener('mousedown', function (event) { event.preventDefault(); });
        return button;
    }

    function decorateCommand(button, icon, caption) {
        var paths = {
            left: '<rect x="2" y="4" width="8" height="14" fill="currentColor" stroke="none"/><path d="M13 5h9M13 9h9M13 13h9M13 17h9M2 21h20"/>',
            center: '<rect x="7" y="6" width="10" height="12" fill="currentColor" stroke="none"/><path d="M2 2h20M2 22h20"/>',
            right: '<rect x="14" y="4" width="8" height="14" fill="currentColor" stroke="none"/><path d="M2 5h9M2 9h9M2 13h9M2 17h9M2 21h20"/>',
            table: '<rect x="2" y="3" width="20" height="18"/><path d="M2 9h20M2 15h20M9 3v18M16 3v18"/>',
            insertRowAbove: '<rect x="3" y="10" width="18" height="11"/><path d="M3 15h18M9 10v11M15 10v11M12 1v6M9 4h6"/>',
            insertRowBelow: '<rect x="3" y="2" width="18" height="11"/><path d="M3 7h18M9 2v11M15 2v11M12 17v6M9 20h6"/>',
            insertColumnLeft: '<rect x="10" y="3" width="11" height="18"/><path d="M15 3v18M10 9h11M10 15h11M1 12h6M4 9v6"/>',
            insertColumnRight: '<rect x="2" y="3" width="11" height="18"/><path d="M7 3v18M2 9h11M2 15h11M17 12h6M20 9v6"/>',
            deleteRow: '<rect x="3" y="3" width="18" height="18"/><path d="M3 9h18M3 15h18M8 11l8 2M8 13l8-2"/>',
            deleteColumn: '<rect x="3" y="3" width="18" height="18"/><path d="M9 3v18M15 3v18M11 8l2 8M13 8l-2 8"/>',
            deleteTable: '<path d="M5 7h14l-1 14H6L5 7ZM3 4h18M9 4V2h6v2M10 10v8M14 10v8"/>',
            undo: '<path d="M9 4L3 9l6 5M3 9h10a7 7 0 010 14"/>',
            redo: '<path d="M15 4l6 5-6 5M21 9H11a7 7 0 000 14"/>',
            divider: '<path d="M2 12h20M5 5h14M5 19h14"/>',
            up: '<path d="M12 21V3M5 10l7-7 7 7"/>',
            down: '<path d="M12 3v18M5 14l7 7 7-7"/>',
            move: '<path d="M12 2v20M2 12h20M8 6l4-4 4 4M8 18l4 4 4-4M6 8l-4 4 4 4M18 8l4 4-4 4"/>'
        };
        button.classList.add('portix-command');
        button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' + (paths[icon] || paths.table) + '</svg>';
        if (caption) { var text = document.createElement('span'); text.textContent = caption; button.appendChild(text); }
    }

    function makeEditorDialog(title) {
        var dialog = document.createElement('dialog');
        dialog.className = 'portix-editor-dialog';
        dialog.setAttribute('aria-label', title);
        var form = document.createElement('form');
        form.method = 'dialog';
        form.className = 'portix-editor-dialog-form';
        var heading = document.createElement('h3');
        heading.textContent = title;
        var body = document.createElement('div');
        body.className = 'portix-editor-dialog-body';
        var actions = document.createElement('div');
        actions.className = 'portix-editor-dialog-actions';
        form.appendChild(heading);
        form.appendChild(body);
        form.appendChild(actions);
        dialog.appendChild(form);
        document.body.appendChild(dialog);
        return {
            dialog: dialog,
            body: body,
            actions: actions,
            open: function () {
                if (typeof dialog.showModal === 'function') { dialog.showModal(); }
                else { dialog.setAttribute('open', ''); }
            },
            close: function () {
                if (typeof dialog.close === 'function') { dialog.close(); }
                else { dialog.removeAttribute('open'); }
            }
        };
    }

    function labelledField(labelText, input) {
        var label = document.createElement('label');
        label.className = 'portix-editor-dialog-field';
        var caption = document.createElement('span');
        caption.textContent = labelText;
        label.appendChild(caption);
        label.appendChild(input);
        return label;
    }


    function normaliseVideoData(value) {
        if (!value) { return null; }
        if (typeof value === 'object' && value.provider && value.id) {
            var directProvider = String(value.provider || '').toLowerCase();
            var directId = String(value.id || '');
            var directWidth = ['33', '50', '66', '75', '100'].indexOf(String(value.width || '')) !== -1
                ? String(value.width) : '100';
            if (directProvider === 'youtube' && /^[A-Za-z0-9_-]{11}$/.test(directId)) {
                return { provider: directProvider, id: directId, title: String(value.title || '').trim(), width: directWidth };
            }
            if (directProvider === 'vimeo' && /^[0-9]{5,12}$/.test(directId)) {
                return { provider: directProvider, id: directId, title: String(value.title || '').trim(), width: directWidth };
            }
            if (directProvider === 'dailymotion' && /^[A-Za-z0-9]{5,16}$/.test(directId)) {
                return { provider: directProvider, id: directId, title: String(value.title || '').trim(), width: directWidth };
            }
            return null;
        }

        var raw = String(value || '').trim();
        if (!raw) { return null; }

        var parsed;
        try { parsed = new URL(raw); } catch (error) { return null; }
        if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') { return null; }

        var host = parsed.hostname.toLowerCase().replace(/^www\./, '');
        var segments = parsed.pathname.split('/').filter(Boolean);
        var id = '';

        if (host === 'youtu.be') {
            id = segments[0] || '';
            if (/^[A-Za-z0-9_-]{11}$/.test(id)) {
                return { provider: 'youtube', id: id, title: '', width: '100' };
            }
        }

        if (host === 'youtube.com' || host.endsWith('.youtube.com')) {
            if (parsed.searchParams.get('v')) {
                id = parsed.searchParams.get('v') || '';
            } else if ((segments[0] === 'shorts' || segments[0] === 'embed') && segments[1]) {
                id = segments[1];
            }
            if (/^[A-Za-z0-9_-]{11}$/.test(id)) {
                return { provider: 'youtube', id: id, title: '', width: '100' };
            }
        }

        if (host === 'vimeo.com' || host.endsWith('.vimeo.com')) {
            id = segments[0] === 'video' ? (segments[1] || '') : (segments[0] || '');
            if (/^[0-9]{5,12}$/.test(id)) {
                return { provider: 'vimeo', id: id, title: '', width: '100' };
            }
        }

        if (host === 'dailymotion.com' || host.endsWith('.dailymotion.com')) {
            id = segments[0] === 'video' ? (segments[1] || '') : '';
            id = id.split('_')[0];
            if (/^[A-Za-z0-9]{5,16}$/.test(id)) {
                return { provider: 'dailymotion', id: id, title: '', width: '100' };
            }
        }

        if (host === 'dai.ly') {
            id = (segments[0] || '').split('_')[0];
            if (/^[A-Za-z0-9]{5,16}$/.test(id)) {
                return { provider: 'dailymotion', id: id, title: '', width: '100' };
            }
        }

        return null;
    }

    function videoEmbedSource(provider, id) {
        if (provider === 'youtube') {
            return 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id);
        }
        if (provider === 'vimeo') {
            return 'https://player.vimeo.com/video/' + encodeURIComponent(id);
        }
        if (provider === 'dailymotion') {
            return 'https://www.dailymotion.com/embed/video/' + encodeURIComponent(id);
        }
        return '';
    }

    function videoCanonicalUrl(provider, id) {
        if (provider === 'youtube') { return 'https://www.youtube.com/watch?v=' + id; }
        if (provider === 'vimeo') { return 'https://vimeo.com/' + id; }
        if (provider === 'dailymotion') { return 'https://www.dailymotion.com/video/' + id; }
        return '';
    }

    function fillVideoPreview(node, value) {
        var data = normaliseVideoData(value);
        if (!data) { return false; }

        node.setAttribute('data-provider', data.provider);
        node.setAttribute('data-video-id', data.id);
        node.setAttribute('data-width', data.width || '100');
        if (data.title) { node.setAttribute('data-title', data.title.slice(0, 250)); }
        else { node.removeAttribute('data-title'); }
        node.setAttribute('contenteditable', 'false');

        while (node.firstChild) { node.removeChild(node.firstChild); }
        var frame = document.createElement('iframe');
        frame.src = videoEmbedSource(data.provider, data.id);
        frame.title = data.title || message('editor-video-frame-title', 'Embedded video');
        frame.loading = 'lazy';
        frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
        frame.allowFullscreen = true;
        frame.referrerPolicy = 'strict-origin-when-cross-origin';
        frame.tabIndex = -1;

        var overlay = document.createElement('div');
        overlay.className = 'portix-video-editor-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        var hint = document.createElement('span');
        hint.className = 'portix-video-editor-hint';
        hint.textContent = message('editor-video-edit-hint', 'Double-click to edit');
        overlay.appendChild(hint);

        node.append(frame, overlay);
        return true;
    }

    function sourceTableHeaderFlags(html) {
        var template = document.createElement('template');
        template.innerHTML = html || '';
        return Array.from(template.content.querySelectorAll('table')).map(function (table) {
            var firstRow = table.querySelector('tr');
            return Boolean(
                table.querySelector('thead')
                || (firstRow && firstRow.querySelector('th'))
            );
        });
    }

    function normaliseTablesForQuill(fragment) {
        Array.from(fragment.querySelectorAll('table')).forEach(function (table, tableIndex) {
            var caption = table.querySelector('caption');
            var firstCell = table.querySelector('td,th');
            if (caption && firstCell) { firstCell.setAttribute('data-portix-table-caption', caption.textContent); caption.remove(); }
            // Import colgroup geometry into cell formats, without retaining editor markup.
            var cols = Array.from(table.querySelectorAll('col'));
            Array.from(table.rows).forEach(function (row) {
                var column = 0;
                Array.from(row.cells).forEach(function (cell) {
                    var col = cols[column];
                    if (!cell.style.width && col && col.style.width) { cell.style.width = col.style.width; }
                    column += cell.colSpan;
                });
            });
            table.querySelectorAll('colgroup').forEach(function (group) { group.remove(); });
            var body = table.querySelector('tbody');
            if (!body) {
                body = document.createElement('tbody');
                Array.from(table.children).forEach(function (child) {
                    if (child.tagName === 'TR') { body.appendChild(child); }
                });
                table.appendChild(body);
            }

            Array.from(table.querySelectorAll('thead tr,tfoot tr')).forEach(function (row) {
                Array.from(row.cells).forEach(function (cell) { cell.setAttribute('data-portix-table-section', row.parentElement.tagName === 'THEAD' ? 'head' : 'foot'); });
            });
            Array.from(table.querySelectorAll('thead tr')).reverse().forEach(function (row) {
                body.insertBefore(row, body.firstChild);
            });
            Array.from(table.querySelectorAll('tfoot tr')).forEach(function (row) { body.appendChild(row); });
            Array.from(table.querySelectorAll('thead,tfoot')).forEach(function (head) {
                head.remove();
            });

            Array.from(table.querySelectorAll('tr')).forEach(function (row, rowIndex) {
                var rowId = 'portix-row-' + tableIndex + '-' + rowIndex;
                Array.from(row.children).forEach(function (cell) {
                    if (cell.tagName === 'TH') {
                        cell.setAttribute('data-portix-cell-header', ['row', 'col', 'rowgroup', 'colgroup'].indexOf(cell.getAttribute('scope')) !== -1 ? cell.getAttribute('scope') : 'col');
                        var replacement = document.createElement('td');
                        Array.from(cell.attributes).forEach(function (attribute) {
                            if (attribute.name !== 'scope') {
                                replacement.setAttribute(attribute.name, attribute.value);
                            }
                        });
                        replacement.innerHTML = cell.innerHTML;
                        cell.replaceWith(replacement);
                        cell = replacement;
                    }
                    if (cell.tagName === 'TD') {
                        cell.setAttribute('data-row', rowId);
                        cell.setAttribute('data-portix-table-width', table.getAttribute('data-width') || '100');
                    }
                });
            });
        });
    }

    function tableDomNodeFromInfo(info) {
        if (!info || !info[0] || !info[0].domNode) { return null; }
        var node = info[0].domNode;
        if (node.tagName === 'TABLE') { return node; }
        return node.closest ? node.closest('table') : null;
    }

    function setEditorTableHeader(table, enabled) {
        if (!table) { return; }
        var quill = Quill.find(table.closest('.ql-container'));
        var row = table.querySelector('tr');
        if (!quill || !row) { return; }
        quill.getModule('history').cutoff();
        Array.from(row.cells).forEach(function (cell) {
            var blot = Quill.find(cell);
            if (blot) { quill.formatLine(quill.getIndex(blot), 1, 'table-header', enabled ? 'col' : false, 'user'); }
        });
        refreshTableMetadata(quill);
        quill.getModule('history').cutoff();
    }

    function applyInitialTableHeaders(quill, flags) {
        Array.from(quill.root.querySelectorAll('table')).forEach(function (table, index) {
            if (flags[index] && !table.querySelector('[data-portix-cell-header]')) { setEditorTableHeader(table, true); }
        });
    }

    function restoreSemanticTableHeaders(quill, fragment) {
        var editorTables = Array.from(quill.root.querySelectorAll('table'));
        Array.from(fragment.querySelectorAll('table')).forEach(function (table, index) {
            var source = editorTables[index];
            table.removeAttribute('data-portix-header');
            table.removeAttribute('style');
            table.setAttribute('data-width', source ? (source.getAttribute('data-width') || '100') : '100');
            var sourceCells = source ? Array.from(source.querySelectorAll('td,th')) : [];
            Array.from(table.querySelectorAll('td,th')).forEach(function (cell, i) {
                var header = sourceCells[i] && sourceCells[i].getAttribute('data-portix-cell-header');
                cell.removeAttribute('data-row');
                cell.removeAttribute('data-portix-table-width');
                cell.removeAttribute('data-portix-cell-header');
                var original = sourceCells[i];
                ['colspan', 'rowspan'].forEach(function (name) {
                    if (original && original.hasAttribute(name)) { cell.setAttribute(name, original.getAttribute(name)); }
                });
                if (original && original.style.width) { cell.style.width = original.style.width; }
                cell.removeAttribute('data-portix-table-caption');
                cell.removeAttribute('data-portix-table-section');
                if (!header) { return; }
                var th = document.createElement('th');
                Array.from(cell.attributes).forEach(function (attr) { th.setAttribute(attr.name, attr.value); });
                th.setAttribute('scope', header);
                th.innerHTML = cell.innerHTML;
                cell.replaceWith(th);
            });
            var captionCell = source && source.querySelector('[data-portix-table-caption]');
            if (captionCell) { var caption = document.createElement('caption'); caption.textContent = captionCell.getAttribute('data-portix-table-caption'); table.prepend(caption); }
            var sourceRows = source ? Array.from(source.rows) : [];
            var rows = Array.from(table.rows), explicitHead = null, foot = null;
            rows.forEach(function (row, rowIndex) {
                var marker = sourceRows[rowIndex] && sourceRows[rowIndex].querySelector('[data-portix-table-section]');
                if (!marker) { return; }
                if (marker.getAttribute('data-portix-table-section') === 'head') {
                    if (!explicitHead) { explicitHead = document.createElement('thead'); table.insertBefore(explicitHead, table.querySelector('tbody')); }
                    explicitHead.appendChild(row);
                } else {
                    if (!foot) { foot = document.createElement('tfoot'); table.appendChild(foot); }
                    foot.appendChild(row);
                }
            });
            var first = table.querySelector('tr');
            if (!explicitHead && first && first.cells.length && Array.from(first.cells).every(function (cell) { return cell.tagName === 'TH' && cell.getAttribute('scope') === 'col'; })) {
                var head = document.createElement('thead');
                table.insertBefore(head, table.firstChild); head.appendChild(first);
            }
        });
    }

    function clipboardContainsMarkdownTable(text) {
        var lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');

        for (var index = 0; index < lines.length - 1; index += 1) {
            if (lines[index].indexOf('|') !== -1 && isMarkdownTableSeparator(lines[index + 1])) {
                return true;
            }
        }

        var pipeRun = 0;
        var pipeColumns = 0;
        var tabRun = 0;
        var tabColumns = 0;

        for (var row = 0; row < lines.length; row += 1) {
            var line = lines[row];

            if (line.indexOf('\t') !== -1) {
                var tabs = line.split('\t').length;
                if (tabs >= 2 && (tabColumns === 0 || tabs === tabColumns)) {
                    tabColumns = tabs;
                    tabRun += 1;
                    if (tabRun >= 2) { return true; }
                } else {
                    tabColumns = tabs >= 2 ? tabs : 0;
                    tabRun = tabs >= 2 ? 1 : 0;
                }
            } else {
                tabColumns = 0;
                tabRun = 0;
            }

            if (line.indexOf('|') !== -1 && !isMarkdownTableSeparator(line)) {
                var cells = splitMarkdownTableRow(line).length;
                if (cells >= 2 && (pipeColumns === 0 || cells === pipeColumns)) {
                    pipeColumns = cells;
                    pipeRun += 1;
                    if (pipeRun >= 2) { return true; }
                } else {
                    pipeColumns = cells >= 2 ? cells : 0;
                    pipeRun = cells >= 2 ? 1 : 0;
                }
            } else {
                pipeColumns = 0;
                pipeRun = 0;
            }

            var whitespace = whitespaceTableRows(lines, row);
            if (whitespace) { return true; }
        }

        return false;
    }

    function escapeMarkdownTableCell(value) {
        return String(value || '')
            .replace(/\\/g, '\\\\')
            .replace(/\|/g, '\\|')
            .trim();
    }

    function normaliseClipboardPlainTables(text) {
        var lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
        var output = [];
        var index = 0;

        while (index < lines.length) {
            var line = lines[index];

            // Table copied as TSV: Firefox/clipboard commonly uses tabs.
            if (line.indexOf('\t') !== -1) {
                var first = line.split('\t');
                var rows = [first];
                var cursor = index + 1;

                while (cursor < lines.length && lines[cursor].indexOf('\t') !== -1) {
                    var next = lines[cursor].split('\t');
                    if (next.length !== first.length) { break; }
                    rows.push(next);
                    cursor += 1;
                }

                if (first.length >= 2 && rows.length >= 2) {
                    output.push('| ' + first.map(escapeMarkdownTableCell).join(' | ') + ' |');
                    output.push('| ' + first.map(function () { return '---'; }).join(' | ') + ' |');
                    rows.slice(1).forEach(function (row) {
                        output.push('| ' + row.map(escapeMarkdownTableCell).join(' | ') + ' |');
                    });
                    index = cursor;
                    continue;
                }
            }

            // Table copied as aligned plain text (columns separated by 2+ spaces).
            var whitespace = whitespaceTableRows(lines, index);
            if (whitespace) {
                var whitespaceRows = whitespace.rows;
                var whitespaceHeader = whitespaceRows[0];
                output.push('| ' + whitespaceHeader.map(escapeMarkdownTableCell).join(' | ') + ' |');
                output.push('| ' + whitespaceHeader.map(function () { return '---'; }).join(' | ') + ' |');
                whitespaceRows.slice(1).forEach(function (row) {
                    output.push('| ' + row.map(escapeMarkdownTableCell).join(' | ') + ' |');
                });
                index = whitespace.next;
                continue;
            }

            // Markdown-like table without the separator row.
            if (line.indexOf('|') !== -1 && !isMarkdownTableSeparator(line)) {
                var firstPipe = splitMarkdownTableRow(line);
                var pipeRows = [firstPipe];
                var pipeCursor = index + 1;

                while (pipeCursor < lines.length
                    && lines[pipeCursor].indexOf('|') !== -1
                    && !isMarkdownTableSeparator(lines[pipeCursor])) {
                    var pipeRow = splitMarkdownTableRow(lines[pipeCursor]);
                    if (pipeRow.length !== firstPipe.length) { break; }
                    pipeRows.push(pipeRow);
                    pipeCursor += 1;
                }

                if (firstPipe.length >= 2 && pipeRows.length >= 2) {
                    output.push('| ' + firstPipe.map(escapeMarkdownTableCell).join(' | ') + ' |');
                    output.push('| ' + firstPipe.map(function () { return '---'; }).join(' | ') + ' |');
                    pipeRows.slice(1).forEach(function (row) {
                        output.push('| ' + row.map(escapeMarkdownTableCell).join(' | ') + ' |');
                    });
                    index = pipeCursor;
                    continue;
                }
            }

            output.push(line);
            index += 1;
        }

        return output.join('\n');
    }

    function htmlContainsTableStructure(html) {
        return /<(?:table|thead|tbody|tr|td|th)\b/i.test(String(html || ''))
            || /\brole=["'](?:table|row|cell|columnheader|rowheader)["']/i.test(String(html || ''));
    }

    function convertRoleTableToSemanticHtml(html) {
        var value = String(html || '');
        if (/<table[\s>]/i.test(value)
            || !/\brole=["'](?:table|row|cell|columnheader|rowheader)["']/i.test(value)) {
            return value;
        }

        var template = document.createElement('template');
        template.innerHTML = value;
        var roleTable = template.content.querySelector('[role="table"], [role="grid"]');
        if (!roleTable) { return value; }

        var rows = Array.from(roleTable.querySelectorAll('[role="row"]'));
        if (!rows.length) { return value; }

        var table = document.createElement('table');
        var body = document.createElement('tbody');
        rows.forEach(function (row) {
            var tr = document.createElement('tr');
            var cells = Array.from(row.querySelectorAll(
                ':scope > [role="cell"], :scope > [role="gridcell"], '
                + ':scope > [role="columnheader"], :scope > [role="rowheader"]'
            ));
            if (!cells.length) {
                cells = Array.from(row.children);
            }
            cells.forEach(function (cell) {
                var isHeader = cell.getAttribute('role') === 'columnheader'
                    || cell.getAttribute('role') === 'rowheader';
                var td = document.createElement(isHeader ? 'th' : 'td');
                td.innerHTML = cell.innerHTML;
                tr.appendChild(td);
            });
            if (tr.children.length) { body.appendChild(tr); }
        });
        if (!body.children.length) { return value; }
        table.appendChild(body);
        roleTable.replaceWith(table);
        return template.innerHTML;
    }

    function whitespaceTableRows(lines, start) {
        var rows = [];
        var cursor = start;
        var columns = 0;

        while (cursor < lines.length) {
            var line = String(lines[cursor] || '').trim();
            if (!line || line.indexOf('|') !== -1 || line.indexOf('\t') !== -1) { break; }
            var cells = line.split(/\s{2,}/).map(function (cell) { return cell.trim(); });
            if (cells.length < 2 || cells.some(function (cell) { return cell === ''; })) { break; }
            if (columns === 0) { columns = cells.length; }
            if (cells.length !== columns) { break; }
            rows.push(cells);
            cursor += 1;
        }

        return rows.length >= 2 ? { rows: rows, next: cursor } : null;
    }

    function wrapLooseTableRows(html) {
        var value = String(html || '');
        if (/<table[\s>]/i.test(value)) { return value; }
        if (!/<(?:tr|td|th)\b/i.test(value)) { return value; }

        var template = document.createElement('template');
        template.innerHTML = value;
        var rows = Array.from(template.content.querySelectorAll('tr'));
        if (rows.length === 0) { return value; }

        var table = document.createElement('table');
        var body = document.createElement('tbody');
        rows.forEach(function (row) {
            body.appendChild(row);
        });
        table.appendChild(body);
        template.content.appendChild(table);
        return template.innerHTML;
    }

    function cleanClipboardHtml(html) {
        var template = document.createElement('template');
        template.innerHTML = String(html || '');

        Array.from(template.content.querySelectorAll(
            'script,style,button,svg,canvas,template,noscript,[contenteditable="false"]'
        )).forEach(function (node) {
            node.remove();
        });

        Array.from(template.content.querySelectorAll('*')).forEach(function (node) {
            Array.from(node.attributes).forEach(function (attribute) {
                var name = attribute.name.toLowerCase();
                if (name === 'class' || name === 'style' || name.indexOf('data-') === 0
                    || name === 'id' || name === 'role' || name.indexOf('aria-') === 0) {
                    node.removeAttribute(attribute.name);
                }
            });
        });

        return template.innerHTML;
    }

    function prepareClipboardTableHtml(html) {
        var cleaned = cleanClipboardHtml(wrapLooseTableRows(convertRoleTableToSemanticHtml(html)));
        var flags = sourceTableHeaderFlags(cleaned);
        var template = document.createElement('template');
        template.innerHTML = cleaned;
        normaliseTablesForQuill(template.content);
        return {
            html: template.innerHTML,
            headerFlags: flags
        };
    }

    function applyPastedTableHeaders(quill, startIndex, flags) {
        window.setTimeout(function () {
            var tables = Array.from(quill.root.querySelectorAll('table')).slice(startIndex);
            tables.forEach(function (table, index) {
                if (flags[index] && !table.querySelector('[data-portix-cell-header]')) { setEditorTableHeader(table, true); }
            });
        }, 0);
    }

    function pasteStructuredTables(event, quill) {
        if (!event.clipboardData) { return false; }

        var html = event.clipboardData.getData('text/html') || '';
        var plain = event.clipboardData.getData('text/plain') || '';
        var htmlHasTable = htmlContainsTableStructure(html);
        var markdownHasTable = clipboardContainsMarkdownTable(plain);

        if (!htmlHasTable && !markdownHasTable) { return false; }

        var prepared;
        if (htmlHasTable) {
            prepared = prepareClipboardTableHtml(html);
            if (!prepared.html || !/<table[\s>]/i.test(prepared.html)) {
                htmlHasTable = false;
            }
        }

        if (!htmlHasTable) {
            var normalisedPlain = normaliseClipboardPlainTables(plain);
            var markdownHtml = markdownToHtml(normalisedPlain);
            prepared = prepareClipboardTableHtml(markdownHtml);
        }

        if (!prepared.html || !/<table[\s>]/i.test(prepared.html)) { return false; }

        event.preventDefault();
        event.stopPropagation();

        var range = quill.getSelection(true) || {
            index: Math.max(0, quill.getLength() - 1),
            length: 0
        };
        if (range.length) {
            quill.deleteText(range.index, range.length, 'user');
        }

        var tableCountBefore = quill.root.querySelectorAll('table').length;
        quill.clipboard.dangerouslyPasteHTML(range.index, prepared.html, 'user');
        applyPastedTableHeaders(quill, tableCountBefore, prepared.headerFlags);
        return true;
    }

    // Pasted paragraphs must stay inside their cell instead of creating Quill blocks.
    function pasteTextInTableCell(event, quill) {
        if (!event.clipboardData) { return false; }
        var html = event.clipboardData.getData('text/html') || '';
        var plain = event.clipboardData.getData('text/plain') || '';
        if ((!html && !plain) || htmlContainsTableStructure(html) || clipboardContainsMarkdownTable(plain)) { return false; }
        var range = quill.getSelection() || quill.selection.savedRange;
        var tableModule = quill.getModule('table');
        var info = range && tableModule ? tableModule.getTable(range) : null;
        var cell = info && info[2];
        if (!cell || range.index + range.length > quill.getIndex(cell) + cell.length() - 1) { return false; }
        var pasted = quill.clipboard.convert({ html: html ? cleanClipboardHtml(html) : '', text: plain });
        if (pasted.ops.some(function (op) { return op.insert && (op.insert['portix-video'] || op.insert['portix-divider']); })) { return false; }
        var Delta = Quill.import('delta');
        var inline = new Delta();
        pasted.ops.forEach(function (op) {
            var attributes = Object.assign({}, op.attributes || {});
            ['header', 'list', 'indent', 'direction', 'align', 'blockquote', 'code-block', 'table', 'table-header', 'table-width', 'cell-vertical', 'cell-width', 'cell-colspan', 'cell-rowspan', 'table-caption', 'table-section'].forEach(function (name) { delete attributes[name]; });
            if (typeof op.insert !== 'string') { inline.insert(op.insert, attributes); return; }
            var lines = op.insert.split('\n');
            lines.forEach(function (line, index) {
                if (index) { inline.insert({ 'portix-break': true }); }
                if (line) { inline.insert(line, attributes); }
            });
        });
        event.preventDefault(); event.stopPropagation();
        quill.getModule('history').cutoff();
        quill.updateContents(new Delta().retain(range.index).delete(range.length).concat(inline), 'user');
        quill.setSelection(range.index + inline.length(), 0, 'silent');
        quill.getModule('history').cutoff();
        return true;
    }

    function moveTableCell(quill, range, backwards) {
        var info = quill.getModule('table').getTable(range);
        if (!info[2]) { return true; }
        var table = tableDomNodeFromInfo(info), cells = Array.from(table.querySelectorAll('td,th'));
        var index = cells.indexOf(info[2].domNode), next = cells[index + (backwards ? -1 : 1)];
        if (!next && !backwards) {
            quill.getModule('history').cutoff();
            editTableGrid(quill, table, info[2].domNode, 'insertRowBelow');
            quill.getModule('history').cutoff();
            next = table.rows[table.rows.length - 1].cells[0];
        }
        if (next) { var blot = Quill.find(next); if (blot) { quill.setSelection(quill.getIndex(blot), 0, 'user'); } }
        return false;
    }

    function surfaceForTable(quill) { return quill.root.closest('.portix-editor-surface'); }

    function tableGrid(table) {
        var rows = Array.from(table.rows).filter(function (row) { return row.closest('table') === table; });
        var grid = [], entries = [], count = 0;
        rows.forEach(function (row, r) {
            grid[r] = grid[r] || []; var c = 0;
            Array.from(row.cells).forEach(function (cell) {
                while (grid[r][c]) { c++; }
                var entry = { cell: cell, row: r, col: c, height: cell.rowSpan || 1, width: cell.colSpan || 1 };
                entries.push(entry);
                for (var y = r; y < r + entry.height; y++) {
                    grid[y] = grid[y] || [];
                    for (var x = c; x < c + entry.width; x++) { grid[y][x] = entry; }
                }
                c += entry.width; count = Math.max(count, c);
            });
        });
        return { rows: rows, grid: grid, entries: entries, columns: count };
    }

    function editTableGrid(quill, table, cell, command) {
        var layout = tableGrid(table), current = layout.entries.find(function (entry) { return entry.cell === cell; });
        if (!current) { return; }
        var rowIndex = current.row, colIndex = current.col;
        var hasWidths = layout.entries.some(function (entry) { return !!entry.cell.style.width; });
        var columnWidths = tableColumnEdges(table, layout).slice(1).map(function (edge, i, ends) {
            return edge - (i ? ends[i - 1] : 0);
        });
        function span(entry, name, value) { if (value > 1) { entry.cell.setAttribute(name, value); } else { entry.cell.removeAttribute(name); } }
        function emptyCell(row, before, header) {
            var td = document.createElement('td'); td.setAttribute('data-row', row.cells[0] ? row.cells[0].getAttribute('data-row') : 'row-' + Math.random().toString(36).slice(2));
            if (header) { td.setAttribute('data-portix-cell-header', 'col'); }
            var first = table.querySelector('td,th');
            td.setAttribute('data-portix-table-width', first ? first.getAttribute('data-portix-table-width') || '100' : '100');
            td.appendChild(document.createElement('br')); row.insertBefore(td, before || null); return td;
        }
        var caption = table.querySelector('[data-portix-table-caption]');
        var captionText = caption && caption.getAttribute('data-portix-table-caption');
        if (command.indexOf('insertRow') === 0) {
            var boundary = rowIndex + (command === 'insertRowBelow' ? 1 : 0);
            var tr = document.createElement('tr');
            var body = layout.rows[0].parentElement; body.insertBefore(tr, layout.rows[boundary] || null);
            var crossed = new Set();
            for (var c = 0; c < layout.columns; c++) {
                var above = boundary > 0 && layout.grid[boundary - 1][c];
                if (above && above.row + above.height > boundary) {
                    if (!crossed.has(above)) { span(above, 'rowspan', above.height + 1); crossed.add(above); }
                } else { emptyCell(tr, null, false); }
            }
        } else if (command === 'deleteRow') {
            if (layout.rows.length === 1) { table.remove(); }
            else {
                layout.entries.forEach(function (entry) {
                    if (entry.row < rowIndex && entry.row + entry.height > rowIndex) { span(entry, 'rowspan', entry.height - 1); }
                    if (entry.row === rowIndex && entry.height > 1) {
                        var next = layout.rows[rowIndex + 1];
                        var following = layout.entries.find(function (candidate) { return candidate.row === rowIndex + 1 && candidate.col > entry.col; });
                        span(entry, 'rowspan', entry.height - 1);
                        entry.cell.setAttribute('data-row', next.cells[0] ? next.cells[0].getAttribute('data-row') : 'row-' + Math.random().toString(36).slice(2));
                        next.insertBefore(entry.cell, following ? following.cell : null);
                    }
                });
                layout.rows[rowIndex].remove();
            }
        } else if (command.indexOf('insertColumn') === 0) {
            var boundary = colIndex + (command === 'insertColumnRight' ? current.width : 0);
            if (hasWidths) {
                var neighbor = Math.min(boundary, columnWidths.length - 1);
                var half = columnWidths[neighbor] / 2; columnWidths[neighbor] = half; columnWidths.splice(boundary, 0, half);
            }
            var crossed = new Set();
            layout.rows.forEach(function (row, r) {
                var left = boundary > 0 && layout.grid[r][boundary - 1];
                if (left && left.col + left.width > boundary) {
                    if (!crossed.has(left)) { span(left, 'colspan', left.width + 1); crossed.add(left); }
                } else {
                    var following = layout.entries.find(function (entry) { return entry.row === r && entry.col >= boundary; });
                    emptyCell(row, following ? following.cell : null, r === 0 && table.getAttribute('data-portix-header') === '1');
                }
            });
        } else if (command === 'deleteColumn') {
            columnWidths.splice(colIndex, 1);
            if (layout.columns === 1) { table.remove(); }
            else {
                var removed = new Set();
                layout.grid.forEach(function (row) {
                    var entry = row[colIndex]; if (!entry || removed.has(entry)) { return; }
                    removed.add(entry);
                    if (entry.width > 1) { span(entry, 'colspan', entry.width - 1); } else { entry.cell.remove(); }
                });
            }
        }
        if (table.isConnected) {
            if (hasWidths) {
                var updated = tableGrid(table), total = columnWidths.reduce(function (a, b) { return a + b; }, 0);
                updated.entries.forEach(function (entry) {
                    var width = columnWidths.slice(entry.col, entry.col + entry.width).reduce(function (a, b) { return a + b; }, 0);
                    entry.cell.style.width = (100 * width / total).toFixed(3) + '%';
                });
            }
            if (captionText !== null && captionText !== undefined && !table.querySelector('[data-portix-table-caption]')) {
                var first = table.querySelector('td,th'); if (first) { first.setAttribute('data-portix-table-caption', captionText); }
            }
        }
        quill.update('user');
        var remaining = table.isConnected && table.querySelector('td,th');
        if (remaining) { var blot = Quill.find(remaining); if (blot) { quill.setSelection(quill.getIndex(blot), 0, 'silent'); } }
    }

    function tableColumnEdges(table, grid) {
        var bounds = table.getBoundingClientRect(), edges = [0];
        for (var i = 1; i < grid.columns; i++) {
            var entry = grid.entries.find(function (item) { return item.col + item.width === i; });
            edges[i] = entry ? entry.cell.getBoundingClientRect().right - bounds.left : bounds.width * i / grid.columns;
        }
        edges.push(bounds.width); return edges;
    }

    function installColumnResize(quill, wrapper, currentTable) {
        var layer = document.createElement('div'); layer.className = 'portix-column-resizers'; wrapper.appendChild(layer);
        var drag = null, pending = false, lastTable = null, lastGeometry = '';
        function measurements(table) {
            var grid = tableGrid(table), bounds = table.getBoundingClientRect();
            return { table: table, grid: grid, bounds: bounds, edges: tableColumnEdges(table, grid) };
        }
        function commit(data, boundary, delta) {
            var widths = data.edges.slice(1).map(function (edge, i) { return edge - data.edges[i]; });
            var minimum = Math.min(40, data.bounds.width / (data.grid.columns * 2));
            delta = Math.max(minimum - widths[boundary - 1], Math.min(widths[boundary] - minimum, delta));
            if (Math.abs(delta) < 0.1) { return; }
            widths[boundary - 1] += delta; widths[boundary] -= delta;
            quill.getModule('history').cutoff();
            data.grid.entries.forEach(function (entry) {
                var width = widths.slice(entry.col, entry.col + entry.width).reduce(function (a, b) { return a + b; }, 0);
                var blot = Quill.find(entry.cell);
                if (blot) { quill.formatLine(quill.getIndex(blot), 1, 'cell-width', (width / data.bounds.width * 100).toFixed(3) + '%', 'user'); }
            });
            quill.getModule('history').cutoff(); schedule();
        }
        function render() {
            pending = false; if (drag) { return; }
            var table = currentTable();
            if (!table) { layer.replaceChildren(); lastTable = null; lastGeometry = ''; return; }
            var data = measurements(table), host = wrapper.getBoundingClientRect(), editor = quill.root.getBoundingClientRect();
            var geometry = [data.edges.join(','), data.bounds.top - host.top, data.bounds.bottom - host.top, data.bounds.left - host.left, editor.top - host.top, editor.bottom - host.top].join('|');
            if (table === lastTable && geometry === lastGeometry) { return; }
            var focusedBoundary = layer.contains(document.activeElement) ? document.activeElement.getAttribute('data-column-boundary') : null;
            lastTable = table; lastGeometry = geometry; layer.replaceChildren();
            if (data.bounds.bottom < editor.top || data.bounds.top > editor.bottom) { return; }
            data.edges.slice(1, -1).forEach(function (edge, index) {
                var boundary = index + 1, handle = document.createElement('button'); handle.type = 'button'; handle.className = 'portix-column-resizer';
                var label = message('editor-table-resize-column', 'Redimensionner les colonnes') + ' ' + boundary + '/' + (boundary + 1);
                handle.title = label; handle.setAttribute('aria-label', label); handle.setAttribute('data-column-boundary', boundary);
                handle.style.left = (data.bounds.left - host.left + edge - 5) + 'px';
                handle.style.top = (Math.max(data.bounds.top, editor.top) - host.top) + 'px';
                handle.style.height = Math.max(0, Math.min(data.bounds.bottom, editor.bottom) - Math.max(data.bounds.top, editor.top)) + 'px';
                handle.addEventListener('pointerdown', function (event) {
                    if (event.button !== 0) { return; } event.preventDefault();
                    drag = { data: measurements(table), boundary: boundary, x: event.clientX, handle: handle, pointer: event.pointerId, delta: 0 };
                    handle.setPointerCapture(event.pointerId); handle.classList.add('is-dragging');
                });
                handle.addEventListener('pointermove', function (event) { if (drag && drag.handle === handle) { drag.delta = event.clientX - drag.x; handle.style.transform = 'translateX(' + drag.delta + 'px)'; } });
                function finish(event) {
                    if (!drag || drag.handle !== handle) { return; } var saved = drag; drag = null; lastGeometry = '';
                    if (event.type === 'pointerup') { commit(saved.data, saved.boundary, saved.delta); } schedule();
                }
                handle.addEventListener('pointerup', finish); handle.addEventListener('pointercancel', finish);
                handle.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && drag) { drag = null; lastGeometry = ''; schedule(); }
                    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); commit(measurements(table), boundary, event.key === 'ArrowRight' ? 10 : -10); handle.focus({preventScroll: true}); }
                });
                layer.appendChild(handle);
                if (focusedBoundary === String(boundary)) { handle.focus({preventScroll: true}); }
            });
        }
        function schedule() { if (!pending) { pending = true; window.requestAnimationFrame(render); } }
        quill.on('selection-change', schedule); quill.on('text-change', schedule);
        quill.root.addEventListener('click', schedule); quill.root.addEventListener('scroll', schedule);
        window.addEventListener('resize', schedule); window.addEventListener('scroll', schedule, true);
    }

    function createTableTools(quill, richTools) {
        var tableModule = null;
        try { tableModule = quill.getModule('table'); } catch (error) { tableModule = null; }

        var tableButton = makeRichButton(message('editor-table', 'Tableau'));
        decorateCommand(tableButton, 'table');
        tableButton.setAttribute('data-command', 'table');
        richTools.appendChild(tableButton);

        var tools = document.createElement('div');
        tools.className = 'portix-table-tools';
        var heading = document.createElement('strong'); heading.textContent = message('editor-table-tools', 'Tableau'); tools.appendChild(heading);
        tools.hidden = true;
        tools.setAttribute('role', 'toolbar');
        tools.setAttribute('aria-label', message('editor-table-tools', 'Tableau'));
        var editorWrapper = quill.root.closest('.portix-editor');
        // A stable ribbon outside the editable surface never covers the cells.
        editorWrapper.insertBefore(tools, surfaceForTable(quill));

        function updateTools() {
            if (!tableModule || typeof tableModule.getTable !== 'function') {
                tools.hidden = true;
                tableButton.disabled = true;
                return;
            }
            var range = quill.getSelection() || contextRange;
            if (range) { contextRange = { index: range.index, length: range.length }; }
            var info = range ? tableModule.getTable(range) : null;
            tools.hidden = !(info && info[0]) || !!quill.root.querySelector('.portix-image-object.is-portix-selected');
            positionTableTools();
            var table = tableDomNodeFromInfo(info);
            headerButton.setAttribute(
                'aria-pressed',
                table && table.getAttribute('data-portix-header') === '1' ? 'true' : 'false'
            );
        }

        var contextRange = null;
        tools.addEventListener('mousedown', function (event) { if (event.target.tagName === 'BUTTON') { event.preventDefault(); } });
        function restoreContext() {
            if (contextRange) { quill.setSelection(contextRange.index, contextRange.length, 'silent'); }
        }
        function currentTable() {
            if (!tableModule || typeof tableModule.getTable !== 'function') { return null; }
            var range = quill.getSelection() || contextRange;
            if (range) { contextRange = { index: range.index, length: range.length }; }
            var info = range ? tableModule.getTable(range) : null;
            return tableDomNodeFromInfo(info);
        }

        function addAction(label, methodName, danger) {
            var button = makeRichButton(label, 'portix-table-tool' + (danger ? ' is-danger' : ''));
            decorateCommand(button, methodName, label);
            button.setAttribute('data-table-command', methodName);
            button.addEventListener('click', function () {
                if (!tableModule || typeof tableModule[methodName] !== 'function') { return; }
                restoreContext();
                quill.focus();
                quill.getModule('history').cutoff();
                var table = currentTable();
                var info = tableModule.getTable(quill.getSelection());
                if (table && info[2] && methodName !== 'deleteTable') { editTableGrid(quill, table, info[2].domNode, methodName); }
                else { tableModule[methodName](); }
                contextRange = quill.getSelection();
                quill.getModule('history').cutoff();
                updateTools();
            });
            tools.appendChild(button);
        }

        var headerButton = makeRichButton(
            message('editor-table-header-row', 'En-tête'),
            'portix-table-tool'
        );
        headerButton.setAttribute('aria-pressed', 'false');
        headerButton.addEventListener('click', function () {
            restoreContext();
            var table = currentTable();
            if (!table) { return; }
            var enabled = table.getAttribute('data-portix-header') !== '1';
            setEditorTableHeader(table, enabled);
            headerButton.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        });
        decorateCommand(headerButton, 'table', headerButton.textContent);
        tools.appendChild(headerButton);
        addTableProperties(quill, tools, currentTable, restoreContext);

        addAction(message('editor-table-row-above', 'Ligne au-dessus'), 'insertRowAbove');
        addAction(message('editor-table-row-below', 'Ligne en dessous'), 'insertRowBelow');
        addAction(message('editor-table-column-left', 'Colonne à gauche'), 'insertColumnLeft');
        addAction(message('editor-table-column-right', 'Colonne à droite'), 'insertColumnRight');
        addAction(message('editor-table-delete-row', 'Supprimer la ligne'), 'deleteRow', true);
        addAction(message('editor-table-delete-column', 'Supprimer la colonne'), 'deleteColumn', true);
        addAction(message('editor-table-delete', 'Supprimer le tableau'), 'deleteTable', true);

        var positionTableTools = function () {};
        installColumnResize(quill, editorWrapper, currentTable);
        var insertion = document.createElement('details');
        var deletion = document.createElement('details');
        var properties = document.createElement('details');
        [[insertion, 'Insérer'], [deletion, 'Supprimer'], [properties, 'Propriétés']].forEach(function (item) {
            item[0].className = 'portix-context-menu';
            var summary = document.createElement('summary'); summary.textContent = item[1]; summary.title = item[1] + ' — tableau';
            var content = document.createElement('div'); content.className = 'portix-context-menu-content';
            item[0].append(summary, content);
        });
        Array.from(tools.children).forEach(function (control) {
            if (control === heading) { control.remove(); return; }
            var method = control.getAttribute('data-table-command');
            var menu = method ? (method.indexOf('insert') === 0 ? insertion : deletion) : properties;
            menu.lastElementChild.appendChild(control);
        });
        tools.append(insertion, deletion, properties);
        tools.addEventListener('toggle', function (event) {
            if (event.target.open) { [insertion, deletion, properties].forEach(function (menu) { if (menu !== event.target) { menu.open = false; } }); }
            positionTableTools();
        }, true);
        tools.addEventListener('click', function (event) {
            if (event.target.closest('[data-table-command]')) { insertion.open = false; deletion.open = false; positionTableTools(); }
        });
        document.addEventListener('click', function (event) {
            if (!tools.contains(event.target) && !quill.root.contains(event.target)) { tools.hidden = true; }
        });

        var dialog = makeEditorDialog(message('editor-table-insert', 'Insert a table'));
        var rows = document.createElement('input');
        rows.type = 'number'; rows.min = '1'; rows.max = '20'; rows.value = '3';
        var columns = document.createElement('input');
        columns.type = 'number'; columns.min = '1'; columns.max = '12'; columns.value = '3';
        var headerRow = document.createElement('input');
        headerRow.type = 'checkbox';
        headerRow.checked = true;
        dialog.body.appendChild(labelledField(message('editor-table-rows', 'Lignes'), rows));
        dialog.body.appendChild(labelledField(message('editor-table-columns', 'Colonnes'), columns));
        var headerLabel = document.createElement('label');
        headerLabel.className = 'portix-editor-dialog-check';
        headerLabel.appendChild(headerRow);
        headerLabel.appendChild(document.createTextNode(
            message('editor-table-header-first-row', 'Première ligne = en-têtes')
        ));
        dialog.body.appendChild(headerLabel);
        var cancel = makeRichButton(message('editor-cancel', 'Cancel'), 'button secondary');
        var insert = makeRichButton(message('editor-insert', 'Insert'), 'button');
        dialog.actions.appendChild(cancel);
        dialog.actions.appendChild(insert);
        cancel.addEventListener('click', dialog.close);
        insert.addEventListener('click', function () {
            if (!tableModule || typeof tableModule.insertTable !== 'function') { return; }
            var rowCount = Math.max(1, Math.min(20, parseInt(rows.value, 10) || 1));
            var columnCount = Math.max(1, Math.min(12, parseInt(columns.value, 10) || 1));
            dialog.close();
            quill.focus();
            restoredEditorRange(quill, dialog);
            tableModule.insertTable(rowCount, columnCount);
            window.setTimeout(function () {
                var table = currentTable();
                setEditorTableHeader(table, headerRow.checked);
                updateTools();
            }, 0);
        });
        tableButton.addEventListener('click', function () { rememberEditorRange(quill, dialog); dialog.open(); rows.focus(); });
        if (!tableModule) { tableButton.disabled = true; }
        quill.root.addEventListener('click', function (event) {
            var cell = event.target.closest('td,th');
            if (cell && !event.target.closest('.portix-image-object')) {
                var selection = window.getSelection();
                if (!selection || !cell.contains(selection.anchorNode)) {
                    var blot = Quill.find(cell);
                    if (blot) { quill.setSelection(quill.getIndex(blot), 0, 'silent'); }
                }
            }
            window.requestAnimationFrame(updateTools);
        });
        quill.on('selection-change', updateTools);
        quill.on('text-change', updateTools);
        refreshTableMetadata(quill);
    }

    function createFormulaTools(quill, wrapper, surface, richTools) {
        var button = wrapper.querySelector('button.ql-formula');
        if (!window.katex) {
            button.disabled = true;
            button.title = message('editor-formula-unavailable', 'KaTeX is unavailable.');
            return;
        }

        var dialog = makeEditorDialog(message('editor-formula-title', 'LaTeX formula'));
        var input = document.createElement('textarea');
        input.className = 'portix-formula-input';
        input.rows = 4;
        input.placeholder = 'E = mc^2';
        dialog.body.appendChild(labelledField(message('editor-formula-latex', 'LaTeX'), input));

        var display = document.createElement('input');
        display.type = 'checkbox';
        var displayLabel = document.createElement('label');
        displayLabel.className = 'portix-editor-dialog-check';
        displayLabel.appendChild(display);
        displayLabel.appendChild(document.createTextNode(' ' + message('editor-formula-display', 'Display on its own line')));
        dialog.body.appendChild(displayLabel);

        var previewLabel = document.createElement('strong');
        previewLabel.textContent = message('editor-formula-preview', 'Preview');
        var preview = document.createElement('div');
        preview.className = 'portix-formula-preview';
        dialog.body.appendChild(previewLabel);
        dialog.body.appendChild(preview);

        var cancel = makeRichButton(message('editor-cancel', 'Cancel'), 'button secondary');
        var save = makeRichButton(message('editor-insert', 'Insert'), 'button');
        dialog.actions.appendChild(cancel);
        dialog.actions.appendChild(save);
        var editingIndex = null;

        function renderPreview() {
            preview.textContent = '';
            var latex = input.value.trim();
            if (!latex) { return; }
            window.katex.render(latex, preview, { throwOnError: false, displayMode: display.checked });
        }

        function openFormula(latex, isDisplay, index) {
            editingIndex = Number.isInteger(index) ? index : null;
            input.value = latex || '';
            display.checked = Boolean(isDisplay);
            save.textContent = editingIndex === null
                ? message('editor-insert', 'Insert')
                : message('editor-update', 'Update');
            save.title = save.textContent; save.setAttribute('aria-label', save.textContent);
            renderPreview();
            rememberEditorRange(quill, dialog);
            dialog.open();
            input.focus();
        }

        input.addEventListener('input', renderPreview);
        display.addEventListener('change', renderPreview);
        cancel.addEventListener('click', dialog.close);
        button.addEventListener('click', function () { openFormula('', false, null); });

        save.addEventListener('click', function () {
            var latex = input.value.trim();
            if (!latex) { return; }
            var range = restoredEditorRange(quill, dialog);
            var index = editingIndex === null ? range.index : editingIndex;
            if (editingIndex !== null) { quill.deleteText(editingIndex, 1, 'user'); }
            quill.insertEmbed(index, 'formula', { latex: latex, display: display.checked }, 'user');
            quill.setSelection(index + 1, 0, 'silent');
            editingIndex = null;
            dialog.close();
        });

        surface.addEventListener('dblclick', function (event) {
            var node = event.target.closest ? event.target.closest('.ql-formula') : null;
            if (!node || !wrapper.contains(node)) { return; }
            var blot = Quill.find(node);
            if (!blot) { return; }
            openFormula(
                node.getAttribute('data-value') || '',
                node.getAttribute('data-display') === '1',
                quill.getIndex(blot)
            );
        });
    }


    function createVideoTools(quill, wrapper, surface, richTools) {
        var button = wrapper.querySelector('button.ql-video');

        var dialog = makeEditorDialog(message('editor-video-title', 'Insert a video'));
        var help = document.createElement('p');
        help.className = 'portix-editor-dialog-help';
        help.textContent = message(
            'editor-video-help',
            'Paste a YouTube, Vimeo or Dailymotion URL.'
        );

        var url = document.createElement('input');
        url.type = 'url';
        url.placeholder = 'https://www.youtube.com/watch?v=...';
        url.autocomplete = 'off';

        var title = document.createElement('input');
        title.type = 'text';
        title.maxLength = 250;

        var width = document.createElement('select');
        [
            ['33', '33 %'],
            ['50', '50 %'],
            ['66', '66 %'],
            ['75', '75 %'],
            ['100', '100 %']
        ].forEach(function (definition) {
            addOption(width, definition[0], definition[1]);
        });
        width.value = '100';

        var error = document.createElement('p');
        error.className = 'portix-editor-dialog-error';
        error.hidden = true;

        dialog.body.appendChild(help);
        dialog.body.appendChild(labelledField(message('editor-video-url', 'Video URL'), url));
        dialog.body.appendChild(labelledField(message('editor-video-label', 'Accessible title (optional)'), title));
        dialog.body.appendChild(labelledField(message('editor-video-size', 'Size'), width));
        dialog.body.appendChild(error);

        var cancel = makeRichButton(message('editor-cancel', 'Cancel'), 'button secondary');
        var save = makeRichButton(message('editor-insert', 'Insert'), 'button');
        dialog.actions.appendChild(cancel);
        dialog.actions.appendChild(save);

        var editingIndex = null;

        function openVideo(data, index) {
            editingIndex = Number.isInteger(index) ? index : null;
            url.value = data ? videoCanonicalUrl(data.provider, data.id) : '';
            title.value = data ? (data.title || '') : '';
            width.value = data ? (data.width || '100') : '100';
            error.hidden = true;
            error.textContent = '';
            save.textContent = editingIndex === null
                ? message('editor-insert', 'Insert')
                : message('editor-update', 'Update');
            save.title = save.textContent; save.setAttribute('aria-label', save.textContent);
            rememberEditorRange(quill, dialog);
            dialog.open();
            url.focus();
        }

        cancel.addEventListener('click', dialog.close);
        button.addEventListener('click', function () { openVideo(null, null); });

        save.addEventListener('click', function () {
            var data = normaliseVideoData(url.value);
            if (!data) {
                error.textContent = message(
                    'editor-video-invalid',
                    'Unsupported or invalid video URL.'
                );
                error.hidden = false;
                return;
            }
            data.title = title.value.trim().slice(0, 250);
            data.width = ['33', '50', '66', '75', '100'].indexOf(width.value) !== -1 ? width.value : '100';
            var range = restoredEditorRange(quill, dialog);
            var index = editingIndex === null ? range.index : editingIndex;
            if (range.length && editingIndex === null) {
                quill.deleteText(range.index, range.length, 'user');
            }
            if (editingIndex !== null) {
                quill.deleteText(editingIndex, 1, 'user');
            }
            quill.insertEmbed(index, 'portix-video', data, 'user');
            if (editingIndex === null) { quill.insertText(index + 1, '\n', 'user'); }
            quill.setSelection(index + 1, 0, 'silent');
            editingIndex = null;
            dialog.close();
        });

        surface.addEventListener('dblclick', function (event) {
            var node = event.target.closest ? event.target.closest('.portix-video') : null;
            if (!node || !wrapper.contains(node)) { return; }
            var blot = Quill.find(node);
            if (!blot) { return; }
            var data = normaliseVideoData({
                provider: node.getAttribute('data-provider') || '',
                id: node.getAttribute('data-video-id') || '',
                title: node.getAttribute('data-title') || '',
                width: node.getAttribute('data-width') || '100'
            });
            if (!data) { return; }
            openVideo(data, quill.getIndex(blot));
        });
    }

    function createMarkdownTools(quill, richTools) {
        var button = makeRichButton(message('editor-markdown', 'Markdown'));
        richTools.appendChild(button);
        var dialog = makeEditorDialog(message('editor-markdown-title', 'Paste Markdown'));

        var help = document.createElement('p');
        help.className = 'portix-editor-dialog-help';
        help.textContent = message(
            'editor-markdown-help',
            'Paste Markdown here. Headings, lists, links, tables and formulas are converted to editable visual content.'
        );
        var input = document.createElement('textarea');
        input.className = 'portix-markdown-input';
        input.rows = 14;
        input.placeholder = '## Title\n\n| Column A | Column B |\n|---|---|\n| Value | Value |';
        dialog.body.appendChild(help);
        dialog.body.appendChild(input);

        var cancel = makeRichButton(message('editor-cancel', 'Cancel'), 'button secondary');
        var insert = makeRichButton(message('editor-markdown-insert', 'Convert and insert'), 'button');
        dialog.actions.appendChild(cancel);
        dialog.actions.appendChild(insert);
        cancel.addEventListener('click', dialog.close);
        button.addEventListener('click', function () {
            rememberEditorRange(quill, dialog);
            dialog.open();
            input.focus();
        });
        insert.addEventListener('click', function () {
            if (!input.value.trim()) { return; }
            var html = markdownToHtml(input.value);
            var range = restoredEditorRange(quill, dialog);
            if (range.length) { quill.deleteText(range.index, range.length, 'user'); }
            var tableCountBefore = quill.root.querySelectorAll('table').length;
            quill.clipboard.dangerouslyPasteHTML(range.index, html, 'user');
            window.setTimeout(function () {
                Array.from(quill.root.querySelectorAll('table'))
                    .slice(tableCountBefore)
                    .forEach(function (table) {
                        setEditorTableHeader(table, true);
                    });
            }, 0);
            input.value = '';
            dialog.close();
            quill.focus();
        });
    }

    function createRichContentTools(quill, wrapper, surface) {
        var toolbar = wrapper.querySelector('.ql-toolbar');
        if (!toolbar || wrapper.querySelector('.portix-rich-tools')) { return; }
        var tools = document.createElement('div');
        tools.className = 'ql-formats portix-rich-tools';
        tools.setAttribute('role', 'toolbar');
        tools.setAttribute('aria-label', message('editor-rich-tools', 'Structured content'));
        toolbar.appendChild(tools);
        var undo = makeRichButton(message('editor-undo', 'Annuler'), 'portix-rich-tool');
        var redo = makeRichButton(message('editor-redo', 'Rétablir'), 'portix-rich-tool');
        undo.addEventListener('click', function () { quill.getModule('history').undo(); });
        redo.addEventListener('click', function () { quill.getModule('history').redo(); });
        var divider = makeRichButton(message('editor-divider', 'Ligne horizontale'));
        divider.addEventListener('click', function () {
            var range = quill.getSelection(true) || quill.selection.savedRange;
            var index = range ? range.index : quill.getLength() - 1;
            quill.insertEmbed(index, 'portix-divider', true, 'user');
            quill.setSelection(index + 1, 0, 'silent');
        });
        decorateCommand(undo, 'undo'); decorateCommand(redo, 'redo');
        decorateCommand(divider, 'divider');
        undo.setAttribute('data-command', 'undo'); redo.setAttribute('data-command', 'redo'); divider.setAttribute('data-command', 'divider');
        tools.append(undo, redo, divider);
        window.PortixCallouts.tools(quill, wrapper, tools);
        createTableTools(quill, tools);
        if (mediaEnabled()) {
            var fileButton = makeRichButton('Insérer un fichier');
            fileButton.setAttribute('data-command', 'file');
            fileButton.innerHTML = '<svg viewBox="0 0 18 18" aria-hidden="true"><path class="ql-stroke" fill="none" d="M6 9l5-5a3 3 0 014 4l-7 7a4 4 0 01-6-6l7-7M5 10l6-6"/></svg>';
            fileButton.addEventListener('click', function () { selectMedia(quill, wrapper, 'document'); });
            tools.appendChild(fileButton);
        }

        tools.querySelectorAll('svg').forEach(function (svg) {
            svg.removeAttribute('stroke'); svg.removeAttribute('stroke-width');
            svg.querySelectorAll('path,rect').forEach(function (shape) { shape.classList.add('ql-stroke'); });
        });
        createFormulaTools(quill, wrapper, surface, tools);
        createVideoTools(quill, wrapper, surface, tools);
        var imports = document.createElement('div'); imports.className = 'portix-editor-imports';
        wrapper.insertBefore(imports, surface.nextSibling);
        createMarkdownTools(quill, imports);
    }

    function editableHtml(html) {
        var template = document.createElement('template');
        template.innerHTML = normaliseBreakableSpaces(html);
        window.PortixCallouts.editable(template.content);
        normaliseTablesForQuill(template.content);
        template.content.querySelectorAll('script,iframe,object,embed,svg,style').forEach(function (node) { node.remove(); });
        template.content.querySelectorAll('img').forEach(function (image) { if (!safeImageSource(image.getAttribute('src'))) { image.remove(); } });
        Array.from(template.content.querySelectorAll('div.portix-video[data-provider][data-video-id]')).forEach(function (video) {
            var data = normaliseVideoData({
                provider: video.getAttribute('data-provider') || '',
                id: video.getAttribute('data-video-id') || '',
                title: video.getAttribute('data-title') || '',
                width: video.getAttribute('data-width') || '100'
            });
            if (!data || !fillVideoPreview(video, data)) {
                video.remove();
            }
        });
        Array.from(template.content.querySelectorAll('span.portix-formula[data-latex]')).forEach(function (formula) {
            if (!window.katex) { return; }
            var latex = formula.getAttribute('data-latex') || formula.textContent || '';
            var display = formula.getAttribute('data-display') === '1';
            formula.className = 'ql-formula';
            formula.setAttribute('data-value', latex);
            formula.removeAttribute('data-latex');
            formula.textContent = '';
            window.katex.render(latex, formula, { throwOnError: false, displayMode: display });
            if (display) { formula.setAttribute('data-display', '1'); }
        });
        Array.from(template.content.querySelectorAll('figure.portix-image-figure')).forEach(function (figure) {
            var image = figure.querySelector('img');
            if (!image) { figure.remove(); return; }
            var caption = figure.querySelector('figcaption');
            image.setAttribute('data-caption', caption ? caption.textContent.trim() : '');
            image.setAttribute('data-wrap', figure.classList.contains('portix-image-wrap-none') ? 'none' : 'square');
            if (figure.hasAttribute('data-width')) { image.setAttribute('data-width', figure.getAttribute('data-width')); }
            imageSizes.forEach(function (size) {
                if (figure.classList.contains('portix-image-size-' + size)) {
                    image.classList.add('portix-image-size-' + size);
                }
            });
            imageAlignments.forEach(function (alignment) {
                if (figure.classList.contains('portix-image-align-' + alignment)) {
                    image.classList.add('portix-image-align-' + alignment);
                }
            });
            var paragraph = document.createElement(figure.closest('td,th') ? 'span' : 'p');
            // Keep the encadre's block format when <figure> becomes an editable image.
            ['advice', 'warning', 'equipment', 'note'].forEach(function (type) {
                if (figure.classList.contains('portix-callout-' + type)) {
                    paragraph.classList.add('portix-callout-' + type);
                }
            });
            paragraph.appendChild(image);
            figure.replaceWith(paragraph);
        });
        return template.innerHTML;
    }

    function registerImageFormats() {
        if (window.PortixImageFormatsRegistered) { return; }
        var BaseImage = Quill.import('formats/image');
        var Parchment = Quill.import('parchment');
        var imageSizeFormat = new Parchment.ClassAttributor(
            'image-size',
            'portix-image-size',
            { scope: Parchment.Scope.INLINE, whitelist: imageSizes }
        );
        var imageAlignmentFormat = new Parchment.ClassAttributor(
            'image-align',
            'portix-image-align',
            { scope: Parchment.Scope.INLINE, whitelist: imageAlignments }
        );
        var imageCaptionFormat = new Parchment.Attributor(
            'image-caption',
            'data-caption',
            { scope: Parchment.Scope.INLINE }
        );

        Quill.register(imageSizeFormat, true);
        Quill.register(imageAlignmentFormat, true);
        Quill.register(imageCaptionFormat, true);

        class PortixImage extends BaseImage {
            static formats(node) {
                var formats = BaseImage.formats(node);
                var size = imageSizeFormat.value(node);
                var alignment = imageAlignmentFormat.value(node);
                var caption = imageCaptionFormat.value(node);
                if (size) { formats['image-size'] = size; }
                if (alignment) { formats['image-align'] = alignment; }
                if (caption) { formats['image-caption'] = caption; }
                return formats;
            }

            format(name, value) {
                if (name === 'image-size') {
                    value ? imageSizeFormat.add(this.domNode, String(value))
                        : imageSizeFormat.remove(this.domNode);
                    return;
                }
                if (name === 'image-align') {
                    value ? imageAlignmentFormat.add(this.domNode, String(value))
                        : imageAlignmentFormat.remove(this.domNode);
                    return;
                }
                if (name === 'image-caption') {
                    value ? imageCaptionFormat.add(this.domNode, String(value))
                        : imageCaptionFormat.remove(this.domNode);
                    return;
                }
                super.format(name, value);
            }
        }

        PortixImage.blotName = 'image';
        PortixImage.tagName = 'IMG';
        Quill.register(PortixImage, true);
        window.PortixImageFormatsRegistered = true;
    }


    function registerVideoFormat() {
        if (window.PortixVideoRegistered) { return; }
        var BlockEmbed = Quill.import('blots/block/embed');

        class PortixVideo extends BlockEmbed {
            static create(value) {
                var data = normaliseVideoData(value);
                var node = super.create();
                if (data) { fillVideoPreview(node, data); }
                return node;
            }

            static value(node) {
                return {
                    provider: node.getAttribute('data-provider') || '',
                    id: node.getAttribute('data-video-id') || '',
                    title: node.getAttribute('data-title') || '',
                    width: node.getAttribute('data-width') || '100'
                };
            }

            html() {
                var data = normaliseVideoData(PortixVideo.value(this.domNode));
                if (!data) { return ''; }
                return '<div class="portix-video" data-provider="'
                    + escapeHtmlAttribute(data.provider)
                    + '" data-video-id="' + escapeHtmlAttribute(data.id) + '"'
                    + ' data-width="' + escapeHtmlAttribute(data.width || '100') + '"'
                    + (data.title ? ' data-title="' + escapeHtmlAttribute(data.title.slice(0, 250)) + '"' : '')
                    + '></div>';
            }
        }

        PortixVideo.blotName = 'portix-video';
        PortixVideo.tagName = 'DIV';
        PortixVideo.className = 'portix-video';
        Quill.register(PortixVideo, true);
        window.PortixVideoRegistered = true;
    }

    function registerSoftBreak() {
        if (window.PortixSoftBreakRegistered) { return; }
        var Embed = Quill.import('blots/embed');
        class PortixSoftBreak extends Embed {}
        PortixSoftBreak.blotName = 'portix-break';
        PortixSoftBreak.tagName = 'BR';
        PortixSoftBreak.className = 'portix-soft-break';
        Quill.register(PortixSoftBreak, true);
        window.PortixSoftBreakRegistered = true;
    }

    function addOption(select, value, label) {
        var option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.appendChild(option);
    }

    function imageFormat(image, prefix, allowed) {
        var found = '';
        allowed.some(function (value) {
            if (image.classList.contains(prefix + value)) {
                found = value;
                return true;
            }
            return false;
        });
        return found;
    }

    function imageBlot(image) {
        return image ? Quill.find(image.closest('.portix-image-object') || image) : null;
    }

    function syncCaptionPreview(image) {
        var object = image ? image.closest('.portix-image-object') : null;
        if (!object) { return; }
        var caption = object.querySelector('.portix-image-caption');
        if (caption) {
            caption.textContent = image.getAttribute('data-caption') || '';
            caption.hidden = !caption.textContent;
        }
    }

    function attachContextPalette(quill, wrapper, panel, target) {
        panel.classList.add('portix-context-palette');
        var scheduled = false;
        function position() {
            if (panel.hidden) { return; }
            var node = target(); if (!node || !node.isConnected) { panel.hidden = true; return; }
            var bounds = node.getBoundingClientRect();
            var editor = quill.root.getBoundingClientRect();
            var host = wrapper.getBoundingClientRect();
            if (bounds.bottom < editor.top || bounds.top > editor.bottom) { panel.style.visibility = 'hidden'; return; }
            panel.style.visibility = '';
            var width = panel.offsetWidth, height = panel.offsetHeight;
            var left = Math.max(4, Math.min(bounds.left - host.left, host.width - width - 4));
            var top = bounds.top - host.top - height - 8;
            var toolbar = wrapper.querySelector('.ql-toolbar').getBoundingClientRect();
            var minimum = Math.max(editor.top, toolbar.bottom) - host.top;
            if (top < minimum) { top = Math.min(bounds.bottom - host.top + 8, editor.bottom - host.top - height); }
            panel.style.left = left + 'px'; panel.style.top = Math.max(minimum, top) + 'px';
        }
        function schedule() {
            if (!scheduled) { scheduled = true; window.requestAnimationFrame(function () { scheduled = false; position(); }); }
        }
        quill.root.addEventListener('scroll', schedule);
        window.addEventListener('scroll', schedule, true);
        window.addEventListener('resize', schedule);
        quill.on('text-change', schedule);
        return position;
    }

    function createImageTools(quill, wrapper, surface) {
        var panel = document.createElement('div');
        var heading = document.createElement('strong');
        var sizeLabel = document.createElement('label');
        var sizeSelect = document.createElement('select');
        var alignmentLabel = document.createElement('label');
        var alignmentSelect = document.createElement('select');
        var altLabel = document.createElement('label');
        var altInput = document.createElement('input');
        var widthLabel = document.createElement('label');
        var widthInput = document.createElement('input');
        widthInput.type = 'number'; widthInput.min = '10'; widthInput.max = '100';
        widthLabel.append(document.createTextNode(message('editor-image-width', 'Largeur (%)')), widthInput);
        var captionLabel = document.createElement('label');
        var captionInput = document.createElement('input');
        var moveGroup = document.createElement('div');
        var moveUp = document.createElement('button');
        var moveDown = document.createElement('button');
        var activeImage = null;

        panel.className = 'portix-image-tools';
        panel.hidden = true;
        heading.textContent = message('editor-image-options', 'Image');
        sizeLabel.append(document.createTextNode(message('editor-image-size', 'Size')));
        addOption(sizeSelect, '', message('editor-image-size-auto', 'Original size'));
        imageSizes.forEach(function (size) { addOption(sizeSelect, size, size + ' %'); });
        sizeLabel.appendChild(sizeSelect);
        alignmentLabel.append(document.createTextNode(message('editor-image-alignment', 'Text wrapping')));
        addOption(alignmentSelect, '', message('editor-image-align-none', 'No wrapping'));
        addOption(alignmentSelect, 'left', message('editor-image-align-left', 'Image on the left'));
        addOption(alignmentSelect, 'center', message('editor-image-align-center', 'Centred image'));
        addOption(alignmentSelect, 'right', message('editor-image-align-right', 'Image on the right'));
        alignmentLabel.appendChild(alignmentSelect);
        var wrapLabel = document.createElement('label');
        var wrapSelect = document.createElement('select');
        addOption(wrapSelect, 'square', message('editor-image-wrap-square', 'Carré — texte à côté'));
        addOption(wrapSelect, 'none', message('editor-image-wrap-none', 'Aucun — sur sa ligne'));
        wrapLabel.append(document.createTextNode(message('editor-image-wrapping', 'Habillage')), wrapSelect);
        altLabel.append(document.createTextNode(message('editor-image-alt', 'Texte alternatif')));
        altInput.type = 'text';
        altInput.maxLength = 250;
        altLabel.appendChild(altInput);
        captionLabel.append(document.createTextNode(message('editor-image-caption', 'Légende')));
        captionInput.type = 'text';
        captionInput.maxLength = 500;
        captionLabel.appendChild(captionInput);
        moveGroup.className = 'portix-image-move';
        moveGroup.setAttribute('role', 'group');
        moveGroup.setAttribute('aria-label', message('editor-image-move', 'Move image'));
        moveUp.type = 'button';
        moveDown.type = 'button';
        moveUp.textContent = '↑ ' + message('editor-image-move-up', 'Move up');
        moveDown.textContent = '↓ ' + message('editor-image-move-down', 'Move down');
        moveUp.title = moveUp.textContent;
        moveDown.title = moveDown.textContent;
        var drag = document.createElement('button');
        drag.type = 'button';
        drag.title = message('editor-image-drag', 'Déplacer par glisser-déposer');
        drag.setAttribute('aria-label', drag.title);
        drag.className = 'portix-image-drag';
        drag.draggable = true;
        decorateCommand(moveUp, 'up');
        decorateCommand(moveDown, 'down');
        decorateCommand(drag, 'move');
        moveGroup.append(moveUp, moveDown, drag);
        var alignGroup = document.createElement('div');
        alignGroup.className = 'portix-image-align-buttons';
        alignGroup.setAttribute('role', 'group');
        alignGroup.setAttribute('aria-label', 'Position de l’image');
        var alignButtons = [];
        [['left', 'À gauche — texte à côté'], ['center', 'Centrer — sur sa ligne'], ['right', 'À droite — texte à côté']].forEach(function (item) {
            var button = makeRichButton(item[1]);
            button.setAttribute('data-image-align', item[0]);
            decorateCommand(button, item[0]);
            button.addEventListener('click', function () {
                alignmentSelect.value = item[0];
                alignmentSelect.dispatchEvent(new Event('change'));
            });
            alignButtons.push(button); alignGroup.appendChild(button);
        });
        var properties = makeRichButton('Propriétés de l’image');
        properties.setAttribute('data-image-properties', 'true');
        properties.textContent = 'Propriétés';
        var propertyDialog = makeEditorDialog('Propriétés de l’image');
        propertyDialog.body.append(captionLabel, altLabel, widthLabel, sizeLabel);
        var closeProperties = makeRichButton('Fermer', 'button');
        propertyDialog.actions.appendChild(closeProperties);
        closeProperties.addEventListener('click', propertyDialog.close);
        properties.addEventListener('click', function () { propertyDialog.open(); captionInput.focus(); });
        panel.setAttribute('role', 'toolbar'); panel.setAttribute('aria-label', 'Format de l’image');
        var wrapMenu = document.createElement('details');
        wrapMenu.className = 'portix-context-menu';
        var wrapSummary = document.createElement('summary');
        wrapSummary.textContent = message('editor-image-wrapping', 'Habillage');
        wrapSummary.title = wrapSummary.textContent;
        var wrapContent = document.createElement('div');
        wrapContent.className = 'portix-context-menu-content';
        wrapContent.appendChild(wrapLabel);
        wrapMenu.append(wrapSummary, wrapContent);
        panel.append(alignGroup, wrapMenu, moveGroup, properties);
        wrapper.appendChild(panel);
        var positionImageTools = attachContextPalette(quill, wrapper, panel, function () { return activeImage && activeImage.closest('.portix-image-object'); });

        function hide() {
            if (activeImage) {
                activeImage.classList.remove('is-portix-selected');
                var object = activeImage.closest('.portix-image-object');
                if (object) { object.classList.remove('is-portix-selected'); }
            }
            activeImage = null;
            panel.hidden = true;
        }

        function show(image) {
            if (activeImage && activeImage !== image) {
                activeImage.classList.remove('is-portix-selected');
                var previousObject = activeImage.closest('.portix-image-object');
                if (previousObject) { previousObject.classList.remove('is-portix-selected'); }
            }
            activeImage = image;
            activeImage.classList.add('is-portix-selected');
            var object = activeImage.closest('.portix-image-object');
            if (object) { object.classList.add('is-portix-selected'); }
            syncCaptionPreview(activeImage);
            sizeSelect.value = imageFormat(image, 'portix-image-size-', imageSizes);
            alignmentSelect.value = imageFormat(image, 'portix-image-align-', imageAlignments);
            wrapSelect.value = image.getAttribute('data-wrap') || 'square';
            wrapSelect.disabled = alignmentSelect.value === 'center';

            alignButtons.forEach(function (button) { button.setAttribute('aria-pressed', button.getAttribute('data-image-align') === alignmentSelect.value ? 'true' : 'false'); });
            altInput.value = image.getAttribute('alt') || '';
            captionInput.value = image.getAttribute('data-caption') || '';
            widthInput.value = image.getAttribute('data-width') || imageFormat(image, 'portix-image-size-', imageSizes) || '100';
            var blot = imageBlot(image);
            var index = blot ? quill.getIndex(blot) : 0;
            var line = blot ? quill.getLine(index)[0] : null;
            var lineStart = line ? quill.getIndex(line) : 0;
            moveUp.disabled = image.closest('td,th') || !line || lineStart <= 0;
            moveDown.disabled = image.closest('td,th') || !line || lineStart + line.length() >= quill.getLength();
            panel.hidden = false;
            var tablePalette = wrapper.querySelector('.portix-table-tools'); if (tablePalette) { tablePalette.hidden = true; }
            positionImageTools();
        }

        function applyFormat(name, value, refresh) {
            if (!activeImage) { return; }
            var blot = imageBlot(activeImage);
            if (!blot) { hide(); return; }
            quill.formatText(quill.getIndex(blot), 1, name, value || false, 'user');
            if (refresh !== false) { show(activeImage); }
        }

        function moveImage(direction) {
            if (!activeImage) { return; }
            var blot = imageBlot(activeImage);
            if (!blot) { hide(); return; }
            if (activeImage.closest('td,th')) { return; }
            var imageIndex = quill.getIndex(blot);
            var lineAndOffset = quill.getLine(imageIndex);
            var line = lineAndOffset[0];
            if (!line) { return; }
            var lineStart = quill.getIndex(line);
            var lineLength = line.length();
            var otherLine;
            var otherStart;
            var otherLength;
            var replacement;
            var destination;
            var Delta = Quill.import('delta');
            var current = quill.getContents(lineStart, lineLength);

            if (direction < 0) {
                if (lineStart <= 0) { return; }
                otherLine = quill.getLine(lineStart - 1)[0];
                if (!otherLine) { return; }
                otherStart = quill.getIndex(otherLine);
                otherLength = otherLine.length();
                replacement = current.concat(quill.getContents(otherStart, otherLength));
                destination = otherStart + lineAndOffset[1];
            } else {
                otherStart = lineStart + lineLength;
                if (otherStart >= quill.getLength()) { return; }
                otherLine = quill.getLine(otherStart)[0];
                if (!otherLine) { return; }
                otherLength = otherLine.length();
                replacement = quill.getContents(otherStart, otherLength).concat(current);
                destination = lineStart + otherLength + lineAndOffset[1];
                otherStart = lineStart;
            }

            quill.updateContents(
                new Delta().retain(otherStart).delete(lineLength + otherLength).concat(replacement),
                'user'
            );
            quill.setSelection(destination, 0, 'silent');
            var moved = quill.getLeaf(destination)[0];
            if (moved && moved.domNode.querySelector && moved.domNode.querySelector('img')) {
                show(moved.domNode.querySelector('img'));
            } else {
                hide();
            }
        }

        installImageDrag(quill, surface, drag, function () { return activeImage; }, hide);
        quill.on('text-change', function () { if (activeImage && activeImage.isConnected) { show(activeImage); } else { hide(); } });

        sizeSelect.addEventListener('change', function () {
            applyFormat('image-width', false);
            applyFormat('image-size', sizeSelect.value);
        });
        alignmentSelect.addEventListener('change', function () {
            var alignment = alignmentSelect.value;
            if ((alignment === 'left' || alignment === 'right')
                && !activeImage.getAttribute('data-width') && sizeSelect.value === '') {
                sizeSelect.value = '50';
                applyFormat('image-size', '50');
            }
            var blot = imageBlot(activeImage);
            if (blot && !activeImage.closest('td,th')) {
                var index = quill.getIndex(blot);
                var line = quill.getLine(index);
                var imageCallout = imageCalloutAttributes(quill, index);
                if (line[1] > 0) { quill.insertText(index, '\n', imageCallout, 'user'); index += 1; }
                if (quill.getText(index + 1, 1) !== '\n') { quill.insertText(index + 1, '\n', imageCallout, 'user'); }
                if (imageCallout.callout) { quill.formatLine(index, 1, 'callout', imageCallout.callout, 'user'); }
            }
            applyFormat('image-wrap', 'square', false);
            applyFormat('image-align', alignment);
        });
        widthInput.addEventListener('change', function () { applyFormat('image-width', String(Math.max(10, Math.min(100, parseInt(widthInput.value, 10) || 100)))); });
        wrapSelect.addEventListener('change', function () {
            applyFormat('image-wrap', wrapSelect.value);
            wrapMenu.open = false;
        });
        altInput.addEventListener('input', function () {
            applyFormat('alt', altInput.value, false);
        });
        captionInput.addEventListener('input', function () {
            applyFormat('image-caption', captionInput.value, false);
            if (activeImage) { syncCaptionPreview(activeImage); }
        });
        moveUp.addEventListener('click', function () { moveImage(-1); });
        moveDown.addEventListener('click', function () { moveImage(1); });
        surface.addEventListener('click', function (event) {
            if (event.target.closest && event.target.closest('.portix-image-resize')) { return; }
            var object = event.target.closest && event.target.closest('.portix-image-object');
            var image = object && object.querySelector('img');
            if (image) {
                var blot = imageBlot(image);
                // Quill must select the embed itself; a visual border alone is not a selection.
                if (blot) { quill.setSelection(quill.getIndex(blot), 1, 'user'); }
                show(image);
            } else {
                hide();
            }
        });
        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target) && !propertyDialog.dialog.contains(event.target)) { hide(); }
        });
    }

    function isExternalWebLink(value) {
        try {
            var url = new URL(String(value || '').trim(), document.baseURI);
            var site = new URL(document.baseURI);
            return (url.protocol === 'http:' || url.protocol === 'https:')
                && url.hostname !== site.hostname
                && url.hostname !== window.location.hostname;
        } catch (error) {
            return false;
        }
    }

    function applyLinkTarget(link, value) {
        link.setAttribute('data-portix-link-target', value === 'blank' ? 'blank' : 'self');
        if (value === 'blank') {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        } else {
            link.removeAttribute('target');
            link.removeAttribute('rel');
        }
    }

    function registerLinkTargetFormat() {
        if (window.PortixLinkTargetRegistered) { return; }
        var Parchment = Quill.import('parchment');
        var BaseLink = Quill.import('formats/link');
        class LinkTarget extends Parchment.Attributor {
            add(node, value) {
                if (node.tagName !== 'A') { return false; }
                if (!super.add(node, value)) { return false; }
                if (node.tagName === 'A') { applyLinkTarget(node, value); }
                return true;
            }
        }
        Quill.register(new LinkTarget('link-target', 'data-portix-link-target', {
            scope: Parchment.Scope.INLINE,
            whitelist: ['blank', 'self']
        }), true);
        class PortixLink extends BaseLink {
            static create(value) {
                var link = super.create(value);
                applyLinkTarget(link, isExternalWebLink(link.getAttribute('href')) ? 'blank' : 'self');
                return link;
            }

            format(name, value) {
                if (name === 'link-target') {
                    // Keep the choice in Quill's formats, including splits and undo/redo.
                    super.format(name, value === 'blank' ? 'blank' : 'self');
                    applyLinkTarget(this.domNode, value);
                } else {
                    super.format(name, value);
                }
            }
        }
        Quill.register(PortixLink, true);
        window.PortixLinkTargetRegistered = true;
    }

    function linkTargetDelta(node, delta) {
        var target = node.getAttribute('data-portix-link-target');
        if (target !== 'blank' && target !== 'self') {
            var sourceTarget = node.getAttribute('target');
            target = sourceTarget === '_self' ? 'self'
                : sourceTarget === '_blank' || isExternalWebLink(node.getAttribute('href')) ? 'blank' : 'self';
        }
        delta.ops.forEach(function (operation) {
            if (operation.attributes && operation.attributes.link) { operation.attributes['link-target'] = target; }
        });
        return delta;
    }

    function preserveInitialLinkTargets(sourceHtml) {
        var template = document.createElement('template');
        template.innerHTML = String(sourceHtml || '');
        template.content.querySelectorAll('a').forEach(function (link) {
            applyLinkTarget(link, link.getAttribute('target') === '_blank' ? 'blank' : 'self');
        });
        return template.innerHTML;
    }

    function linksAtSelection(quill, range) {
        if (!range) { return []; }
        return Array.from(quill.root.querySelectorAll('a')).filter(function (link) {
            var blot = Quill.find(link);
            if (!blot) { return false; }
            var start = quill.getIndex(blot);
            var end = start + blot.length();
            return range.length === 0
                ? range.index >= start && range.index < end
                : range.index < end && range.index + range.length > start;
        });
    }

    function enhanceLinkTooltip(quill) {
        var tooltip = quill.theme && quill.theme.tooltip;
        if (!tooltip || !tooltip.root || tooltip.root.querySelector('.portix-link-target-option')) {
            return;
        }

        var option = document.createElement('label');
        var checkbox = document.createElement('input');
        var optionText = document.createElement('span');
        var pendingRange = null;
        var targetChosenByUser = false;
        checkbox.type = 'checkbox';
        option.className = 'portix-link-target-option';
        optionText.textContent = message('editor-link-new-window', 'Open link in a new tab');
        option.append(checkbox, optionText);
        tooltip.root.appendChild(option);

        function updateDefaultTarget() {
            if (!targetChosenByUser && tooltip.root.getAttribute('data-mode') === 'link') {
                checkbox.checked = isExternalWebLink(tooltip.textbox.value);
            }
        }
        tooltip.textbox.addEventListener('input', updateDefaultTarget);
        checkbox.addEventListener('change', function () { targetChosenByUser = true; });

        var originalEdit = tooltip.edit.bind(tooltip);
        tooltip.edit = function (mode, preview) {
            var range = tooltip.linkRange || quill.getSelection() || quill.selection.savedRange;
            pendingRange = range ? { index: range.index, length: range.length } : null;
            var selectedLinks = linksAtSelection(quill, pendingRange);
            targetChosenByUser = selectedLinks.length > 0;
            checkbox.checked = selectedLinks.length > 0 && selectedLinks.every(function (link) {
                return link.getAttribute('target') === '_blank';
            });
            originalEdit(mode, preview);
            updateDefaultTarget();
        };

        var originalSave = tooltip.save.bind(tooltip);
        tooltip.save = function () {
            var isLink = tooltip.root.getAttribute('data-mode') === 'link';
            var range = tooltip.linkRange || pendingRange || quill.selection.savedRange;
            var savedRange = range ? { index: range.index, length: range.length } : null;
            updateDefaultTarget();
            var openNewWindow = checkbox.checked;
            originalSave();
            if (isLink) {
                linksAtSelection(quill, savedRange).forEach(function (link) {
                    var blot = Quill.find(link);
                    quill.formatText(quill.getIndex(blot), blot.length(),
                        'link-target', openNewWindow ? 'blank' : 'self', 'user');
                });
            }
            pendingRange = null;
        };
    }

    function semanticHtml(quill) {
        var html = quill.getSemanticHTML();
        var template = document.createElement('template');
        template.innerHTML = html;
        template.content.querySelectorAll('[data-portix-link-target]').forEach(function (node) {
            node.removeAttribute('data-portix-link-target');
        });
        restoreSemanticTableHeaders(quill, template.content);
        Array.from(template.content.querySelectorAll('div.portix-video')).forEach(function (video) {
            var data = normaliseVideoData({
                provider: video.getAttribute('data-provider') || '',
                id: video.getAttribute('data-video-id') || '',
                title: video.getAttribute('data-title') || '',
                width: video.getAttribute('data-width') || '100'
            });
            if (!data) { video.remove(); return; }
            Array.from(video.attributes).forEach(function (attribute) {
                video.removeAttribute(attribute.name);
            });
            while (video.firstChild) { video.removeChild(video.firstChild); }
            video.className = 'portix-video';
            video.setAttribute('data-provider', data.provider);
            video.setAttribute('data-video-id', data.id);
            video.setAttribute('data-width', data.width || '100');
            if (data.title) { video.setAttribute('data-title', data.title.slice(0, 250)); }
        });
        var editorLinks = Array.from(quill.root.querySelectorAll('a'));
        Array.from(template.content.querySelectorAll('a')).forEach(function (link, index) {
            if (editorLinks[index] && editorLinks[index].getAttribute('target') === '_blank') {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            } else {
                link.removeAttribute('target');
                link.removeAttribute('rel');
            }
        });
        // Remove empty headings Quill can leave next to an image, even when
        // the inline image temporarily generated an empty paragraph between them.
        function emptyParagraph(node) {
            return node && node.tagName === 'P' && !node.textContent.trim()
                && !node.querySelector('img,figure,video,iframe');
        }
        template.content.querySelectorAll('h2,h3,h4').forEach(function (heading) {
            if (heading.textContent.trim() || heading.querySelector('img,figure,video,iframe')) { return; }
            var previous = heading.previousElementSibling, next = heading.nextElementSibling;
            while (emptyParagraph(previous)) { previous = previous.previousElementSibling; }
            while (emptyParagraph(next)) { next = next.nextElementSibling; }
            if ((previous && previous.tagName === 'FIGURE') || (next && next.tagName === 'FIGURE')) {
                heading.remove();
            }
        });
        // Remove only completely empty separator paragraphs neighboring figures.
        for (var emptyPass = 0; emptyPass < 3; emptyPass++) {
            template.content.querySelectorAll('p').forEach(function (paragraph) {
                if (emptyParagraph(paragraph)
                    && ((paragraph.previousElementSibling && paragraph.previousElementSibling.tagName === 'FIGURE')
                        || (paragraph.nextElementSibling && paragraph.nextElementSibling.tagName === 'FIGURE'))) {
                    paragraph.remove();
                }
            });
        }
        window.PortixCallouts.semantic(template.content, quill);
        return normaliseBreakableSpaces(template.innerHTML);
    }

    function labelEditor(quill, wrapper) {
        var editorLabel = wrapper.getAttribute('data-editor-label') || '';
        if (editorLabel) {
            quill.root.setAttribute('aria-label', editorLabel);
        }
        quill.root.setAttribute('role', 'textbox');
        quill.root.setAttribute('aria-multiline', 'true');

        var toolbar = quill.getModule('toolbar').container;
        toolbar.setAttribute('role', 'toolbar');
        toolbar.setAttribute('aria-label', message('editor-toolbar', 'Content formatting'));
        [
            ['.ql-header', 'editor-heading', 'Heading level'],
            ['.ql-size', 'editor-size', 'Text size'],
            ['.ql-bold', 'editor-bold', 'Bold'],
            ['.ql-italic', 'editor-italic', 'Italic'],
            ['.ql-underline', 'editor-underline', 'Underline'],
            ['.ql-strike', 'editor-strike', 'Strikethrough'],
            ['.ql-list[value="ordered"]', 'editor-numbered-list', 'Numbered list'],
            ['.ql-list[value="bullet"]', 'editor-bullet-list', 'Bulleted list'],
            ['.ql-blockquote', 'editor-quote', 'Block quote'],
            ['.ql-link', 'editor-link', 'Link'],
            ['.ql-image', 'editor-image', 'Insert image'],
            ['.ql-align', 'editor-alignment', 'Alignment'],
            ['.ql-clean', 'editor-clear-formatting', 'Clear formatting']
        ].forEach(function (definition) {
            toolbar.querySelectorAll(definition[0]).forEach(function (control) {
                var label = message(definition[1], definition[2]);
                control.setAttribute('aria-label', label);
                control.setAttribute('title', label);
            });
        });
    }

    function normaliseCellDelta(node, delta) {
        var Delta = Quill.import('delta');
        var result = new Delta();
        var ops = delta.ops || [];
        ops.forEach(function (op, index) {
            if (typeof op.insert !== 'string') { result.push(op); return; }
            var parts = op.insert.split('\n');
            parts.forEach(function (part, i) {
                if (part) { result.insert(part, op.attributes); }
                if (i < parts.length - 1) {
                    var last = index === ops.length - 1 && i === parts.length - 2 && parts[parts.length - 1] === '';
                    if (last) { result.insert('\n', op.attributes); }
                    else { result.insert({ 'portix-break': true }); }
                }
            });
        });
        if (!result.ops.length || typeof result.ops[result.ops.length - 1].insert !== 'string' || !result.ops[result.ops.length - 1].insert.endsWith('\n')) {
            result.insert('\n', { table: node.getAttribute('data-row') || 'portix-row' });
        }
        return result;
    }

    function rememberEditorRange(quill, dialog) {
        var range = quill.getSelection() || quill.selection.savedRange;
        dialog.range = range ? { index: range.index, length: range.length } : { index: quill.getLength() - 1, length: 0 };
    }

    function restoredEditorRange(quill, dialog) {
        var range = dialog.range || quill.selection.savedRange || { index: quill.getLength() - 1, length: 0 };
        range.index = Math.max(0, Math.min(quill.getLength() - 1, range.index));
        quill.setSelection(range.index, range.length, 'silent');
        return range;
    }

    function safeImageSource(src) {
        return /^(?:\.\.\/|\/)?var\/uploads\/images\/(?:[a-f0-9]{32}\.(?:jpe?g|png|webp)|[a-z0-9](?:[a-z0-9-]{0,71}[a-z0-9])?-[a-f0-9]{8}\.webp)(?:\?p3media=[a-f0-9]{16})?$/i.test(String(src || ''));
    }

    function imageData(image) {
        return {
            src: image.getAttribute('src') || '',
            alt: image.getAttribute('alt') || '',
            'image-size': imageFormat(image, 'portix-image-size-', imageSizes),
            'image-align': imageFormat(image, 'portix-image-align-', imageAlignments),
            'image-caption': image.getAttribute('data-caption') || '',
            'image-wrap': image.getAttribute('data-wrap') || 'square',
            'image-width': image.getAttribute('data-width') || ''
        };
    }

    function paintImageObject(node, data) {
        var image = node.querySelector('img');
        var selected = node.classList.contains('is-portix-selected') || (image && image.classList.contains('is-portix-selected'));
        if (!image) { image = document.createElement('img'); node.appendChild(image); }
        image.setAttribute('src', safeImageSource(data.src) ? data.src : '');
        image.setAttribute('alt', String(data.alt || '').slice(0, 250));
        image.setAttribute('data-caption', String(data['image-caption'] || '').slice(0, 500));
        image.setAttribute('data-wrap', data['image-wrap'] === 'none' ? 'none' : 'square');
        image.className = '';
        node.className = 'portix-image-object';
        if (selected) { node.classList.add('is-portix-selected'); image.classList.add('is-portix-selected'); }
        if (imageSizes.indexOf(String(data['image-size'])) !== -1) {
            image.classList.add('portix-image-size-' + data['image-size']);
            node.classList.add('portix-image-size-' + data['image-size']);
        }
        if (imageAlignments.indexOf(data['image-align']) !== -1) {
            image.classList.add('portix-image-align-' + data['image-align']);
            node.classList.add('portix-image-align-' + data['image-align']);
        }
        node.setAttribute('data-wrap', image.getAttribute('data-wrap'));
        var width = parseInt(data['image-width'], 10);
        if (width >= 10 && width <= 100) {
            image.setAttribute('data-width', String(width)); node.style.width = width + '%';
        } else { image.removeAttribute('data-width'); node.style.removeProperty('width'); }
        var caption = node.querySelector('.portix-image-caption');
        if (!caption) { caption = document.createElement('span'); caption.className = 'portix-image-caption'; node.appendChild(caption); }
        caption.textContent = image.getAttribute('data-caption'); caption.hidden = !caption.textContent;
        // Firefox starts the gesture on the IMG itself inside Quill's
        // noneditable content span, rather than on the outer embed.
        image.draggable = true;
        node.draggable = true;
        ['nw', 'ne', 'sw', 'se'].forEach(function (corner) {
            var resize = node.querySelector('.portix-image-resize[data-corner="' + corner + '"]');
            if (!resize) {
                resize = document.createElement('span');
                resize.className = 'portix-image-resize';
                resize.setAttribute('data-corner', corner);
                resize.title = message('editor-image-resize', 'Redimensionner l’image');
                resize.setAttribute('aria-label', resize.title);
                node.appendChild(resize);
            }
        });
    }

    function semanticImage(node) {
        var image = node.querySelector('img');
        if (!image || !safeImageSource(image.getAttribute('src'))) { return ''; }
        var data = imageData(image);
        var clone = document.createElement('img');
        clone.setAttribute('src', data.src); clone.setAttribute('alt', data.alt);
        clone.className = 'portix-content-image';
        var figure = document.createElement('figure'); figure.className = 'portix-image-figure';
        if (data['image-size']) { figure.classList.add('portix-image-size-' + data['image-size']); }
        if (data['image-align']) { figure.classList.add('portix-image-align-' + data['image-align']); }
        if (data['image-width']) { figure.setAttribute('data-width', data['image-width']); }
        if (data['image-wrap'] === 'none') { figure.classList.add('portix-image-wrap-none'); }
        figure.appendChild(clone);
        if (data['image-caption']) {
            var caption = document.createElement('figcaption'); caption.textContent = data['image-caption']; figure.appendChild(caption);
        }
        return figure.outerHTML;
    }

    function registerStructuredFormats() {
        if (window.PortixStructuredRegistered) { return; }
        var Parchment = Quill.import('parchment');
        var Embed = Quill.import('blots/embed');
        var BlockEmbed = Quill.import('blots/block/embed');
        class ImageObject extends Embed {
            static create(value) {
                var node = super.create();
                paintImageObject(node, typeof value === 'string' ? { src: value } : value || {});
                return node;
            }
            static value(node) { var img = node.querySelector('img'); return { src: img ? img.getAttribute('src') : '' }; }
            static formats(node) { var img = node.querySelector('img'); var data = img ? imageData(img) : {}; delete data.src; return data; }
            format(name, value) {
                if (['alt', 'image-size', 'image-align', 'image-caption', 'image-wrap', 'image-width'].indexOf(name) !== -1) {
                    var data = imageData(this.domNode.querySelector('img')); data[name] = value || '';
                    paintImageObject(this.domNode, data); return;
                }
                super.format(name, value);
            }
            html() { return semanticImage(this.domNode); }
        }
        ImageObject.blotName = 'portix-image'; ImageObject.tagName = 'SPAN'; ImageObject.className = 'portix-image-object';
        Quill.register(ImageObject, true);
        Quill.register(new Parchment.Attributor('alt', 'alt', { scope: Parchment.Scope.INLINE }), true);
        Quill.register(new Parchment.Attributor('image-wrap', 'data-wrap', { scope: Parchment.Scope.INLINE, whitelist: ['square', 'none'] }), true);
        Quill.register(new Parchment.Attributor('image-width', 'data-portix-image-width', { scope: Parchment.Scope.INLINE }), true);
        class Divider extends BlockEmbed { static value() { return true; } html() { return '<hr>'; } }
        Divider.blotName = 'portix-divider'; Divider.tagName = 'HR';
        Quill.register(Divider, true);
        class CellWidth extends Parchment.StyleAttributor {
            add(node, value) {
                if (!/^\d+(?:\.\d{1,3})?%$/.test(String(value)) || parseFloat(value) <= 0 || parseFloat(value) > 100) { return false; }
                return super.add(node, value);
            }
        }
        Quill.register(new CellWidth('cell-width', 'width', { scope: Parchment.Scope.BLOCK }), true);
        ['colspan', 'rowspan'].forEach(function (name) {
            Quill.register(new Parchment.Attributor('cell-' + name, name, { scope: Parchment.Scope.BLOCK, whitelist: Array.from({length: 19}, function (_, i) { return String(i + 2); }) }), true);
        });
        Quill.register(new Parchment.Attributor('table-section', 'data-portix-table-section', { scope: Parchment.Scope.BLOCK, whitelist: ['head', 'foot'] }), true);
        Quill.register(new Parchment.Attributor('table-caption', 'data-portix-table-caption', { scope: Parchment.Scope.BLOCK }), true);
        // Native Quill balances physical cells; merged tables need logical columns.
        var TableContainer = Quill.import('formats/table-container');
        TableContainer.prototype.balanceCells = function () {
            if (this.domNode.querySelector('[colspan],[rowspan]')) { return; }
            var rows = this.rows();
            var count = rows.reduce(function (n, row) { return Math.max(n, row.children.length); }, 0);
            rows.forEach(function (row) {
                while (row.children.length < count) { row.appendChild(row.scroll.create('table', row.children.head ? row.children.head.domNode.getAttribute('data-row') : 'row-' + Math.random().toString(36).slice(2))); }
            });
        };
        Quill.register(new Parchment.Attributor('table-header', 'data-portix-cell-header', { scope: Parchment.Scope.BLOCK, whitelist: ['col', 'row', 'colgroup', 'rowgroup'] }), true);
        Quill.register(new Parchment.Attributor('table-width', 'data-portix-table-width', { scope: Parchment.Scope.BLOCK, whitelist: ['25', '33', '50', '66', '75', '100'] }), true);
        Quill.register(new Parchment.ClassAttributor('cell-vertical', 'portix-cell-valign', { scope: Parchment.Scope.BLOCK, whitelist: ['top', 'middle', 'bottom'] }), true);
        window.PortixStructuredRegistered = true;
    }

    function refreshTableMetadata(quill) {
        quill.root.querySelectorAll('table').forEach(function (table) {
            var first = table.querySelector('tr');
            var header = first && first.cells.length && Array.from(first.cells).every(function (cell) { return cell.getAttribute('data-portix-cell-header') === 'col'; });
            if (header) { table.setAttribute('data-portix-header', '1'); } else { table.removeAttribute('data-portix-header'); }
            var cell = table.querySelector('td,th');
            var width = cell ? cell.getAttribute('data-portix-table-width') : '';
            table.setAttribute('data-width', width || '100');
            table.style.width = (width || '100') + '%';
            table.style.tableLayout = table.querySelector('td[style*="width"],th[style*="width"]') ? 'fixed' : 'auto';
        });
    }

    function addTableProperties(quill, tools, currentTable, restoreContext) {
        var width = document.createElement('select');
        ['25', '33', '50', '66', '75', '100'].forEach(function (size) { addOption(width, size, size + ' %'); });
        width.value = '100';
        tools.appendChild(labelledField(message('editor-table-width', 'Largeur du tableau'), width));
        width.addEventListener('change', function () {
            restoreContext(); var table = currentTable(); if (!table) { return; }
            quill.getModule('history').cutoff();
            Array.from(table.querySelectorAll('td,th')).forEach(function (cell) {
                var blot = Quill.find(cell); if (blot) { quill.formatLine(quill.getIndex(blot), 1, 'table-width', width.value, 'user'); }
            });
            refreshTableMetadata(quill); quill.getModule('history').cutoff();
        });
        var header = makeRichButton(message('editor-table-cell-header', 'Cellule d’en-tête'));
        decorateCommand(header, 'table', header.textContent);
        tools.appendChild(header);
        header.addEventListener('click', function () {
            restoreContext(); var range = quill.getSelection(); if (!range) { return; }
            var format = quill.getFormat(range);
            quill.formatLine(range.index, Math.max(1, range.length), 'table-header', format['table-header'] ? false : 'row', 'user');
            refreshTableMetadata(quill);
        });
        var vertical = document.createElement('select');
        [['top', message('editor-cell-top', 'Haut')], ['middle', message('editor-cell-middle', 'Milieu')], ['bottom', message('editor-cell-bottom', 'Bas')]].forEach(function (item) { addOption(vertical, item[0], item[1]); });
        tools.appendChild(labelledField(message('editor-cell-vertical', 'Alignement vertical'), vertical));
        vertical.addEventListener('change', function () { restoreContext(); quill.format('cell-vertical', vertical.value, 'user'); });
        function sync(range) {
            if (!range || !currentTable()) { return; }
            var format = quill.getFormat(range);
            width.value = format['table-width'] || '100'; vertical.value = format['cell-vertical'] || 'top';
            header.setAttribute('aria-pressed', format['table-header'] ? 'true' : 'false');
        }
        quill.on('selection-change', sync);
    }

    function installImageDrag(quill, surface, handle, active, hide) {
        var moving = null;
        var marker = document.createElement('div'); marker.className = 'portix-image-drop-marker'; marker.hidden = true;
        surface.parentElement.appendChild(marker);
        function targetAt(event) {
            var cell = event.target.closest && event.target.closest('td,th');
            var block = cell || (event.target.closest && event.target.closest('.ql-editor > *'));
            if (!block || !quill.root.contains(block)) { return quill.getLength() - 1; }
            var blot = Quill.find(block); if (!blot) { return null; }
            var rect = block.getBoundingClientRect();
            var after = !cell && event.clientY > rect.top + rect.height / 2;
            var index = quill.getIndex(blot) + (after ? blot.length() : 0);
            marker.hidden = false;
            var parent = surface.parentElement.getBoundingClientRect();
            marker.style.top = ((after ? rect.bottom : rect.top) - parent.top + surface.parentElement.scrollTop) + 'px';
            return Math.min(index, quill.getLength() - 1);
        }
        function start(event, image) {
            image = image || active();
            var blot = imageBlot(image); if (!blot) { event.preventDefault(); return; }
            // Quill cancels native dragstart on its root. Only our image drag
            // bypasses that listener; text and foreign drags keep Quill's rules.
            event.stopImmediatePropagation();
            moving = { index: quill.getIndex(blot), delta: quill.getContents(quill.getIndex(blot), 1) };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-portix-image', '1');
            event.dataTransfer.setData('text/plain', 'P3 image');
        }
        handle.addEventListener('dragstart', function (event) { start(event); });
        quill.root.addEventListener('dragstart', function (event) {
            var object = event.target.closest && event.target.closest('.portix-image-object');
            if (object) { start(event, object.querySelector('img')); }
        }, true);
        surface.addEventListener('dragover', function (event) {
            if (!moving) { return; } event.preventDefault(); event.stopImmediatePropagation(); targetAt(event);
        }, true);
        surface.addEventListener('drop', function (event) {
            if (!moving) { return; }
            event.preventDefault(); event.stopImmediatePropagation();
            var index = targetAt(event); var original = moving.index; var delta = moving.delta;
            var targetCallout = index === null ? {} : imageCalloutAttributes(quill, index);
            moving = null; marker.hidden = true;
            if (index === null || index === original || index === original + 1) { return; }
            var Delta = Quill.import('delta');
            var change = index < original
                ? new Delta().retain(index).concat(delta).retain(original - index).delete(1)
                : new Delta().retain(original).delete(1).retain(index - original - 1).concat(delta);
            quill.getModule('history').cutoff(); quill.updateContents(change, 'user');
            var destination = index < original ? index : index - 1;
            var cell = quill.getLine(destination)[0];
            if (cell && cell.domNode.tagName !== 'TD') {
                var offset = quill.getLine(destination)[1];
                if (quill.getText(destination + 1, 1) !== '\n') { quill.insertText(destination + 1, '\n', targetCallout, 'user'); }
                if (offset > 0) { quill.insertText(destination, '\n', targetCallout, 'user'); destination += 1; }
                quill.formatLine(destination, 1, 'callout', targetCallout.callout || false, 'user');
            }
            quill.getModule('history').cutoff();
            quill.setSelection(destination, 0, 'silent'); hide();
        }, true);
        function end() { moving = null; marker.hidden = true; }
        handle.addEventListener('dragend', end); quill.root.addEventListener('dragend', end);
    }

    function installImageResize(quill) {
        var resizing = null;
        quill.root.addEventListener('pointerdown', function (event) {
            var handle = event.target.closest && event.target.closest('.portix-image-resize');
            if (!handle) { return; }
            event.preventDefault(); event.stopPropagation();
            var object = handle.closest('.portix-image-object');
            var parent = object.closest('td,th') || quill.root;
            var blot = Quill.find(object);
            if (!blot) { return; }
            resizing = { object: object, handle: handle, index: quill.getIndex(blot), left: event.clientX,
                direction: /w$/.test(handle.getAttribute('data-corner')) ? -1 : 1,
                width: object.getBoundingClientRect().width, total: Math.max(1, parent.clientWidth), original: object.style.width };
            if (handle.setPointerCapture) { handle.setPointerCapture(event.pointerId); }
        });
        quill.root.addEventListener('pointermove', function (event) {
            if (!resizing) { return; }
            var percent = Math.round((resizing.width + (event.clientX - resizing.left) * resizing.direction) / resizing.total * 100);
            resizing.percent = Math.max(10, Math.min(100, percent));
            resizing.object.style.width = resizing.percent + '%';
        });
        function finish(cancel) {
            if (!resizing) { return; }
            var state = resizing; resizing = null;
            state.object.style.width = state.original;
            if (!cancel && state.percent) {
                quill.getModule('history').cutoff();
                quill.formatText(state.index, 1, 'image-width', String(state.percent), 'user');
                quill.getModule('history').cutoff();
            }
        }
        quill.root.addEventListener('pointerup', function () { finish(false); });
        quill.root.addEventListener('pointercancel', function () { finish(true); });
    }


    function installAllTooltips(wrapper) {
        var names = {
            font: 'Police', size: 'Taille du texte', header: 'Niveau de titre', bold: 'Gras', italic: 'Italique',
            underline: 'Souligné', strike: 'Barré', color: 'Couleur du texte', background: 'Surlignage',
            script: 'Indice / exposant', list: 'Liste', indent: 'Retrait', direction: 'Sens du texte',
            blockquote: 'Citation', code: 'Code dans le texte', 'code-block': 'Bloc de code', align: 'Alignement',
            link: 'Lien', image: 'Insérer une image', video: 'Insérer une vidéo', formula: 'Formule LaTeX', clean: 'Effacer la mise en forme'
        };
        function labelAll() {
            if (!window.document || !wrapper.isConnected) { return; }
            wrapper.querySelectorAll('button,select,.ql-picker-label,.ql-picker-item,.ql-tooltip a').forEach(function (control) {
                var label = control.getAttribute('title') || control.getAttribute('aria-label');
                var classes = Array.from(control.classList);
                var type = classes.find(function (name) { return names[name.replace(/^ql-/, '')]; });
                if (!label && type) { label = message('editor-' + type.slice(3), names[type.slice(3)]); }
                var picker = control.closest('.ql-picker');
                if (!label && picker) {
                    var format = Array.from(picker.classList).find(function (name) { return names[name.replace(/^ql-/, '')]; });
                    label = format ? message('editor-' + format.slice(3), names[format.slice(3)]) : 'Choisir';
                    if (control.classList.contains('ql-picker-item')) {
                        label += ' : ' + (control.getAttribute('data-label') || control.getAttribute('data-value') || 'Par défaut');
                    }
                }
                if (!label) { label = control.textContent.trim() || (control.closest('label') ? control.closest('label').textContent.trim() : 'Action'); }
                if (control.getAttribute('value') === 'ordered') { label = message('editor-numbered-list', 'Liste numérotée'); }
                if (control.getAttribute('value') === 'bullet') { label = message('editor-bullet-list', 'Liste à puces'); }
                if (control.getAttribute('value') === 'check') { label = message('editor-checked-list', 'Liste de contrôle'); }
                if (control.getAttribute('value') === 'sub') { label = message('editor-sub', 'Indice'); }
                if (control.getAttribute('value') === 'super') { label = message('editor-super', 'Exposant'); }
                if (control.classList.contains('ql-indent')) { label = control.value === '+1' ? message('editor-indent-more', 'Augmenter le retrait') : message('editor-indent-less', 'Réduire le retrait'); }
                if (control.classList.contains('ql-action')) { label = message('editor-link-save', 'Enregistrer le lien'); }
                if (control.classList.contains('ql-remove')) { label = message('editor-link-remove', 'Supprimer le lien'); }
                control.title = label; control.setAttribute('aria-label', label);
            });
        }
        labelAll();
        new MutationObserver(labelAll).observe(wrapper, { childList: true, subtree: true });
    }


    function initialise() {
        var editors = document.querySelectorAll('[data-portix-editor]');
        if (!editors.length || !window.Quill) { return; }
        window.PortixCallouts.register();
        registerImageFormats();
        registerStructuredFormats();
        registerVideoFormat();
        registerSoftBreak();
        registerFormulaFormat();
        registerLinkTargetFormat();

        editors.forEach(function (wrapper) {
            if (wrapper.classList.contains('is-enhanced')) { return; }
            var source = wrapper.querySelector('.portix-editor-source');
            var surface = wrapper.querySelector('.portix-editor-surface');
            if (!source || !surface) { return; }

            if (source.value.indexOf('portix-formula') !== -1 && !window.katex) {
                showStatus(wrapper, message('editor-formula-unavailable', 'KaTeX is unavailable.'), 'error');
                return;
            }

            var initialTableHeaders = sourceTableHeaderFlags(source.value);
            var initialHtml = preserveInitialLinkTargets(editableHtml(source.value));
            surface.innerHTML = initialHtml;
            var quill = new Quill(surface, {
                theme: 'snow',
                formats: [
                    'header', 'bold', 'italic', 'underline', 'strike', 'blockquote',
                    'list', 'link', 'link-target', 'image', 'align', 'color', 'size', 'portix-break',
                    'image-size', 'image-align', 'alt', 'image-caption', 'formula', 'portix-video', 'table',
                    'portix-image', 'image-wrap', 'image-width', 'portix-divider', 'background', 'font', 'code', 'code-block',
                    'callout', 'callout-title', 'script', 'indent', 'direction', 'table-header', 'table-width', 'cell-vertical', 'cell-width', 'cell-colspan', 'cell-rowspan', 'table-caption', 'table-section'
                ],
                modules: {
                    table: true,
                    clipboard: { matchers: [
                        ['A', linkTargetDelta],
                        ['BR', function (node, delta) {
                            if (node.closest('td,th') || node.classList.contains('portix-soft-break')) { return new (Quill.import('delta'))().insert({ 'portix-break': true }); }
                            return delta;
                        }],
                        ['TD, TH', function (node, delta) { return normaliseCellDelta(node, delta); }],
                        ['IMG', function (node) {
                            var Delta = Quill.import('delta');
                            var data = imageData(node);
                            var src = data.src; delete data.src;
                            return safeImageSource(src) ? new Delta().insert({ 'portix-image': { src: src } }, data) : new Delta();
                        }]
                    ] },
                    history: { delay: 800, maxStack: 100, userOnly: true },
                    toolbar: [
                        [{ font: [] }, { size: ['small', false, 'large', 'huge'] }],
                        [{ header: [1, 2, 3, 4, 5, 6, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ color: [] }, { background: [] }],
                        [{ script: 'sub' }, { script: 'super' }],
                        [{ list: 'ordered' }, { list: 'bullet' }, { list: 'check' }],
                        [{ indent: '-1' }, { indent: '+1' }, { direction: 'rtl' }],
                        ['blockquote', 'code', 'code-block'],
                        [{ align: [] }],
                        ['link', 'image', 'video', 'formula'],
                        ['clean']
                    ],
                    keyboard: {
                        bindings: {
                            portixTableEnter: {
                                portixTable: true, key: 'Enter', shiftKey: false,
                                handler: function (range) {
                                    var info = this.quill.getModule('table').getTable(range);
                                    if (!info[2]) { return true; }
                                    var end = this.quill.getIndex(info[2]) + info[2].length() - 1;
                                    this.quill.deleteText(range.index, Math.min(range.length, end - range.index), 'user');
                                    this.quill.insertEmbed(range.index, 'portix-break', true, 'user');
                                    this.quill.setSelection(range.index + 1, 0, 'silent');
                                    return false;
                                }
                            },
                            portixTableTab: {
                                portixTable: true, key: 'Tab', shiftKey: false,
                                handler: function (range) { return moveTableCell(this.quill, range, false); }
                            },
                            portixTableBackTab: {
                                portixTable: true, key: 'Tab', shiftKey: true,
                                handler: function (range) { return moveTableCell(this.quill, range, true); }
                            },
                            portixCalloutEmpty: {
                                key: 'Enter', shiftKey: false, collapsed: true, empty: true, format: ['callout'],
                                handler: function (range) {
                                    this.quill.formatLine(range.index, 1, { callout: false, 'callout-title': false }, 'user');
                                    return false;
                                }
                            },
                            portixCalloutTitle: {
                                key: 'Enter', shiftKey: false, format: ['callout-title'],
                                handler: function (range) {
                                    if (range.length) { this.quill.deleteText(range.index, range.length, 'user'); }
                                    var type = this.quill.getFormat(range.index, 0).callout;
                                    this.quill.insertText(range.index, '\n', { callout: type }, 'user');
                                    this.quill.formatLine(range.index + 1, 1, { 'callout-title': false }, 'user');
                                    this.quill.setSelection(range.index + 1, 0, 'silent');
                                    return false;
                                }
                            },
                            portixSoftBreak: {
                                portixTable: true, key: 'Enter',
                                shiftKey: true,
                                handler: function (range) {
                                    this.quill.deleteText(range.index, range.length, 'user');
                                    this.quill.insertEmbed(range.index, 'portix-break', true, 'user');
                                    this.quill.setSelection(range.index + 1, 0, 'silent');
                                    return false;
                                }
                            }
                        }
                    }
                }
            });
            // Quill registers its own table Enter before user bindings. Keep in-cell
            // Enter/Tab ahead of that handler, which otherwise exits the table.
            ['Enter', 'Tab'].forEach(function (key) {
                var bindings = quill.keyboard.bindings[key] || [];
                quill.keyboard.bindings[key] = bindings.filter(function (binding) { return binding.portixTable; }).concat(bindings.filter(function (binding) { return !binding.portixTable; }));
            });
            // The inline target format requires an existing anchor. Apply initial
            // targets after Quill has built links, before the undo history starts.
            var initialLinks = document.createElement('template'); initialLinks.innerHTML = initialHtml;
            var targets = Array.from(initialLinks.content.querySelectorAll('a'));
            Array.from(quill.root.querySelectorAll('a')).forEach(function (link, index) {
                var original = targets[index], blot = Quill.find(link);
                if (original && blot) { quill.formatText(quill.getIndex(blot), blot.length(), 'link-target', original.getAttribute('data-portix-link-target') || 'self', 'silent'); }
            });
            applyInitialTableHeaders(quill, initialTableHeaders);
            quill.getModule('history').clear();
            quill.getModule('toolbar').addHandler('video', function () {});
            quill.getModule('toolbar').addHandler('formula', function () {});
            Array.from(quill.root.querySelectorAll('img')).forEach(syncCaptionPreview);
            wrapper.classList.add('is-enhanced');
            labelEditor(quill, wrapper);
            quill.getModule('toolbar').addHandler('image', function () {
                selectImage(quill, wrapper);
            });
            var colorPicker = wrapper.querySelector('.ql-color');
            if (colorPicker) {
                colorPicker.setAttribute('aria-label', message('editor-text-color', 'Text colour'));
                colorPicker.setAttribute('title', message('editor-text-color', 'Text colour'));
            }
            createImageTools(quill, wrapper, surface);
            createRichContentTools(quill, wrapper, surface);
            installImageResize(quill);
            installAllTooltips(wrapper);
            quill.on('text-change', function () {
                refreshTableMetadata(quill);
                Array.from(quill.root.querySelectorAll('img')).forEach(syncCaptionPreview);
                source.value = semanticHtml(quill);
                source.dispatchEvent(new Event('input', { bubbles: true }));
            });
            source.value = semanticHtml(quill);
            enhanceLinkTooltip(quill);

            surface.addEventListener('paste', function (event) {
                var files = clipboardImages(event);
                if (files.length) {
                    event.preventDefault();
                    event.stopPropagation();
                    insertImages(files, quill, wrapper);
                    return;
                }
                if (!pasteTextInTableCell(event, quill)) { pasteStructuredTables(event, quill); }
            }, true);
            surface.addEventListener('dragover', function (event) {
                if (event.dataTransfer && Array.from(event.dataTransfer.types).indexOf('Files') !== -1) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            }, true);
            surface.addEventListener('drop', function (event) {
                var files = Array.from(event.dataTransfer ? event.dataTransfer.files : []).filter(function (file) {
                    return file.type.indexOf('image/') === 0;
                });
                if (files.length) {
                    event.preventDefault();
                    event.stopPropagation();
                    insertImages(files, quill, wrapper);
                }
            }, true);

            var form = wrapper.closest('form');
            if (form) {
                form.addEventListener('submit', function (event) {
                    if (Number(form.dataset.portixPendingUploads || 0) > 0) {
                        event.preventDefault();
                        showStatus(
                            wrapper,
                            message('editor-wait-upload', 'Wait for image uploads to finish.'),
                            'error'
                        );
                        return;
                    }
                    source.value = semanticHtml(quill);
                });
            }
        });
    }

    function boot() {
        if (!document.querySelector('[data-portix-editor]')) { return; }
        loadStyle(editorAsset('quill/quill.snow.css'), 'quill');
        loadStyle(editorAsset('portix-editor.css'), 'portix');
        loadQuill(function () {
            loadKatex(function () { initialise(); });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
}());
