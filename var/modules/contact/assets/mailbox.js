(() => {
  const form = document.querySelector('[data-contact-mailbox]');
  if (!form) return;
  const all = form.querySelector('[data-contact-all]');
  const boxes = [...form.querySelectorAll('input[name="ids[]"]')];
  const buttons = [...form.querySelectorAll('button[name="contact_admin_action"]')];
  const key = 'p3-contact-scroll:' + location.search;
  const saved = sessionStorage.getItem(key);
  if (saved) { window.scrollTo(0, Number(saved)); sessionStorage.removeItem(key); }
  function update() {
    const selected = boxes.filter(b => b.checked).length;
    all.checked = boxes.length > 0 && selected === boxes.length;
    all.indeterminate = selected > 0 && selected < boxes.length;
    buttons.forEach(b => b.disabled = selected === 0);
  }
  all.addEventListener('change', () => { boxes.forEach(b => b.checked = all.checked); update(); });
  boxes.forEach(b => b.addEventListener('change', update));
  form.addEventListener('submit', e => {
    const prompt = e.submitter?.dataset.contactConfirm;
    if (!boxes.some(b => b.checked) || (prompt && !window.confirm(prompt))) { e.preventDefault(); return; }
    if (prompt) form.querySelector('input[name=confirm_trash]').value = 'yes';
    sessionStorage.setItem(key, String(window.scrollY));
  });
  update();
})();
