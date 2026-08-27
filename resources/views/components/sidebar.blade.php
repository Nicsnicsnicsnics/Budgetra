@props(['active' => ''])
@php
    $links = [
        ['href' => url('/dashboard'),  'icon' => 'fa-solid fa-house',            'label' => 'Dashboard',    'key' => 'dashboard',   'segment' => 'dashboard'],
        ['href' => url('/trips'),      'icon' => 'fa-solid fa-map-location-dot', 'label' => 'Planner',      'key' => 'trips',       'segment' => 'trips'],
        ['href' => route('destinations.index'), 'icon' => 'fa-solid fa-compass', 'label' => 'Destinations', 'key' => 'destinations', 'segment' => 'destinations'],
        ['href' => route('attractions.index'), 'icon' => 'fa-solid fa-mountain-sun', 'label' => 'Attractions', 'key' => 'attractions', 'segment' => 'attractions'],
        ['href' => route('saved-trips'), 'icon' => 'fa-solid fa-suitcase-rolling', 'label' => 'Saved Trips', 'key' => 'saved-trips', 'segment' => 'saved-trips'],
        ['href' => url('/savings'),    'icon' => 'fa-solid fa-piggy-bank',       'label' => 'Saving Goals', 'key' => 'savings',     'segment' => 'savings'],
        ['href' => url('/itinerary'),  'icon' => 'fa-regular fa-calendar-days',  'label' => 'Itinerary',    'key' => 'itinerary',   'segment' => 'itinerary'],
        ['href' => url('/expenses'),   'icon' => 'fa-solid fa-receipt',          'label' => 'Expenses',     'key' => 'expenses',    'segment' => 'expenses'],
        ['href' => url('/alerts'),     'icon' => 'fa-regular fa-bell',           'label' => 'Notifications','key' => 'alerts',      'segment' => 'alerts'],
        ['href' => route('multi-trips.index'), 'icon' => 'fa-solid fa-layer-group', 'label' => 'Multi Trips', 'key' => 'multi-trips', 'segment' => 'multi-trips'],
        ['href' => route('moments.index'), 'icon' => 'fa-regular fa-images',    'label' => 'Moments',      'key' => 'moments',     'segment' => 'moments'],
    ];

    $bottomLinks = [
        ['href' => auth()->user()?->userProfile ? url('/profile') : url('/profile/setup'), 'icon' => 'fa-regular fa-user-circle', 'label' => 'Profile', 'key' => 'profile', 'segment' => 'profile'],
        ['href' => url('/settings'), 'icon' => 'fa-solid fa-gear', 'label' => 'Settings', 'key' => 'settings', 'segment' => 'settings'],
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

@persist('sidebar')
<aside class="sidebar" id="appSidebar">

    <div class="sidebar-header">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Toggle sidebar">
            <i class="fa-solid fa-angle-left" id="sidebarToggleIcon"></i>
        </button>
    </div>

    <div class="sidebar-brand" style="display:flex;align-items:center;gap:10px;padding:4px 16px 14px;">
        <img src="{{ asset('systemicons/budgetraicon-modified.png') }}" alt="Budgetra"
             style="width:38px;height:38px;border-radius:10px;object-fit:contain;flex-shrink:0;">
        <span class="sidebar-link-label" style="font-size:18px;font-weight:800;color:inherit;letter-spacing:0.01em;">Budgetra</span>
    </div>
    <div class="sidebar-divider sidebar-divider-brand"></div>

    <nav class="sidebar-nav">
        @foreach ($links as $link)
        <a href="{{ $link['href'] }}" wire:navigate data-segment="{{ $link['segment'] }}"
           class="sidebar-link {{ $active === $link['key'] ? 'active' : '' }}"
           title="{{ $link['label'] }}">
            <i class="{{ $link['icon'] }}"></i>
            <span class="sidebar-link-label">{{ $link['label'] }}</span>
            @if ($link['key'] === 'alerts')
                <livewire:traveler.notification-badge />
            @endif
        </a>
        @endforeach

        {{-- Divider --}}
        <div class="sidebar-divider"></div>

        <div class="sidebar-bottom-links">
            {{-- Profile & Settings --}}
            @foreach ($bottomLinks as $link)
            <a href="{{ $link['href'] }}" wire:navigate data-segment="{{ $link['segment'] }}"
               class="sidebar-link {{ $active === $link['key'] ? 'active' : '' }}"
               title="{{ $link['label'] }}">
                @if ($link['key'] === 'profile')
                    @if (auth()->user()?->profile_photo)
                    <img src="{{ Illuminate\Support\Facades\Storage::url(auth()->user()->profile_photo) }}"
                         alt="Profile" class="sidebar-profile-avatar">
                    @else
                    <span class="sidebar-profile-avatar sidebar-profile-avatar-initials">{{ $profileInitials }}</span>
                    @endif
                @else
                <i class="{{ $link['icon'] }}"></i>
                @endif
                <span class="sidebar-link-label">{{ $link['label'] }}</span>
            </a>
            @endforeach

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="sidebar-link sidebar-logout-link" style="width:100%;background:none;border:none;cursor:pointer;text-align:left;" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
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
        var icon = document.getElementById('sidebarToggleIcon');
        if (!wrap) return;
        wrap.classList.toggle('sidebar-collapsed', collapsed);
        if (icon) icon.className = collapsed ? 'fa-solid fa-angle-right' : 'fa-solid fa-angle-left';
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
