@extends('layouts.admin')
@section('content')
<div class="admin-page-head">
    <div>
        <h1>User Reviews</h1>
        <p>Moderate reviews travelers have left on attractions.</p>
    </div>
</div>
@if(session('success'))<div class="admin-alert-success">{{ session('success') }}</div>@endif

<div class="admin-tabs">
    <a href="{{ route('admin.reviews.index') }}" class="admin-tab {{ request('flagged') ? '' : 'active' }}">All</a>
    <a href="{{ route('admin.reviews.index', ['flagged' => 1]) }}" class="admin-tab {{ request('flagged') ? 'active' : '' }}">Flagged</a>
</div>

<div style="display:flex;flex-direction:column;gap:14px;">
    @forelse($reviews as $review)
    <div class="admin-card" style="padding:18px 22px;{{ $review->status === 'hidden' ? 'opacity:.6;' : '' }}{{ $review->flag_reason ? 'border-color:var(--admin-danger, #D64545);' : '' }}">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
            <div style="flex:1;min-width:240px;">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                    <strong style="font-size:14px;color:var(--admin-ink);">{{ $review->attraction->name ?? $review->destination }}</strong>
                    <span style="color:#F59E0B;font-size:13px;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                    @if($review->flag_reason)
                        <span class="admin-badge admin-badge-danger">
                            <i class="fa-solid fa-flag"></i>
                            {{ $review->flag_reason === 'inappropriate' ? 'FLAGGED: INAPPROPRIATE' : 'FLAGGED: SUGGESTS INFO UPDATE' }}
                        </span>
                    @endif
                </div>
                <p style="margin:0 0 8px;font-size:14px;color:var(--admin-ink);line-height:1.6;">{{ $review->body }}</p>
                <small style="color:var(--admin-muted);font-size:12px;">
                    {{ $review->user->full_name ?? 'Traveler' }} &bull; {{ $review->created_at->format('M j, Y') }}
                    @if($review->flag_reason)
                        &bull; flagged by {{ $review->flagger->full_name ?? 'a traveler' }} {{ $review->flagged_at?->diffForHumans() }}
                    @endif
                </small>
            </div>
            <div style="flex-shrink:0;display:flex;gap:6px;">
                @if($review->flag_reason)
                    <form method="POST" action="{{ route('admin.reviews.unflag', $review) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fa-solid fa-flag"></i> Unflag</button>
                    </form>
                @endif
                <button type="button" class="admin-icon-btn admin-icon-btn-danger js-delete-review-btn"
                    data-action="{{ route('admin.reviews.destroy', $review) }}"
                    data-name="{{ Str::limit($review->body, 40) }}"
                    title="Delete"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    </div>
    @empty
    <div class="admin-empty-state">No reviews found.</div>
    @endforelse
</div>
<div class="admin-pagination">{{ $reviews->links() }}</div>

<div id="deleteReviewModal" class="admin-modal-backdrop" style="display:none;" onclick="if(event.target===this)closeDeleteReviewModal();">
    <div class="admin-modal-card">
        <div class="admin-modal-head">
            <h3>Delete Review</h3>
            <button type="button" class="admin-modal-close" onclick="closeDeleteReviewModal();"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="padding:20px 24px;">
            <p style="margin:0 0 20px;font-size:14px;color:var(--admin-muted, #8A7A6C);line-height:1.6;">
                Delete the review "<strong id="deleteReviewName" style="color:var(--admin-ink, #241208);"></strong>"? This cannot be undone.
            </p>
            <form id="deleteReviewForm" method="POST">
                @csrf @method('DELETE')
                <div class="admin-modal-actions">
                    <button type="button" class="admin-btn admin-btn-outline" onclick="closeDeleteReviewModal();">Cancel</button>
                    <button type="submit" class="admin-btn admin-btn-primary" style="background:var(--admin-danger, #D64545);border-color:var(--admin-danger, #D64545);">Delete Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-delete-review-btn');
        if (!btn) return;
        document.getElementById('deleteReviewName').textContent = btn.dataset.name || 'this review';
        document.getElementById('deleteReviewForm').action = btn.dataset.action;
        document.getElementById('deleteReviewModal').style.display = 'flex';
    });
    function closeDeleteReviewModal() {
        document.getElementById('deleteReviewModal').style.display = 'none';
    }
</script>
@endsection
