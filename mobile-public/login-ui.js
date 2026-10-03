(() => {
  const en = document.documentElement.lang === 'en';
  const password = document.getElementById('password');
  if (password) {
    const toggle = document.createElement('button');
    toggle.type = 'button'; toggle.className = 'password-toggle';
    toggle.innerHTML = '<svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
    const update = () => {
      const shown = password.type === 'text';
      toggle.setAttribute('aria-label', en ? (shown ? 'Hide password' : 'Show password') : (shown ? 'Sembunyikan kata laluan' : 'Tunjuk kata laluan'));
      toggle.setAttribute('aria-pressed', String(shown));
    };
    toggle.setAttribute('aria-controls', 'password');
    toggle.addEventListener('click', () => { password.type = password.type === 'password' ? 'text' : 'password'; update(); });
    password.parentElement.append(toggle); update();
  }
  let pending = false;
  const reset = () => {
    pending = false;
    document.querySelectorAll('[data-pending]').forEach(button => {
      button.innerHTML = button.dataset.pending; delete button.dataset.pending;
      button.removeAttribute('aria-disabled'); button.removeAttribute('aria-busy');
    });
    document.getElementById('login-progress').textContent = '';
    if (password) password.type = 'password';
  };
  window.addEventListener('pageshow', reset);
  document.querySelectorAll('form').forEach(form => form.addEventListener('submit', event => {
    if (pending) { event.preventDefault(); return; }
    const button = event.submitter;
    if (!button) return;
    pending = true;
    // Keep submitter enabled: its action name/value must reach the server.
    button.dataset.pending = button.innerHTML;
    button.setAttribute('aria-disabled', 'true'); button.setAttribute('aria-busy', 'true');
    const label = en ? 'Processing…' : 'Sedang diproses…';
    button.textContent = label;
    document.getElementById('login-progress').textContent = label;
    // Permit retry if browser blocks a navigation or the network stalls.
    window.setTimeout(reset, 15000);
  }));
})();
