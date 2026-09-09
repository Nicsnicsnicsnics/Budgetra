<?php
namespace App\Livewire\Traveler\Concerns;

use App\Services\CurrencyConverterService;
use App\Support\PlaceCatalog;

/**
 * Reading money out of what the traveller typed, converting it into the pesos
 * every money column stores, and rendering it back in their own currency.
 *
 * Only the methods live here. The figures themselves — aiCurrency, aiBudgetMin,
 * aiBudgetMax, aiBudgetLocal — stay declared on the component, because Livewire
 * hydrates public properties there and the slot-filling and package code reads
 * them too. Same for the constants (MAX_BUDGET, SUPPORTED_CURRENCIES,
 * CURRENCY_ALIASES, the international floors): a trait is flattened into the
 * using class, so self:: still resolves.
 */
trait HandlesCurrency
{
    private function budgetContextForPrompt(): string
    {
        if ($this->aiBudgetMin <= 0 && $this->aiBudgetMax <= 0) return '';

        $travelers = max(1, $this->aiTravelers);
        $amount    = $this->aiBudgetMax ?: $this->aiBudgetMin;
        $travelerWord = $travelers === 1 ? 'traveler' : 'travelers';

        return "\n\nTraveler's total trip budget: ₱" . number_format($amount) . " for {$travelers} {$travelerWord}. Every suggestion MUST be realistically reachable and affordable within this budget (round-trip flights + accommodation + food + activities combined) — do not suggest a destination that would obviously blow this budget, such as a long-haul international trip on a small domestic-trip budget.";
    }

    private function formattedBudget(): string
    {
        return $this->aiBudgetMin === $this->aiBudgetMax
            ? $this->displayAmount($this->aiBudgetMax)
            : $this->displayAmount($this->aiBudgetMin) . ' - ' . $this->displayAmount($this->aiBudgetMax);
    }

    /**
     * Days to price a trip over — the length once known, otherwise a week, so
     * a budget can still be judged before dates have been given.
     */
    private function floorDays(): int
    {
        return $this->aiDays > 0 ? $this->aiDays : 7;
    }

    /**
     * The least a trip can cost, built the way processAiTrip() already builds
     * the real package: a ticket each, one room between them, food and local
     * transport each. Splitting it that way is why four travellers need far
     * more than one, but not four times as much.
     *
     * ROOM + LIVING equals the flat daily rate this replaced, so a solo trip
     * comes out at exactly the figure it did before travellers were counted.
     */
    private function tripFloor(int $flightEach, int $roomPerDay, int $livingEach): int
    {
        $days       = $this->floorDays();
        $travellers = max(1, $this->aiTravelers);

        return ($flightEach * $travellers)
             + ($roomPerDay * $days)
             + ($livingEach * $days * $travellers);
    }

    private function domesticFloor(): int
    {
        return $this->tripFloor(
            self::DOMESTIC_FLIGHT_FLOOR,
            self::DOMESTIC_ROOM_FLOOR,
            self::DOMESTIC_LIVING_FLOOR,
        );
    }

    private function internationalFloor(): int
    {
        return $this->tripFloor(
            self::INTERNATIONAL_FLIGHT_FLOOR,
            self::INTERNATIONAL_ROOM_FLOOR,
            self::INTERNATIONAL_LIVING_FLOOR,
        );
    }

    /**
     * The per-day figure behind "is my budget enough?".
     *
     * Derived from the same constants as the gate so the two cannot drift:
     * advising that 500/day is fine while refusing to plan anything under
     * 1,000/day would have TARA contradict itself in two messages.
     */
    private function budgetFloor(): int
    {
        return self::DOMESTIC_ROOM_FLOOR + self::DOMESTIC_LIVING_FLOOR;
    }

    private function internationalBudgetShortfallMessage(): ?string
    {
        if ($this->aiBudgetMin <= 0 && $this->aiBudgetMax <= 0) return null;

        $days    = $this->floorDays();
        $budget  = $this->aiBudgetMax ?: $this->aiBudgetMin;
        $minimum = $this->internationalFloor();

        if ($budget >= $minimum) return null;

        $fromText = $this->aiFrom !== '' ? " from {$this->aiFrom}" : '';
        return "Your {$this->formattedBudget()} budget is too low for a {$days}-day international trip{$fromText}. Please increase your budget.";
    }

    private function currencySuffixWords(): string
    {
        $words = array_map(fn ($code) => strtolower($code), array_keys(self::SUPPORTED_CURRENCIES));
        $words[] = 'pesos?';
        return implode('|', $words);
    }

    private function currencyRate(string $code): ?float
    {
        if ($code === 'PHP') return 1.0;

        return (new CurrencyConverterService())->rateToPhp($code);
    }

    private function applyLocalBudget(float $min, float $max): void
    {
        $code = home_currency();

        if ($code === 'PHP') {
            $this->aiCurrency    = $code;
            $this->aiBudgetLocal = null;
            $this->aiBudgetMin   = (int) min(self::MAX_BUDGET, $min);
            $this->aiBudgetMax   = (int) min(self::MAX_BUDGET, $max);
            return;
        }

        $rate = $this->currencyRate($code);
        if ($rate === null) return;

        $this->aiCurrency    = $code;
        $this->aiBudgetLocal = $max;
        $this->aiBudgetMin   = (int) min(self::MAX_BUDGET, round($min * $rate));
        $this->aiBudgetMax   = (int) min(self::MAX_BUDGET, round($max * $rate));
    }

    private function detectAndConvertCurrency(string $text): array|false|null
    {
        $symbolOrCode = '(?:\$|＄|€|£|￡|¥|￥|₩|￦|₱|₹|₫|₦|USD|EUR|GBP|JPY|SGD|AUD|KRW|HKD|THB|MYR|AED|PHP|pesos?'
            . '|IDR|VND|CNY|INR|NZD|CAD|BRL|MXN|ARS|SAR|EGP|NGN|ZAR|KES)';

        $number = '(?:\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)(?:[kK](?![a-zA-Z]))?';

        if (preg_match('/(' . $symbolOrCode . ')\s*(' . $number . ')/iu', $text, $m)) {
            [$marker, $amountRaw] = [$m[1], $m[2]];
        } elseif (preg_match('/(' . $number . ')\s*(' . $symbolOrCode . ')/iu', $text, $m)) {
            [$amountRaw, $marker] = [$m[1], $m[2]];
        } else {
            return null;
        }

        $code = self::CURRENCY_ALIASES[strtolower($marker)] ?? null;
        if ($code === null || $code === 'PHP') return null;

        $currency = self::SUPPORTED_CURRENCIES[$code] ?? null;
        if ($currency === null) return null;

        $hasThousandsSuffix = (bool) preg_match('/[kK]$/', $amountRaw);
        $numericPart = $hasThousandsSuffix ? substr($amountRaw, 0, -1) : $amountRaw;
        $foreignAmount = (float) str_replace(',', '', $numericPart);
        if ($hasThousandsSuffix) $foreignAmount *= 1000;
        if ($foreignAmount <= 0) return null;

        $this->aiCurrency = $code;

        $rate = $this->currencyRate($code);
        if ($rate === null) return false;

        return [
            'code'         => $code,
            'currencyName' => $currency['name'],
            'pesoAmount'   => (int) round($foreignAmount * $rate),
            'localAmount'  => $foreignAmount,
            'displayLabel' => $currency['symbol'] . number_format($foreignAmount),
        ];
    }

    private function detectUnsupportedCurrency(string $text): ?string
    {
        $number = '(?:\d{1,3}(?:,\d{3})+|\d+(?:\.\d+)?)';

        if (preg_match('/\b([A-Z]{3})\b\s*' . $number . '/', $text, $m)
            || preg_match('/' . $number . '\s*\b([A-Z]{3})\b/', $text, $m)) {
            $code = strtoupper($m[1]);
            if (!isset(self::SUPPORTED_CURRENCIES[$code])) return $code;
        }

        foreach (['₴' => 'UAH'] as $sym => $code) {
            if (str_contains($text, $sym)) return $code;
        }

        return null;
    }

    public function displayAmount(int|float $pesoAmount, ?string $currencyCode = null): string
    {
        $code = $currencyCode ?? $this->aiCurrency;
        if (!isset(self::SUPPORTED_CURRENCIES[$code])) $code = 'PHP';

        $rate = $this->currencyRate($code);
        if ($rate === null) {
            return self::SUPPORTED_CURRENCIES['PHP']['symbol'] . number_format($pesoAmount);
        }

        $currency = self::SUPPORTED_CURRENCIES[$code];
        return $currency['symbol'] . number_format($pesoAmount / $rate);
    }

    private function destinationCurrencyCode(): ?string
    {
        if ($this->aiTo === '') return null;
        return PlaceCatalog::DESTINATION_CURRENCIES[strtolower(trim($this->aiTo))] ?? null;
    }

    private function destinationBudgetConversion(): ?array
    {
        if ($this->aiBudgetMax <= 0) return null;

        $code = $this->destinationCurrencyCode();
        if ($code === null) return null;

        $rate = $this->currencyRate($code);
        if ($rate === null) return null;

        return ['code' => $code, 'amount' => round($this->aiBudgetMax / $rate, 2)];
    }

    private function formatDestinationAmount(string $code, float $amount): string
    {
        $symbol = self::SUPPORTED_CURRENCIES[$code]['symbol'] ?? null;
        return $symbol !== null ? $symbol . number_format($amount) : number_format($amount) . ' ' . $code;
    }

    private function parseMoneyToken(string $token): int
    {
        $token = trim($token);
        $token = preg_replace('/\s*(?:php|pesos?)$/i', '', $token);
        if (preg_match('/^(\d+(?:,\d{3})*)\s*[kK]$/', $token, $m)) {
            return min(self::MAX_BUDGET, (int) str_replace(',', '', $m[1]) * 1000);
        }
        return min(self::MAX_BUDGET, (int) str_replace(',', '', $token));
    }
}
