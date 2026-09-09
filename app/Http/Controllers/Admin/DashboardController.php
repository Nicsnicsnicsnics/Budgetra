<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\Destination;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $active = 'dashboard';

        [$from, $to, $rangeKey, $rangeLabel, $customStart, $customEnd] = $this->resolveRange($request);

        // Comparison window: the same span of time immediately before $from, so
        // every "vs. previous" figure on the page is measured against a period
        // of equal length rather than a fixed 30 days.
        $spanDays = $from->diffInDays($to) + 1;
        $previousFrom = $from->copy()->subDays($spanDays);
        $previousTo = $from->copy()->subSecond();
        $previousLabel = $this->previousLabel($rangeKey, $spanDays);

        $stats = [
            'trips' => $this->periodStat(Trip::query(), $from, $to, $previousFrom, $previousTo),
            'users' => $this->periodStat(User::query()->whereNotIn('role', ['admin', 'banned']), $from, $to, $previousFrom, $previousTo),
            'attractions' => $this->periodStat(Attraction::query(), $from, $to, $previousFrom, $previousTo),
            'reviews' => $this->periodStat(Review::query()->whereNotNull('attraction_id'), $from, $to, $previousFrom, $previousTo),
        ];

        $budget = $this->budgetMetrics($from, $to, $previousFrom, $previousTo);

        $tripStatus = $this->tripStatusBreakdown();

        $topDestinations = Destination::query()
            ->selectRaw("destinations.*, (SELECT COUNT(*) FROM trips WHERE trips.created_at BETWEEN ? AND ? AND (LOWER(trips.destination) = LOWER(destinations.name) OR LOWER(trips.leg2_destination) = LOWER(destinations.name))) as trip_count", [$from, $to])
            ->orderByDesc('trip_count')
            ->limit(5)
            ->get()
            ->filter(fn ($destination) => $destination->trip_count > 0)
            ->values();
        $topDestinationsMax = $topDestinations->max('trip_count') ?: 1;

        $tripsByType = Trip::whereBetween('created_at', [$from, $to])
            ->selectRaw('travel_type, COUNT(*) as total')
            ->groupBy('travel_type')
            ->pluck('total', 'travel_type');

        $trendBuckets = [];
        $spanSeconds = max(1, $from->diffInSeconds($to));
        for ($i = 0; $i < 7; $i++) {
            $bucketFrom = $from->copy()->addSeconds((int) floor($spanSeconds * $i / 7));
            $bucketTo = $i === 6
                ? $to
                : $from->copy()->addSeconds((int) floor($spanSeconds * ($i + 1) / 7))->subSecond();
            $trendBuckets[] = [
                'label' => $spanDays <= 31 ? $bucketFrom->format('M j') : $bucketFrom->format('M j'),
                'value' => Trip::whereBetween('created_at', [$bucketFrom, $bucketTo])->count(),
            ];
        }

        $recentTrips = Trip::with('user')->whereBetween('created_at', [$from, $to])->latest()->limit(5)->get();

        $popularAttractions = Attraction::withCount(['reviews' => fn ($query) => $query
            ->where('status', 'active')
            ->whereBetween('created_at', [$from, $to])])
            ->orderByDesc('reviews_count')
            ->limit(20)
            ->get()
            ->filter(fn ($attraction) => $attraction->reviews_count > 0)
            ->take(4)
            ->values();
        $popularAttractionsMax = $popularAttractions->max('reviews_count') ?: 1;

        $recentlyCurated = Destination::query()
            ->where(fn ($query) => $query->whereNotNull('description')->orWhereNotNull('image'))
            ->whereBetween('updated_at', [$from, $to])
            ->latest('updated_at')
            ->limit(4)
            ->get();

        // A brand-new install has nothing to chart; the view swaps in guidance
        // instead of a page of empty panels.
        $isFreshInstall = ! Trip::exists() && Destination::count() === 0;

        return view('admin.dashboard', compact(
            'active', 'stats', 'budget', 'tripStatus', 'topDestinations', 'topDestinationsMax',
            'tripsByType', 'trendBuckets', 'recentTrips', 'popularAttractions',
            'popularAttractionsMax', 'recentlyCurated', 'from', 'to',
            'rangeKey', 'rangeLabel', 'previousLabel', 'customStart', 'customEnd', 'isFreshInstall'
        ));
    }

    /**
     * Resolves the requested reporting window into a concrete [from, to] pair
     * plus display metadata. Accepts the day presets 7/30/90/365, "ytd", and
     * "custom" (with start/end query params); anything unrecognised falls back
     * to the last 30 days.
     *
     * @return array{0:Carbon,1:Carbon,2:string,3:string,4:string,5:string}
     */
    private function resolveRange(Request $request): array
    {
        $now = Carbon::now();
        $key = (string) $request->query('range', '30');

        if ($key === 'custom') {
            $start = $this->tryParse($request->query('start'))?->startOfDay();
            $end = $this->tryParse($request->query('end'))?->endOfDay();

            if (! $start || ! $end || $start->gt($end)) {
                $start = $now->copy()->subDays(29)->startOfDay();
                $end = $now->copy();
            }
            if ($end->gt($now)) {
                $end = $now->copy();
            }

            return [
                $start, $end, 'custom',
                $start->format('M j, Y') . ' – ' . $end->format('M j, Y'),
                $start->format('Y-m-d'), $end->format('Y-m-d'),
            ];
        }

        if ($key === 'ytd') {
            $start = $now->copy()->startOfYear();

            return [$start, $now->copy(), 'ytd', 'This year', $start->format('Y-m-d'), $now->format('Y-m-d')];
        }

        $days = in_array((int) $key, [7, 30, 90, 365], true) ? (int) $key : 30;
        $labels = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];
        $start = $now->copy()->subDays($days - 1)->startOfDay();

        return [$start, $now->copy(), (string) $days, $labels[$days], $start->format('Y-m-d'), $now->format('Y-m-d')];
    }

    private function tryParse($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function previousLabel(string $rangeKey, int $spanDays): string
    {
        return match ($rangeKey) {
            'ytd' => 'the year to date one year ago',
            'custom' => "the prior {$spanDays} days",
            default => 'the previous period of equal length',
        };
    }

    /**
     * Where every trip on the platform sits in its lifecycle right now —
     * a snapshot, not a per-period figure. Mirrors Trip::resolvedStatus:
     * a stored status wins, otherwise the trip's dates decide. Drafts are
     * the wizard's autosaved, never-finished rows.
     *
     * @return list<array{key:string,label:string,icon:string,color:string,count:int}>
     */
    private function tripStatusBreakdown(): array
    {
        $today = Carbon::today()->toDateString();
        $undated = "(status IS NULL OR status = '')";

        $upcoming = Trip::where(fn ($q) => $q->where('status', 'upcoming')
            ->orWhereRaw("{$undated} AND start_date > ?", [$today]))->count();
        $active = Trip::where(fn ($q) => $q->where('status', 'active')
            ->orWhereRaw("{$undated} AND start_date <= ? AND end_date >= ?", [$today, $today]))->count();
        $completed = Trip::where(fn ($q) => $q->where('status', 'past')
            ->orWhereRaw("{$undated} AND end_date < ?", [$today]))->count();
        $drafts = Trip::where('status', 'draft')->count();

        return [
            ['key' => 'upcoming', 'label' => 'Upcoming', 'icon' => 'fa-solid fa-plane-departure', 'color' => '#3B82F6', 'count' => $upcoming],
            ['key' => 'active', 'label' => 'In progress', 'icon' => 'fa-solid fa-person-walking-luggage', 'color' => '#2F9E5B', 'count' => $active],
            ['key' => 'past', 'label' => 'Completed', 'icon' => 'fa-solid fa-flag-checkered', 'color' => '#8A7A6C', 'count' => $completed],
            ['key' => 'draft', 'label' => 'Drafts', 'icon' => 'fa-solid fa-pen-ruler', 'color' => '#D99A3D', 'count' => $drafts],
        ];
    }

    /**
     * Budget-focused figures that speak to what Budgetra is for, each with a
     * change against the comparison window where a change makes sense.
     */
    private function budgetMetrics(Carbon $from, Carbon $to, Carbon $previousFrom, Carbon $previousTo): array
    {
        $current = Trip::whereBetween('created_at', [$from, $to]);
        $previous = Trip::whereBetween('created_at', [$previousFrom, $previousTo]);

        $avgBudgetNow = (float) (clone $current)->where('budget_limit', '>', 0)->avg('budget_limit');
        $avgBudgetPrev = (float) (clone $previous)->where('budget_limit', '>', 0)->avg('budget_limit');

        $totalNow = (float) (clone $current)->sum('budget_limit');
        $totalPrev = (float) (clone $previous)->sum('budget_limit');

        $avgSpendNow = (float) (clone $current)->where('total_cost', '>', 0)->avg('total_cost');

        $distinctDestinations = (clone $current)->whereNotNull('destination')->distinct()->count('destination');
        $avgPerDestination = $distinctDestinations > 0 ? $totalNow / $distinctDestinations : 0.0;

        $costedTrips = (clone $current)->where('total_cost', '>', 0)->where('budget_limit', '>', 0)->count();
        $overrunTrips = (clone $current)->where('total_cost', '>', 0)->where('budget_limit', '>', 0)
            ->whereColumn('total_cost', '>', 'budget_limit')->count();
        $overrunRate = $costedTrips > 0 ? round($overrunTrips / $costedTrips * 100) : null;

        return [
            'total' => ['value' => $totalNow, 'change' => $this->pctChange($totalPrev, $totalNow)],
            'avg_budget' => ['value' => $avgBudgetNow, 'change' => $this->pctChange($avgBudgetPrev, $avgBudgetNow)],
            'avg_spend' => ['value' => $avgSpendNow],
            'avg_per_destination' => ['value' => $avgPerDestination, 'destinations' => $distinctDestinations],
            'overrun_rate' => ['value' => $overrunRate, 'costed' => $costedTrips, 'over' => $overrunTrips],
        ];
    }

    private function periodStat($query, Carbon $from, Carbon $to, Carbon $previousFrom, Carbon $previousTo): array
    {
        $current = (clone $query)->whereBetween('created_at', [$from, $to])->count();
        $previous = (clone $query)->whereBetween('created_at', [$previousFrom, $previousTo])->count();

        return ['total' => $current, 'change' => $this->pctChange($previous, $current)];
    }

    private function pctChange(float $old, float $new): array
    {
        $pct = $old <= 0 ? ($new > 0 ? 100 : 0) : round((($new - $old) / $old) * 100);

        return ['pct' => abs($pct), 'up' => $new >= $old, 'flat' => abs($new - $old) < 0.01];
    }
}
