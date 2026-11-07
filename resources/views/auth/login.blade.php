<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Masuk | RailSafe</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/railsafe.css') }}">
<script src="{{ asset('js/login.js') }}" defer></script>
</head>
<body class="login-page">
<main class="login-shell">
  <section class="login-intro" aria-labelledby="login-intro-title">
    <div class="login-brand">
      <i aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3l-2 18M18 3l2 18M5 8h14M4.5 14h15"/></svg></i>
      <span>RailSafe</span>
    </div>
    <div class="login-intro-copy">
      <p class="login-kicker">Sistem pemantauan perlintasan</p>
      <h1 id="login-intro-title">Masuk ke pusat<br><span>kendali.</span></h1>
      <p>Pantau dan kelola perlintasan kereta api dengan lebih aman, cepat, dan efisien.</p>
    </div>

    <ul class="login-features" aria-label="Keunggulan RailSafe">
      <li>
        <i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 5.5 6v5.4c0 4.1 2.8 7.9 6.5 9.6 3.7-1.7 6.5-5.5 6.5-9.6V6L12 3Z"/><path d="m9 12 2 2 4-4"/></svg></i>
        <span>Keamanan Terjamin</span>
      </li>
      <li>
        <i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m13 2-8 12h6l-1 8 8-12h-6l1-8Z"/></svg></i>
        <span>Monitoring Real-Time</span>
      </li>
      <li>
        <i aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 19V5M4 19h16"/><path d="m7 15 4-4 3 2 5-6"/></svg></i>
        <span>Data Akurat</span>
      </li>
    </ul>

    <figure class="login-scene" aria-hidden="true">
      <!-- <img class="login-railway-art" src="{{ asset('images/trainupdate.png') }}" alt=""> -->
    </figure>
    <p class="login-intro-note">RailSafe © 2025</p>
  </section>

  <section class="login-panel" aria-labelledby="login-title">
    <div class="login-card">
      <div class="login-card-head">
        <p class="login-kicker">Akses Khusus</p>
        <h2 id="login-title">Selamat datang kembali!</h2>
        <p>Masuk untuk melanjutkan ke sistem.</p>
      </div>
      <form method="POST" action="{{ route('login') }}" data-login-form>
        @csrf
        @if($errors->any())
          <div class="alert err" role="alert">{{ $errors->first() }}</div>
        @endif
        <div class="login-field">
          <label for="email">Email</label>
          <div class="login-input-wrap">
            <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="nama@contoh.id">
          </div>
        </div>
        <div class="login-field">
          <label for="password">Kata Sandi</label>
          <div class="login-input-wrap">
            <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
            <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi">
            <button class="password-toggle" type="button" aria-label="Tampilkan kata sandi" aria-pressed="false" data-password-toggle>
              <svg aria-hidden="true" viewBox="0 0 24 24" data-eye-open><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
              <svg aria-hidden="true" viewBox="0 0 24 24" data-eye-closed hidden><path d="m3 3 18 18M10.6 6.2A10.5 10.5 0 0 1 12 6c6.5 0 10 6 10 6a18.2 18.2 0 0 1-3.1 3.7M6.4 6.4A18.5 18.5 0 0 0 2 12s3.5 6 10 6a10.8 10.8 0 0 0 3.1-.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
          </div>
        </div>
        <button class="btn login-submit" type="submit" data-login-submit>
          <span data-login-label>Masuk ke Dashboard</span>
          <span class="login-spinner" aria-hidden="true"></span>
        </button>
      </form>
      <div class="login-card-divider"><span></span><small>atau</small><span></span></div>
      <p class="login-help"><svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Akses dengan akun terdaftar</p>
    </div>
  </section>
</main>
</body>
</html>
