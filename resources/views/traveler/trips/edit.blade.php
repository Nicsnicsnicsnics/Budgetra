@extends('layouts.app')
@section('title', 'Edit Trip')
@section('content')
<h1>Edit Trip</h1>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin:0;padding-left:1.2em;">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<div class="card"><div class="card-body">
<form method="POST" action="{{ route('trips.update', $trip) }}">
    @csrf @method('PUT')
    <div class="form-group">
        <label>Destination</label>
        <input type="text" name="destination" class="form-control"
               value="{{ old('destination', $trip->destination) }}" required>
    </div>
    <div class="form-group">
        <label>Start Date</label>
        <input type="date" name="start_date" class="form-control" min="2000-01-01" max="2099-12-31"
               value="{{ old('start_date', $trip->start_date) }}" required>
    </div>
    <div class="form-group">
        <label>End Date</label>
        <input type="date" name="end_date" class="form-control" min="2000-01-01" max="2099-12-31"
               value="{{ old('end_date', $trip->end_date) }}" required>
    </div>
    <div class="form-group">
        <label>Number of Travelers</label>
        <input type="number" name="num_travelers" class="form-control"
               value="{{ old('num_travelers', $trip->num_travelers) }}" min="1" max="50">
    </div>
    <div class="form-group">
        <label>Budget Limit (optional)</label>
        <input type="number" step="0.01" name="budget_limit" class="form-control"
               value="{{ old('budget_limit', $trip->budget_limit) }}">
    </div>
    <div class="form-group">
        <label>Travel Type</label>
        <select name="travel_type" class="form-control" required>
            @foreach (['Solo','Group'] as $t)
                <option value="{{ $t }}"
                    {{ old('travel_type', $trip->travel_type) === $t ? 'selected' : '' }}>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes', $trip->notes) }}</textarea>
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
    <a href="{{ route('trips.show', $trip) }}" class="btn btn-secondary">Cancel</a>
</form>
</div></div>
@endsection
