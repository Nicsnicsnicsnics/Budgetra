@props(['active' => ''])
@php
    $profileInitials = collect(explode(' ', auth()->user()->full_name ?? ''))
        ->filter()
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->take(2)
        ->implode('');
@endphp
<aside class="admin-sidebar" id="adminSidebar">

    <div class="admin-sidebar-header">
        <button class="admin-sidebar-toggle-btn" id="adminSidebarToggle" title="Toggle sidebar">
            <i class="fa-solid fa-angle-left" id="adminSidebarToggleIcon"></i>
        </button>
    </div>

    <div class="admin-sidebar-brand">
        <img src="{{ asset('systemicons/budgetraicon-modified.png') }}" alt="Budgetra" class="admin-sidebar-logo">
        <div class="admin-sidebar-brand-text">
            <div class="admin-sidebar-name">Budgetra</div>
        </div>
    </div>
    <div class="admin-sidebar-divider admin-sidebar-divider-brand"></div>

    <nav class="admin-sidebar-nav">
        @php
        $primaryLinks = [
            ['href' => route('admin.dashboard'),          'icon' => 'fa-solid fa-house',              'label' => 'Dashboard',      'key' => 'dashboard'],
            ['href' => route('admin.users.index'),         'icon' => 'fa-solid fa-users',              'label' => 'User Accounts',  'key' => 'users'],
            ['href' => route('admin.destinations.index'),  'icon' => 'fa-solid fa-compass',            'label' => 'Destinations',   'key' => 'destinations'],
            ['href' => route('admin.attractions.index'),   'icon' => 'fa-solid fa-map-location-dot',   'label' => 'Attractions',    'key' => 'attractions'],
            ['href' => route('admin.travel-costs.index'),  'icon' => 'fa-solid fa-sack-dollar',        'label' => 'Travel Costs',   'key' => 'travel-costs'],
            ['href' => route('admin.reviews.index'),       'icon' => 'fa-regular fa-flag',             'label' => 'User Reviews',   'key' => 'reviews'],
        ];
        @endphp
        <div class="admin-sidebar-primary">
            @foreach ($primaryLinks as $link)
            <a href="{{ $link['href'] }}" class="admin-sidebar-link {{ $active === $link['key'] ? 'active' : '' }}" title="{{ $link['label'] }}">
                <i class="{{ $link['icon'] }}"></i>
                <span>{{ $link['label'] }}</span>
            </a>
            @endforeach
        </div>

        <div class="admin-sidebar-foot">
            <div class="admin-sidebar-divider"></div>
            {{-- Not route('profile.edit') — that one is inside the traveler
                 group, gated by the not-admin middleware, so an admin
                 clicking it got bounced straight back to the overview. --}}
            <a href="{{ route('admin.profile.edit') }}" class="admin-sidebar-link {{ $active === 'profile' ? 'active' : '' }}" title="Profile">
                @if (auth()->user()?->profile_photo)
                <img src="{{ Illuminate\Support\Facades\Storage::url(auth()->user()->profile_photo) }}"
                     alt="Profile" class="admin-sidebar-avatar">
                @else
                <span class="admin-sidebar-avatar admin-sidebar-avatar-initials">{{ $profileInitials }}</span>
                @endif
                <span>Profile</span>
            </a>
            <a href="{{ route('admin.settings.index') }}" class="admin-sidebar-link {{ $active === 'settings' ? 'active' : '' }}" title="Settings">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="admin-sidebar-link admin-sidebar-logout" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </nav>
</aside>

<script>
(function () {
    function applyState(collapsed) {
        var shell = document.getElementById('adminShell');
        var icon  = document.getElementById('adminSidebarToggleIcon');
        if (!shell) return;
        shell.classList.toggle('admin-sidebar-collapsed', collapsed);
        if (icon) icon.className = collapsed ? 'fa-solid fa-angle-right' : 'fa-solid fa-angle-left';
    }

    applyState(localStorage.getItem('adminSidebarCollapsed') === '1');

    if (!window.__adminSidebarToggleBound) {
        window.__adminSidebarToggleBound = true;
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#adminSidebarToggle')) return;
            var shell = document.getElementById('adminShell');
            if (!shell) return;
            var c = !shell.classList.contains('admin-sidebar-collapsed');
            localStorage.setItem('adminSidebarCollapsed', c ? '1' : '0');
            applyState(c);
        });
    }
})();
</script>
