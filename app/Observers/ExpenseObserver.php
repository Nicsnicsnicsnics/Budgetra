<?php
namespace App\Observers;

use App\Models\Expense;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\TripBudget;

class ExpenseObserver
{
    private const CATEGORY_MAP = [
        'Transportation'     => 'Transportation',
        'Accommodation'      => 'Accommodation',
        'Food'               => 'Food',
        'Activities'         => 'Tourist Attractions',
        'Shopping'           => 'Shopping',
        'Emergency Expenses' => 'Emergency Funds',
    ];

    public function deleted(Expense $expense): void
    {
        $this->adjustActualSpent($expense->trip_id, $expense->category, -$expense->amount);
    }

    // ExpenseController::update() has no budget-sync logic of its own —
    // unlike store() (which calls syncBudgetForExpense() explicitly) and
    // delete() (handled by deleted() above), an edited amount/category/trip
    // would otherwise never be reflected in TripBudget.actual_spent, leaving
    // it permanently wrong after any edit. Reverse whatever the expense used
    // to count toward, then reapply it under its current values — this
    // covers an amount correction, a category change, and moving the
    // expense to a different trip, all with the same two calls.
    public function updated(Expense $expense): void
    {
        if (!$expense->wasChanged(['trip_id', 'category', 'amount'])) return;

        $this->adjustActualSpent(
            (int) $expense->getOriginal('trip_id'),
            $expense->getOriginal('category'),
            -(float) $expense->getOriginal('amount')
        );

        self::syncBudgetForExpense($expense);
    }

    public function created(Expense $expense): void
    {
        $trip = Trip::find($expense->trip_id);

        Notification::create([
            'user_id' => $expense->user_id,
            'trip_id' => $expense->trip_id,
            'type'    => 'expense_added',
            'message' => '₱' . number_format($expense->amount, 2) . " logged for {$expense->category}"
                . ($trip ? " on your {$trip->destination} trip." : '.'),
            'is_read' => false,
        ]);

        $this->checkOverallBudgetExceeded($trip);
    }

    // Overall trip budget (Trip::budget_limit), as opposed to the per-category
    // checks in syncBudgetForExpense() below which compare against each
    // TripBudget row's own estimated_cost.
    private function checkOverallBudgetExceeded(?Trip $trip): void
    {
        if (!$trip || $trip->budget_limit <= 0) return;
        if (!optional($trip->user)->notify_budget_alerts) return;

        $totalSpent = Expense::where('trip_id', $trip->id)->sum('amount');
        if ($totalSpent <= $trip->budget_limit) return;

        $exists = Notification::where('user_id', $trip->user_id)
            ->where('trip_id', $trip->id)
            ->where('type', 'budget_alert')
            ->where('message', 'like', '%exceeded your overall budget%')
            ->exists();
        if ($exists) return;

        Notification::create([
            'user_id' => $trip->user_id,
            'trip_id' => $trip->id,
            'type'    => 'budget_alert',
            'message' => "You've exceeded your overall budget for {$trip->destination}! Total spent: ₱"
                . number_format($totalSpent, 2) . ' of your ₱' . number_format($trip->budget_limit, 2) . ' budget.',
            'is_read' => false,
        ]);
    }

    public static function syncBudgetForExpense(Expense $expense): void
    {
        $budgetCategory = self::CATEGORY_MAP[$expense->category] ?? null;
        if (!$budgetCategory) return;

        $budget = TripBudget::where('trip_id', $expense->trip_id)
                             ->where('category', $budgetCategory)
                             ->first();
        if (!$budget) return;

        $budget->increment('actual_spent', $expense->amount);
        $budget->refresh();

        if ($budget->estimated_cost <= 0) return;

        $pct  = $budget->actual_spent / $budget->estimated_cost;
        $trip = Trip::find($expense->trip_id);
        if (!$trip || !optional($trip->user)->notify_budget_alerts) return;

        // 50% threshold — fires budget_warning (only when below 80%)
        if ($pct >= 0.50 && $pct < 0.80) {
            $exists = Notification::where('user_id', $trip->user_id)
                ->where('trip_id', $expense->trip_id)
                ->where('type', 'budget_warning')
                ->whereRaw("message LIKE ?", ["%{$budgetCategory}%"])
                ->exists();
            if (!$exists) {
                Notification::create([
                    'user_id' => $trip->user_id,
                    'trip_id' => $expense->trip_id,
                    'type'    => 'budget_warning',
                    'message' => "Heads up: Your {$budgetCategory} budget for {$trip->destination} has reached " . round($pct * 100) . "% of the estimated amount.",
                    'is_read' => false,
                ]);
            }
        }

        // 80% threshold — fires budget_alert
        if ($pct >= 0.80) {
            $exists = Notification::where('user_id', $trip->user_id)
                ->where('trip_id', $expense->trip_id)
                ->where('type', 'budget_alert')
                ->whereRaw("message LIKE ?", ["%{$budgetCategory}%"])
                ->exists();
            if (!$exists) {
                Notification::create([
                    'user_id' => $trip->user_id,
                    'trip_id' => $expense->trip_id,
                    'type'    => 'budget_alert',
                    'message' => "Warning: Your {$budgetCategory} budget for {$trip->destination} has reached " . round($pct * 100) . "% of the estimated amount.",
                    'is_read' => false,
                ]);
            }
        }
    }

    private function adjustActualSpent(int $tripId, string $expenseCategory, float $delta): void
    {
        $budgetCategory = self::CATEGORY_MAP[$expenseCategory] ?? null;
        if (!$budgetCategory) return;

        TripBudget::where('trip_id', $tripId)
                  ->where('category', $budgetCategory)
                  ->increment('actual_spent', $delta);
    }
}
