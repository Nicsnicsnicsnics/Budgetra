<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\Review;

class AttractionController extends Controller
{
    public function index()
    {
        return view('traveler.attractions.index');
    }

    public function show(Attraction $attraction)
    {
        $reviews = Review::with('user')
            ->where('attraction_id', $attraction->id)
            ->where('status', 'active')
            ->latest()
            ->get();

        $avgRating = $reviews->avg('rating') ?? 0;
        $myReview  = auth()->user()->reviews()
            ->where('attraction_id', $attraction->id)
            ->first();
        $hasReviewed = (bool) $myReview;

        return view('traveler.attractions.show', compact(
            'attraction', 'reviews', 'avgRating', 'hasReviewed', 'myReview'
        ));
    }
}
