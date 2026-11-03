document.querySelectorAll('[data-series-order]').forEach(list => {
 list.addEventListener('click', event => {
  const button = event.target.closest('[data-series-move]'); if (!button) return;
  const row = button.closest('li');
  const available = [...list.children].filter(item => !item.hidden && (!list.closest('[data-article-picker]') || item.querySelector('input[type="checkbox"]').checked));
  const index = available.indexOf(row);
  const other = available[index + (button.dataset.seriesMove === 'up' ? -1 : 1)];
  if (!other) return;
  if (button.dataset.seriesMove === 'up') list.insertBefore(row, other); else list.insertBefore(other, row);
  list.querySelectorAll('input[type="number"]').forEach((input, i) => { input.value = i; });
  button.focus(); list.dispatchEvent(new Event('change', { bubbles: true }));
 });
});
