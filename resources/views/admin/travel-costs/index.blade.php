@extends('layouts.admin')
@section('content')
<style>
    /* Traveler-style modal shell — rounded card, tinted icon header, actions
       row — reused by both the edit and delete dialogs on this page. */
    .tc-modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 2100;
        display: flex; align-items: center; justify-content: center; padding: 20px;
    }
    .tc-modal-card {
        background: var(--bg-white); border-radius: 20px; width: 100%;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2); overflow: hidden;
        max-height: 90vh; display: flex; flex-direction: column;
    }
    .tc-modal-head { background: var(--bg); padding: 28px 24px 20px; text-align: center; flex-shrink: 0; }
    .tc-modal-icon {
        width: 52px; height: 52px; border-radius: 50%; margin: 0 auto 12px;
        display: flex; align-items: center; justify-content: center; font-size: 22px;
    }
    .tc-modal-icon-primary { background: var(--primary-light); color: var(--primary); }
    .tc-modal-icon-danger  { background: rgba(220,38,38,0.12); color: #DC2626; }
    .tc-modal-title { font-size: 17px; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
    .tc-modal-sub   { font-size: 13px; color: var(--muted); line-height: 1.5; }
    /* The form is the flex column so the field area scrolls on short screens
       while the header and actions row stay pinned. */
    .tc-modal-form  { flex: 1; min-height: 0; display: flex; flex-direction: column; }
    .tc-modal-body  { padding: 20px 24px 4px; overflow-y: auto; flex: 1; min-height: 0; }
    .tc-modal-actions { display: flex; gap: 10px; padding: 18px 20px; flex-shrink: 0; }
    .tc-modal-btn {
        flex: 1; border-radius: 10px; padding: 11px 0; font-size: 13px; font-weight: 600;
        cursor: pointer; font-family: inherit;
    }
    .tc-modal-btn-cancel  { background: transparent; color: var(--muted); border: 1.5px solid var(--border); }
    .tc-modal-btn-primary { background: var(--primary); color: #fff; border: none; }
    .tc-modal-btn-danger  { background: #DC2626; color: #fff; border: none; }
</style>

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
<div id="travelCostModal" class="tc-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeTravelCostModal();">
    <div class="tc-modal-card" style="max-width:480px;">
        <div class="tc-modal-head">
            <div class="tc-modal-icon tc-modal-icon-primary"><i class="fa-solid fa-pen" id="tcModalIcon"></i></div>
            <div class="tc-modal-title" id="tcModalTitle">Edit Travel Cost</div>
            <div class="tc-modal-sub" id="tcModalSub"></div>
        </div>
        <form id="travelCostForm" method="POST" class="tc-modal-form">
            @csrf
            <input type="hidden" name="_method" id="tcModalMethod" value="PUT">
            <div class="tc-modal-body">
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
            <div class="tc-modal-actions">
                <button type="button" class="tc-modal-btn tc-modal-btn-cancel" onclick="closeTravelCostModal();">Cancel</button>
                <button type="submit" class="tc-modal-btn tc-modal-btn-primary" id="tcModalSubmit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Delete modal --}}
<div id="deleteTravelCostModal" class="tc-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteTravelCostModal();">
    <div class="tc-modal-card" style="max-width:360px;">
        <div class="tc-modal-head">
            <div class="tc-modal-icon tc-modal-icon-danger"><i class="fa-solid fa-trash-can"></i></div>
            <div class="tc-modal-title">Delete Travel Cost?</div>
            <div class="tc-modal-sub">
                The benchmark for <strong id="deleteTravelCostName" style="color:var(--dark);"></strong> will be permanently deleted.<br>This action cannot be undone.
            </div>
        </div>
        <form id="deleteTravelCostForm" method="POST">
            @csrf @method('DELETE')
            <div class="tc-modal-actions">
                <button type="button" class="tc-modal-btn tc-modal-btn-cancel" onclick="closeDeleteTravelCostModal();">Cancel</button>
                <button type="submit" class="tc-modal-btn tc-modal-btn-danger">Delete</button>
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

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        closeTravelCostModal();
        closeDeleteTravelCostModal();
    });

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
