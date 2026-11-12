/* P3 mobile header enhancement, independent of the active Six Cordes theme files. */
(function () {
  'use strict';

  function init() {
    var body = document.body;
    if (!body || !body.classList.contains('theme-six-cordes-clair')) return;

    var header = document.querySelector('.site-header.six-cordes-masthead');
    var actions = header && header.querySelector('.site-header-actions');
    var search = header && header.querySelector('.site-header-search');
    var nav = document.getElementById('main-navigation');
    var menuButton = header && header.querySelector('.nav-toggle');
    if (!header || !actions) return;

    var compact = window.matchMedia(
      '(max-width: 640px), (max-width: 900px) and (max-height: 500px)'
    );
    var button = null;
    var lastNavigationOpen = false;
    var lastSearchOpen = false;

    function isCompact() { return compact.matches; }
    function setButtonOpen(state) {
      if (button) button.setAttribute('aria-expanded', state ? 'true' : 'false');
    }
    function closeSearch(restoreFocus) {
      if (!lastSearchOpen && !body.classList.contains('p3-mobile-search-open')) return;
      lastSearchOpen = false;
      body.classList.remove('p3-mobile-search-open');
      setButtonOpen(false);
      if (restoreFocus && button && isCompact()) button.focus();
    }
    function openSearch() {
      if (!isCompact() || !search || !button) return;
      if (nav && nav.classList.contains('is-open') && menuButton) {
        // Let the existing theme own its navigation and submenu state.
        menuButton.click();
      }
      lastSearchOpen = true;
      body.classList.add('p3-mobile-search-open');
      setButtonOpen(true);
      var input = search.querySelector('input:not([type="hidden"]), select');
      if (input) input.focus({ preventScroll: true });
    }

    if (search && search.querySelector('form')) {
      search.id = search.id || 'p3-mobile-search-panel';
      button = document.createElement('button');
      button.type = 'button';
      button.className = 'p3-mobile-search-toggle';
      button.setAttribute('aria-label', 'Rechercher sur le site');
      button.setAttribute('aria-controls', search.id);
      button.setAttribute('aria-expanded', 'false');
      button.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        + '<circle cx="10.5" cy="10.5" r="6.5"></circle><path d="M15.5 15.5L21 21"></path></svg>';
      actions.insertBefore(button, menuButton || actions.firstChild);
      button.addEventListener('click', function () {
        if (!isCompact()) return;
        if (body.classList.contains('p3-mobile-search-open')) closeSearch(true);
        else openSearch();
      });
      document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && body.classList.contains('p3-mobile-search-open')) {
          event.preventDefault();
          closeSearch(true);
        }
      });
      document.addEventListener('click', function (event) {
        if (body.classList.contains('p3-mobile-search-open')
            && !search.contains(event.target) && !button.contains(event.target)) {
          closeSearch(false);
        }
      });
    }

    function syncNavigation() {
      var opened = isCompact() && !!(nav && nav.classList.contains('is-open'));
      if (opened) closeSearch(false);
      body.classList.toggle('p3-mobile-nav-open', opened);
      document.documentElement.classList.toggle('p3-mobile-nav-open', opened);
      if (opened) {
        document.documentElement.style.setProperty(
          '--p3-mobile-header-height', Math.ceil(header.getBoundingClientRect().height) + 'px'
        );
      }
      if (opened && !lastNavigationOpen && nav) {
        var first = nav.querySelector('a[href], button:not([disabled])');
        if (first) first.focus({ preventScroll: true });
      }
      lastNavigationOpen = opened;
    }
    if (nav && window.MutationObserver) {
      var observer = new MutationObserver(syncNavigation);
      observer.observe(nav, { attributes: true, attributeFilter: ['class'] });
    }
    if (menuButton) menuButton.addEventListener('click', function () {
      closeSearch(false);
      window.requestAnimationFrame(syncNavigation);
    });
    function onViewportChange() {
      closeSearch(false);
      syncNavigation();
    }
    if (compact.addEventListener) compact.addEventListener('change', onViewportChange);
    else if (compact.addListener) compact.addListener(onViewportChange);
    window.addEventListener('resize', function () {
      if (lastNavigationOpen) {
        document.documentElement.style.setProperty(
          '--p3-mobile-header-height', Math.ceil(header.getBoundingClientRect().height) + 'px'
        );
      }
    }, { passive: true });
    syncNavigation();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
