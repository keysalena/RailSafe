@extends('layouts.app')
@section('title', 'Parameter sistem')

@section('content')
<div class="top"><h1>Parameter sistem</h1></div>
<section class="card">
  <form method="POST" action="{{ route('admin.settings.update') }}" class="f">
    @csrf @method('PUT')
    <div><label for="ct">Ambang keyakinan TinyML (%)</label><input id="ct" type="number" name="confidence_threshold" min="50" max="99" value="{{ old('confidence_threshold', $s['confidence_threshold']) }}" required><small class="muted">Firmware Node 1 saat ini memakai 0,70 (70%), proposal menyebut 85%. Nilai ini disimpan di server dan bisa diambil perangkat lewat /api/config.</small></div>
    <div><label for="cd">Waktu konfirmasi petugas, mode fail-safe (detik)</label><input id="cd" type="number" name="countdown_seconds" min="5" max="60" value="{{ old('countdown_seconds', $s['countdown_seconds']) }}" required><small class="muted">Alur hitung mundur 15 detik di proposal. Firmware saat ini belum memakainya, jadi nilai ini belum berpengaruh pada alat.</small></div>
    <div><label for="gr">Palang terbuka lagi setelah alert terakhir (detik)</label><input id="gr" type="number" name="gate_reopen_seconds" min="5" max="600" value="{{ old('gate_reopen_seconds', $s['gate_reopen_seconds']) }}" required><small class="muted">Firmware Node 2: 15 detik. Dipakai dashboard untuk memperkirakan kapan palang terbuka lagi.</small></div>
    <div class="full"><button class="btn" type="submit">Simpan parameter</button></div>
  </form>
  <p class="muted" style="font-size:12px;margin-top:14px">Perangkat mengambil nilai ini lewat <span class="mono">GET /api/config</span>. Keputusan kontrol tetap dijalankan lokal oleh Node 2, sehingga palang tetap bekerja walaupun server tidak terjangkau.</p>
</section>
@endsection
