/* Display-only enhancement; original editorial links and media stay intact. */
(function () {
 'use strict';
 var mobile = window.matchMedia('(max-width: 900px), (hover: none) and (pointer: coarse)');
 function listen(query, fn) {
  if (query.addEventListener) query.addEventListener('change', fn);
  else if (query.addListener) query.addListener(fn);
 }
 // The shared engine may use a narrower breakpoint: keep the same navigation
 // interaction on wide touch screens without running two click handlers.
 var toggle = document.querySelector('.nav-toggle');
 var nav = document.getElementById('main-navigation');
 if (toggle && nav) {
  toggle.addEventListener('click', function (event) {
   if (!mobile.matches) return;
   event.preventDefault(); event.stopImmediatePropagation();
   var opened = nav.classList.toggle('is-open');
   toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
  }, true);
  function closeNavigation(restoreFocus) {
   nav.classList.remove('is-open');
   toggle.setAttribute('aria-expanded', 'false');
   if (nav) nav.querySelectorAll('.is-submenu-open').forEach(function (e) { e.classList.remove('is-submenu-open'); });
   if (nav) nav.querySelectorAll('[data-submenu-toggle]').forEach(function (e) { e.setAttribute('aria-expanded','false'); });
   if (restoreFocus) toggle.focus();
  }
  document.addEventListener('keydown', function (event) {
   if (event.key === 'Escape' && nav.classList.contains('is-open') && !nav.querySelector('.is-submenu-open')) closeNavigation(true);
  });
  document.addEventListener('click', function (event) {
   if (nav.classList.contains('is-open') && !nav.contains(event.target) && !toggle.contains(event.target)) closeNavigation(false);
  });
  listen(mobile, function () { if (!mobile.matches) closeNavigation(false); });
 }
 // Menus de rubriques : clic sur le titre = navigation ; clic sur la flèche = déploiement.
 // Garde une seule branche ouverte par niveau et protège contre les gestionnaires globaux P3.
 if (nav) {
  function childrenMenu(parent) {
   return Array.prototype.find.call(parent.children, function (child) {
    return child.classList && child.classList.contains('navigation-submenu');
   });
  }
  function collapse(branch) {
   branch.classList.remove('is-submenu-open');
   var row = Array.prototype.find.call(branch.children, function (child) {
    return child.classList && child.classList.contains('navigation-link-row');
   });
   var button = row && row.querySelector('[data-submenu-toggle]');
   if (button) button.setAttribute('aria-expanded', 'false');
   var nested = childrenMenu(branch);
   if (nested) nested.querySelectorAll('.has-submenu').forEach(collapse);
  }
  function closeAllSubmenus() {
   nav.querySelectorAll('.has-submenu').forEach(collapse);
  }
  nav.addEventListener('click', function (event) {
   var button = event.target.closest('[data-submenu-toggle]');
   if (!button || !nav.contains(button)) return;
   var branch = button.closest('.has-submenu');
   if (!branch) return;
   var isOpen = branch.classList.contains('is-submenu-open');
   var siblings = branch.parentElement;
   if (siblings) Array.prototype.forEach.call(siblings.children, function (other) {
    if (other !== branch && other.classList && other.classList.contains('has-submenu')) collapse(other);
   });
   if (isOpen) collapse(branch);
   else {
    branch.classList.add('is-submenu-open');
    button.setAttribute('aria-expanded', 'true');
   }
   event.preventDefault();
   event.stopImmediatePropagation();
  }, true);
  document.addEventListener('click', function (event) {
   if (!nav.contains(event.target)) closeAllSubmenus();
  });
  document.addEventListener('keydown', function (event) {
   if (event.key !== 'Escape') return;
   var opened = Array.prototype.filter.call(nav.querySelectorAll('.is-submenu-open'), function (e) {
    return e.classList.contains('has-submenu');
   });
   if (!opened.length) return;
   var deepest = opened[opened.length - 1];
   var button = deepest.querySelector('.navigation-link-row > [data-submenu-toggle]');
   collapse(deepest);
   if (button && (!mobile.matches || !nav.classList.contains('is-open'))) button.focus();
   event.preventDefault();
  });
  listen(mobile, closeAllSubmenus);
 }
 var root = document.querySelector('.article-reading');
 if (!root) return;
 if (mobile.matches) {
  root.querySelectorAll('.article-heading-toc details, .article-series details').forEach(function (d) { d.open = false; });
 }
 root.querySelectorAll('.news-rich-text table').forEach(function (table) {
  if (table.closest('.article-table-scroll')) return;
  var wrap = document.createElement('div');
  wrap.className = 'article-table-scroll'; wrap.tabIndex = 0;
  wrap.setAttribute('role', 'region');
  wrap.setAttribute('aria-label', table.caption && table.caption.textContent.trim() || 'Tableau — défilement horizontal');
  table.parentNode.insertBefore(wrap, table); wrap.appendChild(table);
 });
 var nativeDialog = !!(window.HTMLDialogElement && window.HTMLDialogElement.prototype.showModal);
 var panel, overlay, trigger, shown = false, previousOverflow;
 function restore() {
  if (!shown) return;
  shown = false;
  document.documentElement.style.overflow = previousOverflow;
  if (trigger) trigger.focus({preventScroll: true});
 }
 function close() {
  if (!shown) return;
  if (nativeDialog) { panel.close(); restore(); }
  else { overlay.hidden = true; restore(); }
 }
 function createPanel() {
  panel = document.createElement(nativeDialog ? 'dialog' : 'div');
  panel.className = 'reading-image-dialog';
  panel.setAttribute('aria-label', 'Image agrandie');
  if (!nativeDialog) {
   panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true');
   overlay = document.createElement('div'); overlay.className = 'reading-image-overlay'; overlay.hidden = true;
   overlay.appendChild(panel); document.body.appendChild(overlay);
   overlay.addEventListener('click', function (event) { if (event.target === overlay) close(); });
  } else {
   document.body.appendChild(panel);
   panel.addEventListener('close', function () { if (!panel.open) restore(); });
   panel.addEventListener('cancel', function (event) { event.preventDefault(); close(); });
   panel.addEventListener('click', function (event) { if (event.target === panel) close(); });
  }
  var button = document.createElement('button');
  button.type = 'button'; button.textContent = 'Fermer'; button.autofocus = true;
  button.addEventListener('click', close);
  panel.appendChild(button); panel.appendChild(document.createElement('img'));
  panel.appendChild(document.createElement('p'));
  var link = document.createElement('a');
  link.textContent = 'Ouvrir l’image'; link.target = '_blank'; link.rel = 'noopener'; panel.appendChild(link);
  document.addEventListener('keydown', function (event) {
   if (!shown) return;
   if (event.key === 'Escape') { event.preventDefault(); close(); }
   if (event.key === 'Tab') {
    var first = panel.querySelector('button'), last = panel.querySelector('a');
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
   }
  });
  document.addEventListener('focusin', function (event) {
   if (shown && !nativeDialog && !panel.contains(event.target)) panel.querySelector('button').focus();
  });
 }
 function open(img) {
  if (!mobile.matches) return;
  if (!panel) createPanel();
  trigger = img.closest('a') || img;
  var link = img.closest('a');
  var src = link ? link.href : (img.currentSrc || img.src);
  var enlarged = panel.querySelector('img'); enlarged.src = src; enlarged.alt = img.alt;
  var figure = img.closest('figure'), caption = figure && figure.querySelector('figcaption');
  panel.querySelector('p').textContent = caption ? caption.textContent : img.alt;
  panel.querySelector('a').href = src;
  previousOverflow = document.documentElement.style.overflow;
  shown = true; document.documentElement.style.overflow = 'hidden';
  if (nativeDialog) panel.showModal(); else overlay.hidden = false;
  panel.querySelector('button').focus({preventScroll: true});
 }
 listen(mobile, function () { if (!mobile.matches) close(); });
 root.querySelectorAll('.news-rich-text img').forEach(function (img) {
  var link = img.closest('a');
  if (link && (link.hasAttribute('download') || !/\.(?:avif|gif|jpe?g|png|svg|webp)(?:[?#]|$)/i.test(link.href))) return;
  img.classList.add('reading-zoomable');
  if (!link) {
   var saved = {};
   ['tabindex', 'role', 'aria-label'].forEach(function (name) { saved[name] = img.getAttribute(name); });
   var sync = function () {
    if (mobile.matches) { img.tabIndex = 0; img.setAttribute('role', 'button'); img.setAttribute('aria-label', (img.alt || 'Image') + ' — agrandir'); }
    else Object.keys(saved).forEach(function (name) { if (saved[name] === null) img.removeAttribute(name); else img.setAttribute(name, saved[name]); });
   };
   sync(); listen(mobile, sync);
   img.addEventListener('keydown', function (event) {
    if (mobile.matches && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); open(img); }
   });
  }
  img.addEventListener('click', function (event) {
   if (!mobile.matches || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
   event.preventDefault(); open(img);
  });
 });
})();
