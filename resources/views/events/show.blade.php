@extends('layouts.app')
@section('title', $e->event_id)

@section('content')
@php $k = $e->kontrol; @endphp
<div class="top"><h1>{{ $e->event_id }}</h1><a class="btn ghost" href="{{ route('events.index') }}">Kembali</a></div>

<section class="g g3">
  <div class="card">
    <div class="ch"><h2>Detail kejadian</h2></div>
    <div class="item"><div><b>Pos</b><small>{{ $e->crossing->code }} · {{ $e->crossing->name }}</small></div></div>
    <div class="item"><div><b>Waktu deteksi</b><small>{{ $e->detected_at->format('d M Y H:i:s') }} WIB</small></div></div>
    <div class="item"><div><b>Keyakinan TinyML</b><small>{{ $e->confidence !== null ? $e->confidence.'%' : '-' }}</small></div></div>
    <div class="item"><div><b>Pemicu penutupan</b><small>{{ $k ? ($k->control_mode === 'AUTO' ? 'Otomatis (fail-safe)' : 'Manual (petugas)') : 'Menunggu respons' }}</small></div></div>
    <div class="item"><div><b>Respons petugas</b><small>{{ $k ? ($k->guard_response === 'CONFIRMED' ? 'Menekan tombol'.($k->response_time_ms !== null ? ' setelah '.number_format($k->response_time_ms / 1000, 1).' dtk' : '') : 'Tidak ada respons dalam batas waktu') : '-' }}</small></div></div>
    <div class="item"><div><b>Pemeriksaan rintangan</b><small>{{ $k && $k->obstacle_checked ? ($k->obstacle_detected ? 'Rintangan terdeteksi, palang ditahan, alarm darurat' : 'Area rel bebas') : 'Tidak dijalankan (mode manual)' }}</small></div></div>
    <div class="item"><div><b>Status palang</b><small>{{ $k->gate_text ?? 'Menunggu' }}</small></div></div>
    <p class="muted" style="font-size:12px;margin-top:10px">Tidak ada respons tidak otomatis berarti kelalaian. Gunakan catatan untuk menambahkan konteks evaluasi.</p>
  </div>

  <div class="card">
    <div class="ch"><h2>Catatan evaluasi</h2></div>
    @forelse($e->notes as $n)
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
