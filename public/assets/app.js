(() => {
  document.querySelectorAll('table').forEach((table) => {
    if (table.parentElement && table.parentElement.classList.contains('table-wrap')) return;
    const wrap = document.createElement('div');
    wrap.className = 'table-wrap';
    table.parentNode.insertBefore(wrap, table);
    wrap.appendChild(table);
  });
  document.documentElement.style.setProperty('overflow-x', 'clip');

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-copy]');
    if (!btn) return;
    const sel = btn.getAttribute('data-copy');
    const el = sel ? document.querySelector(sel) : null;
    const text = (el?.textContent || '').trim();
    if (!text) return;
    navigator.clipboard?.writeText(text).then(() => {
      const prev = btn.textContent;
      btn.textContent = 'Copiado';
      setTimeout(() => { btn.textContent = prev; }, 1500);
    }).catch(() => {});
  });
})();
