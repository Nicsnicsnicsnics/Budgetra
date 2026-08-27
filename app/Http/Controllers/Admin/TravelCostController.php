<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationCost;
use Illuminate\Http\Request;

class TravelCostController extends Controller
{
    /**
     * Each sortable column's default direction is the one that produces the
     * order asked for at a glance: priciest tier first, biggest multiplier
     * first, local before international. 'desc' just flips it.
     */
    private const SORT_DEFAULTS = [
        'cost_level' => 'asc',
        'multiplier' => 'desc',
        'category'   => 'asc',
    ];

    public function index(Request $request)
    {
        $active = 'travel-costs';

        $sort = $request->query('sort');
        $sort = array_key_exists($sort, self::SORT_DEFAULTS) ? $sort : null;

        // Falls back to the column's own default rather than a blanket 'asc',
        // so a bare ?sort=multiplier still lands highest-first.
        $dir = $request->query('dir');
        $dir = in_array($dir, ['asc', 'desc'], true)
            ? $dir
            : ($sort ? self::SORT_DEFAULTS[$sort] : 'asc');

        $query = DestinationCost::query();

        // Cost level and category are ranked, not compared as text —
        // alphabetically 'Budget-friendly' would land above 'Very Expensive'
        // and 'International' above 'Local', which is the opposite of both
        // orderings these columns are meant to convey.
        match ($sort) {
            'cost_level' => $query->orderByRaw("CASE cost_level
                    WHEN 'Very Expensive'  THEN 1
                    WHEN 'Pricey'          THEN 2
                    WHEN 'Moderate'        THEN 3
                    WHEN 'Budget-friendly' THEN 4
                    ELSE 5 END " . ($dir === 'desc' ? 'DESC' : 'ASC')),
            'category'   => $query->orderByRaw("CASE LOWER(category)
                    WHEN 'local'         THEN 1
                    WHEN 'international' THEN 2
                    ELSE 3 END " . ($dir === 'desc' ? 'DESC' : 'ASC')),
            'multiplier' => $query->orderBy('multiplier', $dir),
            default      => $query->orderBy('destination'),
        };

        // Secondary key so rows that tie on the sorted column keep a stable,
        // predictable order instead of shuffling between page loads.
        if ($sort) {
            $query->orderBy('destination');
        }

        $destinations = $query->paginate(25)->withQueryString();

        return view('admin.travel-costs.index', [
            'destinations' => $destinations,
            'active'       => $active,
            'sort'         => $sort,
            'dir'          => $dir,
            'sortDefaults' => self::SORT_DEFAULTS,
        ]);
    }

    public function create()
    {
        $active = 'travel-costs';
        $costLevels = ['Budget-friendly', 'Moderate', 'Pricey', 'Very Expensive'];
        return view('admin.travel-costs.create', compact('costLevels', 'active'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'required|string|max:255|unique:destination_costs',
            'cost_level'  => 'required|in:Budget-friendly,Moderate,Pricey,Very Expensive',
            'multiplier'  => 'required|numeric|min:0.1|max:10',
            'category'    => 'nullable|string|max:100',
            'image_url'   => 'nullable|url|max:500',
            'description' => 'nullable|string|max:2000',
        ]);
        DestinationCost::create($validated);
        return redirect()->route('admin.travel-costs.index')->with('success', 'Travel cost added.');
    }

    public function edit(DestinationCost $travel_cost)
    {
        $active = 'travel-costs';
        $costLevels = ['Budget-friendly', 'Moderate', 'Pricey', 'Very Expensive'];
        $destination = $travel_cost;
        return view('admin.travel-costs.edit', compact('destination', 'costLevels', 'active'));
    }

    public function update(Request $request, DestinationCost $travel_cost)
    {
        $validated = $request->validate([
            'destination' => "required|string|max:255|unique:destination_costs,destination,{$travel_cost->id}",
            'cost_level'  => 'required|in:Budget-friendly,Moderate,Pricey,Very Expensive',
            'multiplier'  => 'required|numeric|min:0.1|max:10',
            'category'    => 'nullable|string|max:100',
            'image_url'   => 'nullable|url|max:500',
            'description' => 'nullable|string|max:2000',
        ]);
        $travel_cost->update($validated);
        return redirect()->route('admin.travel-costs.index')->with('success', 'Travel cost updated.');
    }

    public function destroy(DestinationCost $travel_cost)
    {
        $travel_cost->delete();
        return redirect()->route('admin.travel-costs.index')->with('success', 'Travel cost deleted.');
    }
}
