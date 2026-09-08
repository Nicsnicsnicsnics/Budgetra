@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>Tourist Attractions</h1>
        <p>Global overview and budgetary management for curated points of interest.</p>
    </div>
    <div class="admin-page-head-actions">
        <button type="button" class="admin-btn admin-btn-primary js-add-attraction-btn"><i class="fa-solid fa-plus"></i> Add Attraction</button>
    </div>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="admin-alert-error">{{ session('error') }}</div>@endif

<div class="admin-tabs">
    <a href="{{ route('admin.attractions.index', ['category' => request('category')]) }}" class="admin-tab {{ request('region') ? '' : 'active' }}">All Regions</a>
    <a href="{{ route('admin.attractions.index', ['region' => 'local', 'category' => request('category')]) }}" class="admin-tab {{ request('region') === 'local' ? 'active' : '' }}">Local</a>
    <a href="{{ route('admin.attractions.index', ['region' => 'international', 'category' => request('category')]) }}" class="admin-tab {{ request('region') === 'international' ? 'active' : '' }}">International</a>
</div>

<div class="admin-tabs">
    <a href="{{ route('admin.attractions.index', ['region' => request('region')]) }}" class="admin-tab {{ request('category') ? '' : 'active' }}">All Categories</a>
    @foreach($categories as $cat)
        <a href="{{ route('admin.attractions.index', ['category' => $cat, 'region' => request('region')]) }}" class="admin-tab {{ request('category') === $cat ? 'active' : '' }}">{{ $cat }}</a>
    @endforeach
</div>

<div class="admin-card-grid">
    @forelse($attractions as $attr)
    <div class="admin-attr-card">
        <div class="admin-attr-card-img" style="{{ $attr->image ? 'background-image:url(' . asset('storage/' . $attr->image) . ')' : '' }}">
            @unless($attr->image)
                <i class="fa-solid fa-image"></i>
            @endunless
            <button type="button" class="admin-attr-card-edit js-edit-attraction-btn"
                data-action="{{ route('admin.attractions.update', $attr) }}"
                data-fetch-action="{{ route('admin.attractions.fetch-image', $attr) }}"
                data-id="{{ $attr->id }}"
                data-name="{{ $attr->name }}"
                data-destination="{{ $attr->destination }}"
                data-category="{{ $attr->category }}"
                data-region="{{ $attr->region }}"
                data-rating="{{ $attr->rating }}"
                data-estimated-cost="{{ $attr->estimated_cost }}"
                data-description="{{ $attr->description }}"
                data-image="{{ $attr->image ? asset('storage/' . $attr->image) : '' }}"
                title="Edit"><i class="fa-solid fa-pen"></i></button>
        </div>
        <div class="admin-attr-card-body">
            <div class="admin-card-head">
                <h3>{{ $attr->name }}</h3>
                <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end;">
                    <span class="admin-badge admin-badge-outline">{{ $attr->region === 'local' ? 'LOCAL' : 'INTERNATIONAL' }}</span>
                    @if($attr->category)<span class="admin-badge admin-badge-outline">{{ strtoupper($attr->category) }}</span>@endif
                </div>
            </div>
            <div class="admin-dest-card-country"><i class="fa-solid fa-location-dot"></i> {{ $attr->destination }}</div>
            <p>{{ $attr->description ?: 'No description yet.' }}</p>
            <div class="admin-attr-card-foot">
                <span>{{ $attr->rating ? number_format($attr->rating, 1) . ' ★' : 'Unrated' }}</span>
                <button type="button" class="admin-icon-btn admin-icon-btn-danger js-delete-attraction-btn"
                    data-action="{{ route('admin.attractions.destroy', $attr) }}"
                    data-name="{{ $attr->name }}"
                    title="Delete"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    </div>
    @empty
    <div class="admin-empty-state">No attractions found.</div>
    @endforelse
</div>
<div class="admin-pagination">{{ $attractions->links() }}</div>

{{-- Add / Edit modal — one form for both, since the two operations take the
     exact same seven fields. Mode only changes the header, the action and the
     spoofed method. Keeping them apart is what let the edit dialog drift out
     of sync with the create page and lose the Estimated Cost field. --}}
<div id="attractionModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeAttractionModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <div class="admin-modal-icon"><i class="fa-solid fa-pen" id="attrModalIcon"></i></div>
            <h3 class="admin-modal-title" id="attrModalTitle">Edit Attraction</h3>
            {{-- Filled in with the attraction's name so the dialog says which
                 record is being edited without a second heading. --}}
            <p class="admin-modal-sub" id="attrModalSub"></p>
            {{-- No corner ✕: Cancel in the actions row already closes this,
                 and so do Escape and a click on the scrim (both from the admin
                 layout). A third control for the same thing is just clutter. --}}
        </div>
        <form id="attractionForm" method="POST" enctype="multipart/form-data" class="admin-modal-form"
              data-store-action="{{ route('admin.attractions.store') }}"
              data-base-url="{{ url('admin/attractions') }}">
            @csrf
            <input type="hidden" name="_method" id="attrModalMethod" value="PUT">
            {{-- Carried so a validation bounce can rebuild the update action
                 without the button that opened the dialog. --}}
            <input type="hidden" name="attraction_id" id="attrModalId" value="{{ old('attraction_id') }}">
            <div class="admin-modal-body">
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Attraction Name</label>
                        <input type="text" name="name" id="attrModalName" class="admin-input" value="{{ old('name') }}" required>
                        @error('name')<div class="admin-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="admin-form-group">
                        <label>Destination</label>
                        <input type="text" name="destination" id="attrModalDestination" class="admin-input" value="{{ old('destination') }}" required>
                        @error('destination')<div class="admin-form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Category</label>
                        <input type="text" name="category" id="attrModalCategory" class="admin-input" value="{{ old('category') }}">
                        @error('category')<div class="admin-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="admin-form-group">
                        <label>Region</label>
                        <select name="region" id="attrModalRegion" class="admin-input" required>
                            <option value="local" {{ old('region', 'local') === 'local' ? 'selected' : '' }}>Local</option>
                            <option value="international" {{ old('region') === 'international' ? 'selected' : '' }}>International</option>
                        </select>
                        @error('region')<div class="admin-form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-group">
                        <label>Rating (0–5)</label>
                        <input type="number" step="0.1" min="0" max="5" name="rating" id="attrModalRating" class="admin-input" value="{{ old('rating') }}">
                        @error('rating')<div class="admin-form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="admin-form-group">
                        <label>Estimated Cost ({{ currency_symbol() }} per person)</label>
                        <input type="number" step="0.01" min="0" name="estimated_cost" id="attrModalEstimatedCost" class="admin-input" value="{{ old('estimated_cost') }}">
                        @error('estimated_cost')<div class="admin-form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <small class="admin-form-hint" style="margin-top:-10px;margin-bottom:16px;">Typical per-person cost to visit — used to compute the "cost matched estimate" figure against traveler reviews.</small>
                <div class="admin-form-group">
                    <label>Description</label>
                    <textarea name="description" id="attrModalDescription" class="admin-input" rows="4">{{ old('description') }}</textarea>
                    @error('description')<div class="admin-form-error">{{ $message }}</div>@enderror
                </div>
                <div class="admin-form-group">
                    <label>Featured Image</label>
                    <img id="attrModalImagePreview" class="admin-modal-thumb" src="" alt="" style="display:none;">
                    <label class="admin-file-drop">
                        <input type="file" name="image" accept="image/*" style="display:none;" onchange="this.closest('.admin-file-drop').querySelector('span').textContent = this.files[0]?.name || 'Click to upload or drag and drop';">
                        <i class="fa-solid fa-upload"></i>
                        <span>Click to upload or drag and drop</span>
                        <small>PNG, JPG up to 10MB</small>
                    </label>
                    @error('image')<div class="admin-form-error">{{ $message }}</div>@enderror
                    {{-- Edit only: the endpoint takes an existing {attraction},
                         so there is nothing to fetch a photo for until the
                         record has been saved once. --}}
                    <button type="button" id="attrFetchImageBtn" class="admin-btn admin-btn-outline admin-btn-sm" style="margin-top:8px;">
                        <i class="fa-solid fa-cloud-arrow-down"></i> Fetch Photo via API
                    </button>
                </div>
            </div>
            <div class="admin-modal-actions">
                <button type="button" class="admin-modal-btn admin-modal-btn-cancel" onclick="closeAttractionModal();">Cancel</button>
                <button type="submit" class="admin-modal-btn admin-modal-btn-primary" id="attrModalSubmit">Save Changes</button>
            </div>
        </form>
        <form id="attractionFetchImageForm" method="POST" style="display:none;">@csrf</form>
    </div>
</div>

<div id="deleteAttractionModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteAttractionModal();">
    <div class="admin-modal-card admin-modal-card-sm">
        <div class="admin-modal-head">
            <div class="admin-modal-icon admin-modal-icon-danger"><i class="fa-solid fa-trash-can"></i></div>
            <h3 class="admin-modal-title">Delete Attraction?</h3>
            <p class="admin-modal-sub">
                <strong id="deleteAttractionName"></strong> will be permanently removed.<br>This action cannot be undone.
            </p>
        </div>
        <form id="deleteAttractionForm" method="POST">
            @csrf @method('DELETE')
            <div class="admin-modal-actions">
                <button type="button" class="admin-modal-btn admin-modal-btn-cancel" onclick="closeDeleteAttractionModal();">Cancel</button>
                <button type="submit" class="admin-modal-btn admin-modal-btn-danger"><i class="fa-solid fa-trash"></i> Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const delBtn = e.target.closest('.js-delete-attraction-btn');
        if (delBtn) {
            document.getElementById('deleteAttractionName').textContent = delBtn.dataset.name || 'this attraction';
            document.getElementById('deleteAttractionForm').action = delBtn.dataset.action;
            document.getElementById('deleteAttractionModal').style.display = 'flex';
        }
    });
    function closeDeleteAttractionModal() {
        document.getElementById('deleteAttractionModal').style.display = 'none';
    }
</script>

<script>
    (function () {
        const form    = document.getElementById('attractionForm');
        const fetchBtn = document.getElementById('attrFetchImageBtn');
        const preview  = document.getElementById('attrModalImagePreview');

        function fillAttractionModal(values) {
            document.getElementById('attrModalName').value          = values.name          || '';
            document.getElementById('attrModalDestination').value    = values.destination   || '';
            document.getElementById('attrModalCategory').value       = values.category      || '';
            document.getElementById('attrModalRegion').value         = values.region        || 'local';
            document.getElementById('attrModalRating').value         = values.rating        || '';
            document.getElementById('attrModalEstimatedCost').value  = values.estimatedCost || '';
            document.getElementById('attrModalDescription').value    = values.description   || '';
            // The dropzone keeps the last chosen filename otherwise, and it
            // would be describing a file the reopened dialog no longer holds.
            const drop = form.querySelector('.admin-file-drop');
            drop.querySelector('input[type=file]').value = '';
            drop.querySelector('span').textContent = 'Click to upload or drag and drop';
        }

        // Fetching a photo needs a saved record to hang it on, and the preview
        // has nothing to show before one exists.
        function setMode(isEdit, image) {
            document.getElementById('attrModalMethod').value    = isEdit ? 'PUT' : 'POST';
            document.getElementById('attrModalIcon').className  = isEdit ? 'fa-solid fa-pen' : 'fa-solid fa-plus';
            document.getElementById('attrModalTitle').textContent  = isEdit ? 'Edit Attraction' : 'Add Attraction';
            document.getElementById('attrModalSubmit').textContent = isEdit ? 'Save Changes' : 'Add Attraction';
            fetchBtn.style.display = isEdit ? '' : 'none';

            if (isEdit && image) {
                preview.src = image;
                preview.style.display = 'block';
            } else {
                preview.removeAttribute('src');
                preview.style.display = 'none';
            }
        }

        document.addEventListener('click', function (e) {
            const addBtn = e.target.closest('.js-add-attraction-btn');
            if (addBtn) {
                form.action = form.dataset.storeAction;
                document.getElementById('attrModalId').value  = '';
                document.getElementById('attrModalSub').textContent = 'A new point of interest travelers can browse and budget for.';
                setMode(false, null);
                fillAttractionModal({});
                document.getElementById('attractionModal').style.display = 'flex';
                return;
            }

            const editBtn = e.target.closest('.js-edit-attraction-btn');
            if (!editBtn) return;

            form.action = editBtn.dataset.action;
            document.getElementById('attrModalId').value  = editBtn.dataset.id || '';
            document.getElementById('attrModalSub').textContent = editBtn.dataset.name || '';
            document.getElementById('attractionFetchImageForm').action = editBtn.dataset.fetchAction;
            setMode(true, editBtn.dataset.image);
            fillAttractionModal(editBtn.dataset);
            document.getElementById('attractionModal').style.display = 'flex';
        });

        fetchBtn.addEventListener('click', function () {
            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Fetching...';
            document.getElementById('attractionFetchImageForm').submit();
        });

        window.closeAttractionModal = function () {
            document.getElementById('attractionModal').style.display = 'none';
        };

        @if ($errors->any())
        // A rejected submit lands back on the index, where the dialog would
        // otherwise be closed and every field blank — worse than the page this
        // replaced, which kept the input via old(). The values are already
        // rendered from old(); this only restores the mode and reopens.
        (function () {
            const editingId = @json(old('attraction_id'));
            const isEdit    = @json(old('_method')) === 'PUT' && editingId;

            form.action = isEdit
                ? form.dataset.baseUrl + '/' + editingId
                : form.dataset.storeAction;

            if (isEdit) {
                document.getElementById('attractionFetchImageForm').action =
                    form.dataset.baseUrl + '/' + editingId + '/fetch-image';
            }
            document.getElementById('attrModalSub').textContent = isEdit
                ? (@json(old('name')) || '')
                : 'A new point of interest travelers can browse and budget for.';

            setMode(!!isEdit, null);
            document.getElementById('attractionModal').style.display = 'flex';
        })();
        @endif
    })();
</script>
@endsection
