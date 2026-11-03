(function () {
    'use strict';

    var list = document.querySelector('[data-sortable-menu]');
    if (!list) { return; }

    var dragged = null;

    function items() {
        return Array.prototype.slice.call(list.querySelectorAll('[data-menu-item]'));
    }

    function refresh() {
        items().forEach(function (item, index, allItems) {
            var position = item.querySelector('[data-position]');
            var up = item.querySelector('[data-move="up"]');
            var down = item.querySelector('[data-move="down"]');
            if (position) { position.textContent = String(index + 1); }
            if (up) { up.disabled = index === 0; }
            if (down) { down.disabled = index === allItems.length - 1; }
        });
    }

    list.addEventListener('dragstart', function (event) {
        var item = event.target.closest('[data-menu-item]');
        if (!item) { return; }
        dragged = item;
        item.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', 'menu-item');
    });

    list.addEventListener('dragover', function (event) {
        if (!dragged) { return; }
        event.preventDefault();
        var target = event.target.closest('[data-menu-item]');
        if (!target || target === dragged) { return; }
        var bounds = target.getBoundingClientRect();
        var insertAfter = event.clientY > bounds.top + bounds.height / 2;
        list.insertBefore(dragged, insertAfter ? target.nextSibling : target);
        refresh();
    });

    list.addEventListener('dragend', function () {
        if (dragged) { dragged.classList.remove('is-dragging'); }
        dragged = null;
        refresh();
    });

    list.addEventListener('click', function (event) {
        var button = event.target.closest('[data-move]');
        if (!button) { return; }
        var item = button.closest('[data-menu-item]');
        var direction = button.getAttribute('data-move');
        if (direction === 'up' && item.previousElementSibling) {
            list.insertBefore(item, item.previousElementSibling);
        } else if (direction === 'down' && item.nextElementSibling) {
            list.insertBefore(item.nextElementSibling, item);
        }
        refresh();
    });

    refresh();
}());
