<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewModerationController extends Controller
{
    public function index(Request $request)
    {
        $active = 'reviews';
        // Flagged reviews surface first — inappropriate-content reports in
        // particular need prompt moderation attention, not just whatever
        // happened to be posted most recently.
        $query = Review::with(['user', 'flagger', 'attraction'])
            ->whereNotNull('attraction_id')
            ->orderByRaw('CASE WHEN flag_reason IS NOT NULL THEN 0 ELSE 1 END')
            ->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->boolean('flagged')) {
            $query->whereNotNull('flag_reason');
        }
        if ($request->filled('destination')) {
            $query->where('destination', $request->destination);
        }
        $reviews = $query->paginate(25)->withQueryString();
        return view('admin.reviews.index', compact('reviews', 'active'));
    }

    public function hide(Review $review)
    {
        $review->update(['status' => 'hidden']);
        return back()->with('success', 'Review hidden.');
    }

    public function show(Review $review)
    {
        $review->update(['status' => 'active']);
        return back()->with('success', 'Review is now active.');
    }

    public function destroy(Review $review)
    {
        $review->delete();
        return back()->with('success', 'Review deleted.');
    }

    public function unflag(Review $review)
    {
        $review->update(['flag_reason' => null, 'flagged_at' => null, 'flagged_by' => null]);
        return back()->with('success', 'Flag cleared.');
    }
}
