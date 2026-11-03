(function () {
    'use strict';

    var form = document.getElementById('footer-menu-admin-form');
    var columnsRoot = document.getElementById('footer-admin-columns');
    var countSelect = document.getElementById('footer-column-count');
    var template = document.getElementById('footer-link-template');
    if (!form || !columnsRoot || !countSelect || !template) { return; }

    var dragged = null;
    var newCounter = 0;

    function activeRows(dropzone) {
        return Array.prototype.filter.call(
            dropzone.querySelectorAll('[data-footer-link]'),
            function (row) { return !row.classList.contains('is-deleted'); }
        );
    }

    function syncOrder() {
        columnsRoot.querySelectorAll('[data-footer-dropzone]').forEach(function (dropzone) {
            var columnId = dropzone.getAttribute('data-footer-dropzone');
            activeRows(dropzone).forEach(function (row, index) {
                var columnInput = row.querySelector('[data-footer-column-input]');
                var positionInput = row.querySelector('[data-footer-position-input]');
                if (columnInput) { columnInput.value = columnId; }
                if (positionInput) { positionInput.value = String((index + 1) * 10); }
            });
        });
    }

    function refreshColumnVisibility() {
        var count = parseInt(countSelect.value, 10) === 2 ? 2 : 3;
        columnsRoot.setAttribute('data-column-count', String(count));
        columnsRoot.querySelectorAll('[data-column-position]').forEach(function (column) {
            var position = parseInt(column.getAttribute('data-column-position'), 10);
            column.hidden = position > count;
        });
    }

    function getInsertBefore(dropzone, clientY) {
        var rows = activeRows(dropzone).filter(function (row) { return row !== dragged; });
        var closest = { offset: Number.NEGATIVE_INFINITY, element: null };
        rows.forEach(function (row) {
            var box = row.getBoundingClientRect();
            var offset = clientY - box.top - (box.height / 2);
            if (offset < 0 && offset > closest.offset) {
                closest = { offset: offset, element: row };
            }
        });
        return closest.element;
    }

    function addLink(columnId) {
        newCounter += 1;
        var key = 'new_' + Date.now() + '_' + newCounter;
        var html = template.innerHTML
            .split('__KEY__').join(key)
            .split('__COLUMN__').join(String(columnId));
        var dropzone = columnsRoot.querySelector('[data-footer-dropzone="' + columnId + '"]');
        if (!dropzone) { return; }
        dropzone.insertAdjacentHTML('beforeend', html);
        syncOrder();
        var row = dropzone.lastElementChild;
        var firstInput = row ? row.querySelector('input[type="text"]') : null;
        if (firstInput) { firstInput.focus(); }
    }

    columnsRoot.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-footer-add]');
        if (addButton) {
            addLink(addButton.getAttribute('data-footer-add'));
            return;
        }

        var removeButton = event.target.closest('[data-footer-remove]');
        if (!removeButton) { return; }
        var row = removeButton.closest('[data-footer-link]');
        if (!row) { return; }
        var idInput = row.querySelector('input[name$="[id]"]');
        var deleteInput = row.querySelector('[data-footer-delete-input]');
        if (idInput && parseInt(idInput.value, 10) > 0 && deleteInput) {
            deleteInput.value = '1';
            row.classList.add('is-deleted');
        } else {
            row.remove();
        }
        syncOrder();
    });

    columnsRoot.addEventListener('dragstart', function (event) {
        var handle = event.target.closest('[data-footer-drag-handle]');
        if (!handle) { return; }
        var row = handle.closest('[data-footer-link]');
        if (!row || row.classList.contains('is-deleted')) { return; }
        dragged = row;
        row.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', row.getAttribute('data-link-key') || 'footer-link');
    });

    columnsRoot.addEventListener('dragend', function () {
        if (dragged) { dragged.classList.remove('is-dragging'); }
        dragged = null;
        columnsRoot.querySelectorAll('.is-drag-over').forEach(function (zone) {
            zone.classList.remove('is-drag-over');
        });
        syncOrder();
    });

    columnsRoot.addEventListener('dragover', function (event) {
        var dropzone = event.target.closest('[data-footer-dropzone]');
        if (!dropzone || !dragged || dropzone.closest('[hidden]')) { return; }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        columnsRoot.querySelectorAll('.is-drag-over').forEach(function (zone) {
            if (zone !== dropzone) { zone.classList.remove('is-drag-over'); }
        });
        dropzone.classList.add('is-drag-over');

        var before = getInsertBefore(dropzone, event.clientY);
        if (before) {
            dropzone.insertBefore(dragged, before);
        } else {
            dropzone.appendChild(dragged);
        }
        syncOrder();
    });

    columnsRoot.addEventListener('drop', function (event) {
        var dropzone = event.target.closest('[data-footer-dropzone]');
        if (!dropzone || !dragged) { return; }
        event.preventDefault();
        dropzone.classList.remove('is-drag-over');
        syncOrder();
    });

    countSelect.addEventListener('change', refreshColumnVisibility);
    form.addEventListener('submit', syncOrder);

    refreshColumnVisibility();
    syncOrder();
}());
