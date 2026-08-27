<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItineraryItemRequest;
use App\Models\Itinerary;
use App\Models\Trip;

class ItineraryController extends Controller
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

        return view('traveler.itinerary.index', compact('trips'));
    }

    public function store(StoreItineraryItemRequest $request)
    {
        $data = $request->validated();

        $trip = Trip::findOrFail($data['trip_id']);
        abort_if($trip->user_id !== auth()->id(), 403);

        Itinerary::create($data);

        return redirect()->route('itinerary.index')->with('success', 'Itinerary item added.');
    }

    public function destroy(Itinerary $item)
    {
        abort_if($item->trip->user_id !== auth()->id(), 403);
        $item->delete();
        return redirect()->route('itinerary.index')->with('success', 'Item removed.');
    }
}
