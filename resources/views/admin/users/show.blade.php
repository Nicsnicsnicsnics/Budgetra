@extends('layouts.admin')
@section('title', 'User Detail')
@section('content')
<a href="{{ route('admin.users.index') }}">&larr; Back</a>
<h1>{{ $user->full_name }}</h1>
<p>{{ $user->email }} &bull; {{ $user->phone ?? '—' }} &bull; {{ $user->country ?? '—' }} &bull; Role: <strong>{{ $user->role }}</strong></p>
<h3>Trips ({{ $user->trips->count() }})</h3>
@forelse($user->trips as $trip)
    <p>{{ $trip->destination }} ({{ $trip->start_date }} – {{ $trip->end_date }})</p>
@empty <p>None.</p>
@endforelse
<h3>Reviews ({{ $user->reviews->count() }})</h3>
@forelse($user->reviews as $review)
    <p>{{ $review->destination }} — {{ $review->rating }}&#9733; — <em>{{ $review->status }}</em></p>
@empty <p>None.</p>
@endforelse
@endsection
