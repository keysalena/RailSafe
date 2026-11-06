@extends('layouts.app')
@section('title', 'Riwayat kejadian')

@section('content')
<div class="top"><h1>Riwayat kejadian</h1></div>

<section class="kpis">
  <div class="kpi"><b>{{ $stats['total'] }}</b><span>Total log palang</span></div>
  <div class="kpi"><b>{{ $stats['auto'] }}</b><span>Penutupan otomatis (sistem)</span></div>
  <div class="kpi"><b>{{ $stats['manual'] }}</b><span>Penutupan manual (petugas)</span></div>
  <div class="kpi blue"><b>{{ $stats['opened'] }}</b><span>Palang dibuka</span></div>
</section>

<section class="card mb">
  <form method="GET" action="{{ route('events.index') }}" class="f">
    <div><label for="from">Dari tanggal</label><input id="from" type="date" name="from" value="{{ $f['from'] }}"></div>
    <div><label for="to">Sampai tanggal</label><input id="to" type="date" name="to" value="{{ $f['to'] }}"></div>
    <div><label for="trigger">Metode penutupan</label>
      <select id="trigger" name="trigger"><option value="">Semua</option><option value="AUTOMATIC" @selected($f['trigger']==='AUTOMATIC')>Otomatis</option><option value="MANUAL" @selected($f['trigger']==='MANUAL')>Manual</option></select></div>
    <div><label for="action">Aksi palang</label>
      <select id="action" name="action"><option value="">Semua</option><option value="CLOSED" @selected($f['action']==='CLOSED')>Ditutup</option><option value="OPENED" @selected($f['action']==='OPENED')>Dibuka</option><option value="HELD" @selected($f['action']==='HELD')>Ditahan</option></select></div>
    @if($crossings->count() > 1)
    <div><label for="crossing">Pos</label>
      <select id="crossing" name="crossing"><option value="">Semua pos</option>@foreach($crossings as $c)<option value="{{ $c->id }}" @selected((int)$f['crossing']===$c->id)>{{ $c->code }}</option>@endforeach</select></div>
    @endif
    <div style="display:flex;gap:8px"><button class="btn" type="submit">Terapkan</button><a class="btn ghost" href="{{ route('events.index') }}">Reset</a></div>
  </form>
</section>

<section class="card">
  <div class="ch"><h2>Tabel log kejadian</h2><a class="btn sm y" href="{{ route('events.export', request()->query()) }}">Ekspor CSV</a></div>
  <div class="tw"><table>
    <thead><tr><th>Waktu</th><th>ID</th><th>Pemicu</th><th>Probabilitas</th><th>Status palang</th><th>Catatan</th></tr></thead>
    <tbody>
    @forelse($events as $e)
      <tr onclick="location.href='{{ route('events.show', $e->id) }}'">
        <td>{{ $e->logged_at->format('d/m H:i') }}</td>
        <td class="mono"><a href="{{ route('events.show', $e->id) }}">{{ $e->event_id }}</a></td>
        <td>{{ $e->trigger_type === 'AUTOMATIC' ? 'Otomatis' : 'Manual' }}</td>
        <td>{{ $e->confidence_score !== null ? $e->confidence_score.'%' : '-' }}</td>
        <td><span class="tag {{ $e->tone }}">{{ $e->status_text }}</span></td>
        <td>{{ \Illuminate\Support\Str::limit($e->notes, 45) }}@if($e->evaluations->count()) <span class="tag t-mute">+{{ $e->evaluations->count() }} evaluasi</span>@endif</td>
      </tr>
    @empty
      <tr><td colspan="6">Tidak ada log pada filter ini. Perluas rentang tanggal.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  <div class="pager">
    @if($events->previousPageUrl())<a class="btn sm ghost" href="{{ $events->previousPageUrl() }}">Sebelumnya</a>@endif
    @if($events->nextPageUrl())<a class="btn sm ghost" href="{{ $events->nextPageUrl() }}">Berikutnya</a>@endif
  </div>
</section>
@endsection
