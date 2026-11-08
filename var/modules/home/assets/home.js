(() => {
  'use strict';
  const root = document.querySelector('[data-home-page]');
  if (!root || !root.dataset.homeTicket || !window.crypto?.getRandomValues || navigator.doNotTrack === '1') return;
  const page = Array.from(crypto.getRandomValues(new Uint8Array(16)), b => b.toString(16).padStart(2, '0')).join('');
  const endpoint = new URL(root.dataset.homeEndpoint, document.baseURI);
  if (endpoint.origin !== location.origin) return;
  let pending = [{kind: 'page'}];
  let timer;
  const seen = new Set();
  function flush() {
    clearTimeout(timer);
    if (!pending.length) return;
    const events = pending.splice(0, 61);
    fetch(endpoint, {method: 'POST', credentials: 'same-origin', keepalive: true,
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ticket: root.dataset.homeTicket, page, events})}).catch(() => {});
  }
  function push(kind, slot) {
    const key = `${kind}:${slot}`;
    if (seen.has(key)) return;
    seen.add(key);
    pending.push({kind, slot});
    if (pending.length >= 60 || kind === 'click') flush();
    else { clearTimeout(timer); timer = setTimeout(flush, 400); }
  }
  function clicked(event) {
    const link = event.target.closest('a');
    const card = link?.closest('[data-home-slot]');
    // Series-start links are useful navigation, but do not represent a click on this article.
    if (card && root.contains(card) && !link.closest('.home-series')) push('click', card.dataset.homeSlot);
  }
  root.addEventListener('click', clicked);
  root.addEventListener('auxclick', event => { if (event.button === 1) clicked(event); });
  const waits = new Map();
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      const card = entry.target;
      clearTimeout(waits.get(card));
      if (entry.isIntersecting && entry.intersectionRatio >= 0.5 && document.visibilityState === 'visible') {
        waits.set(card, setTimeout(() => {
          if (document.visibilityState === 'visible') push('visible', card.dataset.homeSlot);
        }, 800));
      }
    }), {threshold: [0, 0.5]});
    root.querySelectorAll('[data-home-slot]').forEach(card => observer.observe(card));
    document.addEventListener('visibilitychange', () => {
      waits.forEach(clearTimeout); waits.clear();
      if (document.visibilityState === 'hidden') flush();
      else root.querySelectorAll('[data-home-slot]').forEach(card => { observer.unobserve(card); observer.observe(card); });
    });
  }
  window.addEventListener('pagehide', flush);
  timer = setTimeout(flush, 400);
})();
