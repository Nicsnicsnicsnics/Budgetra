<?php
namespace App\Services;

use App\Models\Trip;

class BudgetService
{
    public function summary(Trip $trip): array
    {
        $budgets = $trip->budgets;

        $totalEstimated = $budgets->sum('estimated_cost');
        $totalSpent     = $budgets->sum('actual_spent');

        return [
            'total_estimated' => $totalEstimated,
            'total_spent'     => $totalSpent,
            'remaining'       => $totalEstimated - $totalSpent,
            'categories'      => $budgets->map(fn($b) => [
                'category'       => $b->category,
                'estimated_cost' => $b->estimated_cost,
                'actual_spent'   => $b->actual_spent,
                'remaining'      => $b->estimated_cost - $b->actual_spent,
            ])->values()->all(),
        ];
    }
}
