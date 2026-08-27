@extends('layouts.admin')
@section('content')
<a href="{{ route('admin.attractions.index') }}" class="admin-back-link"><i class="fa-solid fa-arrow-left"></i> Back</a>
<div class="admin-card admin-form-card">
    <h1>Add Attraction</h1>
    <form method="POST" action="{{ route('admin.attractions.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="admin-form-group"><label>Destination *</label><input type="text" name="destination" value="{{ old('destination') }}" class="admin-input" required></div>
        <div class="admin-form-group"><label>Name *</label><input type="text" name="name" value="{{ old('name') }}" class="admin-input" required></div>
        <div class="admin-form-group"><label>Category</label><input type="text" name="category" value="{{ old('category') }}" class="admin-input"></div>
        <div class="admin-form-group">
            <label>Region *</label>
            <select name="region" class="admin-input" required>
                <option value="local" {{ old('region', 'local') === 'local' ? 'selected' : '' }}>Local</option>
                <option value="international" {{ old('region') === 'international' ? 'selected' : '' }}>International</option>
            </select>
        </div>
        <div class="admin-form-group"><label>Rating (0–5)</label><input type="number" step="0.1" min="0" max="5" name="rating" value="{{ old('rating') }}" class="admin-input"></div>
        <div class="admin-form-group">
            <label>Estimated Cost (₱ per person)</label>
            <input type="number" step="0.01" min="0" name="estimated_cost" value="{{ old('estimated_cost') }}" class="admin-input">
            <small class="admin-form-hint">Typical per-person cost to visit — used to compute the "cost matched estimate" figure against traveler reviews.</small>
        </div>
        <div class="admin-form-group"><label>Description</label><textarea name="description" class="admin-input" rows="3">{{ old('description') }}</textarea></div>
        <div class="admin-form-group">
            <label>Featured Image</label>
            <label class="admin-file-drop">
                <input type="file" name="image" accept="image/*" style="display:none;" onchange="this.closest('.admin-file-drop').querySelector('span').textContent = this.files[0]?.name || 'Click to upload or drag and drop';">
                <i class="fa-solid fa-upload"></i>
                <span>Click to upload or drag and drop</span>
                <small>PNG, JPG up to 10MB</small>
            </label>
        </div>
        <button type="submit" class="admin-btn admin-btn-primary">Save</button>
    </form>
</div>
@endsection
