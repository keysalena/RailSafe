@extends('layouts.app')
@section('title', 'Pos dan perangkat')

@section('content')
<div class="top"><h1>Pos dan perangkat</h1></div>

<!-- @if(session('status'))
<div class="alert ok">{{ session('status') }}</div>
@endif -->

@if(session('error'))
<div class="alert err">{{ session('error') }}</div>
@endif

@if($errors->any())
<div class="alert err">
  @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
</div>
@endif

@if(session('token'))
<div class="alert ok">
  Token untuk <b>{{ session('token')['code'] }}</b> (hanya ditampilkan sekali, salin sekarang):
  <div class="tokenbox">{{ session('token')['value'] }}</div>
  <small>Tanam di firmware sebagai header <span class="mono">Authorization: Bearer ...</span></small>
</div>
@endif

{{-- ===== Daftar Pos ===== --}}
<section class="card mb">
  <div class="ch"><h2>Daftar Pos</h2><span class="pill">{{ $crossings->count() }} pos</span></div>
  <div class="tw"><table class="tbl-static">
    <thead><tr><th>Kode Pos</th><th>Jumlah perangkat</th><th class="col-act">Aksi</th></tr></thead>
    <tbody>
    @forelse($crossings as $c)
      <tr>
        <td class="mono">{{ $c->code }}</td>
        <td>{{ $c->devices_count }}</td>
        <td class="col-act">
          <div class="acts">
            <button class="btn sm ghost" type="button"
                    data-edit-crossing
                    data-action="{{ route('admin.crossings.update', $c) }}"
                    data-code="{{ $c->code }}">Edit</button>
            <form method="POST" action="{{ route('admin.crossings.destroy', $c) }}"
                  onsubmit="return confirm('Yakin ingin menghapus pos ini beserta semua perangkat di dalamnya?')">
              @csrf
              @method('DELETE')
              <button class="btn sm ghost danger-text" type="submit">Hapus</button>
            </form>
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="3" class="muted">Belum ada pos.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

{{-- ===== Daftar Perangkat ===== --}}
<section class="card mb">
  <div class="ch"><h2>Daftar Perangkat</h2><span class="pill">{{ $devices->count() }} perangkat</span></div>
  <div class="tw"><table class="tbl-static">
    <thead><tr><th>Kode</th><th>Pos</th><th>Jenis</th><th>Status</th><th>Terakhir lapor</th><th class="col-act">Aksi</th></tr></thead>
    <tbody>
    @php
      $statusMap = [
        'ACTIVE'       => ['Aktif', 't-ok'],
        'DISCONNECTED' => ['Terputus', 't-bad'],
        'FAULT'        => ['Gangguan', 't-warn'],
        'UNKNOWN'      => ['Belum diketahui', 't-mute'],
      ];
    @endphp
    @forelse($devices as $d)
      @php $st = $statusMap[$d->status] ?? $statusMap['UNKNOWN']; @endphp
      <tr>
        <td class="mono">{{ $d->device_code }}</td>
        <td>{{ $d->crossing->code ?? '-' }}</td>
        <td>{{ $d->type === 'SENSOR_NODE' ? 'Node 1 (sensor)' : 'Node 2 (kontroler pos)' }}</td>
        <td><span class="tag {{ $st[1] }}">{{ $st[0] }}</span></td>
        <td>{{ $d->last_seen_at ? $d->last_seen_at->diffForHumans() : '-' }}</td>
        <td class="col-act">
          <div class="acts">
            <form method="POST" action="{{ route('admin.devices.token', $d) }}"
                  onsubmit="return confirm('Ganti token? Token lama langsung tidak berlaku.')">
              @csrf
              <button class="btn sm ghost" type="submit">Ganti token</button>
            </form>
            @if($d->status !== 'ACTIVE')
            <form method="POST" action="{{ route('admin.devices.destroy', $d) }}"
                  onsubmit="return confirm('Yakin ingin menghapus node ini permanen?')">
              @csrf
              @method('DELETE')
              <button class="btn sm ghost danger-text" type="submit">Hapus</button>
            </form>
            @endif
          </div>
        </td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">Belum ada perangkat.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>

{{-- ===== Form Tambah/Edit ===== --}}
<section class="g g3">
  <div class="card">
    <div class="ch"><h2 id="title-crossing">Tambah pos</h2></div>
    <form id="form-crossing" method="POST" action="{{ route('admin.crossings.store') }}" class="f">
      @csrf
      <input type="hidden" name="_method" id="method-crossing" value="POST">
      <div class="full">
        <label for="code">Kode pos</label>
        <input id="code" name="code" placeholder="PJL-MLG-02" maxlength="30" value="{{ old('code') }}" required>
      </div>
      <div class="full acts">
        <button class="btn" id="btn-submit-crossing" type="submit">Simpan pos</button>
        <button class="btn ghost" id="btn-cancel-crossing" type="button" hidden>Batal</button>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="ch"><h2>Tambah perangkat</h2></div>
    <form method="POST" action="{{ route('admin.devices.store') }}" class="f">
      @csrf
      <div>
        <label for="cid">Pos</label>
        <select id="cid" name="crossing_id" required>
          @forelse($crossings as $c)
            <option value="{{ $c->id }}" @selected(old('crossing_id') == $c->id)>{{ $c->code }}</option>
          @empty
            <option value="" disabled selected>Tambahkan pos dulu</option>
          @endforelse
        </select>
      </div>
      <div>
        <label for="dc">Kode perangkat</label>
        <input id="dc" name="device_code" placeholder="NODE-03" maxlength="30" value="{{ old('device_code') }}" required>
      </div>
      <div>
        <label for="dtype">Jenis</label>
        <select id="dtype" name="type">
          <option value="SENSOR_NODE" @selected(old('type') === 'SENSOR_NODE')>Node 1 (sensor getaran)</option>
          <option value="GATE_CONTROLLER" @selected(old('type') === 'GATE_CONTROLLER')>Node 2 (kontroler pos)</option>
        </select>
      </div>
      <div class="full">
        <button class="btn" type="submit" @disabled($crossings->isEmpty())>Simpan dan buat token</button>
      </div>
    </form>
  </div>
</section>
@endsection

@push('scripts')
<style>
/* tabel aksi: jangan jadikan <td> flex, bungkus tombol dengan .acts */
.tbl-static tbody tr{cursor:default}
.tbl-static tbody tr:hover{background:transparent}
.col-act{width:1%;white-space:nowrap}
.acts{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.acts form{margin:0}
.btn.danger-text{color:var(--red)}
.btn.danger-text:hover{background:rgba(229,72,77,.14)}
.alert.err div+div{margin-top:2px}
.btn[disabled]{opacity:.5;cursor:not-allowed}
[hidden]{display:none!important}
</style>
<script>
(function(){
  const form   = document.getElementById('form-crossing');
  const method = document.getElementById('method-crossing');
  const input  = document.getElementById('code');
  const submit = document.getElementById('btn-submit-crossing');
  const cancel = document.getElementById('btn-cancel-crossing');
  const title  = document.getElementById('title-crossing');
  const storeUrl = @json(route('admin.crossings.store'));

  function reset(){
    form.action = storeUrl;
    method.value = 'POST';
    input.value = '';
    submit.textContent = 'Simpan pos';
    title.textContent = 'Tambah pos';
    cancel.hidden = true;
  }

  document.querySelectorAll('[data-edit-crossing]').forEach(btn => {
    btn.addEventListener('click', () => {
      form.action = btn.dataset.action;
      method.value = 'PUT';
      input.value = btn.dataset.code;
      submit.textContent = 'Update pos';
      title.textContent = 'Edit pos';
      cancel.hidden = false;
      form.scrollIntoView({behavior:'smooth', block:'center'});
      input.focus();
    });
  });

  cancel.addEventListener('click', reset);
})();
</script>
@endpush