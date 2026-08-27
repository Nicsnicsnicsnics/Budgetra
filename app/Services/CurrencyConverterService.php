<?php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyConverterService
{
    /**
     * Rates are live-only, by design: no hardcoded table anywhere. If the
     * provider can't be reached the caller is told so and decides what to do —
     * display pesos, or refuse to save. What a caller must never do is treat a
     * foreign amount as though it were already pesos.
     */
    private string $key;

    /**
     * Within one request the same currency is asked for over and over — the
     * trip planner's blade alone calls tripDisplayAmount() 26 times. This
     * memo keeps all of those to a single cache round trip. Static because a
     * new service instance is constructed at nearly every call site.
     *
     * @var array<string, float|null>
     */
    private static array $memo = [];

    public function __construct()
    {
        $this->key = config('services.currency_converter.key');
    }

    /**
     * "1 unit of $code equals this many PHP", so foreignAmount * rate = pesos.
     *
     * Returns null both for PHP (nothing to convert) and for an unreachable
     * rate. Callers that persist money must guard PHP themselves and treat the
     * remaining null as "cannot convert" — see ProfileBuilder::persistProfile()
     * and TripPlannerWizard::confirmEmergencyFund().
     */
    public function rateToPhp(string $code): ?float
    {
        if (empty($this->key) || $code === 'PHP') return null;

        if (array_key_exists($code, self::$memo)) return self::$memo[$code];

        $cacheKey = "currency_rate_{$code}_php";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) return self::$memo[$code] = $cached;

        // Failures are cached too, briefly. Without this a provider outage cost
        // one 10-second request per call — 26 of them on a single trip-planner
        // render — and on a rate-limited free tier the retries were themselves
        // what kept the limit tripped.
        if (Cache::get("{$cacheKey}_miss") !== null) return self::$memo[$code] = null;

        try {
            $response = Http::timeout(4)->get('https://api.twelvedata.com/exchange_rate', [
                'symbol' => "{$code}/PHP",
                'apikey' => $this->key,
            ]);
        } catch (\Throwable) {
            return $this->rememberMiss($code, $cacheKey);
        }

        if (!$response->successful()) return $this->rememberMiss($code, $cacheKey);

        $rate = $response->json('rate');
        if (!is_numeric($rate) || $rate <= 0) return $this->rememberMiss($code, $cacheKey);

        Cache::put($cacheKey, (float) $rate, now()->addHours(6));
        return self::$memo[$code] = (float) $rate;
    }

    private function rememberMiss(string $code, string $cacheKey): null
    {
        // Short, so a recovered provider is picked up quickly.
        Cache::put("{$cacheKey}_miss", true, now()->addMinutes(5));

        return self::$memo[$code] = null;
    }

    /**
     * Drops the in-request memo. Only needed in tests, where several provider
     * states are exercised inside one process.
     */
    public static function forgetMemo(): void
    {
        self::$memo = [];
    }
}
