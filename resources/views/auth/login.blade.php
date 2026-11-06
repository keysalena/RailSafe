<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Masuk · RailSafe</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="{{ asset('css/railsafe.css') }}">
</head>
<body>
<div class="login">
  <form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="brand" style="margin:0"><i><svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3l-2 18M18 3l2 18M5 8h14M4.5 14h15"/></svg></i>RailSafe</div>
    <h1>Masuk</h1>
    <p>Dashboard monitoring perlintasan kereta api</p>
    @if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif
    <label for="email">Email</label>
    <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
    <label for="password">Kata sandi</label>
    <input id="password" type="password" name="password" required autocomplete="current-password">
    <button class="btn" type="submit">Masuk</button>
  </form>
</div>
</body>
</html>
