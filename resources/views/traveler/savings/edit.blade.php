@extends('layouts.app')
@section('title', 'Edit Savings Goal')
@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;" class="mb-24">
    <h1>Edit: {{ $goal->goal_name }}</h1>
    <a href="{{ route('savings.index') }}" class="btn btn-outline">← Back</a>
</div>

@if ($errors->any())
<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card" style="max-width:540px;">
    <form method="POST" action="{{ route('savings.update', $goal) }}">
        @csrf @method('PUT')
        <div class="form-group">
            <label class="form-label" for="goal_name">Goal Name</label>
            <input id="goal_name" type="text" name="goal_name"
                   value="{{ old('goal_name', $goal->goal_name) }}"
                   class="form-control {{ $errors->has('goal_name') ? 'is-invalid' : '' }}" required>
            @error('goal_name')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label" for="target_amount">Target Amount ({{ currency_symbol() }})</label>
                <input id="target_amount" type="number" step="0.01" name="target_amount"
                       value="{{ old('target_amount', $goal->target_amount) }}"
                       class="form-control {{ $errors->has('target_amount') ? 'is-invalid' : '' }}"
                       min="1" required>
                @error('target_amount')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="current_savings">Current Savings ({{ currency_symbol() }})</label>
                <input id="current_savings" type="number" step="0.01" name="current_savings"
                       value="{{ old('current_savings', $goal->current_savings) }}"
                       class="form-control {{ $errors->has('current_savings') ? 'is-invalid' : '' }}" min="0">
                @error('current_savings')<div class="error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="deadline">Deadline</label>
            <input id="deadline" type="date" name="deadline"
                   value="{{ old('deadline', $goal->deadline->format('Y-m-d')) }}" min="2000-01-01" max="2099-12-31"
                   class="form-control {{ $errors->has('deadline') ? 'is-invalid' : '' }}" required>
            @error('deadline')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="trip_id">Link to Trip (optional)</label>
            <select id="trip_id" name="trip_id" class="form-control">
                <option value="">— No trip —</option>
                @foreach ($trips as $trip)
                <option value="{{ $trip->id }}"
                    {{ old('trip_id', $goal->trip_id) == $trip->id ? 'selected' : '' }}>
                    {{ $trip->destination }} ({{ $trip->start_date->format('M Y') }})
                </option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;" class="mt-8">
            <a href="{{ route('savings.index') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Update Goal
            </button>
        </div>
    </form>
</div>

@endsection
