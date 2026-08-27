@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>Manage Destinations</h1>
        <p>Configure travel data for global locations.</p>
    </div>
    <div class="admin-page-head-actions">
        <form method="GET" class="admin-search-form">
            @if(request('region'))<input type="hidden" name="region" value="{{ request('region') }}">@endif
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search destinations..." class="admin-input">
            <button type="submit" class="admin-btn admin-btn-outline"><i class="fa-solid fa-filter"></i> Search</button>
        </form>
    </div>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="admin-alert-error">{{ session('error') }}</div>@endif

<div class="admin-tabs">
    <a href="{{ route('admin.destinations.index', ['search' => request('search')]) }}" class="admin-tab {{ request('region') ? '' : 'active' }}">All</a>
    <a href="{{ route('admin.destinations.index', ['region' => 'local', 'search' => request('search')]) }}" class="admin-tab {{ request('region') === 'local' ? 'active' : '' }}">Local</a>
    <a href="{{ route('admin.destinations.index', ['region' => 'international', 'search' => request('search')]) }}" class="admin-tab {{ request('region') === 'international' ? 'active' : '' }}">International</a>
</div>

<div class="admin-card-grid">
    @forelse($destinations as $dest)
    <div class="admin-dest-card">
        <div class="admin-dest-card-img" style="{{ $dest->image ? 'background-image:url(' . asset('storage/' . $dest->image) . ')' : '' }}">
            @unless($dest->image)
                <i class="fa-solid fa-image"></i>
            @endunless
        </div>
        <div class="admin-dest-card-body">
            <div class="admin-card-head">
                <h3>{{ $dest->name }}</h3>
                <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                    <span class="admin-badge admin-badge-outline">{{ $dest->region === 'local' ? 'LOCAL' : 'INTERNATIONAL' }}</span>
                    @if($dest->active_trip_count > 0)
                        <span class="admin-badge admin-badge-success">ACTIVE TRIP</span>
                    @endif
                </div>
            </div>
            @if($dest->country)
                <div class="admin-dest-card-country"><i class="fa-solid fa-location-dot"></i> {{ $dest->country }}</div>
            @endif
            <p>{{ $dest->description ?: 'No description yet.' }}</p>
            <div class="admin-dest-card-stat">
                <span>Attractions</span>
                <strong>{{ $dest->attractions_count }} Active</strong>
            </div>
            <div class="admin-dest-card-foot">
                <span class="admin-dest-card-edited">Last edit: {{ $dest->updated_at->diffForHumans() }}</span>
                <div style="display:flex;gap:6px;">
                    <button type="button" class="admin-icon-btn admin-icon-btn-danger js-delete-destination-btn"
                        data-action="{{ route('admin.destinations.destroy', $dest) }}"
                        data-name="{{ $dest->name }}"
                        title="Delete"><i class="fa-solid fa-trash"></i></button>
                    <button type="button" class="admin-btn admin-btn-outline admin-btn-sm js-edit-destination-btn"
                        data-action="{{ route('admin.destinations.update', $dest) }}"
                        data-fetch-action="{{ route('admin.destinations.fetch-image', $dest) }}"
                        data-name="{{ $dest->name }}"
                        data-country="{{ $dest->country }}"
                        data-region="{{ $dest->region }}"
                        data-description="{{ $dest->description }}"
                        data-image="{{ $dest->image ? asset('storage/' . $dest->image) : '' }}">
                        Edit Info
                    </button>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="admin-empty-state">No destinations found.</div>
    @endforelse
</div>
<div class="admin-pagination">{{ $destinations->links() }}</div>

<div id="destinationModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDestinationModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Edit Destination</h3>
            <button type="button" class="admin-modal-close" onclick="closeDestinationModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form id="destinationForm" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="admin-form-group">
                <label>Destination Name</label>
                <input type="text" name="name" id="destModalName" class="admin-input" required>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-group">
                    <label>Country</label>
                    <input type="text" name="country" id="destModalCountry" class="admin-input">
                </div>
                <div class="admin-form-group">
                    <label>Region</label>
                    <select name="region" id="destModalRegion" class="admin-input" required>
                        <option value="local">Local</option>
                        <option value="international">International</option>
                    </select>
                </div>
            </div>
            <div class="admin-form-group">
                <label>Description</label>
                <textarea name="description" id="destModalDescription" class="admin-input" rows="4"></textarea>
            </div>
            <div class="admin-form-group">
                <label>Featured Image</label>
                <img id="destModalImagePreview" src="" alt="" style="display:none;height:70px;border-radius:8px;margin-bottom:8px;">
                <label class="admin-file-drop">
                    <input type="file" name="image" accept="image/*" style="display:none;" onchange="this.closest('.admin-file-drop').querySelector('span').textContent = this.files[0]?.name || 'Click to upload or drag and drop';">
                    <i class="fa-solid fa-upload"></i>
                    <span>Click to upload or drag and drop</span>
                    <small>PNG, JPG up to 10MB</small>
                </label>
                <button type="button" id="destFetchImageBtn" class="admin-btn admin-btn-outline admin-btn-sm" style="margin-top:8px;">
                    <i class="fa-solid fa-cloud-arrow-down"></i> Fetch Photo via API
                </button>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="admin-btn admin-btn-outline" onclick="closeDestinationModal();">Cancel</button>
                <button type="submit" class="admin-btn admin-btn-primary">Save Changes</button>
            </div>
        </form>
        <form id="destinationFetchImageForm" method="POST" style="display:none;">@csrf</form>
    </div>
</div>

<div id="deleteDestinationModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteDestinationModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Delete Destination</h3>
            <button type="button" class="admin-modal-close" onclick="closeDeleteDestinationModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:20px 24px;">
            <p style="margin:0 0 20px;font-size:14px;color:var(--admin-muted, #8A7A6C);line-height:1.6;">
                Delete <strong id="deleteDestinationName" style="color:var(--admin-ink, #241208);"></strong>? This cannot be undone.
            </p>
            <form id="deleteDestinationForm" method="POST">
                @csrf @method('DELETE')
                <div class="admin-modal-actions">
                    <button type="button" class="admin-btn admin-btn-outline" onclick="closeDeleteDestinationModal();">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" style="background:var(--admin-danger, #D64545);border-color:var(--admin-danger, #D64545);">Delete Destination</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const delBtn = e.target.closest('.js-delete-destination-btn');
        if (delBtn) {
            document.getElementById('deleteDestinationName').textContent = delBtn.dataset.name || 'this destination';
            document.getElementById('deleteDestinationForm').action = delBtn.dataset.action;
            document.getElementById('deleteDestinationModal').style.display = 'flex';
        }
    });
    function closeDeleteDestinationModal() {
        document.getElementById('deleteDestinationModal').style.display = 'none';
    }
</script>

<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-edit-destination-btn');
        if (!btn) return;
        document.getElementById('destModalName').value = btn.dataset.name || '';
        document.getElementById('destModalCountry').value = btn.dataset.country || '';
        document.getElementById('destModalRegion').value = btn.dataset.region || 'local';
        document.getElementById('destModalDescription').value = btn.dataset.description || '';
        document.getElementById('destinationForm').action = btn.dataset.action;
        document.getElementById('destinationFetchImageForm').action = btn.dataset.fetchAction;

        const preview = document.getElementById('destModalImagePreview');
        if (btn.dataset.image) {
            preview.src = btn.dataset.image;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }

        document.getElementById('destinationModal').style.display = 'flex';
    });
    function closeDestinationModal() {
        document.getElementById('destinationModal').style.display = 'none';
    }
    document.getElementById('destFetchImageBtn').addEventListener('click', function () {
        this.disabled = true;
        this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching...';
        document.getElementById('destinationFetchImageForm').submit();
    });
</script>
@endsection
