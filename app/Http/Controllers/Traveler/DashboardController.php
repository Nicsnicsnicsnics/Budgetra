<?php
namespace App\Http\Controllers\Traveler;

use App\Http\Controllers\Controller;
use App\Models\Attraction;
use App\Models\Expense;
use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // Same category->color mapping used throughout the app (itinerary
    // calendar, trip summary breakdown) so the dashboard's donut reads as
    // the same visual language rather than introducing a new palette.
    private const CATEGORY_COLORS = [
        'Transportation'     => '#3B82F6',
        'Accommodation'      => '#0D9488',
        'Food'                => '#EF4444',
        'Activities'          => '#10B981',
        'Shopping'            => '#A855F7',
        'Emergency Expenses' => '#2563EB',
    ];

    public function __invoke()
    {
        $user = auth()->user();
        $recommended = Attraction::withCount(['reviews' => fn ($q) => $q->where('status', 'active')])
            ->orderByDesc('rating')->limit(4)->get();

        if (!$user) {
            return view('traveler.dashboard.index', [
                'trips' => collect(), 'totalBudget' => 0, 'totalCost' => 0, 'totalSpent' => 0,
                'recommended' => $recommended, 'categorySpend' => [], 'monthlySpend' => [],
                'activeTrips' => collect(), 'nextDeparture' => null, 'tripChips' => [],
            ]);
        }

        return view('traveler.dashboard.index', array_merge(
            $this->buildData($user), compact('recommended')
        ));
    }

    public function downloadReport()
    {
        $user = auth()->user();
        abort_if(!$user, 403);

        $data = $this->buildData($user);
        $data['generatedAt'] = now();

        $pdf = Pdf::loadView('traveler.dashboard.report-pdf', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('dashboard-report-' . now()->format('Y-m-d') . '.pdf');
    }

    private function buildData($user): array
    {
        // "!= 'draft'" alone silently drops any trip with a NULL status too
        // (SQL: NULL != 'draft' is unknown, not true) — explicitly keep
        // NULL-status trips since they're real trips, not drafts.
        $trips = $user->accessibleTrips()->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhereNull('status'))->withSum('expenses', 'amount')->latest()->get()->map(function (Trip $trip) {
            $spent = $trip->expenses_sum_amount ?? 0;
            $trip->setAttribute('total_spent', $spent);
            $trip->setAttribute('pct_used', $trip->budget_limit > 0 ? round($spent / $trip->budget_limit * 100) : 0);
            // Was recomputed from the dates alone, which silently discarded a
            // status the traveller had set from Saved Trips — so a trip marked
            // Ongoing there could show as Upcoming here. resolved_status is the
            // model's own rule and is what Saved Trips reads too.
            $trip->setAttribute('status', $trip->resolved_status);
            return $trip;
        });
        $totalBudget = $trips->sum('budget_limit');
        $totalSpent  = $trips->sum('total_spent');

        // What the trips are projected to COST, which is not the same as what
        // the traveller capped their budget at. total_cost is what the planner
        // priced the trip at; a hand-created trip has none, so its budget is
        // the only cost figure available. Same fallback as SavedTrips and the
        // PDF report, so the three agree.
        $totalCost = $trips->sum(fn (Trip $t) => (float) ($t->total_cost ?? $t->budget_limit ?? 0));

        // Donut: spend by category, across every non-draft trip.
        $categorySpend = Expense::where('user_id', $user->id)
            ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'draft')->orWhereNull('status'))
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->category,
                'value' => (float) $row->total,
                'color' => self::CATEGORY_COLORS[$row->category] ?? 'var(--muted)',
            ])
            ->values()
            ->all();

        // Bars: total spend per calendar month, last 6 months.
        //
        // Bucketed in PHP rather than by the database. This used to group on
        // to_char(expense_date, 'YYYY-MM'), which exists only in Postgres and
        // made the whole dashboard unrenderable on any other driver. The
        // window is one traveller's last six months, so the row count is small
        // and the totals come out the same either way.
        $monthlyRaw = Expense::where('user_id', $user->id)
            ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'draft')->orWhereNull('status'))
            ->where('expense_date', '>=', Carbon::today()->subMonths(5)->startOfMonth())
            ->get(['expense_date', 'amount'])
            ->groupBy(fn (Expense $e) => Carbon::parse($e->expense_date)->format('Y-m'))
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $monthlySpend = [];
        for ($i = 5; $i >= 0; $i--) {
            $cursor = Carbon::today()->subMonths($i);
            $key    = $cursor->format('Y-m');
            $monthlySpend[] = [
                'label' => $cursor->format('M'),
                'value' => (float) ($monthlyRaw[$key] ?? 0),
            ];
        }

        // The trip list, defined exactly as the Saved Trips "Active Trips" tab
        // defines it: everything that is neither finished nor a draft. The
        // whitelist this replaced ('active' or 'upcoming') dropped any trip
        // carrying some other stored status, so a trip could sit in that tab
        // and be missing from this list. Soonest departure first.
        $activeTrips = $trips->whereNotIn('status', ['past', 'draft'])
            ->sortBy(fn (Trip $t) => $t->start_date->timestamp)
            ->values();

        // The stubs show the same figures as the Saved Trips cards, so they
        // follow the same rule about which currency to show them in.
        $activeTrips->each(fn (Trip $t) => trip_apply_display_currency($t));

        $nextDeparture = $activeTrips->firstWhere('status', 'upcoming');

        return compact(
            'trips', 'totalBudget', 'totalCost', 'totalSpent',
            'categorySpend', 'monthlySpend',
            'activeTrips', 'nextDeparture'
        ) + ['tripChips' => $this->categoryChips($activeTrips)];
    }

    /**
     * Top spending categories per trip, for the chips on each trip stub.
     *
     * One grouped query for every trip on screen rather than one per card —
     * the dashboard already runs a handful of aggregates and this is the only
     * one that would otherwise scale with the number of active trips.
     *
     * @return array<int, array<int, array{label: string, value: float, color: string}>>
     */
    private function categoryChips($activeTrips): array
    {
        $ids = $activeTrips->pluck('id')->all();

        if (empty($ids)) {
            return [];
        }

        return Expense::whereIn('trip_id', $ids)
            ->selectRaw('trip_id, category, SUM(amount) as total')
            ->groupBy('trip_id', 'category')
            ->orderByDesc('total')
            ->get()
            ->groupBy('trip_id')
            ->map(fn ($rows) => $rows->take(3)->map(fn ($row) => [
                'label' => $row->category,
                'value' => (float) $row->total,
                'color' => self::CATEGORY_COLORS[$row->category] ?? 'var(--muted)',
            ])->values()->all())
            ->all();
    }

}
