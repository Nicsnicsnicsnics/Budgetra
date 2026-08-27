<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\DestinationCost;
use Illuminate\Http\Request;

class ComparisonController extends Controller
{
    private const BASE_DAILY_COST = 2500;

    public function index(Request $request)
    {
        $allDestinations = DestinationCost::orderBy('destination')->pluck('destination')->unique()->values();
        $selected        = $request->input('destinations', []);
        $days            = max(1, (int) $request->input('days', 5));
        $travelers       = max(1, (int) $request->input('travelers', 1));
        $comparisons     = [];

        foreach (array_slice($selected, 0, 3) as $destName) {
            $dest = DestinationCost::where('destination', $destName)->first();
            if (!$dest) continue;

            $baseTotal     = self::BASE_DAILY_COST * $travelers * $days * $dest->multiplier;
            $comparisons[] = [
                'destination' => $dest->destination,
                'cost_level'  => $dest->cost_level,
                'multiplier'  => $dest->multiplier,
                'total'       => round($baseTotal, 2),
                'per_day'     => round($baseTotal / $days, 2),
            ];
        }

        return view('traveler.comparison.index', compact('allDestinations', 'selected', 'days', 'travelers', 'comparisons'));
    }
}
