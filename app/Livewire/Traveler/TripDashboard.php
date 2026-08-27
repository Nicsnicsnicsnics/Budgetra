<?php
namespace App\Livewire\Traveler;

use App\Models\Expense;
use App\Models\Trip;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['active' => 'trips'])]
class TripDashboard extends Component
{
    public Trip $trip;

    public function mount(Trip $trip): void
    {
        abort_if($trip->user_id !== auth()->id(), 403);
        $this->trip = $trip;
    }

    public function getTotalSpentProperty(): float
    {
        return (float) $this->trip->expenses()->sum('amount');
    }

    public function getRemainingProperty(): float
    {
        return (float) $this->trip->budget_limit - $this->totalSpent;
    }

    public function getSpentPctProperty(): float
    {
        if (!$this->trip->budget_limit) return 0;
        return round($this->totalSpent / $this->trip->budget_limit * 100, 1);
    }

    public function getDaysProperty(): int
    {
        return max(1, (int) $this->trip->start_date->diffInDays($this->trip->end_date));
    }

    public function getCategorySpendProperty(): array
    {
        return $this->trip->expenses()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();
    }

    public function getDailySpendProperty(): array
    {
        return $this->trip->expenses()
            ->selectRaw('expense_date, SUM(amount) as total')
            ->groupBy('expense_date')
            ->orderBy('expense_date')
            ->pluck('total', 'expense_date')
            ->toArray();
    }

    public function getBudgetBreakdownProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->trip->budgets()->get();
    }

    public function getRecentExpensesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->trip->expenses()->latest('expense_date')->limit(5)->get();
    }

    public function render()
    {
        return view('livewire.traveler.trip-dashboard');
    }
}
