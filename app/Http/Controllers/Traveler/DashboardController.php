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
                'trips' => collect(), 'totalBudget' => 0, 'totalSpent' => 0, 'recommended' => $recommended,
                'categorySpend' => [], 'monthlySpend' => [],
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
            $today = Carbon::today();
            $trip->setAttribute('total_spent', $spent);
            $trip->setAttribute('pct_used', $trip->budget_limit > 0 ? round($spent / $trip->budget_limit * 100) : 0);
            $trip->setAttribute('status',
                $trip->start_date->gt($today) ? 'upcoming' :
                ($trip->end_date->lt($today) ? 'past' : 'active'));
            return $trip;
        });
        $totalBudget = $trips->sum('budget_limit');
        $totalSpent  = $trips->sum('total_spent');

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
        $monthlyRaw = Expense::where('user_id', $user->id)
            ->whereHas('trip', fn ($q) => $q->where('status', '!=', 'draft')->orWhereNull('status'))
            ->where('expense_date', '>=', Carbon::today()->subMonths(5)->startOfMonth())
            ->selectRaw("to_char(expense_date, 'YYYY-MM') as ym, SUM(amount) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $monthlySpend = [];
        for ($i = 5; $i >= 0; $i--) {
            $cursor = Carbon::today()->subMonths($i);
            $key    = $cursor->format('Y-m');
            $monthlySpend[] = [
                'label' => $cursor->format('M'),
                'value' => (float) ($monthlyRaw[$key] ?? 0),
            ];
        }

        return compact('trips', 'totalBudget', 'totalSpent', 'categorySpend', 'monthlySpend');
    }
}
