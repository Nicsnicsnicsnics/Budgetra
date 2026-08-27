<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class MistralService
{
    private const MAX_AI_ITINERARY_DAYS = 10;

    private string $key;
    private string $endpoint = 'https://api.mistral.ai/v1/chat/completions';
    private string $model    = 'mistral-small-latest';

    public function __construct()
    {
        $this->key = config('services.mistral.key', '');
    }

    public function generate(string $prompt, int $timeout = 18): ?string
    {
        if (!$this->key) return null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $response = Http::timeout($timeout)
                    ->connectTimeout(min(10, $timeout))
                    ->withToken($this->key)
                    ->post($this->endpoint, [
                        'model'       => $this->model,
                        'temperature' => 0.7,
                        'max_tokens'  => 2048,
                        'messages'    => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ]);
            } catch (\Illuminate\Http\Client\ConnectionException) {
                // Transient network blip (connection reset/timeout) rather
                // than a real rejection — worth one retry instead of giving
                // up and falling through to a lower-quality provider.
                if ($attempt === 0) continue;
                return null;
            }

            if ($response->successful()) {
                return data_get($response->json(), 'choices.0.message.content');
            }

            if ($response->status() === 429 && $attempt === 0) {
                $retryAfter = (float) ($response->header('Retry-After') ?: 1.5);
                usleep((int) min($retryAfter, 2) * 1_000_000);
                continue;
            }

            return null;
        }

        return null;
    }

    // Same contract as the other planTrip() providers (Groq/OpenRouter/Gemini).
    public function planTrip(string $userPrompt): ?array
    {
        // Without an explicit "today" anchor, a relative duration ("1 week",
        // "next month") has nothing to be relative TO — the model falls back
        // to whatever date it associates with "now" from its own training
        // data, which is routinely in the past relative to the server's
        // actual today.
        $today = date('l, M j, Y');

        $prompt = <<<PROMPT
You are a Philippine travel planner AI. Read the traveler's input, extract all details, then generate a realistic trip package. Return JSON only — no markdown, no explanation, no extra text.

Today's date is {$today}.

Traveler's input: "{$userPrompt}"

Instructions:
1. Extract: origin city (default "Manila" if not mentioned), destination city, budget (min and max in PHP pesos — if single value use it for both), travel start date, travel end date, and number of days.
2. Generate a realistic trip package using REAL hotels, airlines, restaurants, and attractions that exist at the destination.
3. ALL costs combined must NOT exceed the budget_max.
4. Budget split suggestion: transport 18%, accommodation 50%, food 28%, attractions 4%.
5. Use correct Philippine IATA airport codes (MNL=Manila, CEB=Cebu City, DVO=Davao, BCD=Bacolod, ILO=Iloilo, ZAM=Zamboanga, KLO=Kalibo/Boracay, MPH=Malay/Boracay, TAG=Tagbilaran/Bohol, PPS=Puerto Princesa, CGY=Cagayan de Oro, GES=General Santos, etc.).
6. Dates: date_from format "Mon D" (e.g. "Jul 16"), date_to format "Mon D, YYYY" (e.g. "Jul 21, 2026"). If the traveler gave a relative time frame ("next week", "in 3 days") or a duration instead of an end date ("for 5 days", "for a week"), compute the actual calendar dates using today's date above as the reference point — the resulting date_from must never be earlier than today.

Return ONLY this JSON:
{
  "from": "origin city name",
  "to": "destination city name",
  "budget_min": number,
  "budget_max": number,
  "date_from": "Mon D",
  "date_to": "Mon D, YYYY",
  "days": number,
  "transport": {
    "from_code": "IATA",
    "to_code": "IATA",
    "detail": "Airline name · Direct Flight · Round Trip",
    "cost": number
  },
  "accommodation": {
    "name": "Hotel name",
    "stars": number,
    "detail": "N Nights · Room type · City",
    "cost": number
  },
  "food": {
    "name": "Restaurant name (₱amount/pax)",
    "detail": "N Days · Breakfast, Lunch & Dinner · City",
    "cost": number
  },
  "attractions": {
    "items": [["Attraction name", "Free or ₱amount"], ["Attraction name", "Free or ₱amount"]],
    "cost": number
  },
  "total": number,
  "budget": number,
  "pct": number
}
PROMPT;

        $raw = $this->generate($prompt);
        if (!$raw) return null;

        $raw = preg_replace('/```json\s*/i', '', $raw);
        $raw = preg_replace('/```\s*/i', '', $raw);
        $raw = trim($raw);

        return json_decode($raw, true);
    }

    // Same contract as the other enrichPackage() providers (Groq/OpenRouter/Gemini).
    public function enrichPackage(array $package, string $destination, int $days, int $budget): ?array
    {
        $json = json_encode($package, JSON_UNESCAPED_UNICODE);
        $prompt = <<<PROMPT
You are a Philippine travel expert. A trip package has been assembled from live search data for {$destination} ({$days} days, ₱{$budget} budget). Enrich and fix missing or placeholder values only — do NOT change costs or items that already look real.

Current package JSON:
{$json}

Rules:
1. If hotel name is generic (e.g. "Hotel in {$destination}"), replace with a real hotel name that exists in {$destination}.
2. If hotel room type is "Standard Room" and stars ≥ 4, upgrade to "Deluxe Room" or "Superior Room".
3. If any attraction is listed as "Free" but has a known entrance fee in the Philippines, add the real price as "₱NNN".
4. If food detail city says "Philippines" or is empty, replace with "{$destination}".
5. Keep all numeric costs exactly as-is — never change any number.
6. Return the same JSON structure with only the string fields fixed. Return JSON only, no markdown.
PROMPT;

        $raw = $this->generate($prompt);
        if (!$raw) return null;

        $raw = preg_replace('/```json\s*/i', '', $raw);
        $raw = preg_replace('/```\s*/i', '', $raw);
        $decoded = json_decode(trim($raw), true);

        return is_array($decoded) ? $decoded : null;
    }

    // Same contract as the other suggestAdditionalItinerary() providers.
    public function suggestAdditionalItinerary(
        string  $destination,
        string  $startDate,
        string  $endDate,
        int     $budgetMin,
        int     $budgetMax,
        int     $profileBudget,
        array   $interests,
        array   $alreadySelected = [],
        ?string $constraint = null,
        string  $departTime = '',
        bool    $needsAccommodation = false,
        int     $timeout = 18
    ): ?array {
        $days         = max(1, (int) round((strtotime($endDate) - strtotime($startDate)) / 86400));
        // See GroqService::suggestAdditionalItinerary() for why this is capped.
        $days         = min($days, self::MAX_AI_ITINERARY_DAYS);
        $selected     = implode(', ', $alreadySelected) ?: 'none';
        $tags         = implode(', ', array_filter($interests, fn($i) => strlen($i) < 80)) ?: 'general travel';
        $minBudg      = $budgetMin ?: (int) round(($budgetMax ?: 0) * 0.7);
        $maxBudg      = $budgetMax ?: $budgetMin;
        $minPerDay    = (int) round($minBudg / max(1, $days));
        $maxPerDay    = (int) round($maxBudg / max(1, $days));
        $extraRule       = $constraint ? "\n0. OVERRIDE CONSTRAINT: {$constraint}" : '';
        $departTimeLabel = $departTime ?: '05:00 PM';
        // The traveler skipped picking a hotel — have the AI suggest one
        // instead of leaving the whole trip with no lodging at all.
        $accommodationRule = $needsAccommodation
            ? "\n7. The traveler has NOT booked accommodation yet. Add ONE lodging suggestion as the FIRST activity on Day 1: a real, existing hotel or guesthouse in {$destination} that fits the budget, type \"accommodation\", time \"02:00 PM\", title the hotel's name, description mentioning room type and star rating, and cost equal to the TOTAL price for all {$days} night(s) of the stay (not a per-night rate). Do not add a separate accommodation entry on any other day."
            : '';

        $prompt = <<<PROMPT
You are a Philippine travel itinerary AI. Generate ADDITIONAL daily activities for a trip to {$destination} that complement what the traveler already selected.

Trip details:
- Destination: {$destination}
- Dates: {$startDate} to {$endDate} ({$days} days)
- Budget range: ₱{$minBudg} (minimum) to ₱{$maxBudg} (maximum)
- Target daily spend: ₱{$minPerDay}–₱{$maxPerDay}/day
- Traveler interests: {$tags}
- Already selected by traveler: {$selected}

Rules:{$extraRule}
1. Do NOT repeat the already-selected items.
2. Suggest realistic, existing places in {$destination}.
3. ALL suggested activities MUST directly reflect the traveler's interests: {$tags}. Every day's label and activities should align with one or more of these interests.
4. BUDGET RULE: The combined cost of ALL suggested activities MUST fall between ₱{$minBudg} and ₱{$maxBudg}. Do NOT exceed ₱{$maxBudg}. Aim close to ₱{$maxBudg}.
5. Each day's activities should collectively cost between ₱{$minPerDay} and ₱{$maxPerDay}.
6. Spread activities across morning / afternoon / evening slots (3 activities per day minimum).{$accommodationRule}
7. On the LAST day, schedule light morning/midday activities (souvenir shopping, nearby café, short attraction) that finish at least 3 hours before the departure flight. The traveler's departure flight is at {$departTimeLabel}. Add a final activity entry on the last day: type "transport", title "Head to Airport / Departure", time set to 2 hours before {$departTimeLabel}, cost 0.
8. Return JSON only — no markdown, no explanation.

Return this exact JSON structure:
{
  "days": [
    {
      "day": 1,
      "label": "Day 1 label (e.g. Arrival & Explore)",
      "activities": [
        {
          "time": "09:00 AM",
          "title": "Activity name",
          "description": "Short description",
          "type": "accommodation|food|restaurant|attraction|leisure|activity|transport|shopping|nature|beach|culture|adventure|nightlife|spa",
          "cost": 0
        }
      ]
    }
  ]
}
PROMPT;

        $raw = $this->generate($prompt, $timeout);
        if (!$raw) return null;

        $raw = preg_replace('/```json\s*/i', '', $raw);
        $raw = preg_replace('/```\s*$/im', '', $raw);
        $decoded = json_decode(trim($raw), true);

        return is_array($decoded) ? $decoded : null;
    }
}
