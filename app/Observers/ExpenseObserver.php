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
