@extends('layouts.app')
@section('title', 'Pos dan perangkat')

@section('content')
<div class="top"><h1>Pos dan perangkat</h1></div>

@if(session('token'))
<div class="alert ok">
  Token untuk <b>{{ session('token')['code'] }}</b> (hanya ditampilkan sekali, salin sekarang):
  <div class="tokenbox">{{ session('token')['value'] }}</div>
  <small>Tanam di firmware sebagai header <span class="mono">Authorization: Bearer ...</span></small>
</div>
@endif

<section class="card mb">
  <div class="ch"><h2>Perangkat</h2></div>
  <div class="tw"><table>
    <thead><tr><th>Kode</th><th>Pos</th><th>Jenis</th><th>Status</th><th>Terakhir lapor</th><th></th></tr></thead>
    <tbody>
    @forelse($devices as $d)
      @php $st = ['ACTIVE'=>['Aktif','t-ok'],'DISCONNECTED'=>['Terputus','t-bad'],'FAULT'=>['Gangguan','t-warn'],'UNKNOWN'=>['Belum diketahui','t-mute']][$d->status]; @endphp
      <tr style="cursor:default">
        <td class="mono">{{ $d->device_code }}</td><td>{{ $d->crossing->code }}</td>
        <td>{{ $d->type === 'SENSOR_NODE' ? 'Node 1 (sensor)' : 'Node 2 (kontroler pos)' }}</td>
        <td><span class="tag {{ $st[1] }}">{{ $st[0] }}</span></td>
        <td>{{ $d->last_seen_at ? $d->last_seen_at->diffForHumans() : '-' }}</td>
        <td><form method="POST" action="{{ route('admin.devices.token', $d) }}" onsubmit="return confirm('Ganti token? Token lama langsung tidak berlaku.')">@csrf<button class="btn sm ghost" type="submit">Ganti token</button></form></td>
      </tr>
    @empty
      <tr><td colspan="6">Belum ada perangkat.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

<section class="g g3">
  <div class="card">
    <div class="ch"><h2>Tambah pos</h2></div>
    <form method="POST" action="{{ route('admin.crossings.store') }}" class="f">@csrf
      <div><label for="code">Kode pos</label><input id="code" name="code" placeholder="PJL-MLG-02" required></div>
      <div><label for="cname">Nama</label><input id="cname" name="name" required></div>
      <div class="full"><label for="addr">Alamat</label><input id="addr" name="address"></div>
      <div><button class="btn" type="submit">Simpan pos</button></div>
    </form>
  </div>
  <div class="card">
    <div class="ch"><h2>Tambah perangkat</h2></div>
    <form method="POST" action="{{ route('admin.devices.store') }}" class="f">@csrf
      <div><label for="cid">Pos</label><select id="cid" name="crossing_id" required>@foreach($crossings as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach</select></div>
      <div><label for="dc">Kode perangkat</label><input id="dc" name="device_code" placeholder="NODE-03" required></div>
      <div><label for="dt">Jenis</label><select id="dt" name="type"><option value="SENSOR_NODE">Node 1 (sensor getaran)</option><option value="GATE_CONTROLLER">Node 2 (kontroler pos)</option></select></div>
      <div><label for="dn">Nama (opsional)</label><input id="dn" name="name"></div>
      <div><button class="btn" type="submit">Simpan dan buat token</button></div>
    </form>
  </div>
</section>
@endsection
