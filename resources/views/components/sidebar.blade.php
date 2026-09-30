@props(['active' => ''])
@php
    $links = [
        ['href' => url('/dashboard'),  'icon' => 'dashboard',                    'label' => 'Dashboard',    'key' => 'dashboard',   'segment' => 'dashboard'],
        ['href' => url('/trips'),      'icon' => 'trip-planner',                 'label' => 'Planner',      'key' => 'trips',       'segment' => 'trips'],
        ['href' => route('destinations.index'), 'icon' => 'destinations', 'label' => 'Destinations', 'key' => 'destinations', 'segment' => 'destinations'],
        ['href' => route('attractions.index'), 'icon' => 'attractions', 'label' => 'Attractions', 'key' => 'attractions', 'segment' => 'attractions'],
        ['href' => route('saved-trips'), 'icon' => 'saved-trips', 'label' => 'Saved Trips', 'key' => 'saved-trips', 'segment' => 'saved-trips'],
        ['href' => url('/savings'),    'icon' => 'saving-goals',                 'label' => 'Saving Goals', 'key' => 'savings',     'segment' => 'savings'],
        ['href' => url('/itinerary'),  'icon' => 'itinerary',                    'label' => 'Itinerary',    'key' => 'itinerary',   'segment' => 'itinerary'],
        ['href' => url('/expenses'),   'icon' => 'expenses',                     'label' => 'Expenses',     'key' => 'expenses',    'segment' => 'expenses'],
        ['href' => url('/notifications'), 'icon' => 'notifications',             'label' => 'Notifications','key' => 'notifications','segment' => 'notifications'],
        ['href' => route('multi-trips.index'), 'icon' => 'multi-trips', 'label' => 'Multi Trips', 'key' => 'multi-trips', 'segment' => 'multi-trips'],
        ['href' => route('moments.index'), 'icon' => 'moments',           'label' => 'Moments',      'key' => 'moments',     'segment' => 'moments'],
    ];

    $bottomLinks = [
        // setup_href makes this one entry render twice — see the swap in the
        // markup below and the comment above @persist.
        ['href' => url('/profile'), 'setup_href' => url('/profile/setup'), 'icon' => 'fa-regular fa-user-circle', 'label' => 'Profile', 'key' => 'profile', 'segment' => 'profile'],
        ['href' => url('/settings'), 'icon' => 'settings', 'label' => 'Settings', 'key' => 'settings', 'segment' => 'settings'],
    ];

    $profileInitials = collect(explode(' ', auth()->user()->full_name ?? ''))
        ->filter()
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->take(2)
        ->implode('');

    // Auto-detect active from URL if not explicitly passed
    $currentPath = request()->path();
    if (!$active) {
        foreach (array_merge($links, $bottomLinks) as $link) {
            if ($currentPath === $link['segment'] || str_starts_with($currentPath, $link['segment'] . '/')) {
                $active = $link['key'];
                break;
            }
        }
    }
@endphp

{{-- Every href here is computed once and then frozen: this block is
     @persist'ed, so wire:navigate never re-renders it. Profile used to be a
     single link whose href was chosen server-side — /profile/setup for a
     traveler with no profile yet — and it stayed pointing there for the rest
     of the session even after they finished the builder.

     Profile is now BOTH links, always, with CSS showing one. The hrefs are
     constants, so freezing them costs nothing, and the choice between them is
     re-made by the browser on every navigation from :root[data-has-profile]
     (layouts/app.blade.php) — an attribute Livewire's replaceHtmlAttributes()
     refreshes on each wire:navigate. That is the only piece of per-request
     truth that reaches inside a persisted block.

     ProfileController::edit() still redirects a profile-less traveler to the
     builder. It is the guarantee behind all of this: bookmarks, the other
     views that link to /profile, and any browser where this CSS never lands. --}}
@persist('sidebar')
<aside class="sidebar" id="appSidebar">

    <div class="sidebar-header">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle sidebar">
            {{-- Carries both chevrons; style.css picks one off .sidebar-collapsed.
                 Deliberately not swapped in JS: the direction used to be set by
                 assigning className in three separate places, and they drifted. --}}
            <i class="app-icon" id="sidebarToggleIcon"
               style="{{ system_icon('left-chevron', 'chevron-left') }};{{ system_icon('right-chevron', 'chevron-right') }}"></i>
        </button>
    </div>

    <div class="sidebar-brand" style="display:flex;align-items:center;gap:10px;padding:4px 16px 14px;">
        {{-- No border-radius any more: that was rounding the corners of a
             square photo tile. This is a transparent line mark, so there is
             no tile to round — and it is painted white by .brand-mark,
             because the sidebar is dark in both themes. --}}
        <img src="{{ asset('systemicons/budgetraicon.png') }}?v={{ filemtime(public_path('systemicons/budgetraicon.png')) }}"
             alt="Budgetra" class="brand-mark"
             style="width:38px;height:38px;object-fit:contain;flex-shrink:0;">
        <span class="sidebar-link-label" style="font-size:18px;font-weight:800;color:inherit;letter-spacing:0.01em;">Budgetra</span>
    </div>
    <div class="sidebar-divider sidebar-divider-brand"></div>

    <nav class="sidebar-nav">
        @foreach ($links as $link)
        <a href="{{ $link['href'] }}" wire:navigate data-segment="{{ $link['segment'] }}"
           class="sidebar-link {{ $active === $link['key'] ? 'active' : '' }}"
           title="{{ $link['label'] }}">
            <x-nav-icon :icon="$link['icon']" />
            <span class="sidebar-link-label">{{ $link['label'] }}</span>
            {{-- Tied to the key above: this is what puts the unread count on
                 the bell, and it fails silently if the two drift apart. --}}
            @if ($link['key'] === 'notifications')
                <livewire:traveler.notification-badge />
            @endif
        </a>
        @endforeach

        {{-- Divider --}}
        <div class="sidebar-divider"></div>

        <div class="sidebar-bottom-links">
            {{-- Profile & Settings --}}
            @foreach ($bottomLinks as $link)
            @php
                // An entry with setup_href is drawn twice, once per destination,
                // and .sidebar-profile-swap hides whichever one does not apply.
                // Writing it as a loop keeps the anchor itself — avatar, label,
                // active class and all — defined once.
                $variants = isset($link['setup_href'])
                    ? [['when' => 'ready', 'href' => $link['href']],
                       ['when' => 'setup', 'href' => $link['setup_href']]]
                    : [['when' => null, 'href' => $link['href']]];
            @endphp
            @foreach ($variants as $variant)
            @if ($variant['when'])<span class="sidebar-profile-swap" data-profile-when="{{ $variant['when'] }}">@endif
            <a href="{{ $variant['href'] }}" wire:navigate data-segment="{{ $link['segment'] }}"
               class="sidebar-link {{ $active === $link['key'] ? 'active' : '' }}"
               title="{{ $link['label'] }}">
                @if ($link['key'] === 'profile')
                    @if (auth()->user()?->profile_photo)
                    {{-- Both copies carry the hook; avatar-sync.js repaints every
                         element that has it, and only one of them is ever on
                         screen, so painting the hidden one costs nothing. --}}
                    <img src="{{ Illuminate\Support\Facades\Storage::url(auth()->user()->profile_photo) }}"
                         alt="Profile" class="sidebar-profile-avatar" data-user-avatar>
                    @else
                    {{-- data-user-avatar lets avatar-sync.js repaint this after a
                         profile save. It has to: @persist means wire:navigate
                         never re-renders the sidebar, so without it the old
                         picture survives the rest of the session. --}}
                    <span class="sidebar-profile-avatar sidebar-profile-avatar-initials"
                          data-user-avatar data-avatar-img-class="sidebar-profile-avatar">{{ $profileInitials }}</span>
                    @endif
                @else
                <x-nav-icon :icon="$link['icon']" />
                @endif
                <span class="sidebar-link-label">{{ $link['label'] }}</span>
            </a>
            @if ($variant['when'])</span>@endif
            @endforeach
            @endforeach

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="sidebar-link sidebar-logout-link" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;" title="Logout">
                    <x-nav-icon icon="logout" />
                    <span class="sidebar-link-label">Logout</span>
                </button>
            </form>
        </div>
    </nav>

</aside>
@endpersist

<script>
(function () {
    function applyState(collapsed) {
        var wrap = document.getElementById('dashWrapper');
        if (!wrap) return;
        // The chevron follows this class in CSS — see #sidebarToggleIcon in
        // style.css. Setting its className here would strip .app-icon and
        // leave the button empty.
        wrap.classList.toggle('sidebar-collapsed', collapsed);
    }

    applyState(localStorage.getItem('sidebarCollapsed') === '1');

    // #dashWrapper isn't inside the persisted sidebar block, so it (and the
    // button itself, depending on how a given navigation morphs the page)
    // can be swapped for a new DOM node on a wire:navigate transition. A
    // listener bound directly to those elements would then be left attached
    // to a detached, invisible node. Delegate from `document` instead (bound
    // exactly once, ever) and re-query everything fresh at click time so
    // this keeps working no matter which nodes got recreated.
    if (!window.__sidebarToggleBound) {
        window.__sidebarToggleBound = true;
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#sidebarToggle')) return;
            var wrap = document.getElementById('dashWrapper');
            if (!wrap) return;
            var c = !wrap.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', c ? '1' : '0');
            applyState(c);
        });
    }

    // The sidebar is persisted across navigations, so Livewire never re-renders
    // it (and its server-computed "active" class) after wire:navigate transitions.
    // Recompute the active link from the current URL on every navigation instead.
    function syncActiveLink() {
        var path = window.location.pathname.replace(/^\/+|\/+$/g, '');
        var links = document.querySelectorAll('#appSidebar .sidebar-link[data-segment]');
        links.forEach(function (link) {
            var seg = link.dataset.segment;
            var isActive = path === seg || path.indexOf(seg + '/') === 0;
            link.classList.toggle('active', isActive);
        });
    }
    syncActiveLink();
    if (!window.__sidebarActiveSyncBound) {
        window.__sidebarActiveSyncBound = true;
        document.addEventListener('livewire:navigated', syncActiveLink);
    }
})();

(function () {
    // The sidebar markup uses the persist directive across wire:navigate transitions for a
    // smooth SPA feel, so its server-rendered "active" class is frozen at
    // whatever page first mounted it. A plain re-run of this script won't fix
    // that: Livewire's morph skips re-executing <script> tags whose content is
    // byte-identical to the previous page's (true here, since this component
    // is the same on every page), so it only ever fires once per session.
    // Binding to the livewire:navigated event instead guarantees a re-check on
    // every navigation, independent of whether the script tag itself re-runs.
    function updateActiveSidebarLink() {
        var path = window.location.pathname.replace(/^\/+/, '');
        document.querySelectorAll('.sidebar-link[data-segment]').forEach(function (link) {
            var segment = link.dataset.segment;
            var isActive = path === segment || path.indexOf(segment + '/') === 0;
            link.classList.toggle('active', isActive);
        });
    }

    updateActiveSidebarLink();
    if (!window.__sidebarActiveListenerBound) {
        window.__sidebarActiveListenerBound = true;
        document.addEventListener('livewire:navigated', updateActiveSidebarLink);
    }
})();
</script>
