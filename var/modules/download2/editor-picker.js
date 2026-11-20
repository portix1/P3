(function () {
    'use strict';
    var lang = document.documentElement.lang === 'en' ? 'en' : 'fr';
    var labels = lang === 'en'
        ? { button: 'Insert a download', heading: 'Choose a published file', search: 'Search files',
            empty: 'No published files.', error: 'Unable to load files.', insert: 'Insert', cancel: 'Cancel' }
        : { button: 'Insérer un téléchargement', heading: 'Choisir un fichier publié', search: 'Rechercher un fichier',
            empty: 'Aucun fichier publié.', error: 'Impossible de charger les fichiers.',
            insert: 'Insérer', cancel: 'Annuler' };

    function install(wrapper, quill) {
        if (!quill || wrapper.dataset.dl2PickerReady === '1') { return; }
        var toolbar = wrapper.querySelector('.ql-toolbar');
        if (!toolbar) { return; }
        wrapper.dataset.dl2PickerReady = '1';
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'portix-rich-tool portix-download-picker';
        button.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.5 11.5 12.2 19.8a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/></svg>';
        button.title = labels.button;
        button.setAttribute('aria-label', labels.button);
        toolbar.appendChild(button);

        button.addEventListener('click', async function () {
            var range = quill.getSelection() || quill.selection.savedRange;
            var index = range ? range.index : Math.max(0, quill.getLength() - 1);
            var length = range ? range.length : 0;
            var dialog = document.createElement('dialog');
            dialog.className = 'portix-download-dialog';
            dialog.setAttribute('aria-labelledby', 'portix-download-picker-heading');
            var heading = document.createElement('h2');
            heading.id = 'portix-download-picker-heading';
            heading.textContent = labels.heading;
            var search = document.createElement('input');
            search.type = 'search';
            search.placeholder = labels.search;
            search.setAttribute('aria-label', labels.search);
            var status = document.createElement('p');
            status.setAttribute('role', 'status');
            var list = document.createElement('div');
            list.className = 'portix-download-choices';
            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.textContent = labels.cancel;
            function close() { dialog.close(); dialog.remove(); quill.focus(); }
            cancel.addEventListener('click', close);
            dialog.addEventListener('cancel', function (event) { event.preventDefault(); close(); });
            dialog.append(heading, search, status, list, cancel);
            document.body.appendChild(dialog);
            dialog.showModal();
            search.focus();
            try {
                var response = await fetch('download-catalog.html', { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) { throw new Error('download catalogue unavailable'); }
                var payload = await response.json();
                var files = Array.isArray(payload.files) ? payload.files : [];
                function render() {
                    list.replaceChildren();
                    var query = search.value.trim().toLocaleLowerCase();
                    var matching = files.filter(function (file) {
                        return String(file.title || '').toLocaleLowerCase().includes(query) ||
                            String(file.category || '').toLocaleLowerCase().includes(query);
                    });
                    status.textContent = matching.length ? '' : labels.empty;
                    matching.forEach(function (file) {
                        if (!Number.isSafeInteger(file.id) || file.id < 1) { return; }
                        var row = document.createElement('button');
                        row.type = 'button';
                        row.className = 'portix-download-choice';
                        row.textContent = String(file.title) + (file.category ? ' — ' + file.category : '');
                        row.title = labels.insert + ' : ' + file.title;
                        row.addEventListener('click', function () {
                            var text = String(file.title).trim();
                            if (!text) { return; }
                            if (length) { quill.deleteText(index, length, 'user'); }
                            quill.insertText(index, text, { link: 'download-file-' + file.id + '.html' }, 'user');
                            quill.insertText(index + text.length, ' ', 'user');
                            quill.setSelection(index + text.length + 1, 0, 'silent');
                            close();
                        });
                        list.appendChild(row);
                    });
                }
                render();
                search.addEventListener('input', render);
            } catch (error) {
                status.textContent = labels.error;
            }
        });
    }

    document.addEventListener('p3:editor-ready', function (event) {
        if (event.target instanceof Element && event.target.matches('.portix-editor')) {
            install(event.target, event.detail && event.detail.quill);
        }
    });
    function scan() {
        document.querySelectorAll('.portix-editor').forEach(function (wrapper) {
            install(wrapper, wrapper.p3Quill);
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', scan); }
    else { scan(); }
})();
