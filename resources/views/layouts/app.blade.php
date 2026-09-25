<!DOCTYPE html>
<html lang="en" data-no-progress-bar @auth data-user-id="{{ auth()->id() }}" @endauth @if (auth()->user()?->userProfile) data-has-profile @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Budgetra') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('systemicons/budgetraicon.png') }}?v={{ filemtime(public_path('systemicons/budgetraicon.png')) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
    {{-- Basemap config for every Leaflet map in the app. Defines a global; the
         pages that draw maps load Leaflet itself and call it. --}}
    <script src="{{ asset('js/basemap.js') }}?v={{ filemtime(public_path('js/basemap.js')) }}"></script>

    {{-- Keeps the signed-in traveler's avatar current after a profile save.
         Needed app-wide, not just on /profile: the sidebar is @persist'ed and
         your review rows live on attraction pages. --}}
    <script src="{{ asset('js/avatar-sync.js') }}?v={{ filemtime(public_path('js/avatar-sync.js')) }}"></script>
    @livewireStyles
    @stack('styles')
    <script>
        // "Skip for now" on the first-run empty states. Kept in the <head> and
        // applied to <html> so the right half of the empty state is chosen before
        // the page paints, and read from one key so skipping on any tab carries to
        // every other tab. Paired with .empty-state-swap in style.css.
        (function () {
            // Scoped to the signed-in user. localStorage is per-browser, not
            // per-account, so a single shared key would hand one account's skip
            // to whoever signs in next on this machine — a brand-new user would
            // start already-skipped and never see the prompt at all.
            function key() {
                return 'budgetraProfileSkipped:' +
                    (document.documentElement.getAttribute('data-user-id') || 'guest');
            }

            function apply() {
                var root = document.documentElement;
                try {
                    // data-has-profile is server-rendered, so it is the authority:
                    // once a profile exists the flag has outlived its purpose.
                    if (root.hasAttribute('data-has-profile')) {
                        localStorage.removeItem(key());
                        root.removeAttribute('data-profile-skipped');
                    } else if (localStorage.getItem(key()) === '1') {
                        root.setAttribute('data-profile-skipped', '');
                    } else {
                        root.removeAttribute('data-profile-skipped');
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
                try { localStorage.setItem(key(), '1'); } catch (e) {}
                document.documentElement.setAttribute('data-profile-skipped', '');
            };
        })();
    </script>
</head>
@php
    $userTheme = auth()->user()->theme ?? 'original';
@endphp
<body class="dashboard-body" data-theme="{{ $userTheme }}">
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
        // The chevron turns with .sidebar-collapsed in CSS. This block used to
        // set the icon's className too, and was the copy that got missed when
        // the icons last changed.
        if (wrap) wrap.classList.add('sidebar-collapsed');
    </script>
    @endif
</body>
</html>
