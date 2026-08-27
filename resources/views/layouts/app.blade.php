<!DOCTYPE html>
<html lang="en" data-no-progress-bar>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Budgetra') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('systemicons/budgetraicon-modified.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
    @livewireStyles
    @stack('styles')
</head>
@php
    $userTheme = auth()->user()->theme ?? 'daylight';
@endphp
<body class="dashboard-body" data-theme="{{ $userTheme }}">
    @if ($userTheme === 'auto')
    <script>
        document.body.setAttribute('data-theme',
            window.matchMedia('(prefers-color-scheme: dark)').matches ? 'nightflight' : 'daylight');
    </script>
    @endif
    <div class="dashboard-wrapper" id="dashWrapper">
        <x-sidebar :active="$active ?? ''" />
        <div class="dash-main">
            <div class="dash-content">
                @yield('content')
                {{ $slot ?? '' }}
            </div>
        </div>
    </div>
    @livewireScripts
    @stack('scripts')
    @if (session()->pull('collapse_sidebar'))
    <script>
        localStorage.setItem('sidebarCollapsed', '1');
        var wrap = document.getElementById('dashWrapper');
        var icon = document.getElementById('sidebarToggleIcon');
        if (wrap) wrap.classList.add('sidebar-collapsed');
        if (icon) icon.className = 'fa-solid fa-angle-right';
    </script>
    @endif
</body>
</html>
