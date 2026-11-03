(function () {
    'use strict';
    function message(name, fallback) {
        return document.body ? (document.body.getAttribute('data-' + name) || fallback) : fallback;
    }

    function toggle(buttonSelector, panelSelector) {
        var button = document.querySelector(buttonSelector);
        var panel = document.querySelector(panelSelector);
        if (!button || !panel) { return; }
        button.addEventListener('click', function () {
            var expanded = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            panel.classList.toggle('is-open', !expanded);
        });
    }
    toggle('.nav-toggle', '#main-navigation');
    toggle('.admin-nav-toggle', '#admin-sidebar');

    document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
        control.addEventListener('change', function () {
            if (control.form) { control.form.requestSubmit(); }
        });
    });

    document.querySelectorAll('form[data-confirm-message]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm-message'))) { event.preventDefault(); }
        });
    });

    function directSubmenuToggle(item) {
        var row = Array.from(item.children).find(function (child) {
            return child.classList && child.classList.contains('navigation-link-row');
        });
        return row ? row.querySelector('[data-submenu-toggle]') : null;
    }

    function closeSubmenu(item) {
        item.classList.remove('is-submenu-open');
        var button = directSubmenuToggle(item);
        if (button) { button.setAttribute('aria-expanded', 'false'); }
        item.querySelectorAll('.has-submenu.is-submenu-open').forEach(function (child) {
            child.classList.remove('is-submenu-open');
            var childButton = directSubmenuToggle(child);
            if (childButton) { childButton.setAttribute('aria-expanded', 'false'); }
        });
    }

    function directSubmenu(item) {
        return Array.from(item.children).find(function (child) {
            return child.classList && child.classList.contains('navigation-submenu');
        }) || null;
    }

    function fitSubmenuToViewport(item) {
        if (!item || window.matchMedia('(max-width: 900px)').matches) { return; }
        var submenu = directSubmenu(item);
        if (!submenu) { return; }

        item.classList.remove('submenu-align-right', 'submenu-opens-left');
        submenu.style.removeProperty('max-height');
        submenu.style.removeProperty('overflow-y');
        submenu.style.removeProperty('overscroll-behavior');

        window.requestAnimationFrame(function () {
            var rect = submenu.getBoundingClientRect();

            if (rect.right > window.innerWidth - 8) {
                if (item.classList.contains('navigation-item')) {
                    item.classList.add('submenu-align-right');
                } else {
                    item.classList.add('submenu-opens-left');
                }
                rect = submenu.getBoundingClientRect();
            }

            if (rect.bottom <= window.innerHeight - 8) { return; }

            var availableHeight = Math.floor(window.innerHeight - Math.max(rect.top, 8) - 8);
            if (availableHeight < 80) { return; }

            submenu.style.maxHeight = availableHeight + 'px';
            submenu.style.overflowY = 'auto';
            submenu.style.overscrollBehavior = 'contain';
        });
    }

    document.querySelectorAll('#main-navigation .has-submenu').forEach(function (item) {
        item.addEventListener('mouseenter', function () { fitSubmenuToViewport(item); });
        item.addEventListener('focusin', function () { fitSubmenuToViewport(item); });
    });

    document.querySelectorAll('[data-submenu-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var item = button.closest('.has-submenu');
            if (!item) { return; }
            var opening = !item.classList.contains('is-submenu-open');
            Array.from(item.parentElement ? item.parentElement.children : []).forEach(function (sibling) {
                if (sibling !== item && sibling.classList && sibling.classList.contains('has-submenu')) {
                    closeSubmenu(sibling);
                }
            });
            item.classList.toggle('is-submenu-open', opening);
            button.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest || event.target.closest('#main-navigation')) { return; }
        document.querySelectorAll('#main-navigation .has-submenu.is-submenu-open').forEach(closeSubmenu);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') { return; }
        var focused = document.activeElement;
        var item = focused && focused.closest ? focused.closest('.has-submenu.is-submenu-open') : null;
        if (item) {
            var button = directSubmenuToggle(item);
            closeSubmenu(item);
            if (button) { button.focus(); }
            return;
        }

        [
            ['.nav-toggle', '#main-navigation'],
            ['.admin-nav-toggle', '#admin-sidebar']
        ].some(function (pair) {
            var toggleButton = document.querySelector(pair[0]);
            var panel = document.querySelector(pair[1]);
            if (!toggleButton || !panel || !panel.classList.contains('is-open')) { return false; }
            if (focused !== toggleButton && !(focused && panel.contains(focused))) { return false; }
            panel.classList.remove('is-open');
            toggleButton.setAttribute('aria-expanded', 'false');
            toggleButton.focus();
            return true;
        });
    });

    var publicMenuToggle = document.querySelector('.nav-toggle');
    if (publicMenuToggle) {
        publicMenuToggle.addEventListener('click', function () {
            if (publicMenuToggle.getAttribute('aria-expanded') === 'false') {
                document.querySelectorAll('#main-navigation .has-submenu.is-submenu-open').forEach(closeSubmenu);
            }
        });
    }

    document.querySelectorAll('form[data-unsaved-warning]').forEach(function (form) {
        var dirty = false;
        var submitting = false;
        var markDirty = function (event) {
            if (event.target && event.target.name === 'csrf_token') { return; }
            dirty = true;
        };
        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);
        form.addEventListener('submit', function (event) {
            var submitter = event.submitter;
            if (submitter && submitter.hasAttribute('data-preview-submit')) {
                return;
            }
            submitting = true;
            window.setTimeout(function () { submitting = false; }, 1200);
        });
        window.addEventListener('beforeunload', function (event) {
            if (!dirty || submitting) { return; }
            event.preventDefault();
            event.returnValue = '';
        });
    });

    var confirmationDialog = document.querySelector('#confirm-delete-dialog');
    if (confirmationDialog && typeof confirmationDialog.showModal === 'function') {
        document.querySelectorAll('form[data-confirm-delete]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (form.dataset.confirmed === '1') { return; }
                event.preventDefault();
                var title = confirmationDialog.querySelector('[data-confirm-title]');
                if (title) {
                    title.textContent = form.getAttribute('data-confirm-title')
                        || message('news-delete-title', 'this news item');
                }
                confirmationDialog.returnValue = 'cancel';
                confirmationDialog.showModal();
                confirmationDialog.addEventListener('close', function decide() {
                    confirmationDialog.removeEventListener('close', decide);
                    if (confirmationDialog.returnValue === 'confirm') {
                        form.dataset.confirmed = '1';
                        form.requestSubmit();
                    }
                });
            });
        });
    } else {
        document.querySelectorAll('form[data-confirm-delete]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var title = form.getAttribute('data-confirm-title')
                    || message('news-delete-title', 'this news item');
                if (!window.confirm(
                    (form.getAttribute('data-confirm-prefix')
                        || message('news-delete-prefix', 'Permanently delete “'))
                    + title
                    + (form.getAttribute('data-confirm-suffix')
                        || message('news-delete-suffix', '”?'))
                )) {
                    event.preventDefault();
                }
            });
        });
    }

    function renderPortixFormulas() {
        var formulas = document.querySelectorAll('.portix-formula[data-latex]');
        if (!formulas.length) { return; }

        var katexBase = 'https://cdn.jsdelivr.net/npm/katex@0.18.9/dist/';

        if (!document.querySelector('link[data-portix-public-katex]')) {
            var style = document.createElement('link');
            style.rel = 'stylesheet';
            style.href = katexBase + 'katex.min.css';
            style.integrity = 'sha384-lPx0C4zIUZLpveABMwOFcFeGZwsvKBJfhJ85FN1PYOV7xApBcFMhcAEMVKF8loOI';
            style.crossOrigin = 'anonymous';
            style.setAttribute('data-portix-public-katex', '1');
            document.head.appendChild(style);
        }

        function render() {
            if (!window.katex) { return; }
            formulas.forEach(function (formula) {
                var latex = formula.getAttribute('data-latex') || formula.textContent || '';
                window.katex.render(latex, formula, {
                    throwOnError: false,
                    displayMode: formula.getAttribute('data-display') === '1'
                });
            });
        }

        if (window.katex) {
            render();
            return;
        }

        if (document.querySelector('script[data-portix-public-katex]')) { return; }
        var script = document.createElement('script');
        script.src = katexBase + 'katex.min.js';
        script.integrity = 'sha384-19KE2cFb3U+RUWmyhBz7aLOGDG8WrRC6hE3oY/HTZZlAAVWYTdmvLC//+TIV3zUx';
        script.crossOrigin = 'anonymous';
        script.setAttribute('data-portix-public-katex', '1');
        script.addEventListener('load', render, { once: true });
        document.head.appendChild(script);
    }

    document.querySelectorAll('[data-copy-diagnostics]').forEach(function (button) {
        button.addEventListener('click', function () {
            var selector = button.getAttribute('data-copy-diagnostics') || '';
            var field = selector ? document.querySelector(selector) : null;
            var status = document.querySelector('[data-copy-diagnostics-status]');
            if (!field) { return; }
            var value = field.value || field.textContent || '';

            function success() {
                if (status) { status.textContent = document.body.getAttribute('data-diagnostics-copied') || 'Copied.'; }
            }
            function fallback() {
                field.focus();
                field.select();
                try {
                    if (document.execCommand('copy')) { success(); }
                } catch (error) {
                    if (status) { status.textContent = document.body.getAttribute('data-diagnostics-copy-failed') || 'Copy failed.'; }
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(success, fallback);
            } else {
                fallback();
            }
        });
    });

    function renderPortixVideos() {
        document.querySelectorAll('.portix-video[data-provider][data-video-id]').forEach(function (video) {
            if (video.querySelector('iframe')) { return; }
            var provider = (video.getAttribute('data-provider') || '').toLowerCase();
            var id = video.getAttribute('data-video-id') || '';
            var src = '';

            if (provider === 'youtube' && /^[A-Za-z0-9_-]{11}$/.test(id)) {
                src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id);
            } else if (provider === 'vimeo' && /^[0-9]{5,12}$/.test(id)) {
                src = 'https://player.vimeo.com/video/' + encodeURIComponent(id);
            } else if (provider === 'dailymotion' && /^[A-Za-z0-9]{5,16}$/.test(id)) {
                src = 'https://www.dailymotion.com/embed/video/' + encodeURIComponent(id);
            }

            if (!src) {
                video.remove();
                return;
            }

            var frame = document.createElement('iframe');
            frame.src = src;
            frame.title = video.getAttribute('data-title') || 'Embedded video';
            frame.loading = 'lazy';
            frame.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
            frame.allowFullscreen = true;
            frame.referrerPolicy = 'strict-origin-when-cross-origin';
            video.appendChild(frame);
        });
    }


    renderPortixFormulas();
    renderPortixVideos();

}());
