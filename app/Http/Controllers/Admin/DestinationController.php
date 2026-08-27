<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Services\PlaceImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DestinationController extends Controller
{
    public function index(Request $request)
    {
        $active = 'destinations';

        // Surface destinations travelers are actually actively/upcoming
        // trip-ing to first, ahead of the ~260 pre-seeded reference cities —
        // same "active"/"upcoming" status values the traveler dashboard's
        // own active-trips section already treats as authoritative.
        $activeTripSub = "(
            SELECT COUNT(*) FROM trips
            WHERE trips.status IN ('active', 'upcoming')
            AND (
                LOWER(trips.destination) = LOWER(destinations.name)
                OR LOWER(trips.leg2_destination) = LOWER(destinations.name)
            )
        )";

        $query = Destination::withCount('attractions')
            ->selectRaw('destinations.*, ' . $activeTripSub . ' as active_trip_count')
            ->orderByDesc('active_trip_count')
            ->orderBy('name');

        if ($request->filled('search')) {
            $s = '%' . strtolower($request->search) . '%';
            $query->where(fn($q) => $q->whereRaw('LOWER(name) LIKE ?', [$s])->orWhereRaw('LOWER(country) LIKE ?', [$s]));
        }
        if ($request->filled('region')) {
            $query->where('region', $request->region);
        }
        $destinations = $query->paginate(24)->withQueryString();
        return view('admin.destinations.index', compact('destinations', 'active'));
    }

    public function update(Request $request, Destination $destination)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'country'     => 'nullable|string|max:255',
            'region'      => 'required|in:local,international',
            'description' => 'nullable|string|max:2000',
            'image'       => 'nullable|image|max:5120',
        ]);

        $updateData = [
            'name'        => $validated['name'],
            'country'     => $validated['country'] ?? null,
            'region'      => $validated['region'],
            'description' => $validated['description'] ?? null,
        ];

        if ($request->hasFile('image')) {
            $filename = Str::slug($validated['name']) . '.' . $request->file('image')->getClientOriginalExtension();
            $request->file('image')->storeAs('destination-images', $filename, 'public');
            $updateData['image'] = 'destination-images/' . $filename;
        }

        $destination->update($updateData);
        return redirect()->route('admin.destinations.index')->with('success', 'Destination updated.');
    }

    public function destroy(Destination $destination)
    {
        $destination->delete();
        return redirect()->route('admin.destinations.index')->with('success', 'Destination deleted.');
    }

    public function fetchImage(Destination $destination, PlaceImageService $images)
    {
        if (!$images->fetchForDestination($destination)) {
            return back()->with('error', 'Could not find or download a photo for this destination right now.');
        }
        return redirect()->route('admin.destinations.index')->with('success', 'Photo fetched for ' . $destination->name . '.');
    }
}
