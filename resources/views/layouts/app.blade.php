<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · RailSafe</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/railsafe.css') }}">
<link rel="icon" href="{{ asset('images/favicon.png') }}" type="image/png">
<script>try{var t=localStorage.getItem('rs-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
<script>try{if(localStorage.getItem('rs-side-min')==='1')document.documentElement.classList.add('preload-side-min')}catch(e){}</script>
</head>
<body>
<div class="app">
<aside class="side" id="side">
  <div class="side-head">
    <div class="brand">
  <i>
    <img src="{{ asset('images/favicon.png') }}" alt="RailSafe Logo" style="width: 34px; height: 34px; object-fit: contain;">
  </i>
    <span class="brand-text">RailSafe</span>
    </div>
    <button class="side-toggle" id="side-toggle" type="button" aria-label="Kecilkan menu" aria-expanded="true" title="Kecilkan/Perbesar menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
    </button>
  </div>
  <nav aria-label="Menu utama">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}" title="Dashboard"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Dashboard</span></a>
    <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'on' : '' }}" title="Riwayat kejadian"><svg viewBox="0 0 24 24"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg><span>Riwayat kejadian</span></a>
    @if(auth()->user()->isSuperadmin())
      <h6 class="nav-heading">Khusus superadmin</h6>
      <hr class="nav-sep" aria-hidden="true">
      <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}" title="Kelola akun"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 3-5.5 6.5-5.5s6 2 6.5 5.5M17 6a3 3 0 010 6M18.5 14.7c1.8.6 3 2.3 3.3 5.3"/></svg><span>Kelola akun</span></a>
      <a href="{{ route('admin.crossings.index') }}" class="{{ request()->routeIs('admin.crossings.*') ? 'on' : '' }}" title="Pos dan perangkat"><svg viewBox="0 0 24 24"><path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg><span>Pos dan perangkat</span></a>
      <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'on' : '' }}" title="Parameter sistem"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></svg><span>Parameter sistem</span></a>
      <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'on' : '' }}" title="Audit log"><svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 12h6M10 16h6"/></svg><span>Audit log</span></a>
    @endif
  </nav>
  <div class="prof">
    <div class="av" title="{{ auth()->user()->name }}">{{ auth()->user()->initials() }}</div>
    <div class="prof-info"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->isSuperadmin() ? 'Superadmin' : 'Operator' }}</small></div>
    <div class="ctrl">
      <button class="sq" id="theme" type="button" aria-label="Ganti tema terang atau gelap" title="Ganti tema"><svg viewBox="0 0 24 24"><path d="M21 13A9 9 0 1111 3a7 7 0 0010 10z"/></svg></button>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="sq" type="submit" aria-label="Keluar" title="Keluar"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></button></form>
    </div>
  </div>
</aside>

<main class="main">
  @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert err">@foreach($errors->all() as $msg)<div>{{ $msg }}</div>@endforeach</div>@endif
  @yield('content')
</main>

</div>
<script>
document.getElementById('theme').onclick=function(){var r=document.documentElement,d=r.dataset.theme?r.dataset.theme==='dark':matchMedia('(prefers-color-scheme:dark)').matches;r.dataset.theme=d?'light':'dark';try{localStorage.setItem('rs-theme',r.dataset.theme)}catch(e){}};
(function(){
  var app=document.querySelector('.app'),btn=document.getElementById('side-toggle');
  var isMin=false;try{isMin=localStorage.getItem('rs-side-min')==='1'}catch(e){}
  if(isMin&&app){app.classList.add('side-min');if(btn)btn.setAttribute('aria-expanded','false')}
  document.documentElement.classList.remove('preload-side-min');
  if(btn&&app){
    btn.onclick=function(){
      var min=app.classList.toggle('side-min');
      btn.setAttribute('aria-expanded',String(!min));
      try{localStorage.setItem('rs-side-min',min?'1':'0')}catch(e){}
    };
  }
})();
</script>
@stack('scripts')
</body>
</html>