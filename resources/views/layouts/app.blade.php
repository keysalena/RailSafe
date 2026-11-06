<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') · RailSafe</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/railsafe.css') }}">
<script>try{var t=localStorage.getItem('rs-theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
</head>
<body>
<div class="app">
<aside class="side">
  <div class="brand"><i><svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3l-2 18M18 3l2 18M5 8h14M4.5 14h15"/></svg></i>RailSafe</div>
  <nav aria-label="Menu utama">
    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Dashboard</a>
    <a href="{{ route('events.index') }}" class="{{ request()->routeIs('events.*') ? 'on' : '' }}"><svg viewBox="0 0 24 24"><path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/></svg>Riwayat kejadian</a>
    @if(auth()->user()->isSuperadmin())
      <h6>Khusus superadmin</h6>
      <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'on' : '' }}"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.5-3.5 3-5.5 6.5-5.5s6 2 6.5 5.5M17 6a3 3 0 010 6M18.5 14.7c1.8.6 3 2.3 3.3 5.3"/></svg>Kelola akun</a>
      <a href="{{ route('admin.crossings.index') }}" class="{{ request()->routeIs('admin.crossings.*') ? 'on' : '' }}"><svg viewBox="0 0 24 24"><path d="M12 21s7-6 7-11a7 7 0 10-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>Pos dan perangkat</a>
      <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'on' : '' }}"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></svg>Parameter sistem</a>
      <a href="{{ route('admin.audit.index') }}" class="{{ request()->routeIs('admin.audit.*') ? 'on' : '' }}"><svg viewBox="0 0 24 24"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 12h6M10 16h6"/></svg>Audit log</a>
    @endif
  </nav>
</aside>

<main class="main">
  @if(session('status'))<div class="alert ok">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert err">@foreach($errors->all() as $msg)<div>{{ $msg }}</div>@endforeach</div>@endif
  @yield('content')
</main>

<aside class="rp">
  <div class="me">
    <div class="av">{{ auth()->user()->initials() }}</div>
    <div><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->isSuperadmin() ? 'Superadmin' : 'Operator' }}</small></div>
    <div style="margin-left:auto;display:flex;gap:8px">
      <button class="sq" id="theme" type="button" aria-label="Ganti tema terang atau gelap"><svg viewBox="0 0 24 24"><path d="M21 13A9 9 0 1111 3a7 7 0 0010 10z"/></svg></button>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="sq" type="submit" aria-label="Keluar"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></button></form>
    </div>
  </div>
  <div class="cal"><h3 id="cm"></h3><div class="cg" id="cg"></div></div>
  @yield('right')
</aside>
</div>
<script>
document.getElementById('theme').onclick=function(){var r=document.documentElement,d=r.dataset.theme?r.dataset.theme==='dark':matchMedia('(prefers-color-scheme:dark)').matches;r.dataset.theme=d?'light':'dark';try{localStorage.setItem('rs-theme',r.dataset.theme)}catch(e){}};
(function(){var n=new Date(),y=n.getFullYear(),m=n.getMonth();
document.getElementById('cm').textContent=n.toLocaleDateString('id-ID',{month:'long',year:'numeric'});
var first=(new Date(y,m,1).getDay()+6)%7,days=new Date(y,m+1,0).getDate(),prev=new Date(y,m,0).getDate();
var h=['Sen','Sel','Rab','Kam','Jum','Sab','Min'].map(function(d){return '<span class="h">'+d+'</span>'}).join('');
for(var i=first-1;i>=0;i--)h+='<span class="o">'+(prev-i)+'</span>';
for(var d=1;d<=days;d++)h+='<span class="'+(d===n.getDate()?'today':'')+'">'+d+'</span>';
document.getElementById('cg').innerHTML=h})();
</script>
@stack('scripts')
</body>
</html>
