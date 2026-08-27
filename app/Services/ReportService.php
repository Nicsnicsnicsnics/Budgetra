<?php
namespace App\Services;

use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportService
{
    // The five cost lines the planner saves into Trip::summary_data, in the
    // order the Saved Trips detail panel shows them.
    private const COST_LABELS = [
        'transportation' => 'Transportation',
        'accommodation'  => 'Accommodation',
        'food'           => 'Food & Dining',
        'attractions'    => 'Attractions',
        'emergency_fund' => 'Emergency Fund',
    ];

    public function __construct(private BudgetService $budget) {}

    public function generatePdf(Trip $trip): \Barryvdh\DomPDF\PDF
    {
        $summary  = $this->budget->summary($trip->load('budgets'));
        $expenses = $trip->expenses()->orderBy('expense_date')->get();

        // Day-by-day itinerary, keyed by Y-m-d so the view can print one
        // block per day in trip order. Grouping here rather than in the
        // Blade keeps the template to presentation only.
        $itinerary = $trip->itinerary()
            ->orderBy('start_datetime')
            ->get()
            ->groupBy(fn ($item) => $item->start_datetime->format('Y-m-d'));

        $costRows = $this->costRows($trip);
        $totals   = $this->totals($trip, $expenses->sum('amount'));

        $pdf = Pdf::loadView('traveler.reports.pdf', compact(
            'trip', 'summary', 'expenses', 'itinerary', 'costRows', 'totals'
        ));
        $pdf->setPaper('a4', 'portrait');
        return $pdf;
    }

    /**
     * The trip's estimated cost breakdown, read from summary_data — the same
     * source the Saved Trips detail panel renders. The trip_budgets table is
     * only written by TripPlannerWizard::confirm(), a legacy path no button
     * reaches any more, so it holds no rows and can't back this section.
     */
    private function costRows(Trip $trip): array
    {
        $data = $trip->summary_data ?? [];
        $rows = [];

        foreach (self::COST_LABELS as $key => $label) {
            $row    = $data[$key] ?? null;
            $cost   = (float) ($row['cost'] ?? 0);
            $detail = $row['detail'] ?? null;

            // A line the planner never filled in is left out rather than
            // printed as a zero, which would read as "this costs nothing".
            if (!$cost && !$detail) continue;

            $rows[] = [
                'label'  => $label,
                'detail' => $detail,
                'cost'   => $cost,
                'extra'  => (int) ($row['extra'] ?? 0),
            ];
        }

        return $rows;
    }

    private function totals(Trip $trip, float $spent): array
    {
        $estimated = (float) ($trip->total_cost ?? $trip->budget_limit ?? 0);

        // Head count mirrors SavedTrips: a Solo trip is always one head no
        // matter what num_travelers holds, and a Group trip counts at least
        // its accepted members plus the owner.
        $heads = strcasecmp($trip->travel_type ?? 'Solo', 'Group') === 0
            ? max(1, (int) $trip->num_travelers, $trip->groupMembers()->count() + 1)
            : 1;

        return [
            'estimated'    => $estimated,
            'budget_limit' => (float) ($trip->budget_limit ?? 0),
            'spent'        => $spent,
            'remaining'    => $estimated - $spent,
            'heads'        => $heads,
            'per_person'   => $heads > 1 ? $estimated / $heads : null,
        ];
    }
}
