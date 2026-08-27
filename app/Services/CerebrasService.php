<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class CerebrasService
{
    private const MAX_AI_ITINERARY_DAYS = 10;

    private string $key;
    private string $endpoint = 'https://api.cerebras.ai/v1/chat/completions';
    private string $model    = 'gpt-oss-120b';

    public function __construct()
    {
        $this->key = config('services.cerebras.key', '');
    }

    public function generate(string $prompt, int $timeout = 18): ?string
    {
        if (!$this->key) return null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
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

    // Same contract as GeminiService/GroqService/OpenRouterService::suggestAdditionalItinerary.
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
