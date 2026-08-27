@extends('layouts.app')
@section('title', 'New Savings Goal')
@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;" class="mb-24">
    <h1>New Savings Goal</h1>
    <a href="{{ route('savings.index') }}" class="btn btn-outline">← Back</a>
</div>

@if ($errors->any())
<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card" style="max-width:540px;">
    <form method="POST" action="{{ route('savings.store') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="goal_name">Goal Name</label>
            <input id="goal_name" type="text" name="goal_name"
                   value="{{ old('goal_name') }}"
                   class="form-control {{ $errors->has('goal_name') ? 'is-invalid' : '' }}"
                   placeholder="e.g. Boracay Trip Fund" required autofocus>
            @error('goal_name')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group">
                <label class="form-label" for="target_amount">Target Amount ({{ currency_symbol() }})</label>
                <input id="target_amount" type="number" step="0.01" name="target_amount"
                       value="{{ old('target_amount') }}"
                       class="form-control {{ $errors->has('target_amount') ? 'is-invalid' : '' }}"
                       placeholder="50000" min="1" required>
                @error('target_amount')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="current_savings">Current Savings ({{ currency_symbol() }})</label>
                <input id="current_savings" type="number" step="0.01" name="current_savings"
                       value="{{ old('current_savings', 0) }}"
                       class="form-control" placeholder="0.00" min="0">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="deadline">Deadline</label>
            <input id="deadline" type="date" name="deadline"
                   value="{{ old('deadline') }}"
                   class="form-control {{ $errors->has('deadline') ? 'is-invalid' : '' }}"
                   min="{{ date('Y-m-d', strtotime('+1 day')) }}" max="2099-12-31" required>
            @error('deadline')<div class="error">{{ $message }}</div>@enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="trip_id">Link to Trip (optional)</label>
            <select id="trip_id" name="trip_id" class="form-control">
                <option value="">— No trip —</option>
                @foreach ($trips as $trip)
                <option value="{{ $trip->id }}" {{ old('trip_id') == $trip->id ? 'selected' : '' }}>
                    {{ $trip->destination }} ({{ $trip->start_date->format('M Y') }})
                </option>
                @endforeach
            </select>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;" class="mt-8">
            <a href="{{ route('savings.index') }}" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Create Goal
            </button>
        </div>
    </form>
</div>

@endsection
