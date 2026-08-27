@extends('layouts.app')
@section('title', 'Traveler Reviews')
@section('content')
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <h1>Traveler Reviews</h1>
    <button onclick="document.getElementById('review-modal').style.display='flex'" class="btn btn-primary">+ Write Review</button>
</div>

<form method="GET" style="margin-bottom:1rem;">
    <select name="destination" class="form-control" style="width:auto;display:inline-block;" onchange="this.form.submit()">
        <option value="">All Destinations</option>
        @foreach($destinations as $d)
            <option value="{{ $d }}" {{ request('destination') === $d ? 'selected' : '' }}>{{ $d }}</option>
        @endforeach
    </select>
</form>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

@forelse($reviews as $review)
    <div class="card" style="margin-bottom:1rem;">
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;">
                <div>
                    <strong>{{ $review->destination }}</strong>
                    <span style="color:#f5a623;margin-left:8px;">{{ str_repeat('★',$review->rating) }}{{ str_repeat('☆',5-$review->rating) }}</span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;">
                    <small style="color:var(--muted);">{{ $review->user->full_name }} &bull; {{ $review->created_at->diffForHumans() }}</small>
                    @if ($review->user_id !== auth()->id())
                        @if ($review->flagged_by === auth()->id())
                        <span style="color:var(--danger, #DC2626);font-size:12px;" title="You flagged this review"><i class="fa-solid fa-flag"></i></span>
                        @else
                        <details style="position:relative;">
                            <summary style="list-style:none;cursor:pointer;color:var(--muted);font-size:12px;" title="Report this review"><i class="fa-regular fa-flag"></i></summary>
                            <div style="position:absolute;right:0;top:20px;z-index:20;background:var(--bg-white);border:1.5px solid var(--border);border-radius:10px;padding:6px;box-shadow:0 8px 24px rgba(0,0,0,.12);display:flex;flex-direction:column;gap:2px;min-width:170px;">
                                <form method="POST" action="{{ route('reviews.flag', $review) }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="reason" value="inappropriate">
                                    <button type="submit" style="width:100%;text-align:left;background:none;border:none;padding:7px 10px;border-radius:6px;font-size:12.5px;color:var(--dark);cursor:pointer;">Inappropriate</button>
                                </form>
                                <form method="POST" action="{{ route('reviews.flag', $review) }}" style="margin:0;">
                                    @csrf
                                    <input type="hidden" name="reason" value="improvement">
                                    <button type="submit" style="width:100%;text-align:left;background:none;border:none;padding:7px 10px;border-radius:6px;font-size:12.5px;color:var(--dark);cursor:pointer;">Suggest info update</button>
                                </form>
                            </div>
                        </details>
                        @endif
                    @endif
                </div>
            </div>
            <p style="margin-top:.5rem;">{{ $review->body }}</p>
        </div>
    </div>
@empty
    <p>No reviews yet. Be the first to share your experience!</p>
@endforelse
{{ $reviews->links() }}

{{-- Submit review modal — auto-opens when arriving with ?write=1 (the
     "Write a review for X" link from Moments), or if a submission just
     failed validation, so the traveler doesn't lose their place. --}}
<div id="review-modal" style="display:{{ (request('write') || $errors->any()) ? 'flex' : 'none' }};position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);justify-content:center;align-items:center;z-index:1000;">
    <div class="card" style="width:500px;max-width:90%;">
        <div class="card-body">
            <h3>Write a Review</h3>
            <form method="POST" action="{{ route('reviews.store') }}">
                @csrf
                <div class="form-group">
                    <label>Destination</label>
                    {{-- Free text, not a fixed list — this modal is also
                         reached from Moments, where a traveler can pin
                         (and now review) any place they typed themselves,
                         not just ones already in DestinationCost. --}}
                    <input type="text" name="destination" class="form-control"
                           value="{{ old('destination', request('destination')) }}"
                           placeholder="e.g. Boracay" required>
                    @error('destination')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Rating</label>
                    <select name="rating" class="form-control" required>
                        @for($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" {{ old('rating') == $i ? 'selected' : '' }}>{{ $i }} Star{{ $i > 1 ? 's' : '' }}</option>
                        @endfor
                    </select>
                    @error('rating')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Your Experience</label>
                    <textarea name="body" class="form-control" rows="4" minlength="10" required>{{ old('body') }}</textarea>
                    @error('body')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary">Submit</button>
                    <button type="button" onclick="document.getElementById('review-modal').style.display='none'" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
