<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Budgetra Admin')</title>
    <link rel="icon" type="image/png" href="{{ asset('systemicons/budgetraicon-modified.png') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @livewireStyles
</head>
@php
    $userTheme = auth()->user()->theme ?? 'daylight';
@endphp
<body class="admin-body" data-theme="{{ $userTheme }}">
    @if ($userTheme === 'auto')
    <script>
        document.body.setAttribute('data-theme',
            window.matchMedia('(prefers-color-scheme: dark)').matches ? 'nightflight' : 'daylight');
    </script>
    @endif
    <div class="admin-shell" id="adminShell">
        <x-admin-sidebar :active="$active ?? ''" />
        <div class="admin-main">
            <div class="admin-content">
                @yield('content')
            </div>
        </div>
    </div>
    @livewireScripts
</body>
</html>
