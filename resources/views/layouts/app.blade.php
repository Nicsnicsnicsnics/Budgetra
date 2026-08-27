<!DOCTYPE html>
<html lang="en" data-no-progress-bar @if (auth()->user()?->userProfile) data-has-profile @endif>
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
    {{-- Basemap config for every Leaflet map in the app. Defines a global; the
         pages that draw maps load Leaflet itself and call it. --}}
    <script src="{{ asset('js/basemap.js') }}?v={{ filemtime(public_path('js/basemap.js')) }}"></script>
    @livewireStyles
    @stack('styles')
    <script>
        // "Skip this step" on the first-run empty states. Kept in the <head> and
        // applied to <html> so the right half of the empty state is chosen before
        // the page paints, and read from one key so skipping on any tab carries to
        // every other tab. Paired with .empty-state-swap in style.css.
        (function () {
            var KEY = 'budgetraProfileSkipped';

            function apply() {
                var root = document.documentElement;
                try {
                    // data-has-profile is server-rendered, so it is the authority:
                    // once a profile exists the flag has outlived its purpose.
                    if (root.hasAttribute('data-has-profile')) {
                        localStorage.removeItem(KEY);
                        root.removeAttribute('data-profile-skipped');
                    } else if (localStorage.getItem(KEY) === '1') {
                        root.setAttribute('data-profile-skipped', '');
                    }
                } catch (e) { /* private mode / storage disabled — show the prompt */ }
            }

            apply();

            // Every sidebar link is wire:navigate. Livewire's swap removes any
            // <html> attribute the server did not send — and this flag is client
            // side, so the server never sends it — while mergeNewHead sees this
            // script as unchanged and does not re-run it. Without re-applying
            // here the skip would die on the first tab change.
            document.addEventListener('livewire:navigated', apply);

            window.budgetraSkipProfileSetup = function () {
                try { localStorage.setItem(KEY, '1'); } catch (e) {}
                document.documentElement.setAttribute('data-profile-skipped', '');
            };
        })();
    </script>
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
