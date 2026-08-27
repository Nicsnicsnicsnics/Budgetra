<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyConverterService
{
    private string $key;

    public function __construct()
    {
        $this->key = config('services.currency_converter.key');
    }

    // Rate is "1 unit of $code equals this many PHP" — matches Llm.php's
    // SUPPORTED_CURRENCIES['rate'] convention (foreignAmount * rate = pesos).
    // Only successful lookups are cached; a failure returns null immediately
    // so the caller can fall back to its own static table without waiting
    // out a stale cache entry.
    public function rateToPhp(string $code): ?float
    {
        if (empty($this->key) || $code === 'PHP') return null;

        $cacheKey = "currency_rate_{$code}_php";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        try {
            $response = Http::timeout(10)->get('https://api.twelvedata.com/exchange_rate', [
                'symbol' => "{$code}/PHP",
                'apikey' => $this->key,
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (!$response->successful()) return null;

        $rate = $response->json('rate');
        if (!is_numeric($rate) || $rate <= 0) return null;

        Cache::put($cacheKey, (float) $rate, now()->addHours(6));
        return (float) $rate;
    }
}
