@extends('layouts.admin')
@section('content')
<a href="{{ route('admin.attractions.index') }}" class="admin-back-link"><i class="fa-solid fa-arrow-left"></i> Back</a>
<div class="admin-card admin-form-card">
    <h1>Edit: {{ $attraction->name }}</h1>
    <form method="POST" action="{{ route('admin.attractions.update', $attraction) }}" enctype="multipart/form-data">
        @csrf @method('PUT')
        <div class="admin-form-group"><label>Destination *</label><input type="text" name="destination" value="{{ old('destination', $attraction->destination) }}" class="admin-input" required></div>
        <div class="admin-form-group"><label>Name *</label><input type="text" name="name" value="{{ old('name', $attraction->name) }}" class="admin-input" required></div>
        <div class="admin-form-group"><label>Category</label><input type="text" name="category" value="{{ old('category', $attraction->category) }}" class="admin-input"></div>
        <div class="admin-form-group">
            <label>Region *</label>
            <select name="region" class="admin-input" required>
                <option value="local" {{ old('region', $attraction->region) === 'local' ? 'selected' : '' }}>Local</option>
                <option value="international" {{ old('region', $attraction->region) === 'international' ? 'selected' : '' }}>International</option>
            </select>
        </div>
        <div class="admin-form-group"><label>Rating (0–5)</label><input type="number" step="0.1" min="0" max="5" name="rating" value="{{ old('rating', $attraction->rating) }}" class="admin-input"></div>
        <div class="admin-form-group">
            <label>Estimated Cost (₱ per person)</label>
            <input type="number" step="0.01" min="0" name="estimated_cost" value="{{ old('estimated_cost', $attraction->estimated_cost) }}" class="admin-input">
            <small class="admin-form-hint">Typical per-person cost to visit — used to compute the "cost matched estimate" figure against traveler reviews.</small>
        </div>
        <div class="admin-form-group"><label>Description</label><textarea name="description" class="admin-input" rows="3">{{ old('description', $attraction->description) }}</textarea></div>
        <div class="admin-form-group">
            <label>Featured Image (leave blank to keep existing)</label>
            @if($attraction->image)
                <img src="{{ asset('storage/'.$attraction->image) }}" style="height:80px;border-radius:8px;display:block;margin-bottom:8px;" alt="{{ $attraction->name }}">
            @endif
            <label class="admin-file-drop">
                <input type="file" name="image" accept="image/*" style="display:none;" onchange="this.closest('.admin-file-drop').querySelector('span').textContent = this.files[0]?.name || 'Click to upload or drag and drop';">
                <i class="fa-solid fa-upload"></i>
                <span>Click to upload or drag and drop</span>
                <small>PNG, JPG up to 10MB</small>
            </label>
        </div>
        <button type="submit" class="admin-btn admin-btn-primary">Update</button>
    </form>
</div>
@endsection
