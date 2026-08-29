<?php
namespace App\Livewire\Traveler;

use App\Models\Notification;
use App\Models\SavingsGoal;
use App\Services\CurrencyConverterService;
use App\Support\PlaceCatalog;
use Carbon\Carbon;
use Livewire\Component;

class SavingsGoalManager extends Component
{
    public SavingsGoal $goal;

    public bool  $showDeposit    = false;
    public bool  $showProjection = false;
    public float $depositAmount  = 0;

    public function openDeposit(): void
    {
        $this->depositAmount = 0;
        $this->showDeposit   = true;
    }

    public function closeDeposit(): void
    {
        $this->showDeposit = false;
    }

    public function openProjection(): void
    {
        $this->showProjection = true;
    }

    public function closeProjection(): void
    {
        $this->showProjection = false;
    }

    public function homeCurrency(): string
    {
        return home_currency();
    }

    public function homeCurrencySymbol(): string
    {
        return home_currency_symbol();
    }

    public function destinationCurrency(): ?string
    {
        $trip = $this->goal->trip;
        if (!$trip) return null;

        if (!empty($trip->destination_currency) && $trip->destination_currency !== 'PHP') {
            return $trip->destination_currency;
        }

        if (!empty($trip->destination)) {
            $code = PlaceCatalog::DESTINATION_CURRENCIES[strtolower(trim($trip->destination))] ?? null;
            if ($code && $code !== 'PHP') {
                return $code;
            }
        }

        return null;
    }

    public function destinationCurrencySymbol(): ?string
    {
        $code = $this->destinationCurrency();
        if (!$code) return null;
        return PlaceCatalog::CURRENCY_SYMBOLS[$code] ?? $code . ' ';
    }

    public function homeRate(): ?float
    {
        $code = $this->homeCurrency();
        if ($code === 'PHP') return 1.0;
        return (new CurrencyConverterService())->rateToPhp($code);
    }

    public function destinationRate(): ?float
    {
        $code = $this->destinationCurrency();
        if (!$code || $code === 'PHP') return 1.0;
        return (new CurrencyConverterService())->rateToPhp($code);
    }

    public function hasCurrencyConversion(): bool
    {
        $destCode = $this->destinationCurrency();
        $homeCode = $this->homeCurrency();
        if (!$destCode || $destCode === $homeCode) {
            return false;
        }
        return $this->destinationRate() !== null;
    }

    public function conversionRateLabel(): ?string
    {
        if (!$this->hasCurrencyConversion()) return null;

        $destCode = $this->destinationCurrency();
        $homeCode = $this->homeCurrency();
        $destRate = $this->destinationRate();
        $homeRate = $this->homeRate() ?? 1.0;

        if (!$destRate || !$homeRate) return null;

        $rateInHome = $destRate / $homeRate;
        $homeSymbol = $this->homeCurrencySymbol();

        if ($rateInHome >= 1) {
            $formatted = number_format($rateInHome, 2);
        } elseif ($rateInHome >= 0.0001) {
            $formatted = rtrim(rtrim(number_format($rateInHome, 4), '0'), '.');
            if (strlen(explode('.', $formatted)[1] ?? '') < 2) {
                $formatted = number_format($rateInHome, 2);
            }
        } else {
            $formatted = rtrim(rtrim(number_format($rateInHome, 6), '0'), '.');
        }

        return "1 {$destCode} ≈ {$homeSymbol}{$formatted} {$homeCode}";
    }

    public function displayHomeAmount(float $pesoAmount): string
    {
        $code   = $this->homeCurrency();
        $symbol = $this->homeCurrencySymbol();
        if ($code === 'PHP') {
            return '₱' . number_format($pesoAmount, 2);
        }
        $rate = $this->homeRate();
        if ($rate === null) {
            return '₱' . number_format($pesoAmount, 2);
        }
        return $symbol . number_format($pesoAmount / $rate, 2);
    }

    public function displayDestinationAmount(float $pesoAmount): ?string
    {
        $destCode = $this->destinationCurrency();
        if (!$destCode) return null;
        $rate = $this->destinationRate();
        if ($rate === null) return null;
        $symbol = $this->destinationCurrencySymbol();
        return $symbol . number_format($pesoAmount / $rate, 2);
    }

    public function displayAmount(float $pesoAmount): string
    {
        return $this->displayHomeAmount($pesoAmount);
    }

    public function submitDeposit(): void
    {
        abort_if($this->goal->user_id !== auth()->id(), 403);

        $homeCode = $this->homeCurrency();
        $homeRate = $this->homeRate() ?? 1.0;
        $targetCost = $this->goal->trip?->total_cost ?? $this->goal->target_amount;
        $remainingPesos = max(0, $targetCost - $this->goal->current_savings);
        $remainingHome  = $homeRate > 0 ? round($remainingPesos / $homeRate, 2) : $remainingPesos;

        $this->validate([
            'depositAmount' => [
                'required', 'numeric', 'min:0.01',
                'max:' . max(0.01, $remainingHome),
            ],
        ], [
            'depositAmount.max' => 'Amount can\'t exceed the remaining ' . $this->homeCurrencySymbol() . number_format($remainingHome, 2) . ' needed to reach this goal.',
        ]);

        $pesoDeposit = $homeCode === 'PHP' ? (float) $this->depositAmount : round((float) $this->depositAmount * $homeRate, 2);

        $wasCompleted = $this->goal->current_savings >= $targetCost;

        $this->goal->increment('current_savings', $pesoDeposit);
        $this->goal->refresh();

        $savedHomeFormatted = $this->displayHomeAmount($this->goal->current_savings);
        $depositHomeFormatted = $this->homeCurrencySymbol() . number_format($this->depositAmount, 2);
        $targetHomeFormatted = $this->displayHomeAmount($targetCost);

        if (!$wasCompleted && $this->goal->current_savings >= $targetCost) {
            Notification::create([
                'user_id' => $this->goal->user_id,
                'trip_id' => $this->goal->trip_id,
                'type'    => 'savings_goal_reached',
                'message' => "Congratulations! You've reached your savings goal \"{$this->goal->goal_name}\" — {$savedHomeFormatted} saved!",
                'is_read' => false,
            ]);
        } elseif (!$wasCompleted) {
            $pct = $targetCost > 0
                ? min(100, round($this->goal->current_savings / $targetCost * 100))
                : 0;
            Notification::create([
                'user_id' => $this->goal->user_id,
                'trip_id' => $this->goal->trip_id,
                'type'    => 'savings_goal_deposit',
                'message' => "You added {$depositHomeFormatted} to \"{$this->goal->goal_name}\" — now {$savedHomeFormatted} of {$targetHomeFormatted} saved ({$pct}%).",
                'is_read' => false,
            ]);
        }

        $this->depositAmount = 0;
        $this->showDeposit   = false;
        $this->dispatch('goalUpdated');
    }

    public function getPctProperty(): float
    {
        $targetCost = $this->goal->trip?->total_cost ?? $this->goal->target_amount;
        if (!$targetCost) return 0;
        return min(100, round($this->goal->current_savings / $targetCost * 100, 1));
    }

    public function getDailyNeededProperty(): float
    {
        $targetCost = $this->goal->trip?->total_cost ?? $this->goal->target_amount;
        $remaining = $targetCost - $this->goal->current_savings;
        if ($remaining <= 0) return 0;
        $days = max(1, (int) Carbon::today()->diffInDays($this->goal->deadline, false));
        return $days > 0 ? round($remaining / $days, 2) : $remaining;
    }

    public function getDaysLeftProperty(): int
    {
        return max(0, (int) Carbon::today()->diffInDays($this->goal->deadline, false));
    }

    public function getIsCompletedProperty(): bool
    {
        $targetCost = $this->goal->trip?->total_cost ?? $this->goal->target_amount;
        return $this->goal->current_savings >= $targetCost;
    }

    public function render()
    {
        return view('livewire.traveler.savings-goal-manager');
    }
}

