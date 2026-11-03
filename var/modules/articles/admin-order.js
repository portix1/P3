(function () {
    'use strict';

    var organizer = document.querySelector('[data-article-organizer]');
    if (!organizer) {
        return;
    }

    var draggedItem = null;
    var sourceList = null;

    function directItems(list) {
        return Array.prototype.filter.call(list.children, function (child) {
            return child.matches('[data-sortable-item]');
        });
    }

    function refresh(list) {
        var items = directItems(list);
        items.forEach(function (item, index) {
            var position = item.querySelector(':scope > .article-order-row [data-order-position]');
            var up = item.querySelector(':scope > .article-order-row [data-move="up"]');
            var down = item.querySelector(':scope > .article-order-row [data-move="down"]');
            if (position) {
                position.textContent = String(index + 1);
            }
            if (up) {
                up.disabled = index === 0;
            }
            if (down) {
                down.disabled = index === items.length - 1;
            }
        });
    }

    organizer.querySelectorAll('[data-sortable-list]').forEach(refresh);

    organizer.addEventListener('dragstart', function (event) {
        var item = event.target.closest('[data-sortable-item]');
        if (!item || !organizer.contains(item)) {
            return;
        }
        draggedItem = item;
        sourceList = item.parentElement;
        item.classList.add('is-dragging');
        if (event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.orderId || '');
        }
    });

    organizer.addEventListener('dragover', function (event) {
        if (!draggedItem || !sourceList) {
            return;
        }
        var target = event.target.closest('[data-sortable-item]');
        if (!target || target === draggedItem || target.parentElement !== sourceList) {
            return;
        }
        event.preventDefault();
        var box = target.getBoundingClientRect();
        sourceList.insertBefore(draggedItem, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
        refresh(sourceList);
    });

    organizer.addEventListener('dragend', function () {
        if (draggedItem) {
            draggedItem.classList.remove('is-dragging');
        }
        if (sourceList) {
            refresh(sourceList);
        }
        draggedItem = null;
        sourceList = null;
    });

    organizer.addEventListener('click', function (event) {
        var button = event.target.closest('[data-move]');
        if (!button) {
            return;
        }
        var item = button.closest('[data-sortable-item]');
        var list = item ? item.parentElement : null;
        if (!item || !list) {
            return;
        }
        var items = directItems(list);
        var index = items.indexOf(item);
        if (button.dataset.move === 'up' && index > 0) {
            list.insertBefore(item, items[index - 1]);
        } else if (button.dataset.move === 'down' && index >= 0 && index < items.length - 1) {
            list.insertBefore(items[index + 1], item);
        }
        refresh(list);
        button.focus();
    });
}());
