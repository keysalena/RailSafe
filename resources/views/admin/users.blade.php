@extends('layouts.app')
@section('title', 'Kelola akun')

@section('content')
<div class="top"><h1>Kelola akun</h1></div>

<section class="card mb">
  <div class="ch"><h2>Daftar akun</h2></div>
  <div class="tw"><table>
    <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Pos</th><th>Login terakhir</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @foreach($users as $u)
      <tr style="cursor:default">
        <td>{{ $u->name }}</td><td>{{ $u->email }}</td>
        <td><span class="tag {{ $u->isSuperadmin() ? 't-warn' : 't-mute' }}">{{ $u->isSuperadmin() ? 'Superadmin' : 'Operator' }}</span></td>
        <td>{{ $u->isSuperadmin() ? 'Semua pos' : ($u->crossings->pluck('code')->implode(', ') ?: '-') }}</td>
        <td>{{ $u->last_login_at ? $u->last_login_at->format('d M H:i') : '-' }}</td>
        <td><span class="tag {{ $u->is_active ? 't-ok' : 't-bad' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
        <td style="white-space:nowrap">
          <a class="btn sm ghost" href="{{ route('admin.users.edit', $u) }}">Edit</a>
          @if($u->id !== auth()->id())
          <form class="inl" method="POST" action="{{ route('admin.users.toggle', $u) }}">@csrf @method('PATCH')<button class="btn sm {{ $u->is_active ? 'danger' : '' }}" type="submit">{{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
          @endif
        </td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</section>

<section class="card">
  <div class="ch"><h2>Tambah akun</h2></div>
  <form method="POST" action="{{ route('admin.users.store') }}" class="f">
    @csrf
    <div><label for="name">Nama</label><input id="name" name="name" value="{{ old('name') }}" required></div>
    <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required></div>
    <div><label for="password">Kata sandi (min. 8 karakter)</label><input id="password" type="password" name="password" required minlength="8"></div>
    <div><label for="role">Role</label><select id="role" name="role"><option value="operator">Operator</option><option value="superadmin">Superadmin</option></select></div>
    <div class="full"><label>Pos yang ditugaskan (untuk operator)</label><div class="checks">@foreach($crossings as $c)<label><input type="checkbox" name="crossings[]" value="{{ $c->id }}"> {{ $c->code }}</label>@endforeach</div></div>
    <div><button class="btn" type="submit">Buat akun</button></div>
  </form>
</section>
@endsection
