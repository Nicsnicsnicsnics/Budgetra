<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;

class MomentController extends Controller
{
    public function index()
    {
        // accessibleTrips(), not trips(): a member added to someone's group
        // trip has none of their own and would otherwise land on an empty state.
        $trips = auth()->user()
            ->accessibleTrips()
            ->with(['itinerary' => fn($q) => $q->orderBy('start_datetime')])
            ->latest()
            ->get();

        return view('traveler.itinerary.index', ['trips' => $trips, 'tab' => 'moments', 'active' => 'moments']);
    }
}
