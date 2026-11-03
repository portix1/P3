/* Display-only layout: move the existing TOC, never duplicate its anchors. */
(function () {
 'use strict';
 document.documentElement.classList.remove('no-js');
 var layout = document.querySelector('.page-layout');
 var context = document.querySelector('.six-cordes-left');
 var search = document.querySelector('.site-header-search');
 if (search && !search.querySelector('form')) search.hidden = true;
 var compact = window.matchMedia('(max-width: 900px), (hover: none) and (pointer: coarse)');
 var toc = document.querySelector('.article-reading .article-heading-toc');
 var placeholder = toc ? document.createComment('Reading contents location') : null;
 var disclosure = toc && toc.querySelector('details');
 var lastCompact = null;
 var openState = {compact: false, desktop: true};
 if (toc) toc.parentNode.insertBefore(placeholder, toc);
 function sync() {
  if (toc && context && placeholder.parentNode) {
   if (lastCompact !== null && disclosure) openState[lastCompact ? 'compact' : 'desktop'] = disclosure.open;
   if (compact.matches) placeholder.parentNode.insertBefore(toc, placeholder.nextSibling);
   else context.insertBefore(toc, context.firstChild);
   if (disclosure) disclosure.open = openState[compact.matches ? 'compact' : 'desktop'];
   lastCompact = compact.matches;
  }
  if (layout && context) layout.classList.toggle('has-context', !!context.querySelector('.article-heading-toc, .six-cordes-category-panel, .side-panel'));
 }
 sync();
 if (compact.addEventListener) compact.addEventListener('change', sync);
 else if (compact.addListener) compact.addListener(sync);
 if (!toc) return;
 var links = Array.prototype.slice.call(toc.querySelectorAll('a[href]'));
 var headings = links.map(function (link) {
  try { var hash = new URL(link.href, document.baseURI).hash; return hash ? document.getElementById(decodeURIComponent(hash.slice(1))) : null; }
  catch (_) { return null; }
 });
 var pending = false;
 function update() {
  pending = false;
  var current = -1;
  headings.forEach(function (heading, index) { if (heading && heading.getBoundingClientRect().top <= 100) current = index; });
  links.forEach(function (link, index) {
   if (index === current) link.setAttribute('aria-current', 'location');
   else link.removeAttribute('aria-current');
  });
 }
 window.addEventListener('scroll', function () { if (!pending) { pending = true; window.requestAnimationFrame(update); } }, {passive: true});
 update();
})();
