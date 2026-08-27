<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\Destination;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $active = 'dashboard';
        $now  = Carbon::now();
        $prev = $now->copy()->subMonth();

        $stats = [
            'trips'       => $this->statCard(Trip::class, $now, $prev),
            'attractions' => $this->statCard(Attraction::class, $now, $prev),
            'users'       => $this->statCard(User::class, $now, $prev, fn ($q) => $q->where('role', '!=', 'banned')),
        ];

        // Top destinations — ranked by how many trips (either leg) actually
        // went there, case-insensitively since trip destination strings and
        // Destination.name can differ only in casing.
        $topDestinations = Destination::query()
            ->selectRaw("destinations.*, (
                SELECT COUNT(*) FROM trips
                WHERE LOWER(trips.destination) = LOWER(destinations.name)
                   OR LOWER(trips.leg2_destination) = LOWER(destinations.name)
            ) as trip_count")
            ->orderByDesc('trip_count')
            ->limit(5)
            ->get()
            ->filter(fn ($d) => $d->trip_count > 0)
            ->values();
        $topDestinationsMax = $topDestinations->max('trip_count') ?: 1;

        // Trips by travel type (Solo vs Group) — the closest real equivalent
        // this app has to a demographic breakdown.
        $tripsByType = Trip::selectRaw('travel_type, COUNT(*) as total')
            ->groupBy('travel_type')
            ->pluck('total', 'travel_type');

        // Trips created per month, last 6 months — same pattern as the
        // traveler dashboard's own monthly-spend bars.
        $tripsByMonth = [];
        for ($i = 5; $i >= 0; $i--) {
            $cursor = $now->copy()->subMonths($i);
            $tripsByMonth[] = [
                'label' => $cursor->format('M'),
                'value' => Trip::whereMonth('created_at', $cursor->month)->whereYear('created_at', $cursor->year)->count(),
            ];
        }

        $recentTrips = Trip::with('user')->latest()->limit(5)->get();

        // "Popular" by review volume — an attraction nobody's reviewed yet
        // isn't meaningfully popular regardless of its (possibly unrated) score.
        $popularAttractions = Attraction::withCount(['reviews' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('reviews_count')
            ->limit(20)
            ->get()
            ->filter(fn ($a) => $a->reviews_count > 0)
            ->take(4)
            ->values();
        $popularAttractionsMax = $popularAttractions->max('reviews_count') ?: 1;

        $recentlyCurated = Destination::query()
            ->where(fn ($q) => $q->whereNotNull('description')->orWhereNotNull('image'))
            ->latest('updated_at')
            ->limit(4)
            ->get();

        return view('admin.dashboard', compact(
            'active', 'stats', 'topDestinations', 'topDestinationsMax',
            'tripsByType', 'tripsByMonth', 'recentTrips',
            'popularAttractions', 'popularAttractionsMax', 'recentlyCurated'
        ));
    }

    // Total count + this-month-vs-last-month % change, for any model with
    // standard created_at timestamps.
    private function statCard(string $model, Carbon $now, Carbon $prev, ?callable $scope = null): array
    {
        $base = fn () => $scope ? $scope($model::query()) : $model::query();

        $total = $base()->count();
        $thisMonth = (clone $base())->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count();
        $lastMonth = (clone $base())->whereMonth('created_at', $prev->month)->whereYear('created_at', $prev->year)->count();

        return [
            'total'  => $total,
            'change' => $this->pctChange($lastMonth, $thisMonth),
        ];
    }

    private function pctChange(float $old, float $new): array
    {
        if ($old <= 0) {
            $pct = $new > 0 ? 100 : 0;
        } else {
            $pct = round((($new - $old) / $old) * 100);
        }
        return ['pct' => abs($pct), 'up' => $new >= $old];
    }
}
