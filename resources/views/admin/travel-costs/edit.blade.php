@extends('layouts.admin')
@section('content')
<a href="{{ route('admin.travel-costs.index') }}" class="admin-back-link"><i class="fa-solid fa-arrow-left"></i> Back</a>
<div class="admin-card admin-form-card">
    <h1>Edit: {{ $destination->destination }}</h1>
    <form method="POST" action="{{ route('admin.travel-costs.update', $destination) }}">
        @csrf @method('PUT')
        <div class="admin-form-group">
            <label>Destination Name *</label>
            <input type="text" name="destination" value="{{ old('destination', $destination->destination) }}" class="admin-input" required>
            @error('destination')<div class="admin-form-error">{{ $message }}</div>@enderror
        </div>
        <div class="admin-form-group">
            <label>Cost Level *</label>
            <select name="cost_level" class="admin-input" required>
                @foreach($costLevels as $level)
                    <option value="{{ $level }}" {{ old('cost_level', $destination->cost_level) === $level ? 'selected' : '' }}>{{ $level }}</option>
                @endforeach
            </select>
        </div>
        <div class="admin-form-group">
            <label>Multiplier *</label>
            <input type="number" step="0.001" name="multiplier" value="{{ old('multiplier', $destination->multiplier) }}" class="admin-input" required>
        </div>
        <div class="admin-form-group">
            <label>Category</label>
            <input type="text" name="category" value="{{ old('category', $destination->category) }}" class="admin-input">
        </div>
        <div class="admin-form-group">
            <label>Image URL</label>
            <input type="url" name="image_url" value="{{ old('image_url', $destination->image_url) }}" class="admin-input">
        </div>
        <div class="admin-form-group">
            <label>Description</label>
            <textarea name="description" class="admin-input" rows="3">{{ old('description', $destination->description) }}</textarea>
        </div>
        <button type="submit" class="admin-btn admin-btn-primary">Update</button>
    </form>
</div>
@endsection
