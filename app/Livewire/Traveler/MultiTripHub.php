<?php
namespace App\Livewire\Traveler;

use App\Models\Expense;
use App\Models\SavingsGoal;
use App\Models\Trip;
use Carbon\Carbon;
use Livewire\Component;

class MultiTripHub extends Component
{
    public string $search         = '';
    public ?int   $detailTripId   = null;
    public array  $compareIds     = [];
    public bool   $showComparison = false;

    private array $compareCategories = ['Transportation', 'Accommodation', 'Food', 'Tourist Attractions'];

    public function showDetail(int $id): void
    {
        $this->detailTripId = $this->detailTripId === $id ? null : $id;
    }

    public function closeDetail(): void
    {
        $this->detailTripId = null;
    }

    // Two-step selection: clicking a card's Compare button toggles it in/out
    // of the pick list (max 2 — picking a third drops the oldest pick) and
    // highlights the card, rather than instantly pairing it with whatever
    // other trip happens to be first. The actual comparison only opens once
    // two cards are picked, via the floating bar's own Compare button.
    public function toggleCompare(int $tripId): void
    {
        if (in_array($tripId, $this->compareIds, true)) {
            $this->compareIds = array_values(array_diff($this->compareIds, [$tripId]));
            return;
        }

        if (count($this->compareIds) >= 2) {
            array_shift($this->compareIds);
        }
        $this->compareIds[] = $tripId;
    }

    public function runComparison(): void
    {
        if (count($this->compareIds) === 2) {
            $this->showComparison = true;
        }
    }

    public function closeComparison(): void
    {
        $this->showComparison = false;
    }

    public function clearCompareSelection(): void
    {
        $this->showComparison = false;
        $this->compareIds     = [];
    }

    private function fetchTrips()
    {
        // Multi Trip Hub has no Draft group — a draft is an unfinished trip
        // with no real data yet, so it doesn't belong in Active or Past here
        // (unlike Saved Trips, which does group them separately).
        // A real (non-draft) trip's status column is usually left NULL
        // (only set when a draft is created, or when a status is manually
        // overridden from Saved Trips) — `!= 'draft'` alone would silently
        // exclude every one of those NULL rows too, so NULL has to be
        // allowed through explicitly.
        $query = auth()->user()->accessibleTrips()
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'draft'))
            ->withSum('expenses', 'amount')->latest('start_date');
        if ($this->search !== '') {
            // Same matching as Saved Trips: a renamed trip should still be
            // findable by the place it goes to, and vice versa.
            $term = '%' . str_replace('%', '\%', $this->search) . '%';
            $query->where(fn ($w) => $w->where('destination', 'ilike', $term)
                                       ->orWhere('trip_name', 'ilike', $term)
                                       ->orWhere('leg2_destination', 'ilike', $term));
        }
        return $query->get()->map(function (Trip $trip) {
            $today = Carbon::today();
            $spent = $trip->expenses_sum_amount ?? 0;
            $days  = max(1, (int) $trip->start_date->diffInDays($trip->end_date));
            $trip->setAttribute('total_spent', (float) $spent);
            $trip->setAttribute('pct_used',    $trip->budget_limit > 0 ? round($spent / $trip->budget_limit * 100) : 0);
            $trip->setAttribute('days',        $days);
            $trip->setAttribute('status', $trip->resolved_status);
            return $trip;
        });
    }

    private function fetchCompareData(\Illuminate\Support\Collection $trips): array
    {
        if (count($this->compareIds) !== 2) return [];

        return array_map(function ($id) use ($trips) {
            $categories = [];
            foreach ($this->compareCategories as $cat) {
                $categories[$cat] = (float) Expense::where('trip_id', $id)->where('category', $cat)->sum('amount');
            }
            $trip = $trips->firstWhere('id', $id);

            // "Budget" in the comparison card means what the traveler has
            // actually saved toward this trip (their Savings Goal deposits),
            // not the budget_limit set back when the trip was planned —
            // falls back to budget_limit only when the trip has no savings
            // goal at all, so older/goal-less trips don't show ₱0.
            $savedAmount = (float) (SavingsGoal::where('trip_id', $id)->value('current_savings') ?? $trip->budget_limit);

            return [
                'trip'       => $trip,
                'categories' => $categories,
                'budget'     => $savedAmount,
            ];
        }, $this->compareIds);
    }

    public function render()
    {
        $trips = $this->fetchTrips();
        $totals = [
            'count'  => $trips->count(),
            'budget' => $trips->sum('budget_limit'),
            'spent'  => $trips->sum('total_spent'),
        ];
        $detailTrip  = $this->detailTripId ? $trips->firstWhere('id', $this->detailTripId) : null;
        $compareData = $this->fetchCompareData($trips);

        return view('livewire.traveler.multi-trip-hub', compact('trips', 'totals', 'detailTrip', 'compareData'))
            ->layout('layouts.app', ['title' => 'Multi Trip Hub', 'active' => 'multi-trips']);
    }
}
