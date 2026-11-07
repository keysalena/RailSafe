document.addEventListener('DOMContentLoaded', () => {
  const password = document.getElementById('password');
  const toggle = document.querySelector('[data-password-toggle]');
  const form = document.querySelector('[data-login-form]');
  const submit = document.querySelector('[data-login-submit]');

  if (password && toggle) {
    toggle.addEventListener('click', () => {
      const isVisible = password.type === 'text';
      password.type = isVisible ? 'password' : 'text';
      toggle.setAttribute('aria-pressed', String(!isVisible));
      toggle.setAttribute('aria-label', isVisible ? 'Tampilkan kata sandi' : 'Sembunyikan kata sandi');
      toggle.querySelector('[data-eye-open]').hidden = !isVisible;
      toggle.querySelector('[data-eye-closed]').hidden = isVisible;
    });
  }

  if (form && submit) {
    form.addEventListener('submit', () => {
      if (!form.checkValidity()) return;
      submit.disabled = true;
      submit.classList.add('is-loading');
      submit.setAttribute('aria-busy', 'true');
      submit.querySelector('[data-login-label]').textContent = 'Memproses...';
    });
  }
});
