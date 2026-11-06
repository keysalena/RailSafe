@extends('layouts.app')
@section('title', 'Edit akun')

@section('content')
<div class="top"><h1>Edit akun</h1><a class="btn ghost" href="{{ route('admin.users.index') }}">Kembali</a></div>
<section class="card">
  <form method="POST" action="{{ route('admin.users.update', $u) }}" class="f">
    @csrf @method('PUT')
    <div><label for="name">Nama</label><input id="name" name="name" value="{{ old('name', $u->name) }}" required></div>
    <div><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $u->email) }}" required></div>
    <div><label for="password">Reset kata sandi (kosongkan jika tidak diubah)</label><input id="password" type="password" name="password" minlength="8"></div>
    <div><label for="role">Role</label><select id="role" name="role"><option value="operator" @selected(old('role', $u->role) === 'operator')>Operator</option><option value="superadmin" @selected(old('role', $u->role) === 'superadmin')>Superadmin</option></select></div>
    <div class="full"><label>Pos yang ditugaskan</label><div class="checks">@foreach($crossings as $c)<label><input type="checkbox" name="crossings[]" value="{{ $c->id }}" @checked($u->crossings->contains($c->id))> {{ $c->code }}</label>@endforeach</div></div>
    <div><button class="btn" type="submit">Simpan</button></div>
  </form>
</section>
@endsection
