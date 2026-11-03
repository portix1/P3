document.addEventListener('click', event => {
 const link = event.target.closest('[data-series-list]'); if (!link) return;
 const parts = document.getElementById('p3-series-parts'); if (parts) parts.open = true;
});
