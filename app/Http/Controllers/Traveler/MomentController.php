<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;

class MomentController extends Controller
{
    public function index()
    {
        $trips = auth()->user()
            ->accessibleTrips()
            ->with(['itinerary' => fn($q) => $q->orderBy('start_datetime')])
            ->latest()
            ->get();

        return view('traveler.itinerary.index', ['trips' => $trips, 'tab' => 'moments', 'active' => 'moments']);
    }
}
