<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - RailSafe</title>
    <link rel="stylesheet" href="{{ asset('css/railsafe.css') }}">
    @stack('styles')
</head>
<body>
<div class="shell">
    @include('partials.sidebar')

    <div class="main">
        @include('partials.navbar')

        <div class="content">
            @yield('content')
        </div>

        @include('partials.footer')
    </div>

    @include('partials.rightbar')
</div>

<script src="{{ asset('js/layout.js') }}"></script>
@stack('scripts')
</body>
</html>
