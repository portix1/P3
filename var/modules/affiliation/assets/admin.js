(function () {
    'use strict';
    function normalise(value) {
        return String(value || '').toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-affiliation-confirm')) { return; }
        if (!window.confirm(form.getAttribute('data-affiliation-confirm'))) { event.preventDefault(); }
    });
    document.addEventListener('input', function (event) {
        var input = event.target.closest('[data-affiliation-article-filter]');
        if (!input) { return; }
        var list = document.querySelector('[data-affiliation-article-list]');
        if (!list) { return; }
        var query = normalise(input.value.trim());
        list.querySelectorAll('[data-affiliation-article]').forEach(function (row) {
            row.hidden = query !== '' && normalise(row.getAttribute('data-affiliation-article')).indexOf(query) === -1;
        });
    });
}());
