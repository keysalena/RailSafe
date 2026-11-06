@extends('layouts.app')
@section('title', $e->event_id)

@section('content')
<div class="top"><h1>{{ $e->event_id }}</h1><a class="btn ghost" href="{{ route('events.index') }}">Kembali</a></div>

<section class="g g3">
  <div class="card">
    <div class="ch"><h2>Detail log</h2></div>
    <div class="item"><div><b>Pos</b><small>{{ $e->crossing->code }} · {{ $e->crossing->name }}</small></div></div>
    <div class="item"><div><b>Waktu</b><small>{{ $e->logged_at->format('d M Y H:i:s') }} WIB (waktu server saat log diterima)</small></div></div>
    <div class="item"><div><b>Pemicu</b><small>{{ $e->trigger_type === 'AUTOMATIC' ? 'Otomatis (sinyal TinyML dari Node 1)' : 'Manual (tombol di pos)' }}</small></div></div>
    <div class="item"><div><b>Status palang</b><small>{{ $e->status_text }}</small></div></div>
    <div class="item"><div><b>Probabilitas sensor</b><small>{{ $e->confidence_score !== null ? $e->confidence_score.'%' : '-' }}</small></div></div>
    <div class="item"><div><b>Catatan sistem</b><small>{{ $e->notes ?: '-' }}</small></div></div>
    @if($e->guard_response)
    <div class="item"><div><b>Respons petugas</b><small>{{ $e->guard_response === 'CONFIRMED' ? 'Menekan tombol'.($e->response_time_ms !== null ? ' setelah '.number_format($e->response_time_ms / 1000, 1).' dtk' : '') : 'Tidak ada respons dalam batas waktu' }}</small></div></div>
    @endif
    @if($e->obstacle_detected !== null)
    <div class="item"><div><b>Pemeriksaan rintangan</b><small>{{ $e->obstacle_detected ? 'Rintangan terdeteksi' : 'Area rel bebas' }}</small></div></div>
    @endif
    <div class="item"><div><b>Perangkat</b><small>{{ $e->device->device_code }}</small></div></div>
  </div>

  <div class="card">
    <div class="ch"><h2>Catatan evaluasi</h2></div>
    @forelse($e->evaluations as $n)
      <div class="item"><div><b>{{ $n->user->name }}</b><small>{{ $n->created_at->format('d M Y H:i') }}</small>{{ $n->note }}</div></div>
    @empty
      <p class="muted mb">Belum ada catatan.</p>
    @endforelse
    <form method="POST" action="{{ route('events.note', $e->id) }}" class="f" style="margin-top:12px">
      @csrf
      <div class="full"><label for="note">Tambah catatan</label><textarea id="note" name="note" rows="3" maxlength="500" required></textarea></div>
      <div><button class="btn" type="submit">Simpan catatan</button></div>
    </form>
  </div>
</section>
@endsection
