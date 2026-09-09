<?php
namespace App\Livewire\Traveler\Concerns;

use App\Support\PlaceCatalog;

/**
 * Turning what a traveller typed into a place the app actually knows: catalogue
 * lookup first, then a fuzzy "did you mean", then an AI verifier as a last
 * resort — plus the questions asked about a place once resolved (its IATA code,
 * whether two places are the same, whether the trip leaves the country).
 *
 * Methods only. The state they read — aiFrom, aiTo, aiPlaceCache,
 * pendingPlaceSuggestion, placeVerificationFailed — stays declared on the
 * component, along with the PHILIPPINE_IATA_CODES, NON_ANSWER_FILLERS and
 * PROVIDER_ORDER constants. A trait is flattened into the using class, so
 * $this-> and self:: resolve exactly as before.
 *
 * matchKnownPlace() calls looksLikeGibberish() from ClassifiesText, and
 * askPlaceVerifier() calls decodeAiJson() on the component — both fine for the
 * same reason.
 */
trait ResolvesPlaces
{
    private function looksLikeAttempt(string $slot, string $userText): bool
    {
        return match ($slot) {
            'destination' => $this->placeCueDirection($userText) !== 'origin' && $this->hasPlaceLikeCandidate($userText),
            'origin'      => $this->placeCueDirection($userText) !== 'destination' && $this->hasPlaceLikeCandidate($userText),
            'travelers' => (bool) preg_match('/\d|\bsolo\b|\balone\b|\bjust me\b|\bmyself\b/i', $userText),
            'budget'    => (bool) preg_match('/\d/', $userText),
            'dates'     => (bool) preg_match(
                '/\d|january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec/i',
                $userText
            ),
            default => false,
        };
    }

    private function placeCueDirection(string $text): ?string
    {
        $hasOriginCue = (bool) preg_match('/\b(?:from|leaving from|departing from|starting from)\s+[a-z]{2,}/i', $text);
        $hasDestinationCue = (bool) preg_match(
            '/\b(?:to|in|at|visit(?:ing)?|travel(?:l?ing)?\s+to|fly(?:ing)?\s+to|go(?:ing)?\s+to|stay(?:ing)?\s+(?:in|at))\s+[a-z]{2,}/i',
            $text
        );

        if ($hasOriginCue && !$hasDestinationCue) return 'origin';
        if ($hasDestinationCue && !$hasOriginCue) return 'destination';
        return null;
    }

    private function hasPlaceLikeCandidate(string $text): bool
    {
        if (preg_match('/[A-Z][a-z]+/', $text)) return true;

        $notPlaceWords = 'plan|book|go|travel|visit|find|get|make|do|have|see|know|ask|try|be|buy|spend|save|figure|decide|somewhere|anywhere|someplace';
        return (bool) preg_match(
            '/\b(?:to|in|at|from|visit(?:ing)?|travel(?:l?ing)?\s+to|fly(?:ing)?\s+to|go(?:ing)?\s+to|stay(?:ing)?\s+(?:in|at))\s+(?!(?:' . $notPlaceWords . ')\b)[a-z]{2,}\b/iu',
            $text
        );
    }

    private function matchKnownPlace(string $city, ?string $slotContext = null): ?array
    {
        $map = PlaceCatalog::IATA_CODES;

        $key = strtolower(trim($city));
        if (isset($map[$key])) return ['name' => $key, 'code' => $map[$key]];

        $words = preg_split('/[\s,!?.;:]+/', $key, -1, PREG_SPLIT_NO_EMPTY);
        $count = count($words);
        for ($len = $count - 1; $len >= 1; $len--) {
            for ($start = 0; $start + $len <= $count; $start++) {
                $candidate = implode(' ', array_slice($words, $start, $len));
                if (!isset($map[$candidate])) continue;

                if ($len === 1 && $count > 1 && mb_strlen($candidate) <= 3) continue;

                return ['name' => $candidate, 'code' => $map[$candidate]];
            }
        }

        if ($key !== '' && mb_strlen($key) <= 40 && !$this->looksLikeGibberish($key)) {
            $bestName = null;
            $bestPct  = 0.0;
            foreach (array_keys($map) as $candidateName) {
                similar_text($key, $candidateName, $pct);
                if ($pct > $bestPct) {
                    $bestPct  = $pct;
                    $bestName = $candidateName;
                }
            }

            if ($bestName !== null && $bestPct >= 75.0
                && !($this->pendingPlaceSuggestion !== null && $this->pendingPlaceSuggestionSlot !== null)) {
                $this->pendingPlaceSuggestion = ucwords($bestName);
                $this->pendingPlaceSuggestionSlot = $slotContext;
                return null;
            }
        }

        return $this->aiPlaceFallback($key, $slotContext);
    }

    private function aiPlaceFallback(string $key, ?string $slotContext = null): ?array
    {
        if ($key === '' || mb_strlen($key) > 40) return null;
        if (in_array($key, self::NON_ANSWER_FILLERS, true)) return null;
        if (array_key_exists($key, $this->aiPlaceCache)) return $this->aiPlaceCache[$key];

        if ($this->looksLikeGibberish($key)) return $this->aiPlaceCache[$key] = null;

        set_time_limit(90);

        $prompt = <<<PROMPT
        Is "{$key}" a real, specific travel destination — an actual city, town, or island that genuinely exists?

        Be skeptical. Only answer yes if you are genuinely confident this is a real place you have real knowledge of — not just because the name sounds plausible or place-like. If you don't specifically recognize it, or aren't sure, answer no. This is NOT a made-up place, NOT a generic word or phrase, and NOT a whole country by itself.

        If yes, return its most common English city/town/island name, and its IATA code is REQUIRED — never leave it null when is_real_place is true. If the place has no airport of its own, you MUST still give the IATA code of the real nearest major airport travelers would actually fly into to reach it (e.g. a small town near a bigger city uses that city's airport code).
        If no, return false and null for both fields.

        Return JSON only, no markdown:
        {"is_real_place": true or false, "name": "city name or null", "iata_code": "CODE or null"}
        PROMPT;

        $providers = self::PROVIDER_ORDER;

        $first = $this->askPlaceVerifier($prompt, $providers);
        if ($first === null) {
            $this->placeVerificationFailed = true;
            $this->placeVerificationFailedSlot = $slotContext;
            return $this->aiPlaceCache[$key] = null;
        }

        $second = $this->askPlaceVerifier($prompt, array_values(array_diff($providers, [$first['provider']])));
        if ($second === null) {
            $this->placeVerificationFailed = true;
            $this->placeVerificationFailedSlot = $slotContext;
            return $this->aiPlaceCache[$key] = null;
        }

        if ($first['data'] === null || $second['data'] === null) {
            return $this->aiPlaceCache[$key] = null;
        }

        $code = strtoupper(trim((string) ($first['data']['iata_code'] ?? '')));
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            $code = strtoupper(trim((string) ($second['data']['iata_code'] ?? '')));
        }
        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return $this->aiPlaceCache[$key] = null;
        }

        return $this->aiPlaceCache[$key] = ['name' => strtolower(trim((string) $first['data']['name'])), 'code' => $code];
    }

    private function askPlaceVerifier(string $prompt, array $providerClasses): ?array
    {
        foreach ($providerClasses as $class) {
            try {
                $raw = (new $class())->generate($prompt);
            } catch (\Throwable) {
                continue;
            }

            if (!$raw) continue;

            $json  = $this->decodeAiJson($raw);
            $valid = $json !== null && !empty($json['is_real_place']) && !empty($json['name']) && !empty($json['iata_code']);

            return ['provider' => $class, 'data' => $valid ? $json : null];
        }

        return null;
    }

    public function iataCode(string $city): string
    {
        return $this->matchKnownPlace($city)['code'] ?? '';
    }

    private function isInternationalDestination(string $cityName): bool
    {
        $destination = PlaceCatalog::countryFor($cityName);

        if ($destination === null) {
            $code = $this->iataCode($cityName);
            return $code !== '' && !in_array($code, self::PHILIPPINE_IATA_CODES, true);
        }

        $origin = PlaceCatalog::countryFor($this->aiFrom)
            ?? PlaceCatalog::originCountryFor(auth()->user()?->country);

        return strcasecmp($destination, $origin) !== 0;
    }

    private function knownPlaceName(string $text, ?string $slotContext = null): string
    {
        $match = $this->matchKnownPlace($text, $slotContext);
        return $match !== null ? ucwords($match['name']) : '';
    }

    private function resolveCode(string $city): string
    {
        $code = $this->iataCode($city);
        return $code !== '' ? $code : trim($city);
    }

    private function sameOriginAndDestination(): bool
    {
        if ($this->aiFrom === '' || $this->aiTo === '') return false;
        return $this->samePlace($this->aiFrom, $this->aiTo);
    }

    private function samePlace(string $a, string $b): bool
    {
        if ($a === '' || $b === '') return false;
        if (strtolower($a) === strtolower($b)) return true;

        $codeA = $this->iataCode($a);
        return $codeA !== '' && $codeA === $this->iataCode($b);
    }

    private function wantsInternational(string $text): bool
    {
        return (bool) preg_match('/\binternational\b|\babroad\b|\boverseas\b|\bout of the country\b/i', $text);
    }

    private function cleanCityName(string $name): string
    {
        $name = trim($name);

        $name = preg_replace('/^\s*(go(?:ing)?|travel(?:ling)?|fly(?:ing)?|visit(?:ing)?|head(?:ing)?|trip)\s+(?:to\s+)?/i', '', $name);

        $name = preg_replace('/\s+(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec)\b.*/i', '', $name);

        $name = preg_replace('/\s+\d+.*$/', '', $name);
        return ucwords(strtolower(trim($name)));
    }
}
