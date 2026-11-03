(() => {
 const normalize = text => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();
 document.querySelectorAll('[data-article-picker]').forEach(picker => {
  const list = picker.querySelector('[data-series-order]');
  const search = picker.querySelector('[data-picker-search]');
  const category = picker.querySelector('[data-picker-category-filter]');
  const status = picker.querySelector('[data-picker-count]');
  const more = picker.querySelector('[data-picker-more]');
  const rows = [...list.querySelectorAll('[data-picker-row]')];
  const rank = new Map(rows.map((row, i) => [row, i]));
  const sourceCategory = picker.closest('form').querySelector('[name="category_id"]');
  const checked = row => row.querySelector('input[type="checkbox"]').checked;
  const categories = [...new Set(rows.map(row => row.dataset.pickerCategory))].sort((a, b) => a.localeCompare(b));
  categories.forEach(name => { const option = document.createElement('option'); option.value = name; option.textContent = name; category.append(option); });
  let limit = 6;
  function refresh() {
   const words = normalize(search.value).trim().split(/\s+/).filter(Boolean);
   const selected = rows.filter(checked).length;
   let total = 0, shown = 0;
   [...list.children].forEach(row => {
    const isSelected = checked(row); row.dataset.selected = String(isSelected);
    const matches = words.every(word => normalize(row.dataset.pickerTitle + ' ' + row.dataset.pickerCategory).includes(word)) && (!category.value || category.value === row.dataset.pickerCategory);
    if (isSelected) { row.hidden = false; return; }
    if (matches) total++;
    row.hidden = !matches || shown >= limit;
    if (!row.hidden) shown++;
   });
   status.textContent = status.dataset.format.replace('{selected}', selected).replace('{shown}', shown).replace('{total}', total);
   more.hidden = shown >= total;
  }
  function arrange() {
   const current = sourceCategory ? sourceCategory.value : '';
   [...list.children].sort((a,b) => {
    if (checked(a) !== checked(b)) return checked(a) ? -1 : 1;
    if (checked(a)) return 0;
    const sameA = current !== '' && a.dataset.pickerCategoryId === current;
    const sameB = current !== '' && b.dataset.pickerCategoryId === current;
    return Number(sameB)-Number(sameA) || a.dataset.pickerTitle.localeCompare(b.dataset.pickerTitle, undefined, {numeric:true,sensitivity:'base'}) || rank.get(a)-rank.get(b);
   }).forEach(row => list.append(row));
  }
  function renumber() { [...list.children].forEach((row, i) => { row.querySelector('input[type="number"]').value = i; }); }
  picker.querySelector('[data-picker-controls]').hidden = false;
  picker.dataset.enhanced = 'true';
  search.addEventListener('input', () => { limit = 6; refresh(); });
  category.addEventListener('change', () => { limit = 6; refresh(); });
  more.addEventListener('click', () => { limit += 12; refresh(); });
  list.addEventListener('change', event => {
   if (!event.target.matches('input[type="checkbox"]')) return;
   // Preserve the author's selected order; only suggestions return to their initial rank.
   const active = document.activeElement;
   arrange();
   renumber(); refresh();
   if (active && picker.contains(active)) active.focus();
  });
  if (sourceCategory) sourceCategory.addEventListener('change', () => { arrange(); renumber(); refresh(); });
  arrange(); refresh();
 });
})();
