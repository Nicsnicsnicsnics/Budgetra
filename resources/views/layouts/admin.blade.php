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
    {{-- Shared dialog behaviour. Every admin modal is a .admin-modal-backdrop
         that its own page shows and hides by flipping display, so rather than
         teach each page about Escape and scroll locking, watch the backdrops
         from here: Escape closes whatever is on top, and the page behind an
         open dialog is frozen so it can't scroll under it. --}}
    <script>
        (function () {
            function openBackdrops() {
                return Array.prototype.filter.call(
                    document.querySelectorAll('.admin-modal-backdrop'),
                    // Not offsetParent: these are position:fixed, so that is
                    // null whether they are open or not.
                    function (el) { return getComputedStyle(el).display !== 'none'; }
                );
            }

            function syncScrollLock() {
                document.body.classList.toggle('admin-modal-open', openBackdrops().length > 0);
            }

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                var open = openBackdrops();
                if (!open.length) return;
                // Last one in the DOM is the one drawn on top.
                open[open.length - 1].style.display = 'none';
                syncScrollLock();
            });

            // The pages set display straight on the element rather than
            // firing an event, so an attribute observer is what actually
            // catches an open or a close.
            var observer = new MutationObserver(syncScrollLock);
            document.querySelectorAll('.admin-modal-backdrop').forEach(function (el) {
                observer.observe(el, { attributes: true, attributeFilter: ['style', 'class'] });
            });
        })();
    </script>
    @livewireScripts
</body>
</html>
