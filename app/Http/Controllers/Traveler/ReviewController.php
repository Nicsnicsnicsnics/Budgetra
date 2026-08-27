<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\DestinationCost;
use App\Models\Review;
use App\Models\ReviewHelpfulVote;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query  = Review::with('user')->where('status', 'active')->latest();

        if ($request->filled('destination')) {
            // Reviews are written against DestinationCost's fixed, curated
            // names ("Boracay"), but this page is also linked to from
            // Moments, where a traveler can type ANY free-text place name
            // ("Boracay Beach Sunset Spot") when pinning a photo. An exact
            // match would silently show nothing for every Moment whose
            // wording doesn't happen to match a DestinationCost entry
            // verbatim. Matching in both directions — does the review's
            // destination appear inside what was searched, or vice versa —
            // catches both "search is more specific than the review" and
            // "review is more specific than the search" without needing
            // the two features to agree on a shared vocabulary.
            $term = strtolower(trim($request->destination));
            $query->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(destination) LIKE ?', ["%{$term}%"])
                  ->orWhereRaw("? LIKE ('%' || LOWER(destination) || '%')", [$term]);
            });
        }

        // withQueryString() would carry "write=1" into every pagination
        // link too — since that param only means "auto-open the write
        // modal on arrival", not a real filter, keeping it would make the
        // modal pop back open every time the traveler pages through
        // results after closing it once.
        $reviews      = $query->paginate(15)->appends($request->except('write'));
        $destinations = DestinationCost::orderBy('destination')->pluck('destination')->unique()->values();
        return view('traveler.reviews.index', compact('reviews', 'destinations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination'    => 'required|string|max:255',
            'rating'         => 'required|integer|min:1|max:5',
            'body'           => 'required|string|min:10|max:2000',
            'attraction_id'  => 'nullable|exists:attractions,id',
            'trip_type'      => 'nullable|in:Solo,Couple,Family,Barkada',
            'pax_count'      => 'nullable|integer|min:1|max:50',
            'spent_amount'   => 'nullable|numeric|min:0|max:9999999',
        ]);

        auth()->user()->reviews()->create(array_merge($validated, ['status' => 'active']));

        if ($request->filled('attraction_id')) {
            return redirect()->route('attractions.show', $request->attraction_id)
                             ->with('success', 'Review submitted!');
        }
        return redirect()->route('reviews.index')->with('success', 'Review submitted!');
    }

    public function update(Request $request, Review $review)
    {
        abort_if($review->user_id !== auth()->id(), 403, "You can only edit your own review.");

        $validated = $request->validate([
            'rating'        => 'required|integer|min:1|max:5',
            'body'          => 'required|string|min:10|max:2000',
            'trip_type'     => 'nullable|in:Solo,Couple,Family,Barkada',
            'pax_count'     => 'nullable|integer|min:1|max:50',
            'spent_amount'  => 'nullable|numeric|min:0|max:9999999',
        ]);

        $review->update($validated);

        if ($request->filled('attraction_id')) {
            return redirect()->route('attractions.show', $request->attraction_id)
                             ->with('success', 'Review updated!');
        }
        return redirect()->route('reviews.index')->with('success', 'Review updated!');
    }

    public function destroy(Request $request, Review $review)
    {
        abort_if($review->user_id !== auth()->id(), 403, "You can only delete your own review.");

        $review->delete();

        if ($request->filled('attraction_id')) {
            return redirect()->route('attractions.show', $request->attraction_id)
                             ->with('success', 'Review deleted.');
        }
        return redirect()->route('reviews.index')->with('success', 'Review deleted.');
    }

    public function flag(Request $request, Review $review)
    {
        abort_if($review->user_id === auth()->id(), 403, "You can't flag your own review.");

        $validated = $request->validate([
            'reason' => 'required|in:inappropriate,improvement',
        ]);

        if ($review->flagged_by === auth()->id()) {
            return back()->with('success', "You've already flagged this review — an admin will take a look.");
        }

        $review->update([
            'flag_reason' => $validated['reason'],
            'flagged_at'  => now(),
            'flagged_by'  => auth()->id(),
        ]);

        return back()->with('success', 'Thanks — this review has been flagged for admin review.');
    }

    // One vote per traveler per review — firstOrCreate on the unique
    // (review_id, user_id) pair silently no-ops on a repeat click instead
    // of inflating the count, so helpful_count always matches the real
    // number of distinct voters.
    public function markHelpful(Review $review)
    {
        $vote = ReviewHelpfulVote::firstOrCreate([
            'review_id' => $review->id,
            'user_id'   => auth()->id(),
        ]);

        if ($vote->wasRecentlyCreated) {
            $review->increment('helpful_count');
        }

        return back();
    }
}
