@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>User Management</h1>
        <p>Manage, monitor, and moderate user accounts across the Budgetra platform.</p>
    </div>
    <div class="admin-page-head-actions">
        <a href="{{ route('admin.users.export', request()->query()) }}" class="admin-btn admin-btn-outline"><i class="fa-solid fa-download"></i> Export</a>
    </div>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif

<div class="admin-stat-row">
    <div class="admin-stat-card">
        <span class="admin-stat-label">Total Users</span>
        <strong class="admin-stat-value">{{ $totalUsers }}</strong>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-label">Trips Planned (Month)</span>
        <strong class="admin-stat-value">{{ $tripsThisMonth }}</strong>
    </div>
    <div class="admin-stat-card">
        <span class="admin-stat-label">Banned Users</span>
        <strong class="admin-stat-value">{{ $bannedUsers }}</strong>
    </div>
</div>

<form method="GET" class="admin-search-form" style="margin-bottom:16px;">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name or email..." class="admin-input">
    <button type="submit" class="admin-btn admin-btn-outline">Search</button>
</form>

{{-- Everything the sort affects lives in one wrapper so a sort click can
     swap it in place instead of navigating. --}}
<div id="usersTableWrap" class="admin-sort-target">
<div class="admin-card">
    <table class="admin-table">
        @php
            // Each column opens "most first"; clicking the one already sorted
            // flips it, and the chevron rotates to match.
            $sortLink = function (string $key) use ($sort, $dir, $sortDefaults) {
                $isActive = $sort === $key;
                $next     = $isActive && $dir === $sortDefaults[$key]
                    ? ($sortDefaults[$key] === 'asc' ? 'desc' : 'asc')
                    : $sortDefaults[$key];
                return [
                    'url'      => route('admin.users.index', array_filter([
                                      'sort'   => $key,
                                      'dir'    => $next,
                                      'search' => request('search'),
                                  ])),
                    'reversed' => $isActive && $dir !== $sortDefaults[$key],
                ];
            };
        @endphp
        <thead>
            <tr>
                <th>User</th>
                @foreach (['trips' => 'Trip Count', 'total' => 'Total Travel Cost', 'average' => 'Average Travel Cost'] as $key => $label)
                @php $sl = $sortLink($key); @endphp
                <th>
                    <a href="{{ $sl['url'] }}" class="admin-sort">
                        {{ $label }}
                        <i class="fa-solid fa-circle-chevron-down {{ $sl['reversed'] ? 'is-reversed' : '' }}"></i>
                    </a>
                </th>
                @endforeach
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($users as $user)
            <tr>
                <td>
                    <div class="admin-user-cell">
                        <div class="admin-user-avatar">{{ strtoupper(substr($user->full_name ?: $user->email, 0, 1)) }}</div>
                        <div>
                            <div class="admin-user-name">{{ $user->full_name ?: '—' }}</div>
                            <div class="admin-user-email">{{ $user->email }}</div>
                        </div>
                    </div>
                </td>
                <td>{{ $user->trips_count }} {{ Str::plural('Trip', $user->trips_count) }}</td>
                @php $totalTravelCost = $user->expenses_sum_amount ?? 0; @endphp
                <td>₱{{ number_format($totalTravelCost, 2) }}</td>
                <td>{{ $user->trips_count > 0 ? '₱' . number_format($totalTravelCost / $user->trips_count, 2) : '—' }}</td>
                <td class="admin-table-actions">
                    @if($user->id !== auth()->id())
                        <button type="button" class="admin-icon-btn admin-icon-btn-danger js-delete-user-btn"
                            data-action="{{ route('admin.users.destroy', $user) }}"
                            data-name="{{ $user->full_name ?: $user->email }}"
                            title="Delete"><i class="fa-solid fa-trash"></i></button>
                        <form method="POST" action="{{ route('admin.users.ban', $user) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="admin-icon-btn {{ $user->role === 'banned' ? 'admin-icon-btn-active' : '' }}" title="{{ $user->role === 'banned' ? 'Unban' : 'Ban' }}">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="admin-table-empty">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="admin-pagination">{{ $users->links() }}</div>
</div>{{-- /#usersTableWrap --}}

<div id="deleteUserModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteUserModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Delete User</h3>
            <button type="button" class="admin-modal-close" onclick="closeDeleteUserModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:20px 24px;">
            <p style="margin:0 0 20px;font-size:14px;color:var(--admin-muted, #8A7A6C);line-height:1.6;">
                Delete <strong id="deleteUserName" style="color:var(--admin-ink, #241208);"></strong>? This cannot be undone — their trips, expenses, and other data will be permanently removed.
            </p>
            <form id="deleteUserForm" method="POST">
                @csrf @method('DELETE')
                <div class="admin-modal-actions">
                    <button type="button" class="admin-btn admin-btn-outline" onclick="closeDeleteUserModal();">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" style="background:var(--admin-danger, #D64545);border-color:var(--admin-danger, #D64545);">Delete User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Sorting without a page reload. The sort still runs on the server (it has
    // to — ordering the full set and re-paginating can't be done from the 25
    // rows in the DOM), but the response is fetched and the table swapped in
    // place. The headers stay real hrefs so middle-click still works.
    function swapUsersTable(url, push) {
        const wrap = document.getElementById('usersTableWrap');
        wrap.classList.add('is-loading');
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(r => { if (!r.ok) throw new Error('sort failed'); return r.text(); })
            .then(html => {
                const fresh = new DOMParser().parseFromString(html, 'text/html')
                                             .getElementById('usersTableWrap');
                if (!fresh) throw new Error('table missing');
                wrap.innerHTML = fresh.innerHTML;
                if (push) history.pushState({ usersUrl: url }, '', url);
            })
            // Never strand the admin on a half-updated table.
            .catch(() => { window.location.href = url; })
            .finally(() => wrap.classList.remove('is-loading'));
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('#usersTableWrap .admin-sort, #usersTableWrap .admin-pagination a');
        if (link && link.href && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey && e.button === 0) {
            e.preventDefault();
            swapUsersTable(link.href, true);
            return;
        }

        const btn = e.target.closest('.js-delete-user-btn');
        if (!btn) return;
        document.getElementById('deleteUserName').textContent = btn.dataset.name || 'this user';
        document.getElementById('deleteUserForm').action = btn.dataset.action;
        document.getElementById('deleteUserModal').style.display = 'flex';
    });
    window.addEventListener('popstate', function () { swapUsersTable(window.location.href, false); });

    function closeDeleteUserModal() {
        document.getElementById('deleteUserModal').style.display = 'none';
    }
</script>
@endsection
