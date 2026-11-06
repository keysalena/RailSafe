@extends('layouts.app')
@section('title', 'Riwayat kejadian')

@section('content')
<div class="top"><h1>Riwayat kejadian</h1></div>

<section class="kpis">
  <div class="kpi"><b>{{ $stats['total'] }}</b><span>Total kejadian deteksi</span></div>
  <div class="kpi"><b>{{ $stats['manual'] }}</b><span>Penutupan manual (petugas)</span></div>
  <div class="kpi"><b>{{ $stats['auto'] }}</b><span>Penutupan otomatis (fail-safe)</span></div>
  <div class="kpi blue"><b>{{ $stats['no_response'] }}</b><span>Tanpa respons petugas</span></div>
</section>

<section class="card mb">
  <form method="GET" action="{{ route('events.index') }}" class="f">
    <div><label for="from">Dari tanggal</label><input id="from" type="date" name="from" value="{{ $f['from'] }}"></div>
    <div><label for="to">Sampai tanggal</label><input id="to" type="date" name="to" value="{{ $f['to'] }}"></div>
    <div><label for="mode">Metode penutupan</label>
      <select id="mode" name="mode"><option value="">Semua</option><option value="MANUAL" @selected($f['mode']==='MANUAL')>Manual</option><option value="AUTO" @selected($f['mode']==='AUTO')>Otomatis</option></select></div>
    <div><label for="response">Respons petugas</label>
      <select id="response" name="response"><option value="">Semua</option><option value="CONFIRMED" @selected($f['response']==='CONFIRMED')>Menekan tombol</option><option value="NO_RESPONSE" @selected($f['response']==='NO_RESPONSE')>Tanpa respons</option></select></div>
    @if($crossings->count() > 1)
    <div><label for="crossing">Pos</label>
      <select id="crossing" name="crossing"><option value="">Semua pos</option>@foreach($crossings as $c)<option value="{{ $c->id }}" @selected((int)$f['crossing']===$c->id)>{{ $c->code }}</option>@endforeach</select></div>
    @endif
    <div style="display:flex;gap:8px"><button class="btn" type="submit">Terapkan</button><a class="btn ghost" href="{{ route('events.index') }}">Reset</a></div>
  </form>
</section>

<section class="card">
  <div class="ch"><h2>Tabel log kejadian</h2><a class="btn sm y" href="{{ route('events.export', request()->query()) }}">Ekspor CSV</a></div>
  <p class="muted mb" style="font-size:12px">Satu kereta bisa menghasilkan lebih dari satu deteksi. Angka di sini adalah jumlah kejadian deteksi, bukan jumlah pasti kereta yang melintas.</p>
  <div class="tw"><table>
    <thead><tr><th>Waktu</th><th>ID</th><th>Pemicu</th><th>Probabilitas</th><th>Status palang</th><th>Respons petugas</th><th>Catatan</th></tr></thead>
    <tbody>
    @forelse($events as $e)
      @php $k = $e->kontrol; @endphp
      <tr onclick="location.href='{{ route('events.show', $e->id) }}'">
        <td>{{ $e->detected_at->format('d/m H:i') }}</td>
        <td class="mono"><a href="{{ route('events.show', $e->id) }}">{{ $e->event_id }}</a></td>
        <td>{{ $k ? ($k->control_mode === 'AUTO' ? 'Otomatis' : 'Manual') : '-' }}</td>
        <td>{{ $e->confidence !== null ? $e->confidence.'%' : '-' }}</td>
        <td>@if(! $k)<span class="tag t-mute">Menunggu</span>@else<span class="tag {{ $k->status_label === 'MANUAL_OK' ? 't-ok' : ($k->status_label === 'CRITICAL_OBSTACLE' ? 't-bad' : 't-warn') }}">{{ $k->gate_text }}</span>@endif</td>
        <td>{{ $k ? ($k->guard_response === 'CONFIRMED' ? 'Tombol ditekan'.($k->response_time_ms !== null ? ' ('.number_format($k->response_time_ms / 1000, 1).' dtk)' : '') : 'Tidak ada respons') : '-' }}</td>
        <td>{{ \Illuminate\Support\Str::limit($e->notes->last()->note ?? '', 40) }}</td>
      </tr>
    @empty
      <tr><td colspan="7">Tidak ada kejadian pada filter ini. Perluas rentang tanggal.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <div class="pager">
    @if($events->previousPageUrl())<a class="btn sm ghost" href="{{ $events->previousPageUrl() }}">Sebelumnya</a>@endif
    @if($events->nextPageUrl())<a class="btn sm ghost" href="{{ $events->nextPageUrl() }}">Berikutnya</a>@endif
  </div>
</section>
@endsection
