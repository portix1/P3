(function () {
    'use strict';

    function boot() {
        var form = document.querySelector('form[data-article-autosave]');
        if (!form) { return; }
        var endpoint = form.getAttribute('data-article-autosave');
        var status = document.getElementById('article-autosave-status');
        var snapshotNode = document.getElementById('article-autosave-snapshot');
        var base = form.querySelector('input[name="base_updated_at"]');
        var revision = 0;
        var storedRevision = 0;
        var sending = false;
        var submitting = false;

        function say(message) {
            if (status) { status.textContent = message; }
        }
        function controlsFor(name) {
            return Array.from(form.querySelectorAll('[name]')).filter(function (node) {
                return node.name === name && !node.disabled;
            });
        }
        function readValue(name) {
            var fields = controlsFor(name);
            if (!fields.length) { return ''; }
            return fields[0].value;
        }
        function restore(saved) {
            var data = saved && saved.fields;
            if (!data || typeof data !== 'object') { return; }
            var keys = ['title', 'slug', 'category_id', 'content', 'summary',
                'author_name', 'featured_image'];
            keys.forEach(function (name) {
                var control = controlsFor(name)[0];
                if (control && typeof data[name] === 'string') {
                    control.value = data[name];
                    control.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
            var toc = controlsFor('toc_enabled')[0];
            if (toc) { toc.checked = data.toc_enabled === '1'; }
            var related = Array.isArray(data.related) ? data.related.map(String) : [];
            controlsFor('related[]').forEach(function (node) {
                node.checked = related.indexOf(node.value) !== -1;
            });
            var positions = data.related_positions || {};
            Object.keys(positions).forEach(function (id) {
                var control = controlsFor('related_positions[' + id + ']')[0];
                if (control) { control.value = String(positions[id]); }
            });
            revision += 1;
            say('Brouillon récupéré. La publication reste inchangée.');
        }
        if (snapshotNode && snapshotNode.value) {
            try {
                var saved = JSON.parse(snapshotNode.value);
                if (saved && saved.fields && typeof saved.fields.content === 'string') {
                    var differs = saved.fields.content !== readValue('content')
                        || saved.fields.title !== readValue('title')
                        || saved.fields.summary !== readValue('summary')
                        || saved.fields.slug !== readValue('slug');
                    if (differs && window.confirm(
                        'Une sauvegarde automatique du ' + saved.saved_at
                        + ' est disponible. Récupérer les modifications non enregistrées ?'
                    )) {
                        restore(saved);
                    }
                }
            } catch (error) {
                say('Impossible de lire la sauvegarde automatique.');
            }
        }

        function changed(event) {
            if (event.target && (event.target.name === 'csrf_token'
                || event.target.name === 'base_updated_at')) { return; }
            revision += 1;
        }
        form.addEventListener('input', changed);
        form.addEventListener('change', changed);
        form.addEventListener('submit', function (event) {
            if (event.submitter && event.submitter.hasAttribute('data-preview-submit')) { return; }
            submitting = true;
            window.setTimeout(function () { submitting = false; }, 1500);
        });

        function autosave() {
            if (sending || submitting || revision === storedRevision
                || Number(form.dataset.portixPendingUploads || 0) > 0) { return; }
            sending = true;
            var requestRevision = revision;
            var data = new FormData(form);
            data.delete('status'); // A recovery copy must never publish or unpublish content.
            data.set('base_updated_at', base ? base.value : '');
            say('Sauvegarde automatique en cours…');
            fetch(endpoint, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'error',
                headers: { 'Accept': 'application/json' }
            }).then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                return response.json();
            }).then(function (result) {
                if (!result || result.ok !== true) { throw new Error('invalid response'); }
                if (revision === requestRevision) { storedRevision = revision; }
                say('Brouillon sauvegardé automatiquement à '
                    + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                    + '. Non publié.');
            }).catch(function () {
                say('Sauvegarde automatique indisponible : enregistrez manuellement.');
            }).finally(function () { sending = false; });
        }

        window.setInterval(autosave, 30000);
        say('Sauvegarde automatique du brouillon toutes les 30 secondes.');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
}());
