<?php
namespace App\Livewire\Traveler\Concerns;

use App\Models\UserProfile;
use App\Services\CurrencyConverterService;
use App\Services\UserProfileSaver;
use App\Support\PlaceCatalog;
use App\Support\ProfileCatalog;

trait BuildsProfile
{
    private function offerSavedPreferencesIfAny(): void
    {
        $profile = auth()->user()?->userProfile;
        if (!$profile) return;

        $hasHomeCity = trim((string) $profile->home_city) !== '';
        $hasBudget   = (float) $profile->daily_budget > 0;

        if (!$hasHomeCity && !$hasBudget) return;

        $clauses = [];
        if ($hasHomeCity) {
            $clauses[] = trim($profile->home_city) . ' as your starting point';
        }
        if ($hasBudget) {
            $localCode = $profile->daily_budget_currency;
            $clauses[] = ($localCode !== null && $profile->daily_budget_local > 0)
                ? (PlaceCatalog::CURRENCY_SYMBOLS[$localCode] ?? '₱') . number_format($profile->daily_budget_local) . ' as your budget'
                : '₱' . number_format($profile->daily_budget) . ' as your budget';
        }
        if (!empty($profile->interests)) {
            $word = count($profile->interests) === 1 ? 'a travel interest' : 'travel interests';
            $clauses[] = $this->joinNaturally($profile->interests) . " as {$word}";
        }

        $this->messages[] = ['role' => 'assistant', 'text' =>
            "Would you like me to use your saved travel preferences for this trip? "
            . "I see you've set " . $this->joinNaturally($clauses) . " — want me to use these details?"];

        $this->pendingProfileOffer = true;
    }

    private function applyProfileToTrip(?UserProfile $profile): array
    {
        if (!$profile) return [];

        $applied = [];

        if ($this->aiFrom === '' && trim((string) $profile->home_city) !== '') {
            $this->aiFrom = trim($profile->home_city);
            $applied[]    = $this->aiFrom;
        }

        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0 && (float) $profile->daily_budget > 0) {
            $this->aiBudgetMin = $this->aiBudgetMax = (int) round($profile->daily_budget);
            if ($profile->daily_budget_currency !== null) {
                $this->aiCurrency = $profile->daily_budget_currency;
            }
            $applied[] = $this->displayAmount($this->aiBudgetMax);
        }

        return $applied;
    }

    private function pfBlankDraft(): array
    {
        return [
            'home_city'                => '',
            'daily_budget'             => 0.0,
            'travel_style'             => '',
            'group_member_emails'      => [],
            'interests'                => [],
            'sub_interests'            => [],
            'preferred_transportation' => '',
            'preferred_accommodation'  => '',
            'awaiting_slot'            => '',

            'trip_offer_pending'       => false,
            'arrival_offer_pending'    => false,
        ];
    }

    private function pfSay(string $text): void
    {
        $this->messages[] = ['role' => 'assistant', 'text' => $text];
        $this->dispatch('message-added');
    }

    private function pfIsCancel(string $text): bool
    {
        return (bool) preg_match('/^(?:cancel|stop|never\s*mind|nevermind|quit|exit|forget it|not now|later)\b/i', trim($text));
    }

    public function pickInterestsInForm(): mixed
    {
        session(['tara_interests_return' => true]);

        return $this->redirect(
            route('profile.setup', ['step' => 4, 'return' => 'trips.plan.ai']),
            navigate: true
        );
    }

    public function startProfileConversation(?string $focusSlot = null): void
    {
        $this->buildingProfile = true;
        $this->aiPrompt        = '';
        $this->profileDraft    = $this->pfBlankDraft();

        $profile = auth()->user()?->userProfile;
        if ($profile) {
            $city = (string) ($profile->home_city ?? '');
            $this->profileDraft['home_city'] = $this->pfCanonicalCity($city) ?? '';
            $this->profileDraft['daily_budget']             = (float) ($profile->daily_budget_local ?? $profile->daily_budget ?? 0);
            $this->profileDraft['travel_style']             = (string) ($profile->travel_style ?? '');
            $this->profileDraft['group_member_emails']      = (array) ($profile->group_member_emails ?? []);
            $this->profileDraft['interests']                = (array) ($profile->interests ?? []);
            $this->profileDraft['sub_interests']            = (array) ($profile->sub_interests ?? []);
            $this->profileDraft['preferred_transportation'] = (string) ($profile->preferred_transportation ?? '');
            $this->profileDraft['preferred_accommodation']  = (string) ($profile->preferred_accommodation ?? '');
        }

        if ($focusSlot !== null && array_key_exists($focusSlot, $this->pfBlankDraft())) {
            $this->pfClearSlot($focusSlot);
            $this->profileDraft['awaiting_slot'] = $focusSlot;
            $this->pfSay($this->pfQuestionFor($focusSlot));
            return;
        }

        $this->pfAdvanceAndAsk();
    }

    private function offerProfileBuildIfNoProfile(): void
    {
        if (auth()->user()?->userProfile) return;

        $this->profileDraft['arrival_offer_pending'] = true;

        $this->messages[] = ['role' => 'assistant', 'text' =>
            "You haven't set up your travel profile yet. Would you like to set it up?"];
    }

    private function offerProfileFromTripIfNone(): void
    {
        if (auth()->user()?->userProfile) return;
        if ($this->buildingProfile) return;
        if (trim($this->aiFrom) === '') return;

        $daily = $this->pfDailyBudgetFromTrip();

        $known = $daily !== null
            ? trim($this->aiFrom) . ' and ' . UserProfileSaver::budgetSymbolForHomeCity($this->aiFrom) . number_format($daily) . '/day'
            : trim($this->aiFrom) . ' as your usual starting point';

        $this->profileDraft['arrival_offer_pending'] = false;
        $this->profileDraft['trip_offer_pending']    = true;

        $this->pfSay("By the way — want me to save {$known} as your travel profile? "
            . "Then I won't have to ask next time. Say \"yes\", or just carry on.");
    }

    private function pfDailyBudgetFromTrip(): ?float
    {
        $code = UserProfileSaver::currencyForHomeCity(trim($this->aiFrom));
        if ($code !== null && $code !== 'PHP') return null;

        $total = $this->aiBudgetMax ?: $this->aiBudgetMin;
        if ($total <= 0) return null;

        return round($total / max(1, $this->aiDays), 2);
    }

    private function startProfileConversationFromTrip(): void
    {
        $this->buildingProfile = true;
        $this->aiPrompt        = '';

        $daily = $this->pfDailyBudgetFromTrip();

        $this->profileDraft = $this->pfBlankDraft();

        $this->profileDraft['home_city'] = $this->pfCanonicalCity($this->aiFrom) ?? '';
        if ($daily !== null) $this->profileDraft['daily_budget'] = $daily;

        $this->pfSay("Great — I've taken those from your trip. Just a few more questions, and you can say \"cancel\" any time.");
        $this->pfAdvanceAndAsk();
    }

    private function pfClearSlot(string $slot): void
    {
        $blank = $this->pfBlankDraft();
        $this->profileDraft[$slot] = $blank[$slot];

        if ($slot === 'interests') {
            $this->profileDraft['sub_interests'] = [];
        }
    }

    private function cancelProfileConversation(): void
    {
        $this->buildingProfile = false;
        $this->profileDraft    = $this->pfBlankDraft();
        $this->aiPrompt        = '';
        $this->pfSay("No problem — I've left your profile as it is. Tell me about a trip whenever you're ready.");
    }

    private function pfMissingSlotKey(): string
    {
        $d = $this->profileDraft;

        if (trim((string) ($d['home_city'] ?? '')) === '')  return 'home_city';
        if ((float) ($d['daily_budget'] ?? 0) <= 0)         return 'daily_budget';
        if (($d['travel_style'] ?? '') === '')              return 'travel_style';
        if (($d['travel_style'] ?? '') === 'Group' && empty($d['group_member_emails'])) return 'group_member_emails';
        if (empty($d['interests']))                         return 'interests';

        return '';
    }

    private function pfNumberedList(array $options): string
    {
        $out = '';
        foreach (array_values($options) as $i => $name) {
            $out .= ($i + 1) . ". {$name}\n";
        }
        return rtrim($out);
    }

    private function pfSubInterestPool(): array
    {
        $pool = [];
        foreach ($this->profileDraft['interests'] as $interest) {
            foreach (ProfileCatalog::INTERESTS[$interest] ?? [] as $sub) {
                $pool[] = $sub;
            }
        }
        return array_values(array_unique($pool));
    }

    private function pfQuestionFor(string $slot): string
    {
        return match ($slot) {
            'home_city' => "What city are you traveling from?",

            // The column is named daily_budget for historical reasons, but every
            // reader treats it as the whole-trip figure — it becomes aiBudgetMax
            // directly and is checked against MINIMUM_TOTAL_BUDGET. The question
            // says so plainly rather than leaving the traveller to guess.
            'daily_budget' => "How much do you usually budget for a trip?",

            'travel_style' => "Do you usually travel solo, or with a group?",

            'group_member_emails' => "Who travels with you? Give me their email addresses, separated by commas.",

            'interests' => "What do you enjoy doing?",

            'sub_interests' => "Want to narrow that down a bit? Here's what falls under "
                . $this->joinNaturally($this->profileDraft['interests']) . ":\n"
                . $this->pfNumberedList($this->pfSubInterestPool())
                . "\n\nPick any that fit, or just say \"skip\".",

            'preferred_transportation' => "How do you usually get there?\n"
                . $this->pfNumberedList(array_keys(ProfileCatalog::TRANSPORTATION_OPTIONS))
                . "\n\nTell me the number or the name.",

            'preferred_accommodation' => "And where do you like to stay?\n"
                . $this->pfNumberedList(array_keys(ProfileCatalog::ACCOMMODATION_OPTIONS))
                . "\n\nTell me the number or the name.",

            default => '',
        };
    }

    private function pfAdvanceAndAsk(): void
    {
        $slot = $this->pfMissingSlotKey();

        if ($slot === '') {
            $filled = $this->pfAutoCompleteRemaining();

            $this->profileDraft['awaiting_slot'] = 'confirmation';

            if ($filled) {
                $this->pfSay("I've filled in the rest based on what you told me — have a look and change anything that's off.");
            }

            $this->pfSay($this->pfSummary());
            return;
        }

        $this->profileDraft['awaiting_slot'] = $slot;
        $this->pfSay($this->pfQuestionFor($slot));
    }

    private function pfAutoCompleteRemaining(): bool
    {
        $needsSubs   = empty($this->profileDraft['sub_interests']);
        $needsTravel = ($this->profileDraft['preferred_transportation'] ?? '') === '';
        $needsStay   = ($this->profileDraft['preferred_accommodation'] ?? '') === '';

        if (!$needsSubs && !$needsTravel && !$needsStay) return false;

        $choice = $this->pfChoicesFromAi();

        if ($needsSubs) {
            $this->profileDraft['sub_interests'] = $choice['sub_interests'] ?: $this->pfFallbackSubInterests();
        }
        if ($needsTravel) {
            $this->profileDraft['preferred_transportation'] = $choice['transportation'] ?: $this->pfFallbackTransportation();
        }
        if ($needsStay) {
            $this->profileDraft['preferred_accommodation'] = $choice['accommodation'] ?: $this->pfFallbackAccommodation();
        }

        return true;
    }

    private function pfChoicesFromAi(): array
    {
        $empty = ['sub_interests' => [], 'transportation' => '', 'accommodation' => ''];

        $interests = $this->profileDraft['interests'];
        if (empty($interests)) return $empty;

        $subPool = $this->pfSubInterestPool();
        $stays   = array_keys(ProfileCatalog::ACCOMMODATION_OPTIONS);
        $travel  = array_keys(ProfileCatalog::TRANSPORTATION_OPTIONS);

        $interestList = implode(', ', $interests);
        $subList      = implode(', ', $subPool);
        $stayList     = implode(', ', $stays);
        $travelList   = implode(', ', $travel);
        $budget       = (float) $this->profileDraft['daily_budget'];
        $style        = (string) $this->profileDraft['travel_style'];

        $prompt = <<<PROMPT
        A traveler is setting up a travel profile. From their answers, choose the preferences they did not give.

        Their interests: {$interestList}
        Their daily budget: {$budget}
        Travel style: {$style}

        Choose:
        - "sub_interests": 2 to 4 entries chosen ONLY from this list: {$subList}
        - "transportation": exactly one of: {$travelList}
        - "accommodation": exactly one of: {$stayList}

        Pick what genuinely fits their interests, budget and style. Use only the values listed above, spelled exactly as shown.

        Return JSON only, no markdown:
        {"sub_interests": ["..."], "transportation": "...", "accommodation": "..."}
        PROMPT;

        $data = $this->decodeAiJson($this->tryProviders(fn ($provider) => $provider->generate($prompt)));
        if ($data === null) return $empty;

        $subs = is_array($data['sub_interests'] ?? null)
            ? array_values(array_intersect($data['sub_interests'], $subPool))
            : [];

        $chosenTravel = in_array($data['transportation'] ?? null, $travel, true) ? $data['transportation'] : '';
        $chosenStay   = in_array($data['accommodation'] ?? null, $stays, true)   ? $data['accommodation']   : '';

        return [
            'sub_interests'  => $subs,
            'transportation' => $chosenTravel,
            'accommodation'  => $chosenStay,
        ];
    }

    private function pfFallbackSubInterests(): array
    {
        $picked = [];
        foreach ($this->profileDraft['interests'] as $interest) {
            foreach (array_slice(ProfileCatalog::INTERESTS[$interest] ?? [], 0, 2) as $sub) {
                $picked[] = $sub;
            }
        }
        return array_values(array_unique($picked));
    }

    private function pfFallbackTransportation(): string
    {
        return (string) array_key_first(ProfileCatalog::TRANSPORTATION_OPTIONS);
    }

    private function pfFallbackAccommodation(): string
    {
        $indulgent = (float) $this->profileDraft['daily_budget'] >= self::RESORT_BUDGET_HINT
            && (in_array('Beach', $this->profileDraft['interests'], true)
                || in_array('Relaxation', $this->profileDraft['interests'], true));

        return $indulgent ? 'Resort' : 'Hotel';
    }

    private function pfSummary(): string
    {
        $d = $this->profileDraft;

        $symbol = UserProfileSaver::budgetSymbolForHomeCity((string) $d['home_city']);
        $lines  = "Here's your travel profile:\n"
            . "- Home city: {$d['home_city']}\n"
            . "- Budget range: {$symbol}" . number_format((float) $d['daily_budget']) . "\n"
            . "- Travel style: {$d['travel_style']}\n";

        if ($d['travel_style'] === 'Group' && !empty($d['group_member_emails'])) {
            $lines .= "- Traveling with: " . implode(', ', $d['group_member_emails']) . "\n";
        }

        $lines .= "- Interests: " . $this->joinNaturally($d['interests']) . "\n";

        if (!empty($d['sub_interests'])) {
            $lines .= "- More specifically: " . $this->joinNaturally($d['sub_interests']) . "\n";
        }

        $lines .= "- Getting there: {$d['preferred_transportation']}\n"
            . "- Staying in: {$d['preferred_accommodation']}\n\n"
            . "Should I save this to your profile?";

        return $lines;
    }

    private function pfResolveChoices(string $text, array $options): array
    {
        $options  = array_values($options);
        $resolved = [];

        if (preg_match_all('/\d{1,2}/', $text, $m)) {
            foreach ($m[0] as $n) {
                $index = (int) $n - 1;
                if (isset($options[$index])) $resolved[] = $options[$index];
            }
        }

        $lower = mb_strtolower($text);
        foreach ($options as $option) {
            if (str_contains($lower, mb_strtolower($option))) $resolved[] = $option;
        }

        return array_values(array_unique($resolved));
    }

    private function pfInterestsFromAi(string $text): array
    {
        $labels = implode(', ', array_keys(ProfileCatalog::INTERESTS));

        $prompt = <<<PROMPT
        A traveler was asked what kinds of trips they enjoy. Map their answer to the closest matching categories from this fixed list, and nothing outside it.

        The list: {$labels}

        Traveler's answer: "{$text}"

        Return only categories they genuinely expressed interest in. If none of them match, return an empty array.

        Return JSON only, no markdown:
        {"interests": ["Category Name"]}
        PROMPT;

        $data = $this->decodeAiJson($this->tryProviders(fn ($provider) => $provider->generate($prompt)));
        if ($data === null || empty($data['interests']) || !is_array($data['interests'])) return [];

        $valid = array_keys(ProfileCatalog::INTERESTS);
        return array_values(array_intersect($data['interests'], $valid));
    }

    private function pfApplyAnswer(string $slot, string $text): bool
    {
        $text = trim($text);

        switch ($slot) {
            case 'home_city':
                $city = $this->pfCanonicalCity($text);
                if ($city === null) return false;
                $this->profileDraft['home_city'] = $city;
                return true;

            case 'daily_budget':
                if (!preg_match('/\d/', $text)) return false;

                $amount = 0.0;
                foreach (preg_split('/\s*(?:-|–|to)\s*/i', $text) ?: [] as $part) {
                    $parsed = $this->pfParseAmount($part);
                    if ($parsed !== null && $parsed > $amount) $amount = $parsed;
                }
                if ($amount <= 0) return false;

                $pesos = $this->pfBudgetInPesos($amount);
                if ($pesos !== null && $pesos < self::MINIMUM_TOTAL_BUDGET) {
                    $this->pfBudgetBelowFloor = true;
                    return false;
                }

                $this->profileDraft['daily_budget'] = min((float) self::MAX_BUDGET, $amount);
                return true;

            case 'travel_style':
                if (preg_match('/\bsolo\b|\bjust me\b|\balone\b|\bmyself\b|\bby my ?self\b|^1\b/i', $text)) {
                    $this->profileDraft['travel_style']        = 'Solo';
                    $this->profileDraft['group_member_emails'] = [];
                    return true;
                }
                if (preg_match('/\bgroup\b|\bfriends?\b|\bfamily\b|\btogether\b|\bwith\b|\bpartner\b|\bcompanions?\b|^2\b/i', $text)) {
                    $this->profileDraft['travel_style'] = 'Group';
                    return true;
                }
                return false;

            case 'group_member_emails':
                $tokens = preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $valid  = [];
                foreach ($tokens as $token) {
                    if (filter_var($token, FILTER_VALIDATE_EMAIL) && !in_array($token, $valid, true)) {
                        $valid[] = $token;
                    }
                }
                if (empty($valid)) return false;
                $this->profileDraft['group_member_emails'] = $valid;
                return true;

            case 'interests':
                $matched = $this->pfResolveChoices($text, array_keys(ProfileCatalog::INTERESTS));
                if (empty($matched)) $matched = $this->pfInterestsFromAi($text);
                if (empty($matched)) return false;
                $this->profileDraft['interests'] = $matched;

                $this->profileDraft['sub_interests'] = [];
                return true;

            case 'sub_interests':
                if (preg_match('/^(?:skip|no|none|nope|nah|that\'?s? (?:it|all)|nothing)\b/i', $text)) {
                    $this->profileDraft['sub_interests'] = [];
                    return true;
                }
                $this->profileDraft['sub_interests'] = $this->pfResolveChoices($text, $this->pfSubInterestPool());
                return true;

            case 'preferred_transportation':
                $matched = $this->pfResolveChoices($text, array_keys(ProfileCatalog::TRANSPORTATION_OPTIONS));
                if (empty($matched)) return false;
                $this->profileDraft['preferred_transportation'] = $matched[0];
                return true;

            case 'preferred_accommodation':
                $matched = $this->pfResolveChoices($text, array_keys(ProfileCatalog::ACCOMMODATION_OPTIONS));
                if (empty($matched)) return false;
                $this->profileDraft['preferred_accommodation'] = $matched[0];
                return true;
        }

        return false;
    }

    private function pfCanonicalCity(string $text): ?string
    {
        $text = trim($text);
        if ($text === '' || PlaceCatalog::countryFor($text) === null) return null;

        foreach (config('country_cities', []) as $cities) {
            foreach ($cities as $city) {
                if (strcasecmp($city, $text) === 0) return $city;
            }
        }

        return preg_match('/[A-Z]/', $text) ? $text : ucwords(strtolower($text));
    }

    private function pfHomeCitySuggestions(): string
    {
        $country = PlaceCatalog::originCountryFor(auth()->user()?->country);
        $cities  = array_slice(array_values(config("country_cities.{$country}", [])), 0, 3);

        if (empty($cities)) return '';

        $last = array_pop($cities);

        return $cities ? implode(', ', $cities) . " or {$last}" : $last;
    }

    private function pfFloorInHomeCurrency(): string
    {
        $code = UserProfileSaver::currencyForHomeCity((string) ($this->profileDraft['home_city'] ?? ''));
        $rate = $code !== null && $code !== 'PHP' ? $this->currencyRate($code) : null;

        if ($rate === null || $rate <= 0) {
            return '₱' . number_format(self::MINIMUM_TOTAL_BUDGET);
        }

        return (PlaceCatalog::CURRENCY_SYMBOLS[$code] ?? $code . ' ')
            . number_format(self::MINIMUM_TOTAL_BUDGET / $rate);
    }

    private function pfBudgetInPesos(float $amount): ?float
    {
        $code = UserProfileSaver::currencyForHomeCity((string) ($this->profileDraft['home_city'] ?? ''));

        if ($code === null) return null;
        if ($code === 'PHP') return $amount;

        $rate = (new CurrencyConverterService())->rateToPhp($code);

        return $rate === null ? null : $amount * $rate;
    }

    private function pfParseAmount(string $text): ?float
    {
        if (!preg_match('/\d/', $text)) return null;

        $cleaned    = preg_replace('/[^\d.kK]/u', '', str_replace(',', '', $text));
        if ($cleaned === '' || !preg_match('/\d/', $cleaned)) return null;

        $multiplier = preg_match('/[kK]$/', $cleaned) ? 1000 : 1;
        $amount     = (float) rtrim($cleaned, 'kK') * $multiplier;

        return $amount > 0 ? $amount : null;
    }

    private function pfRetryMessageFor(string $slot): string
    {
        $cities = $this->pfHomeCitySuggestions();

        return match ($slot) {
            'home_city'    => $cities === ''
                ? "That doesn't look like a city name — which city do you usually set off from?"
                : "That doesn't look like a city I know — I can work with places like {$cities}. Which one is closest to you?",
            'daily_budget' => $this->pfBudgetBelowFloor
                ? 'That looks too low to plan a real trip — could you give me a more realistic number (at least '
                    . $this->pfFloorInHomeCurrency() . ')?'
                : "I need a number for that — roughly how much?",
            'travel_style' => "Just so I get it right — is that solo, or with a group?",
            'group_member_emails' => "I couldn't read an email address in there. Could you list them like name@example.com, separated by commas?",
            'interests'    => "I couldn't match that to any of the options. Try the numbers, or names like \"Beach\" or \"Food Trip\".",
            default        => "Sorry, I didn't catch that — could you pick from the list above?",
        };
    }

    private function finalizeProfileSave(): void
    {
        $d = $this->profileDraft;

        $result = (new UserProfileSaver())->save(auth()->user(), [
            'home_city'                => $d['home_city'],
            'daily_budget'             => $d['daily_budget'],
            'travel_style'             => $d['travel_style'],
            'group_member_emails'      => $d['group_member_emails'],
            'interests'                => $d['interests'],
            'sub_interests'            => $d['sub_interests'],
            'preferred_transportation' => $d['preferred_transportation'],
            'preferred_accommodation'  => $d['preferred_accommodation'],
        ]);

        if (! $result['ok']) {
            $this->pfSay($result['error'] . " Say \"yes\" again to retry.");
            return;
        }

        $this->buildingProfile = false;
        $this->profileDraft    = $this->pfBlankDraft();

        $applied = $this->applyProfileToTrip($result['profile']);

        $this->pfSay(empty($applied)
            ? "Saved! Your travel profile is all set. Tell me where you'd like to go and I'll plan around it."
            : "Saved! Your travel profile is all set. I'll use " . $this->joinNaturally($applied)
                . " for this trip — just tell me where you'd like to go.");
    }

    private function automateProfileTurn(string $userText): void
    {
        $this->aiPrompt = '';

        if ($this->pfIsCancel($userText)) {
            $this->cancelProfileConversation();
            return;
        }

        $slot = (string) ($this->profileDraft['awaiting_slot'] ?? '');

        if ($slot === 'confirmation') {
            $editSlot = $this->pfDetectEditSlot($userText);

            if (!$editSlot
                && preg_match('/^(?:yes|yeah|yep|yup|sure|correct|right|save|proceed|go ahead|ok|okay)\b/i', $userText)) {
                $this->finalizeProfileSave();
                return;
            }

            if ($editSlot) {
                $this->pfClearSlot($editSlot);
                $this->profileDraft['awaiting_slot'] = $editSlot;
                $this->pfSay($this->pfQuestionFor($editSlot));
                return;
            }

            $this->pfSay("No rush — say \"yes\" when you'd like me to save it, or tell me which part to change (for example, \"change my budget\").");
            return;
        }

        if ($slot === '' || $this->pfApplyAnswer($slot, $userText)) {
            $this->pfAdvanceAndAsk();
            return;
        }

        $this->pfSay($this->pfRetryMessageFor($slot));
    }

    private function pfDetectEditSlot(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\b(?:home\s*)?city\b/i', $text)                   => 'home_city',
            (bool) preg_match('/\bbudget\b|\bspend\b/i', $text)                   => 'daily_budget',
            (bool) preg_match('/\bstyle\b|\bsolo\b|\bgroup\b/i', $text)           => 'travel_style',
            (bool) preg_match('/\bemails?\b|\bcompanions?\b|\bmembers?\b/i', $text) => 'group_member_emails',
            (bool) preg_match('/\binterests?\b/i', $text)                         => 'interests',
            (bool) preg_match('/\btransport\w*\b|\bflight\b/i', $text)            => 'preferred_transportation',
            (bool) preg_match('/\bstay\b|\baccommodation\b|\bhotel\b/i', $text)   => 'preferred_accommodation',

            (bool) preg_match('/\bfrom\b/i', $text)                               => 'home_city',
            default => null,
        };
    }
}
