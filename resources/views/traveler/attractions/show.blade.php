@extends('layouts.app')
@section('title', $attraction->name)

@push('styles')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
@endpush

@section('content')

@php
    $categoryMeta = [
        'Culture'   => ['icon' => 'fa-landmark',      'color' => '#C084FC'],
        'Nature'    => ['icon' => 'fa-leaf',          'color' => '#4ADE80'],
        'Adventure' => ['icon' => 'fa-person-hiking', 'color' => '#FB923C'],
        'Shopping'  => ['icon' => 'fa-bag-shopping',  'color' => '#60A5FA'],
    ][$attraction->category] ?? ['icon' => 'fa-map-pin', 'color' => 'var(--primary)'];
@endphp

<a href="{{ route('attractions.index') }}" class="atd-back">
    <i class="fa-solid fa-arrow-left"></i> All Attractions
</a>

{{-- Hero photo --}}
<div class="atd-hero">
    @if ($attraction->image)
    <img src="{{ asset('storage/' . $attraction->image) }}" alt="{{ $attraction->name }}" class="atd-hero-img">
    @else
    <div class="atd-hero-noimg"><i class="fa-solid fa-image"></i></div>
    @endif

    <div class="atd-hero-overlay">
        <div class="atd-hero-eyebrow">Attraction · {{ $attraction->destination }}</div>
        <h1 class="atd-hero-title">{{ $attraction->name }}</h1>
    </div>

    @if ($attraction->category)
    <span class="atd-chip atd-chip-category" style="--chip-color:{{ $categoryMeta['color'] }};">
        <i class="fa-solid {{ $categoryMeta['icon'] }}"></i> {{ $attraction->category }}
    </span>
    @endif
</div>

@php
    $ratingCounts = $reviews->countBy('rating');
    $totalReviews = $reviews->count();
@endphp

{{-- Rating summary — floats up over the bottom of the hero photo --}}
@if ($totalReviews > 0)
<div class="atd-rev-summary">
    <div class="atd-rev-score">
        <div class="atd-rev-score-num">{{ number_format($avgRating, 1) }}</div>
        <div class="atd-review-stars" style="font-size:16px;">
            @for ($i = 1; $i <= 5; $i++)
                <i class="fa-{{ $i <= round($avgRating) ? 'solid' : 'regular' }} fa-star"></i>
            @endfor
        </div>
        <div class="atd-rev-score-count">{{ $totalReviews }} {{ \Illuminate\Support\Str::plural('rating', $totalReviews) }}</div>
    </div>
    <div class="atd-rev-bars">
        @for ($star = 5; $star >= 1; $star--)
        @php
            $count = $ratingCounts[$star] ?? 0;
            $pct   = $totalReviews > 0 ? round($count / $totalReviews * 100) : 0;
        @endphp
        <div class="atd-rev-bar-row">
            <span>{{ $star }}</span>
            <div class="atd-rev-bar-track"><div class="atd-rev-bar-fill" style="width:{{ $pct }}%;"></div></div>
            <span>{{ $count }}</span>
        </div>
        @endfor
    </div>
</div>
@endif

{{-- Reviews --}}
<div x-data="{ showReviewForm: false }">
<div class="mb-24" id="reviews">
    <div class="atd-rev-controls">
        <h2 class="atd-section-title" style="margin:0;">Reviews</h2>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            @if ($totalReviews > 1)
            <select class="atd-rev-sort" id="atdReviewSort" onchange="atdSortReviews(this.value)">
                <option value="recent">Sort: Most recent</option>
                <option value="highest">Highest rated</option>
                <option value="lowest">Lowest rated</option>
            </select>
            <select class="atd-rev-sort" id="atdReviewFilter" onchange="atdFilterReviews(this.value)">
                <option value="">Trip type: All</option>
                <option value="Solo">Solo</option>
                <option value="Couple">Couple</option>
                <option value="Family">Family</option>
                <option value="Barkada">Barkada / Group</option>
            </select>
            @endif
            @auth
            <button type="button" class="atd-rev-write-btn" @click="showReviewForm = true">
                Write a review
            </button>
            @endauth
        </div>
    </div>

    <div class="atd-reviews-list" id="atdReviewsList">
    @forelse ($reviews as $review)
    @php
        $initials = collect(explode(' ', $review->user->full_name ?? 'Traveler'))->filter()->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode('');
        $isMine = $review->user_id === auth()->id();
        $whoLabel = trim($review->user->full_name ?? 'Traveler');
        // Once a review has actually been edited, show the edit date (not
        // the original post date frozen forever) — updated_at only differs
        // from created_at once an update() has actually run.
        $wasEdited = $review->updated_at->ne($review->created_at);
        $displayDate = $wasEdited ? $review->updated_at : $review->created_at;
    @endphp
    <div class="atd-review-row" x-data="{ editing: false, expanded: false, deleting: false }"
         data-rating="{{ $review->rating }}" data-time="{{ $review->created_at->timestamp }}" data-trip-type="{{ $review->trip_type }}">
        @if ($review->user?->profile_photo)
        <img src="{{ Illuminate\Support\Facades\Storage::url($review->user->profile_photo) }}" alt="{{ $review->user->full_name }}" class="atd-review-avatar atd-review-avatar-img">
        @else
        <div class="atd-review-avatar">{{ strtoupper($initials) }}</div>
        @endif

        <div style="min-width:0;">
            <div class="atd-review-row-head">
                <div>
                    <span class="atd-review-name">{{ $whoLabel }}</span>
                </div>
                <span class="atd-review-date" title="{{ $wasEdited ? 'Edited' : 'Posted' }} {{ $displayDate->format('M j, Y g:i A') }}">
                    {{ $displayDate->format('M j, Y') }}{{ $wasEdited ? ' (edited)' : '' }}
                </span>
            </div>
            <div class="atd-review-stars" style="margin:4px 0 8px;">
                @for ($i = 1; $i <= 5; $i++)
                    <i class="fa-{{ $i <= $review->rating ? 'solid' : 'regular' }} fa-star"></i>
                @endfor
            </div>
            <p class="atd-review-body" :class="{ 'atd-review-body-clamped': !expanded }">{{ $review->body }}</p>
            <button type="button" class="atd-review-more" @click="expanded = !expanded" x-text="expanded ? 'Show less' : 'Read more'"></button>

            @if ($isMine)
            <div class="atd-modal-backdrop" x-show="editing" x-cloak style="display:none;"
                 @click="if (event.target === $el) editing = false" @keydown.escape.window="editing = false">
                <div class="atd-modal-card">
                    <div class="atd-modal-header">
                        <div class="atd-modal-icon"><i class="fa-solid fa-pen"></i></div>
                        <span class="atd-modal-title">Edit Your Review</span>
                    </div>
                    <form method="POST" action="{{ route('reviews.update', $review) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="attraction_id" value="{{ $attraction->id }}">

                        <div class="form-group">
                            <label class="form-label">Your Rating</label>
                            <div class="atd-star-input">
                                @for ($i = 5; $i >= 1; $i--)
                                <input type="radio" name="rating" id="edit-star{{ $review->id }}-{{ $i }}" value="{{ $i }}" {{ $review->rating == $i ? 'checked' : '' }}>
                                <label for="edit-star{{ $review->id }}-{{ $i }}" title="{{ $i }} star"><i class="fa-solid fa-star"></i></label>
                                @endfor
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Your Review</label>
                            <textarea name="body" class="form-control atd-textarea" rows="4" minlength="10" required>{{ $review->body }}</textarea>
                        </div>

                        <button type="submit" class="atd-submit-btn">Save Changes</button>
                        <button type="button" class="atd-modal-cancel-btn" @click="editing = false">Close</button>
                    </form>
                </div>
            </div>

            <div class="atd-modal-backdrop" x-show="deleting" x-cloak style="display:none;"
                 @click="if (event.target === $el) deleting = false" @keydown.escape.window="deleting = false">
                <div class="atd-modal-card" style="max-width:380px;text-align:center;">
                    <div style="width:56px;height:56px;border-radius:16px;background:rgba(226,58,78,.12);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
                        <i class="fa-solid fa-trash-can" style="font-size:22px;color:#E23A4E;"></i>
                    </div>
                    <h2 class="atd-section-title" style="margin:0 0 8px;">Delete Review?</h2>
                    <p style="font-size:13px;color:var(--muted);line-height:1.6;margin:0 0 20px;">This will permanently delete your review. This action cannot be undone.</p>
                    <form method="POST" action="{{ route('reviews.destroy', $review) }}">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="attraction_id" value="{{ $attraction->id }}">
                        <button type="submit" class="atd-delete-confirm-btn">Delete Review</button>
                        <button type="button" class="atd-modal-cancel-btn" @click="deleting = false">Cancel</button>
                    </form>
                </div>
            </div>
            @endif

            <div class="atd-review-foot">
                @if ($isMine)
                <button type="button" class="atd-foot-link" @click="editing = !editing" x-text="editing ? 'Cancel' : 'Edit'"></button>
                <button type="button" class="atd-foot-link atd-foot-link-danger" @click="deleting = true">Delete</button>
                @else
                    @if ($review->flagged_by === auth()->id())
                    <span class="atd-review-flagged" title="You flagged this review"><i class="fa-solid fa-flag"></i> Flagged</span>
                    @else
                    <details class="atd-review-flag">
                        <summary class="atd-foot-link" title="Report this review"><i class="fa-solid fa-flag"></i> Report</summary>
                        <div class="atd-review-flag-menu">
                            <form method="POST" action="{{ route('reviews.flag', $review) }}">
                                @csrf
                                <input type="hidden" name="reason" value="inappropriate">
                                <button type="submit">Inappropriate</button>
                            </form>
                            <form method="POST" action="{{ route('reviews.flag', $review) }}">
                                @csrf
                                <input type="hidden" name="reason" value="improvement">
                                <button type="submit">Suggest info update</button>
                            </form>
                        </div>
                    </details>
                    @endif
                @endif
            </div>
        </div>
    </div>
    @empty
    <div class="atd-empty">
        <div class="atd-empty-icon"><i class="fa-regular fa-comment-dots"></i></div>
        <p>No reviews yet. Be the first!</p>
    </div>
    @endforelse
    </div>
</div>

{{-- Write-a-review modal — hidden until the button above is clicked --}}
@php
    // Traveler already has a review for this attraction — reuse the same
    // "Write a review" button/modal to EDIT it instead of creating a
    // second, duplicate review (store() has no dupe guard, so silently
    // letting this hit the store route would let one traveler stack up
    // multiple reviews on the same attraction).
    $modalRating = $hasReviewed ? $myReview->rating : old('rating');
    $modalBody   = $hasReviewed ? $myReview->body   : old('body');
@endphp
<div class="atd-modal-backdrop" x-show="showReviewForm" x-cloak style="display:none;"
     @click="if (event.target === $el) showReviewForm = false" @keydown.escape.window="showReviewForm = false">
    <div class="atd-modal-card">
        <div class="atd-modal-header">
            <div class="atd-modal-icon"><i class="fa-solid fa-pen"></i></div>
            <span class="atd-modal-title">{{ $hasReviewed ? 'Edit Your Review' : 'Leave a Review' }}</span>
        </div>
        @if ($errors->any())
        <div class="alert alert-danger mb-16">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ $hasReviewed ? route('reviews.update', $myReview) : route('reviews.store') }}">
            @csrf
            @if ($hasReviewed)
            @method('PUT')
            @endif
            <input type="hidden" name="destination"   value="{{ $attraction->destination }}">
            <input type="hidden" name="attraction_id" value="{{ $attraction->id }}">

            <div class="form-group">
                <label class="form-label">Your Rating</label>
                <div class="atd-star-input">
                    @for ($i = 5; $i >= 1; $i--)
                    <input type="radio" name="rating" id="star{{ $i }}" value="{{ $i }}"
                           {{ $modalRating == $i ? 'checked' : '' }}>
                    <label for="star{{ $i }}" title="{{ $i }} star"><i class="fa-solid fa-star"></i></label>
                    @endfor
                </div>
                @error('rating')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Your Review</label>
                <textarea name="body" class="form-control atd-textarea {{ $errors->has('body') ? 'is-invalid' : '' }}"
                          placeholder="Share your experience (min. 10 characters)"
                          rows="4" required>{{ $modalBody }}</textarea>
                @error('body')<div class="error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="atd-submit-btn">
                <i class="fa-solid fa-paper-plane"></i> {{ $hasReviewed ? 'Save Changes' : 'Submit Review' }}
            </button>
            <button type="button" class="atd-modal-cancel-btn" @click="showReviewForm = false">Close</button>
        </form>
    </div>
</div>
</div>

<script>
    function atdSortReviews(mode) {
        const list = document.getElementById('atdReviewsList');
        if (!list) return;
        const items = Array.from(list.children).filter(el => el.classList.contains('atd-review-row'));
        items.sort((a, b) => {
            if (mode === 'highest') return b.dataset.rating - a.dataset.rating;
            if (mode === 'lowest')  return a.dataset.rating - b.dataset.rating;
            return b.dataset.time - a.dataset.time;
        });
        items.forEach(el => list.appendChild(el));
    }

    function atdFilterReviews(type) {
        const list = document.getElementById('atdReviewsList');
        if (!list) return;
        const items = Array.from(list.children).filter(el => el.classList.contains('atd-review-row'));
        items.forEach(el => {
            el.style.display = (!type || el.dataset.tripType === type) ? '' : 'none';
        });
    }
</script>

<style>
    .atd-back {
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 13px; font-weight: 600; color: var(--primary); text-decoration: none;
        background: none; border: none; padding: 0; margin-bottom: 10px;
    }
    .atd-back i { font-size: 11px; }

    .atd-hero {
        position: relative; border-radius: 22px; overflow: hidden;
        height: 420px; min-height: 420px; flex-shrink: 0;
        background: var(--bg);
        box-shadow: 0 12px 32px rgba(0,0,0,0.14);
    }
    .atd-hero-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .atd-hero-noimg {
        position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(150deg, var(--primary) 0%, #C9A84C 100%); color: rgba(255,255,255,.5); font-size: 48px;
    }
    .atd-chip {
        position: absolute; top: 18px; left: 18px; z-index: 2;
        display: inline-flex; align-items: center; gap: 6px;
        font-size: 11.5px; font-weight: 700; letter-spacing: .02em; color: #fff;
        padding: 7px 14px; border-radius: 99px;
        background: color-mix(in srgb, var(--chip-color) 55%, rgba(0,0,0,.35));
        border: 1px solid rgba(255,255,255,.28);
        backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px);
    }

    .atd-hero-overlay {
        position: absolute; inset: 0; z-index: 1;
        display: flex; flex-direction: column; justify-content: flex-end;
        /* Generous bottom padding — the floating rating-summary card below
           pulls up -70px over the hero's bottom edge, so the title needs
           enough clearance to never sit behind it. */
        padding: 28px 32px 110px;
        background: linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,.68) 100%);
    }
    .atd-hero-eyebrow {
        font-family: 'IBM Plex Mono', monospace; font-size: 12px; letter-spacing: .14em; text-transform: uppercase;
        color: rgba(255,255,255,.85); margin-bottom: 8px;
    }
    .atd-hero-title { font-family: 'Fraunces', serif; font-weight: 700; font-size: 36px; line-height: 1.1; color: #fff; margin: 0; }
    @media (max-width: 600px) {
        .atd-hero-overlay { padding: 20px 20px 90px; }
        .atd-hero-title { font-size: 28px; }
    }

    .atd-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px; padding: 22px 24px;
    }
    .atd-section-title { font-size: 18px; font-weight: 800; color: var(--dark); margin: 0 0 10px; }

    .atd-rev-summary {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 18px;
        padding: 26px 28px; display: flex; gap: 36px; align-items: center; flex-wrap: wrap;
        position: relative; z-index: 2; margin: -70px 0 28px;
        box-shadow: 0 16px 40px rgba(0,0,0,0.16);
    }
    @media (max-width: 600px) {
        .atd-rev-summary { margin-top: -40px; }
    }
    .atd-rev-score { text-align: center; min-width: 110px; flex-shrink: 0; }
    .atd-rev-score-num { font-family: 'Fraunces', serif; font-weight: 700; font-size: 44px; line-height: 1; color: var(--dark); }
    .atd-rev-score-count { font-size: 11.5px; color: var(--muted); margin-top: 6px; font-family: 'IBM Plex Mono', monospace; }
    .atd-rev-bars { flex: 1; min-width: 220px; display: flex; flex-direction: column; gap: 7px; }
    .atd-rev-bar-row {
        display: grid; grid-template-columns: 12px 1fr 26px; align-items: center; gap: 10px;
        font-size: 12px; color: var(--muted); font-family: 'IBM Plex Mono', monospace;
    }
    .atd-rev-bar-track { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
    .atd-rev-bar-fill { height: 100%; background: #F5A623; border-radius: 3px; }

    .atd-rev-controls {
        display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
        margin-bottom: 16px;
    }
    .atd-rev-sort {
        font-family: inherit; font-size: 12.5px; font-weight: 600; border: 1.5px solid var(--border);
        background: var(--bg-white); border-radius: 8px; padding: 7px 10px; color: var(--dark); cursor: pointer;
    }
    .atd-rev-write-btn {
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 13px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #fff;
        background: var(--primary); border: none; border-radius: 12px; padding: 12px 22px;
        text-decoration: none; cursor: pointer; font-family: inherit;
        transition: background .18s ease, transform .15s ease;
    }
    .atd-rev-write-btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
    .atd-rev-write-btn:active { transform: translateY(0); }
    .atd-reviews-list { display: flex; flex-direction: column; }
    .atd-review-row {
        display: grid; grid-template-columns: 46px 1fr; gap: 14px;
        padding: 22px 0; border-bottom: 1.5px solid var(--border);
    }
    .atd-review-row:first-child { padding-top: 0; }
    .atd-review-row:last-child { border-bottom: none; padding-bottom: 0; }
    .atd-review-row-head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; flex-wrap: wrap; }

    .atd-review-stars { color: #F5A623; font-size: 14px; letter-spacing: 1.5px; flex-shrink: 0; }
    .atd-review-stars i.fa-regular { color: var(--border); }
    .atd-review-avatar {
        width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
        background: var(--primary); color: #fff; font-family: 'Fraunces', serif; font-size: 16px; font-weight: 700;
        display: flex; align-items: center; justify-content: center;
    }
    .atd-review-avatar-img { object-fit: cover; }
    .atd-review-name { font-size: 14.5px; font-weight: 700; color: var(--dark); font-family: 'Fraunces', serif; }
    .atd-review-date { font-size: 11.5px; color: var(--muted); font-family: 'IBM Plex Mono', monospace; }
    .atd-review-body { font-size: 14px; color: var(--dark); line-height: 1.65; margin: 0; }
    .atd-review-body-clamped {
        display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;
    }
    .atd-review-more {
        background: none; border: none; padding: 0; margin-top: 4px; align-self: flex-start;
        font-size: 13px; font-weight: 700; color: var(--muted); text-decoration: underline; cursor: pointer;
    }
    .atd-review-more:hover { color: var(--primary); }

    /* Every control in this row — <button>, <summary>, <span> — gets the
       identical box: same display, same 18px line-height, same padding and
       border reset. Buttons and summaries each carry their own UA defaults
       for line-height and box model, which is what left Edit and Delete
       sitting a pixel or two apart despite looking like the same element. */
    .atd-review-foot { display: flex; align-items: center; gap: 14px; margin-top: 12px; }
    .atd-foot-link,
    .atd-review-flagged {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 12px; line-height: 18px; height: 18px;
        font-family: inherit; font-weight: 400;
        background: none; border: none; padding: 0; margin: 0;
        list-style: none; vertical-align: middle; box-sizing: content-box;
    }
    /* border-bottom, not text-decoration: underline. Chrome positions an
       auto underline from font metrics per element, and on these 12px flex
       controls it rounded to a different pixel for "Edit" than for "Delete"
       — the boxes measured identical (both top 812.50, height 18.00) while
       the two underlines sat a pixel apart. A border is placed by the box
       model, so it lands in the same place for every control in the row.
       height is 17px + the 1px border = the 18px the shared rule sets. */
    .atd-foot-link {
        color: var(--muted); cursor: pointer;
        border-bottom: 1px solid currentColor; height: 17px;
    }
    .atd-foot-link i { font-size: 10px; }
    .atd-foot-link:hover { color: var(--primary); }
    .atd-foot-link::-webkit-details-marker { display: none; }
    .atd-foot-link-danger { color: #E23A4E; }
    .atd-foot-link-danger:hover { color: #C22B3E; }

    .atd-delete-confirm-btn {
        display: block; width: 100%; background: #E23A4E; color: #fff; border: none; border-radius: 12px;
        padding: 12px 0; font-size: 13px; font-weight: 700; font-family: inherit; cursor: pointer;
        transition: background .18s ease;
    }
    .atd-delete-confirm-btn:hover { background: #C22B3E; }

    .atd-review-flagged { color: var(--danger); }
    .atd-review-flag { position: relative; }
    .atd-review-flag[open] .atd-foot-link { color: var(--danger); }
    .atd-review-flag-menu {
        position: absolute; left: 0; top: 22px; z-index: 20; background: var(--bg-white);
        border: 1.5px solid var(--border); border-radius: 10px; padding: 6px; box-shadow: 0 8px 24px rgba(0,0,0,.12);
        display: flex; flex-direction: column; gap: 2px; min-width: 170px;
    }
    .atd-review-flag-menu form { margin: 0; }
    .atd-review-flag-menu button {
        width: 100%; text-align: left; background: none; border: none; padding: 7px 10px; border-radius: 6px;
        font-size: 12.5px; color: var(--dark); cursor: pointer;
    }
    .atd-review-flag-menu button:hover { background: var(--border-light); }

    .atd-empty {
        text-align: center; padding: 48px 24px; background: var(--bg-white);
        border: 1.5px dashed var(--border); border-radius: 16px;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .atd-empty-icon {
        width: 52px; height: 52px; border-radius: 50%; background: var(--bg); margin: 0 auto 14px;
        display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 20px;
    }
    .atd-empty p { color: var(--muted); font-size: 13.5px; margin: 0; }

    .atd-already-reviewed {
        display: flex; align-items: center; gap: 10px;
        background: var(--primary-light); color: var(--primary); border-radius: 14px;
        padding: 16px 18px; font-size: 13.5px; font-weight: 700;
    }

    .atd-modal-card .form-label {
        font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
        color: var(--muted); margin-bottom: 8px; display: block;
    }
    .atd-modal-card .form-group { margin-bottom: 20px; }
    .atd-modal-card .atd-textarea,
    .atd-modal-card .atd-textinput {
        background: var(--bg); border: 1.5px solid var(--border); border-radius: 12px;
        padding: 12px 14px; font-family: inherit; font-size: 14px; color: var(--dark); width: 100%;
        box-sizing: border-box; transition: border-color .18s ease, background .18s ease;
    }
    .atd-modal-card .atd-textarea:focus,
    .atd-modal-card .atd-textinput:focus {
        outline: none; border-color: var(--primary); background: var(--bg-white);
    }

    .atd-modal-backdrop {
        position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(4px);
        z-index: 2100; display: flex; align-items: center; justify-content: center; padding: 20px;
    }
    .atd-modal-card {
        background: var(--bg-white); border: 1.5px solid var(--border); border-radius: 22px;
        padding: 28px; width: 100%; max-width: 420px; max-height: 90vh; overflow-y: auto;
        box-sizing: border-box; animation: atdModalPop .2s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes atdModalPop { from { opacity: 0; transform: scale(.96) translateY(8px); } to { opacity: 1; transform: none; } }
    .atd-modal-header { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; }
    .atd-modal-icon {
        width: 40px; height: 40px; border-radius: 12px; background: var(--primary); flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; color: #fff; font-size: 16px;
    }
    .atd-modal-title { font-size: 18px; font-weight: 700; color: var(--dark); }
    .atd-modal-cancel-btn {
        display: block; width: 100%; margin-top: 10px; background: transparent; color: var(--dark);
        border: 1.5px solid var(--border); border-radius: 12px; padding: 14px 0; font-size: 13px; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase; font-family: inherit; cursor: pointer;
        transition: background .18s ease, border-color .18s ease;
    }
    .atd-modal-cancel-btn:hover { background: var(--bg); border-color: var(--muted); }
    .atd-submit-btn {
        display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        width: 100%; background: var(--primary); color: #fff; border: none; border-radius: 12px;
        padding: 14px 20px; font-size: 13px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
        cursor: pointer; transition: background .18s ease, transform .15s ease;
    }
    .atd-submit-btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
    .atd-submit-btn:active { transform: translateY(0); }

    /* Interactive star-rating picker: markup is 5→1 in DOM order so the
       CSS sibling-combinator hover/checked trick can light up "this star
       and everything before it" despite visually displaying 1→5. */
    .atd-star-input { display: flex; flex-direction: row-reverse; justify-content: flex-end; gap: 6px; }
    .atd-star-input input { position: absolute; opacity: 0; width: 0; height: 0; }
    .atd-star-input label {
        font-size: 28px; color: var(--border); cursor: pointer; transition: color .12s ease, transform .12s ease;
    }
    .atd-star-input label:hover,
    .atd-star-input label:hover ~ label,
    .atd-star-input input:checked ~ label { color: #F5A623; }
    .atd-star-input label:active { transform: scale(1.15); }

    .atd-textarea { resize: vertical; }
</style>

@endsection
