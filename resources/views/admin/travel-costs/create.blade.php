@extends('layouts.admin')
@section('content')
<a href="{{ route('admin.travel-costs.index') }}" class="admin-back-link"><i class="fa-solid fa-arrow-left"></i> Back</a>
<div class="admin-card admin-form-card">
    <h1>Add Travel Cost</h1>
    <form method="POST" action="{{ route('admin.travel-costs.store') }}">
        @csrf
        <div class="admin-form-group">
            <label>Destination Name *</label>
            <input type="text" name="destination" value="{{ old('destination') }}" class="admin-input" required>
            @error('destination')<div class="admin-form-error">{{ $message }}</div>@enderror
        </div>
        <div class="admin-form-group">
            <label>Cost Level *</label>
            <select name="cost_level" class="admin-input" required>
                @foreach($costLevels as $level)
                    <option value="{{ $level }}" {{ old('cost_level') === $level ? 'selected' : '' }}>{{ $level }}</option>
                @endforeach
            </select>
            @error('cost_level')<div class="admin-form-error">{{ $message }}</div>@enderror
        </div>
        <div class="admin-form-group">
            <label>Multiplier * (e.g. 1.000)</label>
            <input type="number" step="0.001" name="multiplier" value="{{ old('multiplier', '1.000') }}" class="admin-input" required>
            @error('multiplier')<div class="admin-form-error">{{ $message }}</div>@enderror
        </div>
        <div class="admin-form-group">
            <label>Category</label>
            <input type="text" name="category" value="{{ old('category') }}" class="admin-input">
        </div>
        <div class="admin-form-group">
            <label>Image URL</label>
            <input type="url" name="image_url" value="{{ old('image_url') }}" class="admin-input">
        </div>
        <div class="admin-form-group">
            <label>Description</label>
            <textarea name="description" class="admin-input" rows="3">{{ old('description') }}</textarea>
        </div>
        <button type="submit" class="admin-btn admin-btn-primary">Save</button>
    </form>
</div>
@endsection
