@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>Travel Costs</h1>
        <p>Average cost benchmarks used to budget trips per destination.</p>
    </div>
    <button type="button" class="admin-btn admin-btn-primary js-add-travelcost-btn"><i class="fa-solid fa-plus"></i> Add Travel Cost</button>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif
{{-- Everything the sort affects lives in one wrapper so a sort click can
     swap it in place instead of navigating. --}}
<div id="tcTableWrap" class="admin-sort-target">
<div class="admin-card">
    <table class="admin-table">
        @php
            // Clicking a header sorts it the way that column is meant to read
            // (priciest / biggest / local first); clicking the one already
            // sorted flips it, and the caret rotates to match.
            $sortLink = function (string $key) use ($sort, $dir, $sortDefaults) {
                $isActive = $sort === $key;
                $next     = $isActive && $dir === $sortDefaults[$key]
                    ? ($sortDefaults[$key] === 'asc' ? 'desc' : 'asc')
                    : $sortDefaults[$key];
                return [
                    'url'      => route('admin.travel-costs.index', ['sort' => $key, 'dir' => $next]),
                    'active'   => $isActive,
                    'reversed' => $isActive && $dir !== $sortDefaults[$key],
                ];
            };
        @endphp
        <thead><tr>
            <th>Destination</th>
            @foreach (['cost_level' => 'Cost Level', 'multiplier' => 'Multiplier', 'category' => 'Category'] as $key => $label)
            @php $s = $sortLink($key); @endphp
            <th>
                        <a href="{{ $s['url'] }}" class="admin-sort">
                    {{ $label }}
                    {{-- circle-chevron-down, not circle-caret-down: the latter
                         is Font Awesome Pro and renders as nothing against the
                         free 6.5.0 build this layout loads. --}}
                    <i class="fa-solid fa-circle-chevron-down {{ $s['reversed'] ? 'is-reversed' : '' }}"></i>
                </a>
            </th>
            @endforeach
            <th>Actions</th>
        </tr></thead>
        <tbody>
        @forelse($destinations as $dest)
            <tr>
                <td>{{ $dest->destination }}</td>
                <td>{{ $dest->cost_level }}</td>
                <td>{{ $dest->multiplier }}&times;</td>
                <td>{{ $dest->category ?? '—' }}</td>
                <td class="admin-table-actions">
                    <button type="button" class="admin-icon-btn js-edit-travelcost-btn"
                        data-action="{{ route('admin.travel-costs.update', $dest) }}"
                        data-destination="{{ $dest->destination }}"
                        data-cost-level="{{ $dest->cost_level }}"
                        data-multiplier="{{ $dest->multiplier }}"
                        data-category="{{ $dest->category }}"
                        title="Edit"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="admin-icon-btn admin-icon-btn-danger js-delete-travelcost-btn"
                        data-action="{{ route('admin.travel-costs.destroy', $dest) }}"
                        data-name="{{ $dest->destination }}"
                        title="Delete"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="admin-table-empty">No travel cost entries yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="admin-pagination">{{ $destinations->links() }}</div>
</div>{{-- /#tcTableWrap --}}

{{-- Add / Edit modal — one form for both, since the two operations take the
     exact same six fields. Mode only changes the header, the action and the
     spoofed method. --}}
<div id="travelCostModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeTravelCostModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <div class="admin-modal-icon"><i class="fa-solid fa-pen" id="tcModalIcon"></i></div>
            <h3 class="admin-modal-title" id="tcModalTitle">Edit Travel Cost</h3>
            <p class="admin-modal-sub" id="tcModalSub"></p>
            <button type="button" class="admin-modal-close" aria-label="Close" onclick="closeTravelCostModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="travelCostForm" method="POST" class="admin-modal-form">
            @csrf
            <input type="hidden" name="_method" id="tcModalMethod" value="PUT">
            <div class="admin-modal-body">
                <div class="admin-form-group">
                    <label>Destination Name</label>
                    <input type="text" name="destination" id="tcModalDestination" class="admin-input" required>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Cost Level</label>
                        <select name="cost_level" id="tcModalCostLevel" class="admin-input" required>
                            @foreach (['Budget-friendly', 'Moderate', 'Pricey', 'Very Expensive'] as $level)
                            <option value="{{ $level }}">{{ $level }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="admin-form-group">
                        <label>Multiplier</label>
                        <input type="number" step="0.001" min="0.1" max="10" name="multiplier" id="tcModalMultiplier" class="admin-input" required>
                    </div>
                </div>
                <div class="admin-form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="tcModalCategory" class="admin-input">
                </div>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="admin-modal-btn admin-modal-btn-cancel" onclick="closeTravelCostModal();">Cancel</button>
                <button type="submit" class="admin-modal-btn admin-modal-btn-primary" id="tcModalSubmit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete modal --}}
<div id="deleteTravelCostModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteTravelCostModal();">
    <div class="admin-modal-card admin-modal-card-sm">
        <div class="admin-modal-head">
            <div class="admin-modal-icon admin-modal-icon-danger"><i class="fa-solid fa-trash-can"></i></div>
            <h3 class="admin-modal-title">Delete Travel Cost?</h3>
            <p class="admin-modal-sub">
                The benchmark for <strong id="deleteTravelCostName"></strong> will be permanently deleted.<br>This action cannot be undone.
            </p>
            <button type="button" class="admin-modal-close" aria-label="Close" onclick="closeDeleteTravelCostModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="deleteTravelCostForm" method="POST">
            @csrf @method('DELETE')
            <div class="admin-modal-actions">
                <button type="button" class="admin-modal-btn admin-modal-btn-cancel" onclick="closeDeleteTravelCostModal();">Cancel</button>
                <button type="submit" class="admin-modal-btn admin-modal-btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    function fillTravelCostModal(values) {
        document.getElementById('tcModalDestination').value = values.destination || '';
        document.getElementById('tcModalCostLevel').value   = values.costLevel   || 'Moderate';
        document.getElementById('tcModalMultiplier').value  = values.multiplier  || '1.000';
        document.getElementById('tcModalCategory').value    = values.category    || '';
    }

    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.js-add-travelcost-btn');
        if (addBtn) {
            document.getElementById('travelCostForm').action = @json(route('admin.travel-costs.store'));
            // The store route is a plain POST — spoofing it back to POST keeps
            // one form serving both modes without a second <form> on the page.
            document.getElementById('tcModalMethod').value      = 'POST';
            document.getElementById('tcModalIcon').className    = 'fa-solid fa-plus';
            document.getElementById('tcModalTitle').textContent = 'Add Travel Cost';
            document.getElementById('tcModalSub').textContent   = 'A new cost benchmark used to budget trips.';
            document.getElementById('tcModalSubmit').textContent = 'Add Travel Cost';
            fillTravelCostModal({});
            document.getElementById('travelCostModal').style.display = 'flex';
            return;
        }

        const editBtn = e.target.closest('.js-edit-travelcost-btn');
        if (editBtn) {
            document.getElementById('travelCostForm').action = editBtn.dataset.action;
            document.getElementById('tcModalMethod').value      = 'PUT';
            document.getElementById('tcModalIcon').className    = 'fa-solid fa-pen';
            document.getElementById('tcModalTitle').textContent = 'Edit Travel Cost';
            document.getElementById('tcModalSub').textContent   = editBtn.dataset.destination || '';
            document.getElementById('tcModalSubmit').textContent = 'Save Changes';
            fillTravelCostModal(editBtn.dataset);
            document.getElementById('travelCostModal').style.display = 'flex';
            return;
        }

        const delBtn = e.target.closest('.js-delete-travelcost-btn');
        if (!delBtn) return;
        document.getElementById('deleteTravelCostName').textContent = delBtn.dataset.name || 'this entry';
        document.getElementById('deleteTravelCostForm').action = delBtn.dataset.action;
        document.getElementById('deleteTravelCostModal').style.display = 'flex';
    });

    function closeTravelCostModal() {
        document.getElementById('travelCostModal').style.display = 'none';
    }
    function closeDeleteTravelCostModal() {
        document.getElementById('deleteTravelCostModal').style.display = 'none';
    }

    // ── Sorting without a page reload ────────────────────────────────
    // The sort still runs on the server (it has to — ordering the full set
    // and re-paginating can't be done from the 25 rows currently in the DOM),
    // but the response is fetched and the table swapped in place. The links
    // stay real hrefs so middle-click / open-in-new-tab still work.
    function swapTable(url, push) {
        const wrap = document.getElementById('tcTableWrap');
        wrap.classList.add('is-loading');

        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error('Sort request failed');
                return r.text();
            })
            .then(function (html) {
                const fresh = new DOMParser().parseFromString(html, 'text/html')
                                             .getElementById('tcTableWrap');
                if (!fresh) throw new Error('Table missing from response');
                wrap.innerHTML = fresh.innerHTML;
                if (push) history.pushState({ tcUrl: url }, '', url);
            })
            .catch(function () {
                // Never strand the admin on a half-updated table — fall back
                // to a normal navigation if the fetch or parse fails.
                window.location.href = url;
            })
            .finally(function () {
                wrap.classList.remove('is-loading');
            });
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('#tcTableWrap .admin-sort, #tcTableWrap .admin-pagination a');
        if (!link || !link.href) return;
        // Leave modified clicks to the browser (new tab, download, etc).
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
        e.preventDefault();
        swapTable(link.href, true);
    });

    // Back/forward through sorted states rather than reloading the page.
    window.addEventListener('popstate', function () {
        swapTable(window.location.href, false);
    });
</script>
@endsection
