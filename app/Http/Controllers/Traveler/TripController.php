<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\DestinationCost;
use App\Models\Trip;
use App\Services\BudgetService;
use App\Services\KlookService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index()
    {
        return view('traveler.trips.index');
    }

    public function type()
    {
        return view('traveler.trips.type');
    }

    public function create()
    {
        return view('traveler.trips.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination'   => 'required|string|max:255',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'num_travelers' => 'nullable|integer|min:1|max:50',
            'budget_limit'  => 'nullable|numeric|min:0',
            'travel_type'   => 'required|in:Solo,Group',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $trip = auth()->user()->trips()->create($validated);

        return redirect()->route('trips.show', $trip);
    }

    public function show(Trip $trip, BudgetService $budgetService)
    {
        abort_if($trip->user_id !== auth()->id(), 403);
        $summary = $budgetService->summary($trip->load('budgets'));
        return view('traveler.trips.show', compact('trip', 'summary'));
    }

    public function edit(Trip $trip)
    {
        abort_if($trip->user_id !== auth()->id(), 403);
        return view('traveler.trips.edit', compact('trip'));
    }

    public function update(Request $request, Trip $trip)
    {
        abort_if($trip->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'destination'   => 'required|string|max:255',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'num_travelers' => 'nullable|integer|min:1|max:50',
            'budget_limit'  => 'nullable|numeric|min:0',
            'travel_type'   => 'required|in:Solo,Group',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $trip->update($validated);
        return redirect()->route('trips.show', $trip);
    }

    public function destroy(Trip $trip)
    {
        abort_if($trip->user_id !== auth()->id(), 403);
        $trip->delete();
        return redirect()->route('dashboard')->with('success', 'Trip deleted.');
    }

    public function applyEstimates(Request $request, Trip $trip)
    {
        return $this->budgetStore($request, $trip);
    }

    public function estimate(Trip $trip, KlookService $klook)
    {
        abort_if($trip->user_id !== auth()->id(), 403);

        $destInfo   = DestinationCost::where('destination', $trip->destination)->first();
        $multiplier = $destInfo ? (float) $destInfo->multiplier : 1.0;
        $days       = max(1, (int) Carbon::parse($trip->start_date)->diffInDays(Carbon::parse($trip->end_date)));
        $travelers  = max(1, $trip->num_travelers ?? 1);
        $baseDaily  = 2500;

        $categories = [
            'Transportation'      => round($baseDaily * 0.20 * $multiplier * $travelers * $days, 2),
            'Accommodation'       => round($baseDaily * 0.30 * $multiplier * $travelers * $days, 2),
            'Food'                => round($baseDaily * 0.20 * $multiplier * $travelers * $days, 2),
            'Tourist Attractions' => round($baseDaily * 0.15 * $multiplier * $travelers * $days, 2),
            'Shopping'            => round($baseDaily * 0.10 * $multiplier * $travelers * $days, 2),
            'Emergency Funds'     => round($baseDaily * 0.05 * $multiplier * $travelers * $days, 2),
        ];
        $total      = array_sum($categories);
        $activities = $klook->getActivities($trip->destination);

        return view('traveler.trips.estimate', compact('trip', 'destInfo', 'categories', 'total', 'activities', 'days'));
    }

    public function budget(Trip $trip)
    {
        abort_if($trip->user_id !== auth()->id(), 403);
        $categories = ['Transportation', 'Accommodation', 'Food', 'Tourist Attractions', 'Shopping', 'Emergency Funds'];
        $budgets    = $trip->budgets()->pluck('estimated_cost', 'category')->toArray();
        return view('traveler.trips.budget', compact('trip', 'categories', 'budgets'));
    }

    public function budgetStore(Request $request, Trip $trip)
    {
        abort_if($trip->user_id !== auth()->id(), 403);

        $validated = $request->validate([
            'estimated_cost'   => 'required|array',
            'estimated_cost.*' => 'numeric|min:0',
        ]);

        foreach ($validated['estimated_cost'] as $category => $amount) {
            $trip->budgets()->updateOrCreate(
                ['category' => $category],
                ['estimated_cost' => $amount]
            );
        }

        return redirect()->route('trips.show', $trip)->with('success', 'Budget saved.');
    }
}
