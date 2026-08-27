@extends('layouts.app')
@section('title', 'Plan a Trip')
@section('content')
<h1>What kind of trip are you planning?</h1>
<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1rem;max-width:600px;margin-top:1.5rem;">
    @foreach (['Solo','Group'] as $type)
        <a href="{{ route('trips.create') }}?type={{ $type }}"
           class="card" style="text-align:center;padding:2rem;text-decoration:none;color:inherit;">
            <div class="card-body">
                <h3>{{ $type }}</h3>
            </div>
        </a>
    @endforeach
</div>
@endsection
