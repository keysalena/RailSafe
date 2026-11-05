<aside class="sidebar">
    <a href="{{ route('dashboard') }}" class="logo"><i></i> RailSafe</a>

    <nav>
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'on' : '' }}">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 3h8v8H3zM13 3h8v8h-8zM3 13h8v8H3zM13 13h8v8h-8z"/></svg>
            Dashboard
        </a>

        {{-- Halaman 2 (belum dibuat). Ganti href & hapus class "off" kalau route-nya sudah ada --}}
        <a href="#" class="off" title="Segera hadir">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16M4 12h16M4 19h10"/></svg>
            Rekap &amp; Histori <span class="badge">Soon</span>
        </a>
    </nav>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            Keluar
        </button>
    </form>
</aside>
