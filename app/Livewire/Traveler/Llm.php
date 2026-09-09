<?php
namespace App\Livewire\Traveler;

use App\Livewire\Traveler\Concerns\BuildsProfile;
use App\Livewire\Traveler\Concerns\ClassifiesText;
use App\Livewire\Traveler\Concerns\HandlesConversationHistory;
use App\Livewire\Traveler\Concerns\HandlesCurrency;
use App\Livewire\Traveler\Concerns\ResolvesPlaces;
use App\Models\AiConversationDraft;
use App\Models\AiConversationHistory;
use App\Models\Trip;
use App\Services\GeminiService;
use App\Services\GroqService;
use App\Services\MistralService;
use App\Services\OpenRouterService;
use App\Services\SerpApiService;
use App\Services\SerperService;
use App\Support\PlaceCatalog;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app', ['active' => 'trips'])]
class Llm extends Component
{
    use BuildsProfile;
    use ClassifiesText;
    use HandlesConversationHistory;
    use HandlesCurrency;
    use ResolvesPlaces;

    public string $aiPrompt      = '';
    public string $aiStep        = '';
    public string $aiFrom        = '';
    public string $aiTo          = '';
    public int    $aiBudgetMin   = 0;
    public int    $aiBudgetMax   = 0;
    public string $aiDateFrom    = '';
    public string $aiDateTo      = '';
    public int    $aiDays        = 0;
    public int    $aiTravelers   = 0;

    public string $aiCurrency    = '';
    public array  $aiPackage     = [];
    public int    $aiGenCount    = 0;

    public ?int $draftTripId = null;

    public array $aiDestinationChoices = [];

    public array $messages = [];

    public string $awaitingSlot = '';

    public int $missCount = 0;

    private ?string $ambiguityNotice = null;

    private ?string $currencyNotice = null;

    private ?string $currencyNoticeLabel = null;

    private ?float $currencyNoticePesoAmount = null;

    private ?string $unsupportedCurrencyNotice = null;

    private ?string $currencyRateUnavailableNotice = null;

    public ?string $pendingPlaceSuggestion = null;

    public ?string $pendingPlaceSuggestionSlot = null;

    public bool $pendingProfileOffer = false;

    public string $pendingEditSlot = '';

    public array $rejectedDestinations = [];

    private array $aiPlaceCache = [];

    private bool $placeVerificationFailed = false;

    private ?string $placeVerificationFailedSlot = null;

    public bool $aiBudgetIsDaily = false;

    public ?float $aiBudgetLocal = null;
    public bool  $buildingProfile = false;
    public array $profileDraft    = [];

    private bool $pfBudgetBelowFloor = false;

    public function getProfileInterestsProperty(): array
    {
        return auth()->user()->userProfile?->interests ?? [];
    }

    public function mount(): void
    {
        $this->aiCurrency = home_currency();

        $this->profileDraft = $this->pfBlankDraft();

        $draft = AiConversationDraft::where('user_id', auth()->id())->first();
        if (!$draft) {
            $this->offerSavedPreferencesIfAny();
            $this->offerProfileBuildIfNoProfile();
            return;
        }

        $this->messages     = $draft->messages ?? [];
        $this->aiFrom        = $draft->ai_from;
        $this->aiTo          = $draft->ai_to;
        $this->aiBudgetMin   = $draft->ai_budget_min;
        $this->aiBudgetMax   = $draft->ai_budget_max;
        $this->aiBudgetLocal = $draft->ai_budget_local !== null ? (float) $draft->ai_budget_local : null;
        $this->aiDateFrom    = $draft->ai_date_from;
        $this->aiDateTo      = $draft->ai_date_to;
        $this->aiDays        = $draft->ai_days;
        $this->aiTravelers   = $draft->ai_travelers;
        $this->aiCurrency    = $draft->ai_currency ?: $this->aiCurrency;
        $this->awaitingSlot  = $draft->awaiting_slot;
        $this->missCount     = $draft->miss_count;
        $this->aiStep        = $draft->ai_step;
        $this->aiPackage     = $draft->ai_package ?? [];
        $this->aiGenCount    = $draft->ai_gen_count;
        $this->pendingProfileOffer = (bool) $draft->pending_profile_offer;

        $this->buildingProfile = (bool) $draft->building_profile;
        $this->profileDraft    = array_merge($this->pfBlankDraft(), (array) ($draft->profile_draft ?? []));

        if (session()->pull('tara_interests_picked')
            && $this->buildingProfile
            && ($this->profileDraft['awaiting_slot'] ?? '') === 'interests') {
            $profile = auth()->user()?->userProfile;
            $this->profileDraft['interests']     = (array) ($profile?->interests ?? []);
            $this->profileDraft['sub_interests'] = (array) ($profile?->sub_interests ?? []);
            $this->pfAdvanceAndAsk();
        }

        if ($this->aiStep === 'loading') {
            $this->processAiTrip();
        }
    }

    private function joinNaturally(array $items): string
    {
        $items = array_values($items);
        $count = count($items);
        if ($count === 0) return '';
        if ($count === 1) return $items[0];
        if ($count === 2) return "{$items[0]} and {$items[1]}";
        $last = array_pop($items);
        return implode(', ', $items) . ", and {$last}";
    }

    private function looksLikeProfileSetupRequest(string $text): bool
    {
        return (bool) preg_match(
            '/\b(?:set\s*up|setup|build|create|make|complete|fill\s*(?:in|out)|update|edit|change)\b[^.?!]{0,30}\bprofile\b/i',
            $text
        ) || (bool) preg_match('/\bprofile\b[^.?!]{0,20}\b(?:set\s*up|setup|builder)\b/i', $text);
    }

    public function dehydrate(): void
    {
        if (empty($this->messages)) return;

        $this->autosaveDraft();

        AiConversationDraft::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'messages'      => $this->messages,
                'ai_from'       => $this->aiFrom,
                'ai_to'         => $this->aiTo,
                'ai_budget_min' => $this->aiBudgetMin,
                'ai_budget_max' => $this->aiBudgetMax,
                'ai_budget_local' => $this->aiBudgetLocal,
                'ai_date_from'  => $this->aiDateFrom,
                'ai_date_to'    => $this->aiDateTo,
                'ai_days'       => $this->aiDays,
                'ai_travelers'  => $this->aiTravelers,
                'ai_currency'   => $this->aiCurrency,
                'awaiting_slot' => $this->awaitingSlot,
                'miss_count'    => $this->missCount,
                'ai_step'       => $this->aiStep,
                'ai_package'    => $this->aiPackage,
                'ai_gen_count'  => $this->aiGenCount,
                'pending_profile_offer' => $this->pendingProfileOffer,
                'building_profile' => $this->buildingProfile,
                'profile_draft'    => $this->profileDraft,
            ]
        );
    }

    private function autosaveDraft(): void
    {
        if ($this->aiStep !== 'results') return;
        if (trim($this->aiTo) === '' || $this->aiDateFrom === '') return;

        $endDate = $this->aiDateTo !== ''
            ? $this->aiDateTo
            : date('Y-m-d', strtotime($this->aiDateFrom) + max(1, $this->aiDays - 1) * 86400);

        $conversion = $this->destinationBudgetConversion();

        $data = [
            'user_id'              => auth()->id(),
            'destination'          => $this->aiTo,
            'origin'               => trim($this->aiFrom),
            'start_date'           => $this->aiDateFrom,
            'end_date'             => $endDate,
            'budget_limit'         => $this->aiBudgetMax ?: $this->aiBudgetMin,
            'budget_currency'      => $this->aiBudgetLocal !== null ? $this->aiCurrency : null,
            'budget_local'         => $this->aiBudgetLocal,
            'destination_currency' => $conversion['code'] ?? null,
            'travel_type'          => 'Solo',
            'num_travelers'        => max(1, $this->aiTravelers),
            'status'               => 'draft',
        ];

        $existing = $this->draftTripId ? Trip::where('id', $this->draftTripId)
            ->where('user_id', auth()->id())
            ->where('status', 'draft')
            ->first() : null;

        if ($existing) {
            $existing->update($data);
        } else {
            $this->draftTripId = Trip::create($data)->id;
        }
    }

    public function automateTrip(): void
    {
        $userText = trim($this->aiPrompt);
        if ($userText === '') return;

        if (strcasecmp($userText, '/reset') === 0) {
            $this->resetConversation();
            return;
        }

        $previouslyAwaiting = $this->awaitingSlot;
        $this->placeVerificationFailed = false;
        $this->placeVerificationFailedSlot = null;

        $hadBudgetBefore = $this->aiBudgetMin > 0 || $this->aiBudgetMax > 0;

        $this->messages[] = ['role' => 'user', 'text' => $userText];

        if (!empty($this->profileDraft['arrival_offer_pending'])) {
            $this->profileDraft['arrival_offer_pending'] = false;

            if (preg_match('/^(?:yes|yeah|yep|yup|sure|okay|ok)(?:\s+please)?[.!]?$/i', trim($userText))) {
                $this->startProfileConversation();
                return;
            }

            if (preg_match('/^(?:no|nope|nah|not now|later|skip)\b/i', trim($userText))) {
                $this->aiPrompt = '';
                $this->pfSay("No problem — tell me about the trip you'd like to plan.");
                return;
            }

        }

        if ($this->buildingProfile) {
            $this->automateProfileTurn($userText);
            return;
        }

        if ($this->looksLikeProfileSetupRequest($userText)) {
            $this->startProfileConversation();
            return;
        }

        if ($this->pendingProfileOffer) {
            $this->pendingProfileOffer = false;
            $trimmedReply = trim($userText);

            if (preg_match('/^(?:yes|yeah|yep|yup|correct|right|sure|thats right|that\'s right)\b/i', $trimmedReply)) {
                $this->applyProfileToTrip(auth()->user()?->userProfile);

                $this->aiPrompt = '';

            } elseif (preg_match('/^(?:no|nope|nah|not|negative|wrong|incorrect)\b/i', $trimmedReply)
                || preg_match('/\bnot interested\b|\bdon\'?t want\b|\bno thanks\b|\bnot (?:that|this) one\b/i', $trimmedReply)) {
                $this->aiPrompt   = '';
                $this->messages[] = ['role' => 'assistant', 'text' =>
                    "No worries! Tell me about the trip you'd like to plan — where would you like to go, and when?"];
                $this->dispatch('message-added');
                return;
            }
        }

        if (!empty($this->aiDestinationChoices)) {
            $index = null;
            if (preg_match('/^(\d{1,2})$/', $userText, $m)) {
                $index = ((int) $m[1]) - 1;
            } elseif (count($this->aiDestinationChoices) === 1
                && preg_match('/^(?:yes|yeah|yep|yup|sure|ok|okay|correct|right)\b/i', trim($userText))) {
                $index = 0;
            }

            if ($index !== null) {
                if (isset($this->aiDestinationChoices[$index])) {
                    $this->aiTo = $this->aiDestinationChoices[$index];
                    $this->aiDestinationChoices = [];

                    if ($this->awaitingSlot === 'confirmation') {
                        $this->pendingEditSlot = '';
                        $this->aiPrompt = '';
                        $this->messages[] = ['role' => 'assistant', 'text' => 'Got it, updated! ' . $this->confirmationSummary()];
                        $this->dispatch('message-added');
                        return;
                    }
                }
            } elseif (($optionsCount = $this->parseOptionsCount($userText)) !== null
                || preg_match('/^(?:more|others?|different|another|something else)\b/i', trim($userText))
            ) {
                $previousChoices = $this->aiDestinationChoices;
                $choices = $this->suggestDestinations($userText, $optionsCount ?? count($previousChoices));
                $choices = array_values(array_diff($choices, $previousChoices));

                if (!empty($choices)) {
                    $this->aiDestinationChoices = $choices;
                    $this->aiPrompt = '';
                    $list = '';
                    foreach ($choices as $i => $name) {
                        $list .= ($i + 1) . ". {$name}\n";
                    }
                    $this->messages[] = ['role' => 'assistant', 'text' =>
                        "Here are a few more options:\n" . trim($list) . "\n\nJust tell me the number or the name of the one you'd like."];
                    $this->dispatch('message-added');
                    return;
                }

                $this->aiDestinationChoices = [];
                $this->aiPrompt = '';
                $this->messages[] = ['role' => 'assistant', 'text' =>
                    "Sorry, I couldn't come up with more alternatives just now — could you pick from the list above, or name a destination yourself?"];
                $this->dispatch('message-added');
                return;
            }

            $this->aiDestinationChoices = [];
        }

        if ($this->pendingPlaceSuggestion !== null) {
            $suggestion = $this->pendingPlaceSuggestion;
            $suggestionSlot = $this->pendingPlaceSuggestionSlot;
            $this->pendingPlaceSuggestion = null;
            $this->pendingPlaceSuggestionSlot = null;
            $trimmedReply = trim($userText);

            if (preg_match('/^(?:yes|yeah|yep|yup|correct|right|sure|thats right|that\'s right)\b/i', $trimmedReply)) {
                if ($suggestionSlot === 'destination' && $this->aiTo === '') {
                    $this->aiTo = $suggestion;
                } elseif ($suggestionSlot === 'origin' && $this->aiFrom === '') {
                    $this->aiFrom = $suggestion;
                }
                $this->aiPrompt = '';

            } elseif (preg_match('/^(?:no|nope|nah|not|negative|wrong|incorrect)\b/i', $trimmedReply)
                || preg_match('/\bnot interested\b|\bdon\'?t want\b|\bno thanks\b|\bnot (?:that|this) one\b/i', $trimmedReply)) {
                if ($suggestionSlot === 'destination' && !in_array($suggestion, $this->rejectedDestinations, true)) {
                    $this->rejectedDestinations[] = $suggestion;
                }
                $this->awaitingSlot = $suggestionSlot ?? $this->awaitingSlot;
                $this->missCount    = 0;
                $this->aiPrompt     = '';
                $this->messages[]   = ['role' => 'assistant', 'text' => $this->questionFor($suggestionSlot ?? 'destination', 0)];
                $this->dispatch('message-added');
                return;

            } elseif ($suggestionSlot === 'destination'
                && (($optionsCount = $this->parseOptionsCount($trimmedReply)) !== null
                    || preg_match('/^(?:more|others?|different|another|something else)\b/i', $trimmedReply))
            ) {
                $choices = $this->suggestDestinations($trimmedReply, $optionsCount ?? 3);
                if (!empty($choices)) {
                    $this->aiDestinationChoices = $choices;
                    $this->aiPrompt = '';
                    $list = '';
                    foreach ($choices as $i => $name) {
                        $list .= ($i + 1) . ". {$name}\n";
                    }
                    $this->missCount    = 0;
                    $this->awaitingSlot = 'destination';
                    $this->messages[] = ['role' => 'assistant', 'text' =>
                        "Here are a few options:\n" . trim($list) . "\n\nJust tell me the number or the name of the one you'd like."];
                    $this->dispatch('message-added');
                    return;
                }

                $this->awaitingSlot = 'destination';
                $this->missCount    = 0;
                $this->aiPrompt     = '';
                $this->messages[]   = ['role' => 'assistant', 'text' =>
                    "Sorry, I couldn't come up with alternatives just now — could you try again, or name a destination yourself?"];
                $this->dispatch('message-added');
                return;
            }
        }

        if (!empty($this->profileDraft['trip_offer_pending'])) {
            $this->profileDraft['trip_offer_pending'] = false;

            if (preg_match('/^(?:yes|yeah|yep|yup|sure|ok|okay|save it|go ahead)\b/i', trim($userText))) {
                $this->startProfileConversationFromTrip();
                return;
            }

        }

        if ($this->aiStep === 'results') {
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "Glad you like it! You can view the full trip details above, or type /reset if you'd like to plan a different trip."];
            $this->dispatch('message-added');
            return;
        }

        if ($this->awaitingSlot === 'confirmation') {
            $trimmedReply = trim($userText);

            $statusReply = $this->answerStatusQuestion($trimmedReply);
            if ($statusReply !== null) {
                $this->aiPrompt   = '';
                $this->messages[] = ['role' => 'assistant', 'text' => $statusReply];
                $this->dispatch('message-added');
                return;
            }

            if ($this->pendingEditSlot !== '') {
                $slot = $this->pendingEditSlot;

                if (preg_match('/^(?:same|no change|never\s*mind|nevermind|keep it|cancel|skip)\b/i', $trimmedReply)) {
                    $this->pendingEditSlot = '';
                    $this->missCount       = 0;
                    $this->aiPrompt        = '';
                    $this->messages[]      = ['role' => 'assistant', 'text' => 'No changes made. ' . $this->confirmationSummary()];
                    $this->dispatch('message-added');
                    return;
                }

                if ($slot === 'destination' && $this->tryDestinationAlternatives($trimmedReply)) {
                    return;
                }

                if ($this->applyValueToSlot($slot, $trimmedReply)) {
                    if ($this->blockUnaffordableSlotEdit($slot)) {
                        return;
                    }
                    $this->pendingEditSlot = '';
                    $this->missCount       = 0;
                    $this->aiPrompt        = '';
                    $this->messages[]      = ['role' => 'assistant', 'text' => 'Got it, updated! ' . $this->confirmationSummary()];
                    $this->dispatch('message-added');
                    return;
                }

                $this->missCount++;
                $this->aiPrompt   = '';
                $this->messages[] = ['role' => 'assistant', 'text' => $this->questionFor($slot, $this->missCount)];
                $this->dispatch('message-added');
                return;
            }

            if (preg_match('/^(?:yes|yeah|yep|yup|correct|right|sure|proceed|go ahead|thats right|that\'s right)\b/i', $trimmedReply)) {
                $this->awaitingSlot = '';
                $this->aiPrompt     = '';
                $this->messages[]   = ['role' => 'assistant', 'text' => "Great! Let me put together your trip to {$this->aiTo}…"];
                $this->dispatch('message-added');
                $this->aiGenCount = 0;
                $this->aiStep = 'loading';
                $this->dispatch('ai-process-trip');
                return;
            }

            if ($this->tryDestinationAlternatives($trimmedReply)) {
                return;
            }

            $editedSlot = $this->applySlotEdit($trimmedReply);
            if ($editedSlot !== null) {
                if ($this->blockUnaffordableSlotEdit($editedSlot)) {
                    return;
                }
                $this->aiPrompt   = '';
                $this->messages[] = ['role' => 'assistant', 'text' => 'Got it, updated! ' . $this->confirmationSummary()];
                $this->dispatch('message-added');
                return;
            }

            if ($this->pendingEditSlot === '' && $this->applyValueToSlot('dates', $trimmedReply)) {
                $this->aiPrompt   = '';
                $this->messages[] = ['role' => 'assistant', 'text' => 'Got it, updated! ' . $this->confirmationSummary()];
                $this->dispatch('message-added');
                return;
            }

            if ($this->pendingEditSlot !== '') {
                $this->aiPrompt   = '';
                $this->messages[] = ['role' => 'assistant', 'text' => $this->questionFor($this->pendingEditSlot, $this->missCount)];
                $this->dispatch('message-added');
                return;
            }

            $this->aiPrompt   = '';
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "No worries, take your time. Let me know when you're ready to proceed, or type /reset to start over with different details."];
            $this->dispatch('message-added');
            return;
        }

        $statusReply = $this->answerStatusQuestion($userText);
        if ($statusReply !== null) {
            $this->aiPrompt   = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $statusReply];
            $this->dispatch('message-added');
            return;
        }

        if ($this->isGreetingOnly($userText)) {
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => self::GREETING_REPLY];
            $this->dispatch('message-added');
            return;
        }

        if ($this->containsProfanity($userText)) {
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => self::PROFANITY_REPLY];
            $this->dispatch('message-added');
            return;
        }

        $this->parseAiPrompt();

        if ($this->ambiguityNotice !== null) {
            $notice = $this->ambiguityNotice;
            $this->ambiguityNotice = null;
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
            return;
        }

        if ($this->unsupportedCurrencyNotice !== null) {
            $notice = $this->unsupportedCurrencyNotice;
            $this->unsupportedCurrencyNotice = null;
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
            return;
        }

        if ($this->currencyRateUnavailableNotice !== null) {
            $notice = $this->currencyRateUnavailableNotice;
            $this->currencyRateUnavailableNotice = null;
            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
            return;
        }

        if ($this->currencyNotice !== null) {
            $notice = $this->currencyNotice;
            $this->currencyNotice = null;

            $this->messages[] = ['role' => 'assistant', 'text' => $notice];
            $this->dispatch('message-added');
        }

        if ($this->currencyNoticeLabel !== null) {
            $this->currencyNoticeLabel      = null;
            $this->currencyNoticePesoAmount = null;
        }

        $this->applyDirectAnswerFallback($userText);

        $optionsCount = $this->parseOptionsCount($userText);
        if ($optionsCount !== null) {
            $choices = $this->suggestDestinations($userText, $optionsCount);
            if (!empty($choices)) {
                $this->aiDestinationChoices = $choices;
                $this->aiTo     = '';
                $this->aiPrompt = '';
                $list = '';
                foreach ($choices as $i => $name) {
                    $list .= ($i + 1) . ". {$name}\n";
                }
                $this->missCount    = 0;
                $this->awaitingSlot = 'destination';
                $this->messages[] = ['role' => 'assistant', 'text' =>
                    "Here are a few options:\n" . trim($list) . "\n\nJust tell me the number or the name of the one you'd like."];
                $this->dispatch('message-added');
                return;
            }

            $this->missCount    = 0;
            $this->awaitingSlot = 'destination';
            $this->aiPrompt     = '';
            $this->messages[]   = ['role' => 'assistant', 'text' =>
                "Sorry, I couldn't come up with alternatives just now — could you try again, or name a destination yourself?"];
            $this->dispatch('message-added');
            return;
        }

        if ($this->aiTo === '' && $this->isRecommendationRequest($userText)) {
            $namedPlace = $this->knownPlaceName($this->cleanCityName($userText), 'destination');
            if ($namedPlace !== '' && $this->aiFrom !== '' && strtolower($namedPlace) === strtolower($this->aiFrom)) {
                $namedPlace = '';
            }

            if ($namedPlace !== '') {
                $this->aiTo = $namedPlace;

            } elseif ($this->wantsInternational($userText)
                && ($shortfall = $this->internationalBudgetShortfallMessage()) !== null) {
                $this->aiPrompt   = '';
                $this->messages[] = ['role' => 'assistant', 'text' => $shortfall];
                $this->dispatch('message-added');
                return;

            } else {
                $suggestion = $this->suggestDestination($userText, $this->rejectedDestinations);
                if ($suggestion !== '') {
                    $this->pendingPlaceSuggestion     = $suggestion;
                    $this->pendingPlaceSuggestionSlot = 'destination';
                    $this->aiPrompt     = '';
                    $this->missCount    = 0;
                    $this->awaitingSlot = 'destination';
                    $this->messages[] = ['role' => 'assistant', 'text' => "No worries! Based on your interests, how about {$suggestion}?"];
                    $this->dispatch('message-added');
                    return;
                }

                $this->missCount    = 0;
                $this->awaitingSlot = 'destination';
                $this->aiPrompt     = '';
                $this->messages[]   = ['role' => 'assistant', 'text' =>
                    "Sorry, I couldn't come up with a recommendation just now — could you try again, or name a destination yourself?"];
                $this->dispatch('message-added');
                return;
            }
        }

        if ($this->aiTo !== '' && $this->aiBudgetMax > 0 && $this->isBudgetEnoughQuestion($userText)) {
            $days   = $this->aiDays > 0 ? $this->aiDays : 3;
            $perDay = (int) round($this->aiBudgetMax / $days);
            $floor  = $this->budgetFloor();
            $dayLabel = $this->aiDays > 0 ? "{$days}-day" : "typical {$days}-day";

            if ($perDay >= $floor) {
                $reply = "Yes, " . $this->displayAmount($this->aiBudgetMax) . " should be enough for a {$dayLabel} trip to {$this->aiTo} — that's about " . $this->displayAmount($perDay) . "/day, which is a reasonable budget for flights, stay, food, and activities. Want me to put together a package?";
            } else {
                $suggested = $floor * $days;
                $reply = "That might be tight — " . $this->displayAmount($this->aiBudgetMax) . " works out to about " . $this->displayAmount($perDay) . "/day for a {$dayLabel} trip to {$this->aiTo}. Most trips need at least " . $this->displayAmount($floor) . "/day (around " . $this->displayAmount($suggested) . " total) to comfortably cover flights, stay, food, and activities. Want me to plan around that instead, or try to make " . $this->displayAmount($this->aiBudgetMax) . " work?";
            }

            $this->aiPrompt = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $reply];
            $this->dispatch('message-added');
            return;
        }

        $classification = $this->missingSlotKey() !== '' ? $this->extractWithAi($userText) : '';
        $this->aiPrompt = '';

        if ($classification === 'off_topic') {
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "I'm a Travel Assistant, so I can only help with travel-related questions. If you need help planning a trip, finding destinations, estimating costs, or creating an itinerary, I'd be happy to assist!"];
            $this->dispatch('message-added');
            return;
        }

        if ($classification === 'greeting') {
            $this->messages[] = ['role' => 'assistant', 'text' => self::GREETING_REPLY];
            $this->dispatch('message-added');
            return;
        }

        if ($classification === 'inappropriate') {
            $this->messages[] = ['role' => 'assistant', 'text' => self::PROFANITY_REPLY];
            $this->dispatch('message-added');
            return;
        }

        $stillMissing = $this->missingSlotKey();

        if ($stillMissing !== '') {
            $isTangentQuestion = $this->looksLikeQuestion($userText)
                && !$this->looksLikeAttempt($stillMissing, $userText);

            $this->missCount = match (true) {
                $isTangentQuestion => 0,
                $stillMissing === $previouslyAwaiting => $this->missCount + 1,
                $this->looksLikeAttempt($stillMissing, $userText) => 1,
                default => 0,
            };
            $this->awaitingSlot = $stillMissing;

            $reply = match (true) {
                $this->pendingPlaceSuggestion !== null && $this->pendingPlaceSuggestionSlot === $stillMissing
                    => "Did you mean \"{$this->pendingPlaceSuggestion}\"?",
                $this->placeVerificationFailed && $this->placeVerificationFailedSlot === $stillMissing
                    => "I'm having trouble verifying that place right now — could you try again in a moment, or double-check the spelling?",
                default => $this->questionFor($stillMissing, $this->missCount),
            };

            $budgetJustCaptured = !$hadBudgetBefore
                && ($this->aiBudgetMin > 0 || $this->aiBudgetMax > 0)
                && $previouslyAwaiting !== 'budget';
            if ($budgetJustCaptured) {
                $reply = "Got your budget of {$this->formattedBudget()}! " . $reply;
            }

            if ($isTangentQuestion) {
                $reply = "Good question — I can't look that up here, but let's finish your trip details first. " . $reply;
            }

            $this->messages[] = ['role' => 'assistant', 'text' => $reply];
            $this->dispatch('message-added');
            return;
        }

        if ($this->sameOriginAndDestination()) {
            $conflictCity = $this->aiTo;
            $this->aiTo = '';
            $this->awaitingSlot = 'destination';
            $this->missCount    = 0;
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "Looks like your origin and destination are both {$conflictCity} — could you tell me a different destination to travel to?"];
            $this->dispatch('message-added');
            return;
        }

        if ($this->aiBudgetMax > 0 && $this->aiBudgetMax < $this->domesticFloor()) {
            $this->aiBudgetMin  = 0;
            $this->aiBudgetMax  = 0;
            $this->awaitingSlot = 'budget';
            $this->missCount    = 0;
            $this->messages[] = ['role' => 'assistant', 'text' =>
                'That budget looks too low to plan a real trip — could you give me a more realistic number (at least ' . $this->displayAmount($this->domesticFloor()) . ')?'];
            $this->dispatch('message-added');
            return;
        }

        if ($this->isInternationalDestination($this->aiTo)
            && ($shortfall = $this->internationalBudgetShortfallMessage()) !== null) {
            $this->aiBudgetMin  = 0;
            $this->aiBudgetMax  = 0;
            $this->awaitingSlot = 'budget';
            $this->missCount    = 0;
            $this->messages[]   = ['role' => 'assistant', 'text' => $shortfall];
            $this->dispatch('message-added');
            return;
        }

        $this->missCount    = 0;
        $this->messages[] = ['role' => 'assistant', 'text' => 'Got it! ' . $this->confirmationSummary()];
        $this->awaitingSlot = 'confirmation';
        $this->dispatch('message-added');
    }

    private function resetConversation(): void
    {
        AiConversationDraft::where('user_id', auth()->id())->delete();

        if ($this->draftTripId) {
            Trip::where('id', $this->draftTripId)
                ->where('user_id', auth()->id())
                ->where('status', 'draft')
                ->delete();
            $this->draftTripId = null;
        }

        $this->aiPrompt        = '';
        $this->aiStep          = '';
        $this->aiFrom          = '';
        $this->aiTo            = '';
        $this->aiBudgetMin     = 0;
        $this->aiBudgetMax     = 0;
        $this->aiBudgetLocal   = null;
        $this->aiDateFrom      = '';
        $this->aiDateTo        = '';
        $this->aiDays          = 0;
        $this->aiTravelers     = 0;
        $this->aiCurrency      = home_currency();
        $this->aiPackage       = [];
        $this->aiGenCount      = 0;
        $this->aiBudgetIsDaily = false;
        $this->aiDestinationChoices = [];
        $this->pendingPlaceSuggestion = null;
        $this->pendingPlaceSuggestionSlot = null;
        $this->pendingProfileOffer = false;
        $this->pendingEditSlot = '';
        $this->rejectedDestinations = [];

        $this->awaitingSlot    = '';
        $this->missCount       = 0;
        $this->messages        = [];

        $this->buildingProfile = false;
        $this->profileDraft    = $this->pfBlankDraft();
    }

    private const NON_ANSWER_FILLERS = [
        'hi', 'hello', 'hey', 'yo', 'sup', 'hiya',
        'good morning', 'good afternoon', 'good evening', 'good day',
        'ok', 'okay', 'k', 'yes', 'yeah', 'yep', 'yup', 'sure', 'alright', 'fine',
        'no', 'nope', 'nah', 'not sure', 'idk', "i don't know", 'dunno', 'maybe',
        'cool', 'great', 'nice', 'awesome', 'thanks', 'thank you', 'please',
        'what', 'why', 'how', 'um', 'uh', 'hmm', 'huh',
    ];

    private const GREETING_REPLY = "Hello! 😊 How can I help you with your travel plans today?";

    private const PROFANITY_REPLY = "Let's keep things friendly here 🙂 — I'm happy to help plan your trip, just let me know your destination, budget, and dates without the language.";

    private const PROVIDER_ORDER = [
        MistralService::class, OpenRouterService::class, GroqService::class, GeminiService::class,
    ];

    private function tryProviders(\Closure $invoke): mixed
    {
        foreach (self::PROVIDER_ORDER as $class) {
            try {
                $result = $invoke(new $class());
            } catch (\Throwable $e) {
                Log::warning('AI provider failed', [
                    'provider' => class_basename($class),
                    'message'  => $e->getMessage(),
                ]);
                continue;
            }
            if ($result) return $result;
        }
        return null;
    }

    private function decodeAiJson(?string $raw): ?array
    {
        if (!$raw) return null;
        $raw = trim(preg_replace('/```json\s*|```\s*/i', '', $raw));
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function suggestDestination(string $userText = '', array $exclude = []): string
    {
        set_time_limit(90);

        $interests = $this->profileInterests;
        $interestText = !empty($interests)
            ? implode(', ', $interests)
            : 'general sightseeing, popular beaches, and well-rounded trips';
        $requestText = trim($userText) !== '' ? trim($userText) : '(no specific request — just pick something)';
        $excludeText = !empty($exclude)
            ? "\n\nDo NOT suggest any of these — the traveler already turned them down: " . implode(', ', $exclude) . '.'
            : '';

        $prompt = <<<PROMPT
        You are a Philippine travel assistant. A traveler doesn't know where to go and wants a recommendation.

        Traveler's message: "{$requestText}"
        Traveler's saved interests (fallback only, use these if the message above states no specific preference of its own): {$interestText}

        Suggest exactly ONE real, specific travel destination (a city or island, not a country). If the traveler's message names a preference — weather, scenery (beach/mountain/nature), who it's for (couples/family/photographers/first-time travelers), a vibe (hidden gem, food, nightlife), or anything similar — the destination MUST match that preference first, ahead of the saved interests. It can be in the Philippines or an international destination.{$this->budgetContextForPrompt()}{$excludeText}

        Return JSON only, no markdown:
        {"destination": "city name"}
        PROMPT;

        $raw  = $this->tryProviders(fn ($provider) => $provider->generate($prompt));
        $data = $this->decodeAiJson($raw);
        if ($data === null || empty($data['destination'])) return '';

        $resolved = $this->knownPlaceName($this->cleanCityName($data['destination']));

        if ($resolved !== '' && in_array($resolved, $exclude, true)) return '';

        return $resolved;
    }

    private function parseOptionsCount(string $text): ?int
    {
        if (preg_match('/\btop\s*(\d{1,2})\b/i', $text, $m)) {
            return max(2, min(6, (int) $m[1]));
        }
        if (preg_match('/\b(\d{1,2})\s*(?:options|choices|picks|destinations|places)\b/i', $text, $m)) {
            return max(2, min(6, (int) $m[1]));
        }
        if (preg_match('/\b(?:a\s+few|some|multiple|several|other|more)\s+(?:options|choices|destinations|places)\b/i', $text)
            || preg_match('/\b(?:give|show)\s+me\s+(?:some\s+)?options\b|\bmore\s+options\b|\bother\s+options\b|\ba\s+list\s+of\s+(?:destinations|places|options)\b/i', $text)) {
            return 3;
        }
        return null;
    }

    private function suggestDestinations(string $userText, int $count): array
    {
        set_time_limit(90);

        $interests = $this->profileInterests;
        $interestText = !empty($interests)
            ? implode(', ', $interests)
            : 'general sightseeing, popular beaches, and well-rounded trips';
        $requestText = trim($userText) !== '' ? trim($userText) : '(no specific request — just pick some)';

        $prompt = <<<PROMPT
        You are a Philippine travel assistant. A traveler wants several destination options to choose from, not just one.

        Traveler's message: "{$requestText}"
        Traveler's saved interests (fallback only, use these if the message above states no specific preference of its own): {$interestText}

        Suggest exactly {$count} real, specific, DIFFERENT travel destinations (cities or islands, not countries) that best match what the traveler asked for. If the message names a preference — weather, scenery, who it's for, a vibe, or anything similar — every destination MUST match it. They can be in the Philippines or international. No duplicates.{$this->budgetContextForPrompt()}

        Return JSON only, no markdown:
        {"destinations": ["city name", "city name"]}
        PROMPT;

        $raw  = $this->tryProviders(fn ($provider) => $provider->generate($prompt));
        $data = $this->decodeAiJson($raw);
        if ($data === null || empty($data['destinations']) || !is_array($data['destinations'])) return [];

        $resolved = [];
        foreach ($data['destinations'] as $candidate) {
            if (!is_string($candidate)) continue;
            $name = $this->knownPlaceName($this->cleanCityName($candidate));
            if ($name === '') continue;

            $isDuplicate = false;
            foreach ($resolved as $existing) {
                if (strtolower($existing) === strtolower($name)) { $isDuplicate = true; break; }
            }
            if (!$isDuplicate) $resolved[] = $name;
        }

        return $resolved;
    }

    private function applyDirectAnswerFallback(string $userText): void
    {
        if (str_word_count($userText) > 6) return;
        if ($this->isNonAnswerFiller($userText)) return;

        $mentionedSlot = $this->detectEditSlot($userText);
        if ($mentionedSlot !== null && $mentionedSlot !== $this->awaitingSlot) return;

        if ($this->awaitingSlot === 'destination' && $this->aiTo === '') {
            if ($this->placeCueDirection($userText) === 'origin') {
                if ($this->aiFrom === '') {
                    $resolved = $this->knownPlaceName($this->cleanCityName($userText), 'origin');
                    if ($resolved !== '') $this->aiFrom = $resolved;
                }
            } else {
                $resolved = $this->knownPlaceName($this->cleanCityName($userText), 'destination');
                if ($resolved !== '') {
                    $this->aiTo = $resolved;
                }
            }
        } elseif ($this->awaitingSlot === 'origin' && $this->aiFrom === '') {
            if ($this->placeCueDirection($userText) === 'destination') {
                if ($this->aiTo === '') {
                    $resolved = $this->knownPlaceName($this->cleanCityName($userText), 'destination');
                    if ($resolved !== '') $this->aiTo = $resolved;
                }
            } else {
                $resolved = $this->knownPlaceName($this->cleanCityName($userText), 'origin');
                if ($resolved !== '') {
                    $this->aiFrom = $resolved;
                }
            }
        } elseif ($this->awaitingSlot === 'travelers' && $this->aiTravelers === 0) {
            if (preg_match('/\bsolo\b|\bjust me\b|\balone\b|\bmyself\b/i', $userText)
                && !preg_match('/\band\b|\bwith\b|\+/i', $userText)) {
                $this->aiTravelers = 1;

            } elseif (preg_match('/\b(\d{1,2})\b/', $userText, $m)) {
                $v = (int) $m[1];
                if ($v > 0) $this->aiTravelers = $v;
            }
        } elseif ($this->awaitingSlot === 'budget' && $this->aiBudgetMin === 0 && $this->aiBudgetMax === 0) {
            $money = '\d+(?:,\d{3})*(?:\s*[kK])?';
            if (preg_match('/(' . $money . ')\s*(?:to|[-–])\s*(' . $money . ')/', $userText, $m)) {
                $a = $this->parseMoneyToken($m[1]);
                $b = $this->parseMoneyToken($m[2]);
                $this->applyLocalBudget(min($a, $b), max($a, $b));
            } elseif (preg_match('/(\d+(?:,\d{3})*\s*[kK])(?:\b|(?=(?:' . $this->currencySuffixWords() . ')\b))/i', $userText, $m)) {
                $v = $this->parseMoneyToken($m[1]);
                if ($v > 0) $this->applyLocalBudget($v, $v);
            } elseif (preg_match('/(\d[\d,]*)/', $userText, $m)) {
                $v = $this->parseMoneyToken($m[1]);
                if ($v > 0) $this->applyLocalBudget($v, $v);
            }
        }

    }

    private function extractWithAi(string $userText): string
    {
        set_time_limit(90);

        $known = sprintf(
            "- Origin: %s\n- Destination: %s\n- Travelers: %s\n- Budget: %s\n- Travel dates: %s",
            $this->aiFrom !== '' ? $this->aiFrom : 'unknown',
            $this->aiTo !== '' ? $this->aiTo : 'unknown',
            $this->aiTravelers > 0 ? $this->aiTravelers : 'unknown',
            ($this->aiBudgetMin || $this->aiBudgetMax) ? "₱{$this->aiBudgetMin}-₱{$this->aiBudgetMax}" : 'unknown',
            ($this->aiDateFrom && $this->aiDateTo) ? "{$this->aiDateFrom} to {$this->aiDateTo}" : 'unknown',
        );

        $today = date('l, M j, Y');

        $prompt = <<<PROMPT
You are a travel planning assistant extracting structured information from a traveler's message.

Today's date is {$today}.

Known information so far:
{$known}

Traveler's new message: "{$userText}"

First, decide:
1. Is this message COMPLETELY UNRELATED to travel planning — e.g. asking you to write code, general trivia (sports scores, history, etc.), or any other task that has nothing to do with planning a trip? A message that just doesn't mention a specific field yet (like "not sure" or a vague reply) is NOT off-topic — only mark it off-topic if it's asking for something entirely outside travel.
2. Is this message JUST a greeting or small-talk pleasantry (e.g. "hey there", "how's it going?", "good to see you") with no actual travel information and no off-topic request either?
3. Is this message abusive, insulting, or vulgar — profanity, harassment, or hate speech directed at the assistant or in general — regardless of whether it also contains real travel details?

Return JSON only, no markdown:
{
  "off_topic": true or false,
  "is_greeting": true or false,
  "is_inappropriate": true or false,
  "origin": "city name or null",
  "destination": "city name or null",
  "travelers": "number of people traveling, or null",
  "budget_min": number or null,
  "budget_max": number or null,
  "budget_currency": "3-letter code (USD, EUR, GBP, JPY, SGD, AUD, KRW, HKD, THB, MYR, AED, PHP) if the traveler named a currency for the budget, or null if they gave a plain number with no currency mentioned",
  "date_from": "abbreviated MONTH name + day, e.g. 'Jul 28' (NOT a weekday name) — or null",
  "date_to": "abbreviated MONTH name + day + year, e.g. 'Jul 30, 2026' (NOT a weekday name) — or null"
}

Rules:
- If "off_topic", "is_greeting", or "is_inappropriate" is true, set every other field to null.
- Only one of "off_topic", "is_greeting", "is_inappropriate" can be true at once — pick whichever fits best, or leave all three false if it contains real travel info.
- Only include a field if this message actually mentions or changes it.
- If a field isn't mentioned in this message, return null for it — do not guess or repeat the known values above.
- If information is ambiguous, return null for that field rather than assuming.
- If the traveler names a currency (dollars, euros, bucks, etc.), give budget_min/budget_max in THAT currency's own units — do not convert to pesos yourself, the system converts it using "budget_currency".
- Dates: if the traveler gives a RELATIVE time frame instead of a calendar date ("next week", "this weekend", "tomorrow", "in 3 days") and/or a DURATION instead of an end date ("for 5 days", "for a week"), compute the actual calendar dates yourself using today's date above as the reference point, and return real dates in the "Jul 28" / "Jul 30, 2026" style shown above — never return the relative phrase itself, and never return a day-of-the-week name.
PROMPT;

        $raw  = $this->tryProviders(fn ($provider) => $provider->generate($prompt));
        $data = $this->decodeAiJson($raw);
        if ($data === null) return '';

        if (!empty($data['off_topic']))        return 'off_topic';
        if (!empty($data['is_greeting']))      return 'greeting';
        if (!empty($data['is_inappropriate'])) return 'inappropriate';

        if ($this->aiFrom === '' && !empty($data['origin'])) {
            $resolved = $this->knownPlaceName($this->cleanCityName($data['origin']), 'origin');
            if ($resolved !== '') {
                $this->aiFrom = $resolved;
            }
        }
        if ($this->aiTo === '' && !empty($data['destination'])) {
            $resolved = $this->knownPlaceName($this->cleanCityName($data['destination']), 'destination');
            if ($resolved !== '') {
                $this->aiTo = $resolved;
            }
        }

        if ($this->aiTravelers === 0 && !empty($data['travelers'])) {
            $v = (int) $data['travelers'];
            if ($v > 0) {
                $this->aiTravelers = $v;
            }
        }

        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
            && (!empty($data['budget_min']) || !empty($data['budget_max']))
            && preg_match('/\d/', $userText)) {
            $min = (float) ($data['budget_min'] ?? $data['budget_max']);
            $max = (float) ($data['budget_max'] ?? $data['budget_min']);

            $currencyCode = strtoupper(trim((string) ($data['budget_currency'] ?? '')));
            $currency = $currencyCode !== '' && $currencyCode !== 'PHP'
                ? (self::SUPPORTED_CURRENCIES[$currencyCode] ?? null)
                : null;

            if ($currency !== null) {
                $rate = $this->currencyRate($currencyCode);
                if ($rate === null) {
                    $this->currencyNotice = "I couldn't fetch the live exchange rate just now — please try entering your budget again in a moment.";
                } else {
                    $this->aiBudgetMin   = min(self::MAX_BUDGET, (int) round($min * $rate));
                    $this->aiBudgetMax   = min(self::MAX_BUDGET, (int) round($max * $rate));
                    $this->aiCurrency    = $currencyCode;
                    $this->aiBudgetLocal = $max;
                }
            } else {
                $this->applyLocalBudget($min, $max);
            }
        }

        if (($this->aiDateFrom === '' || $this->aiDateTo === '')
            && !empty($data['date_from']) && !empty($data['date_to'])) {
            $tsFrom = strtotime($data['date_from'] . ' ' . date('Y'));
            $tsTo   = strtotime($data['date_to']);

            if ($tsFrom && $tsTo && $tsFrom >= strtotime('today')) {
                $this->aiDateFrom = $data['date_from'];
                $this->aiDateTo   = $data['date_to'];
                $this->aiDays     = (int) ceil(abs($tsTo - $tsFrom) / 86400) + 1;
            }
        }

        return '';
    }

    private function missingSlotKey(): string
    {
        if ($this->aiTo === '') return 'destination';
        if ($this->aiFrom === '') return 'origin';
        if ($this->aiTravelers === 0) return 'travelers';
        if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0) return 'budget';
        if ($this->aiDateFrom === '' || $this->aiDateTo === '') return 'dates';
        return '';
    }

    private function confirmationSummary(): string
    {
        return "Here's what I've got so far:\n"
            . "- From: {$this->aiFrom}\n"
            . "- Destination: {$this->aiTo}\n"
            . "- Travel dates: {$this->aiDateFrom} to {$this->aiDateTo}\n"
            . "- Travelers: {$this->aiTravelers}\n"
            . "- Budget: {$this->formattedBudget()}\n\n"
            . "Would you like me to proceed with this plan?";
    }

    private function statusSummary(): string
    {
        $lines = [
            $this->aiTo !== '' ? "- Destination: {$this->aiTo}" : '- Destination: not set yet',
            $this->aiFrom !== '' ? "- From: {$this->aiFrom}" : '- From: not set yet',
            ($this->aiBudgetMin > 0 || $this->aiBudgetMax > 0) ? "- Budget: {$this->formattedBudget()}" : '- Budget: not set yet',
            $this->aiTravelers > 0 ? "- Travelers: {$this->aiTravelers}" : '- Travelers: not set yet',
            ($this->aiDateFrom !== '' && $this->aiDateTo !== '') ? "- Travel dates: {$this->aiDateFrom} to {$this->aiDateTo}" : '- Travel dates: not set yet',
        ];

        return "Here's what you've told me so far:\n" . implode("\n", $lines);
    }

    private function answerStatusQuestion(string $text): ?string
    {
        $lower = strtolower(trim($text));

        if (preg_match('/\bwhat (?:have i told you|do you have|do you know)\b|\brecap\b|\bso far\b/i', $lower)) {
            return $this->statusSummary();
        }

        if (!preg_match('/\bwhat\'?s?\b|\bhow much\b|\bhow many\b/i', $lower)) return null;
        if (!preg_match('/\bmy (destination|origin|budget|travelers?)\b/i', $lower, $m)) return null;

        $slot = str_starts_with($m[1], 'traveler') ? 'travelers' : $m[1];

        return match ($slot) {
            'destination' => $this->aiTo !== ''
                ? "Your destination so far is {$this->aiTo}."
                : "You haven't told me your destination yet — where would you like to go?",
            'origin' => $this->aiFrom !== ''
                ? "Your origin so far is {$this->aiFrom}."
                : "You haven't told me your origin yet — where will you be traveling from?",
            'budget' => ($this->aiBudgetMin > 0 || $this->aiBudgetMax > 0)
                ? "Your budget so far is {$this->formattedBudget()}."
                : "You haven't told me your budget yet — how much would you like to spend?",
            'travelers' => $this->aiTravelers > 0
                ? "You've told me {$this->aiTravelers} " . \Illuminate\Support\Str::plural('traveler', $this->aiTravelers) . " so far."
                : "You haven't told me how many travelers yet — how many are going?",
            default => null,
        };
    }

    private function detectEditSlot(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\bdestination\b/i', $text) => 'destination',
            (bool) preg_match('/\borigin\b|\bleaving from\b|\bdeparting from\b/i', $text) => 'origin',
            (bool) preg_match('/\bbudget\b/i', $text) => 'budget',
            (bool) preg_match('/\btravelers?\b|\bpeople\b|\bpax\b/i', $text) => 'travelers',

            (bool) preg_match('/\bdates?\b/i', $text) => 'dates',
            default => null,
        };
    }

    private function applyValueToSlot(string $slot, string $value): bool
    {
        if ($slot === 'destination') {
            $resolved = $this->knownPlaceName($this->cleanCityName($value), 'destination');
            if ($resolved === '') return false;

            if ($this->samePlace($resolved, $this->aiFrom)) return false;
            $this->aiTo = $resolved;
            return true;
        }

        if ($slot === 'origin') {
            $resolved = $this->knownPlaceName($this->cleanCityName($value), 'origin');
            if ($resolved === '') return false;
            if ($this->samePlace($resolved, $this->aiTo)) return false;
            $this->aiFrom = $resolved;
            return true;
        }

        if ($slot === 'budget') {
            $conversion = $this->detectAndConvertCurrency($value);
            if ($conversion === false) return false;
            if ($conversion !== null) {
                $this->aiBudgetMin = $this->aiBudgetMax = min(self::MAX_BUDGET, $conversion['pesoAmount']);

                $this->aiBudgetLocal = $conversion['localAmount'];
                return true;
            }

            $v = $this->parseMoneyToken($value);
            if ($v <= 0) return false;

            $before = $this->aiBudgetMax;
            $this->applyLocalBudget($v, $v);

            return $this->aiBudgetMax !== $before || $this->aiBudgetMax > 0;
        }

        if ($slot === 'travelers') {
            $v = (int) $value;
            if ($v <= 0) return false;
            $this->aiTravelers = $v;
            return true;
        }

        if ($slot === 'dates') {
            $range = $this->parseDateRange($value);
            if ($range === null) return false;
            $this->aiDateFrom = $range['from'];
            $this->aiDateTo   = $range['to'];
            $this->aiDays     = $range['days'];
            return true;
        }

        return false;
    }

    private function tryDestinationAlternatives(string $text): bool
    {
        if ($this->wantsInternational($text)
            && ($shortfall = $this->internationalBudgetShortfallMessage()) !== null) {
            $this->aiPrompt   = '';
            $this->messages[] = ['role' => 'assistant', 'text' => $shortfall];
            $this->dispatch('message-added');
            return true;
        }

        $optionsCount = $this->parseOptionsCount($text);
        if ($optionsCount !== null) {
            $choices = $this->suggestDestinations($text, $optionsCount);
            if (!empty($choices)) {
                $this->aiDestinationChoices = $choices;
                $this->pendingEditSlot = 'destination';
                $this->aiPrompt = '';
                $list = '';
                foreach ($choices as $i => $name) {
                    $list .= ($i + 1) . ". {$name}\n";
                }
                $this->messages[] = ['role' => 'assistant', 'text' =>
                    "Here are a few alternatives:\n" . trim($list) . "\n\nJust tell me the number or the name of the one you'd like."];
                $this->dispatch('message-added');
                return true;
            }

            $this->aiPrompt   = '';
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "Sorry, I couldn't come up with alternatives just now — could you try again, or name a destination yourself?"];
            $this->dispatch('message-added');
            return true;
        }

        if ($this->isRecommendationRequest($text)) {
            $suggestion = $this->suggestDestination($text, $this->rejectedDestinations);
            if ($suggestion !== '') {
                $this->aiDestinationChoices = [$suggestion];
                $this->pendingEditSlot = 'destination';
                $this->aiPrompt = '';
                $this->messages[] = ['role' => 'assistant', 'text' =>
                    "How about {$suggestion}? Reply \"yes\" to use it, or tell me a different destination."];
                $this->dispatch('message-added');
                return true;
            }

            $this->aiPrompt   = '';
            $this->messages[] = ['role' => 'assistant', 'text' =>
                "Sorry, I couldn't come up with a recommendation just now — could you try again, or name a destination yourself?"];
            $this->dispatch('message-added');
            return true;
        }

        return false;
    }

    private function applySlotEdit(string $text): ?string
    {
        $slot = $this->detectEditSlot($text);
        if ($slot === null) return null;

        if ($slot === 'dates' && $this->applyValueToSlot('dates', $text)) {
            return $slot;
        }

        $bestPos = null;
        $bestConnectorLen = 0;
        foreach ([' to ', ' in ', ' into '] as $connector) {
            $pos = strripos($text, $connector);
            if ($pos !== false && ($bestPos === null || $pos > $bestPos)) {
                $bestPos = $pos;
                $bestConnectorLen = strlen($connector);
            }
        }
        $value = $bestPos !== null ? trim(substr($text, $bestPos + $bestConnectorLen)) : '';

        if ($value !== '' && $this->applyValueToSlot($slot, $value)) {
            return $slot;
        }

        $this->pendingEditSlot = $slot;
        $this->missCount       = 0;
        return null;
    }

    private function questionFor(string $slot, int $missCount): string
    {
        if ($missCount <= 0) {
            return match ($slot) {
                'destination' => "Sure! Where would you like to go?",
                'origin'      => "Nice choice! Where will you be traveling from?",
                'travelers'   => "Got it. How many people are traveling?",
                'budget'      => "Got it. What's your budget for this trip?",
                'dates'       => "Got it. When are you planning to travel? (e.g. \"August 3 to 10\")",
                default       => '',
            };
        }

        if ($missCount === 1) {
            return match ($slot) {
                'destination' => "Sorry, I didn't quite catch a destination there — which place would you like to go to?",
                'origin'      => "Hmm, I still need a starting point — what city will you be flying from?",
                'travelers'   => "I didn't catch a number — how many people are going, e.g. \"2\" or \"solo\"?",
                'budget'      => "I didn't catch a number — roughly how much do you want to spend, e.g. \"20000\" or \"20k\"?",
                'dates'       => "I still need actual travel dates — something like \"August 3 to 10\" or \"8/3/2026\" works best.",
                default       => '',
            };
        }

        return match ($slot) {
            'destination' => "Let's try just the place name by itself — for example: Boracay",
            'origin'      => "Just the city name works — for example: Manila",
            'travelers'   => "Just a plain number works — for example: 2",
            'budget'      => "Just a plain number works — for example: 20000",
            'dates'       => "Just the dates works — for example: August 3 to 10, 2026",
            default       => '',
        };
    }

    private function conversationSummary(): string
    {
        $facts = [];
        if ($this->aiFrom !== '')   $facts[] = "Origin city: {$this->aiFrom}";
        if ($this->aiTo !== '')     $facts[] = "Destination city: {$this->aiTo}";
        if ($this->aiTravelers > 0) $facts[] = "Travelers: {$this->aiTravelers}";
        if ($this->aiBudgetMax > 0 || $this->aiBudgetMin > 0) {
            $facts[] = 'Budget in PHP: ' . ($this->aiBudgetMax ?: $this->aiBudgetMin);
        }
        if ($this->aiDateFrom !== '' && $this->aiDateTo !== '') {
            $facts[] = "Travel dates: {$this->aiDateFrom} to {$this->aiDateTo}";
        }
        if ($this->aiDays > 0) $facts[] = "Days: {$this->aiDays}";

        $transcript = collect($this->messages)
            ->where('role', 'user')
            ->pluck('text')
            ->implode('. ');

        $summary = empty($facts)
            ? $transcript
            : "CONFIRMED TRIP DETAILS (already verified as real, existing places — "
                . "use these exactly, do not infer a different origin or destination "
                . "from the conversation below):\n" . implode("\n", $facts)
                . "\n\nOriginal conversation for extra context: {$transcript}";

        if (!empty($this->profileInterests)) {
            $summary .= "\nTraveler interests: " . implode(', ', $this->profileInterests) . '.';
        }

        return $summary;
    }

    public function showResults(): void
    {
        if (!empty($this->aiPackage)) {
            $this->aiStep = 'results';
        }
    }

    #[On('ai-process-trip')]
    public function processAiTrip(): void
    {
        set_time_limit(450);

        $summary = $this->conversationSummary();

        $package = $this->tryProviders(function ($provider) use ($summary) {
            $result = $provider->planTrip($summary);
            return !empty($result['to']) ? $result : null;
        });

        if ($package) {
            if ($this->aiFrom === '') {
                $resolved = $this->knownPlaceName($this->cleanCityName($package['from'] ?? ''));
                if ($resolved !== '') $this->aiFrom = $resolved;
            }
            if ($this->aiTo === '') {
                $resolved = $this->knownPlaceName($this->cleanCityName($package['to'] ?? ''));
                if ($resolved !== '') $this->aiTo = $resolved;
            }
            if ($this->aiTravelers === 0 && !empty($package['travelers'])) {
                $v = (int) $package['travelers'];
                if ($v > 0) $this->aiTravelers = $v;
            }

            if (isset($package['transport']) && is_array($package['transport'])) {
                $package['transport']['from_code'] = $this->resolveCode($this->aiFrom ?: 'Manila');
                $package['transport']['to_code']   = $this->resolveCode($this->aiTo);
            }

            if ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
                && (!empty($package['budget_min']) || !empty($package['budget_max']))) {
                $this->aiBudgetMin = (int) ($package['budget_min'] ?? $package['budget_max']);
                $this->aiBudgetMax = (int) ($package['budget_max'] ?? $package['budget_min']);
            }

            if ($this->aiDateFrom === ''
                && !empty($package['date_from'])
                && ($tsFrom = strtotime($package['date_from'])) !== false
                && $tsFrom >= strtotime('today')) {
                $this->aiDateFrom = $package['date_from'];
            }
            if ($this->aiDateTo === '' && !empty($package['date_to']) && strtotime($package['date_to']) !== false) {
                $this->aiDateTo = $package['date_to'];
            }
            if ($this->aiDays === 0 && !empty($package['days'])) {
                $this->aiDays = (int) $package['days'];
            }

            $travelers = max(1, $this->aiTravelers);
            if (isset($package['transport']['cost'])) {
                $package['transport']['cost'] = (int) round($package['transport']['cost'] * $travelers);
            }
            if (isset($package['food']['cost'])) {
                $package['food']['cost'] = (int) round($package['food']['cost'] * $travelers);
            }

            $budget = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;

            $this->aiPackage = $this->capPackageToBudget([
                'transport'     => $package['transport']     ?? [],
                'accommodation' => $package['accommodation'] ?? [],
                'food'          => $package['food']          ?? [],
                'attractions'   => $package['attractions']   ?? ['items'=>[],'cost'=>0],
            ], $budget);
            $this->aiStep = 'results';
            $this->offerProfileFromTripIfNone();
            return;
        }

        $serpPackage = $this->buildSerpApiPackage();
        if ($serpPackage) {
            $this->aiPackage = $serpPackage;
            $this->aiStep    = 'results';
            $this->offerProfileFromTripIfNone();
            return;
        }

        $this->generateAiPackage();
        $this->aiStep = 'results';
        $this->offerProfileFromTripIfNone();
    }

    private function buildSerpApiPackage(): ?array
    {
        if (empty($this->aiTo)) return null;

        $serp   = new SerpApiService();
        $days   = max(1, $this->aiDays);
        $budget = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;

        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }
        $checkIn  = $this->aiDateFrom
            ? date('Y-m-d', strtotime($this->aiDateFrom . ', ' . $year))
            : date('Y-m-d');
        $checkOut = $this->aiDateTo
            ? date('Y-m-d', strtotime($this->aiDateTo))
            : date('Y-m-d', strtotime("+{$days} days"));

        $fromCode = $this->resolveCode($this->aiFrom ?: 'Manila');
        $toCode   = $this->resolveCode($this->aiTo);

        $transportBudget     = (int)round($budget * 0.18);
        $accommodationBudget = (int)round($budget * 0.45);
        $foodBudget          = (int)round($budget * 0.20);
        $attractionBudget    = (int)round($budget * 0.12);

        $gen = $this->aiGenCount;
        $flightData  = $serp->searchFlights($fromCode, $toCode, $checkIn, $checkOut, $gen, $transportBudget);
        $hotelData   = $serp->searchHotels($this->aiTo, $checkIn, $checkOut, $days, $gen, $accommodationBudget);
        $restaurData = $serp->searchRestaurants($this->aiTo, $days, $foodBudget, $gen);
        $attrItems   = $serp->searchAttractions($this->aiTo, $gen);

        if (!$flightData) {
            $list = (new SerperService())->searchFlights($fromCode, $toCode, $checkIn, $checkOut);
            if ($list) {
                usort($list, fn($a, $b) => ($a['price'] ?? 0) <=> ($b['price'] ?? 0));
                $pick = $this->pickFromPool($list, $gen);
                $flightData = [
                    'detail' => trim(($pick['airline'] ?? 'Airline') . ' ' . ($pick['number'] ?? '')) . ' · ' . ($pick['type'] ?? 'Round Trip'),
                    'cost'   => (int) ($pick['price'] ?? 0),
                ];
            }
        }
        if (!$hotelData) {
            $list = (new SerperService())->searchHotels($this->aiTo, $checkIn, $checkOut, $days);
            if ($list) {
                usort($list, fn($a, $b) => ($a['total'] ?? 0) <=> ($b['total'] ?? 0));
                $pick = $this->pickFromPool($list, $gen);
                $hotelData = [
                    'name'   => $pick['name'] ?? ('Hotel in ' . $this->aiTo),
                    'stars'  => $pick['stars'] ?? 3,
                    'detail' => $days . ' Nights · Standard Room · ' . $this->aiTo,
                    'cost'   => (int) ($pick['total'] ?? 0),
                ];
            }
        }
        if (!$restaurData) {
            $list = (new SerperService())->searchRestaurants($this->aiTo);
            if ($list) {
                $pick = $this->pickFromPool($list, $gen);
                $restaurData = [
                    'name'   => $pick['name'] ?? ('Dining in ' . $this->aiTo),
                    'detail' => $days . ' Days · Breakfast, Lunch, & Dinner · ' . $this->aiTo,
                    'cost'   => (int) round((float) ($pick['priceMax'] ?? $pick['priceMin'] ?? 500) * $days),
                ];
            }
        }
        if (!$attrItems) {
            $list = (new SerperService())->searchAttractions($this->aiTo);
            if ($list) {
                $offset    = $gen === 0 ? 0 : min($gen * 3, max(0, count($list) - 3));
                $attrItems = array_map(
                    fn($a) => [$a['name'] ?? 'Attraction', ($a['isFree'] ?? false) ? 'Free' : '₱' . number_format((int) ($a['price'] ?? 300))],
                    array_slice($list, $offset, 3)
                );
            }
        }

        if (!$flightData && !$hotelData && !$restaurData && !$attrItems) return null;

        $travelers = max(1, $this->aiTravelers);
        if ($flightData) {
            $flightData['cost'] = (int) round(($flightData['cost'] ?? 0) * $travelers);
        }
        if ($restaurData) {
            $restaurData['cost'] = (int) round(($restaurData['cost'] ?? 0) * $travelers);
        }

        $transport = $flightData
            ? array_merge(['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$fromCode,'to_code'=>$toCode], $flightData)
            : ['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$fromCode,'to_code'=>$toCode,'detail'=>'Direct Flight · Round Trip','cost'=>$transportBudget];

        $accommodation = $hotelData
            ? array_merge(['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed'], $hotelData)
            : ['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed','name'=>'Hotel in '.$this->aiTo,'stars'=>3,'detail'=>$days.' Nights · Standard Room · '.$this->aiTo,'cost'=>$accommodationBudget];

        $food = $restaurData
            ? array_merge(['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils'], $restaurData)
            : ['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils','name'=>'Dining in '.$this->aiTo,'detail'=>$days.' Days · Breakfast, Lunch, & Dinner · '.$this->aiTo,'cost'=>$foodBudget];

        $items    = $attrItems ?? [[$this->aiTo . ' City Tour', '₱300'],['Local Market Visit','Free']];
        $attrCost = array_sum(array_map(
            fn($a) => is_numeric(str_replace(['₱',','], '', $a[1])) ? (int)str_replace(['₱',','], '', $a[1]) : 0,
            $items
        ));
        $attractions = ['label'=>'ATTRACTIONS','icon'=>'fa-solid fa-landmark','items'=>$items,'cost'=>$attrCost];

        $rawPackage = [
            'transport'     => $transport,
            'accommodation' => $accommodation,
            'food'          => $food,
            'attractions'   => $attractions,
        ];

        $enriched = $this->tryProviders(
            fn ($provider) => $provider->enrichPackage($rawPackage, $this->aiTo, $days, $budget)
        );

        if ($enriched) {
            foreach (['transport','accommodation','food','attractions'] as $key) {
                if (!isset($enriched[$key])) continue;
                foreach ($enriched[$key] as $field => $val) {
                    if (!is_numeric($val)) {
                        $rawPackage[$key][$field] = $val;
                    }
                }
            }

            if (!empty($rawPackage['attractions']['items'])) {
                $rawPackage['attractions']['cost'] = array_sum(array_map(
                    fn($a) => is_numeric(str_replace(['₱',','], '', $a[1] ?? '')) ? (int)str_replace(['₱',','], '', $a[1]) : 0,
                    $rawPackage['attractions']['items']
                ));
            }
        }

        return $this->capPackageToBudget($rawPackage, $budget);
    }

    private const PHILIPPINE_IATA_CODES = [
        'MNL', 'CEB', 'DVO', 'MPH', 'KLO', 'TAG', 'PPS', 'ENI', 'USU', 'IAO',
        'BCD', 'ILO', 'ZAM', 'CGY', 'GES', 'TAC', 'DGT', 'SUG', 'CBO', 'BSO',
        'CGM', 'LAO', 'VIG', 'BAG', 'LGP', 'WNP', 'RXS', 'SJI', 'OZC', 'DPL',
        'BXU', 'PAG', 'VRC', 'TUG', 'CYZ',
    ];

    private const INTERNATIONAL_FLIGHT_FLOOR = 15000;

    private const INTERNATIONAL_ROOM_FLOOR   = 1500;

    private const INTERNATIONAL_LIVING_FLOOR = 1500;

    private function blockUnaffordableSlotEdit(string $slot): bool
    {
        if ($slot !== 'destination' && $slot !== 'budget') return false;

        if ($this->aiBudgetMax > 0 && $this->aiBudgetMax < $this->domesticFloor()) {
            $this->aiBudgetMin      = 0;
            $this->aiBudgetMax      = 0;
            $this->awaitingSlot     = 'budget';
            $this->pendingEditSlot  = '';
            $this->missCount        = 0;
            $this->aiPrompt         = '';
            $this->messages[]       = ['role' => 'assistant', 'text' =>
                'That budget looks too low to plan a real trip — could you give me a more realistic number (at least ' . $this->displayAmount($this->domesticFloor()) . ')?'];
            $this->dispatch('message-added');
            return true;
        }

        if (!$this->isInternationalDestination($this->aiTo)) return false;

        $shortfall = $this->internationalBudgetShortfallMessage();
        if ($shortfall === null) return false;

        $this->aiBudgetMin      = 0;
        $this->aiBudgetMax      = 0;
        $this->awaitingSlot     = 'budget';
        $this->pendingEditSlot  = '';
        $this->missCount        = 0;
        $this->aiPrompt         = '';
        $this->messages[]       = ['role' => 'assistant', 'text' => $shortfall];
        $this->dispatch('message-added');
        return true;
    }

    private const MINIMUM_TOTAL_BUDGET = 10000;

    private const DOMESTIC_FLIGHT_FLOOR = 3000;

    private const DOMESTIC_ROOM_FLOOR   = 500;

    private const DOMESTIC_LIVING_FLOOR = 500;

    private const MIN_FOOD_PER_DAY_PER_TRAVELER = 300;
    private const MIN_ACCOMMODATION_PER_NIGHT   = 800;
    private const MIN_TRANSPORT_RATIO           = 0.7;

    private function capPackageToBudget(array $package, int $budget): array
    {
        $transport     = $package['transport']     ?? [];
        $accommodation = $package['accommodation'] ?? [];
        $food          = $package['food']          ?? [];
        $attractions   = $package['attractions']   ?? ['items' => [], 'cost' => 0];

        $transport['cost']     = (int) ($transport['cost']     ?? 0);
        $accommodation['cost'] = (int) ($accommodation['cost'] ?? 0);
        $food['cost']          = (int) ($food['cost']          ?? 0);
        $attractions['items']  = $attractions['items'] ?? [];
        $attractions['cost']   = (int) ($attractions['cost']   ?? 0);

        $itemCost = fn ($item) => is_numeric(str_replace(['₱', ','], '', $item[1] ?? ''))
            ? (int) str_replace(['₱', ','], '', $item[1]) : 0;

        $total = $transport['cost'] + $accommodation['cost'] + $food['cost'] + $attractions['cost'];

        if ($total > $budget && !empty($attractions['items'])) {
            $items = $attractions['items'];
            usort($items, fn ($a, $b) => $itemCost($b) <=> $itemCost($a));

            $overage = $total - $budget;
            foreach ($items as $i => $item) {
                if ($overage <= 0 || count($items) <= 1) break;
                $cost = $itemCost($item);
                if ($cost <= 0) continue;
                unset($items[$i]);
                $overage -= $cost;
            }
            $items = array_values($items);

            if (count($items) !== count($attractions['items'])) {
                $attractions['items'] = $items;
                $attractions['cost']  = array_sum(array_map($itemCost, $items));
                $total = $transport['cost'] + $accommodation['cost'] + $food['cost'] + $attractions['cost'];
            }
        }

        $days      = max(1, $this->aiDays);
        $travelers = max(1, $this->aiTravelers);

        if ($total > $budget && $food['cost'] > 0) {
            $floor    = self::MIN_FOOD_PER_DAY_PER_TRAVELER * $days * $travelers;
            $reduceBy = min($total - $budget, max(0, $food['cost'] - $floor));
            $food['cost'] -= $reduceBy;
            $total        -= $reduceBy;
        }

        if ($total > $budget && $accommodation['cost'] > 0) {
            $floor    = self::MIN_ACCOMMODATION_PER_NIGHT * $days;
            $reduceBy = min($total - $budget, max(0, $accommodation['cost'] - $floor));
            $accommodation['cost'] -= $reduceBy;
            $total                 -= $reduceBy;
        }

        if ($total > $budget && $transport['cost'] > 0) {
            $floor    = (int) round($transport['cost'] * self::MIN_TRANSPORT_RATIO);
            $reduceBy = min($total - $budget, max(0, $transport['cost'] - $floor));
            $transport['cost'] -= $reduceBy;
            $total             -= $reduceBy;
        }

        return array_merge($package, [
            'transport'     => $transport,
            'accommodation' => $accommodation,
            'food'          => $food,
            'attractions'   => $attractions,
            'total'         => $total,
            'budget'        => $budget,
            'pct'           => min(100, (int) round($total / max(1, $budget) * 100)),
        ]);
    }

    private const SUPPORTED_CURRENCIES = [
        'PHP' => ['name' => 'Philippine pesos',   'symbol' => '₱'],
        'USD' => ['name' => 'US dollars',         'symbol' => '$'],
        'EUR' => ['name' => 'euros',              'symbol' => '€'],
        'GBP' => ['name' => 'British pounds',     'symbol' => '£'],
        'JPY' => ['name' => 'Japanese yen',       'symbol' => '¥'],
        'SGD' => ['name' => 'Singapore dollars',  'symbol' => 'S$'],
        'AUD' => ['name' => 'Australian dollars', 'symbol' => 'A$'],
        'KRW' => ['name' => 'Korean won',         'symbol' => '₩'],
        'HKD' => ['name' => 'Hong Kong dollars',  'symbol' => 'HK$'],
        'THB' => ['name' => 'Thai baht',          'symbol' => '฿'],
        'MYR' => ['name' => 'Malaysian ringgit',  'symbol' => 'RM'],
        'AED' => ['name' => 'UAE dirhams',        'symbol' => 'AED '],
        'IDR' => ['name' => 'Indonesian rupiah',  'symbol' => 'Rp'],
        'VND' => ['name' => 'Vietnamese dong',    'symbol' => '₫'],
        'CNY' => ['name' => 'Chinese yuan',       'symbol' => '¥'],
        'INR' => ['name' => 'Indian rupee',       'symbol' => '₹'],
        'NZD' => ['name' => 'New Zealand dollars','symbol' => 'NZ$'],
        'CAD' => ['name' => 'Canadian dollars',   'symbol' => 'C$'],
        'BRL' => ['name' => 'Brazilian real',     'symbol' => 'R$'],
        'MXN' => ['name' => 'Mexican pesos',      'symbol' => 'MX$'],
        'ARS' => ['name' => 'Argentine pesos',    'symbol' => 'AR$'],
        'SAR' => ['name' => 'Saudi riyals',       'symbol' => 'SAR '],
        'EGP' => ['name' => 'Egyptian pounds',    'symbol' => 'EGP '],
        'NGN' => ['name' => 'Nigerian naira',     'symbol' => '₦'],
        'ZAR' => ['name' => 'South African rand', 'symbol' => 'R'],
        'KES' => ['name' => 'Kenyan shillings',   'symbol' => 'KSh '],
    ];

    private const CURRENCY_ALIASES = [
        '$' => 'USD', '＄' => 'USD', 'usd' => 'USD',
        '€' => 'EUR', 'eur' => 'EUR',
        '£' => 'GBP', '￡' => 'GBP', 'gbp' => 'GBP',
        '¥' => 'JPY', '￥' => 'JPY', 'jpy' => 'JPY',
        '₩' => 'KRW', '￦' => 'KRW', 'krw' => 'KRW',
        'sgd' => 'SGD', 'aud' => 'AUD',
        'hkd' => 'HKD', 'thb' => 'THB',
        'myr' => 'MYR', 'aed' => 'AED',
        'idr' => 'IDR', 'vnd' => 'VND', '₫' => 'VND', 'cny' => 'CNY',
        'inr' => 'INR', '₹' => 'INR', 'nzd' => 'NZD', 'cad' => 'CAD',
        'brl' => 'BRL', 'mxn' => 'MXN', 'ars' => 'ARS', 'sar' => 'SAR',
        'egp' => 'EGP', 'ngn' => 'NGN', '₦' => 'NGN', 'zar' => 'ZAR', 'kes' => 'KES',
        '₱' => 'PHP', 'php' => 'PHP', 'peso' => 'PHP', 'pesos' => 'PHP',
    ];

    private function pickFromPool(array $pool, int $gen, int $poolSize = 3): array
    {
        $offset = $gen === 0 ? 0 : min($gen, max(0, count($pool) - $poolSize));
        $slice  = array_slice($pool, $offset, $poolSize) ?: $pool;
        return $slice[array_rand($slice)];
    }

    public function regeneratePackage(): void
    {
        $this->aiGenCount++;

        $package = $this->buildSerpApiPackage();
        if ($package) {
            $this->aiPackage = $package;
            return;
        }

        $this->generateAiPackage();
    }

    public function proceedToWizardItinerary(): mixed
    {
        if (empty($this->aiPackage)) return null;
        $pkg = $this->aiPackage;

        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }

        $startTs = $this->aiDateFrom ? strtotime($this->aiDateFrom . ', ' . $year) : false;
        $endTs   = $this->aiDateTo   ? strtotime($this->aiDateTo) : false;

        if ($startTs !== false && $endTs !== false && $startTs > $endTs) {
            $startTs = strtotime($this->aiDateFrom . ', ' . ((int) $year - 1));
        }

        $start   = $startTs !== false ? date('Y-m-d', $startTs) : now()->toDateString();
        $end     = $endTs   !== false ? date('Y-m-d', $endTs)   : now()->addDays(max(1, $this->aiDays))->toDateString();
        $nights  = max(1, (int) \Carbon\Carbon::parse($start)->diffInDays(\Carbon\Carbon::parse($end)));

        $transport      = $pkg['transport'] ?? [];
        $transportDetail = $transport['detail'] ?? '';
        $selectedFlight = [
            'airline' => $transportDetail !== '' ? $transportDetail : 'Flight',
            'number'  => null,
            'price'   => (int) ($transport['cost'] ?? 0),
            'dep_id'  => $transport['from_code'] ?? $this->resolveCode($this->aiFrom),
            'arr_id'  => $transport['to_code']   ?? $this->resolveCode($this->aiTo),
            'type'    => stripos($transportDetail, 'one way') !== false ? 'One Way' : 'Round Trip',
            'depart'  => $start,
        ];

        $accommodation = $pkg['accommodation'] ?? [];
        $selectedHotel = !empty($accommodation['name']) ? [
            'name'   => $accommodation['name'],
            'total'  => (int) ($accommodation['cost'] ?? 0),
            'nights' => $nights,
            'image'  => null,
        ] : null;

        $food = $pkg['food'] ?? [];
        $selectedVenue = !empty($food['name']) ? [
            'name'     => $food['name'],
            'priceMax' => (int) ($food['cost'] ?? 0),
            'priceMin' => (int) ($food['cost'] ?? 0),
            'cuisine'  => null,
        ] : null;

        $attrItems = $pkg['attractions']['items'] ?? [];
        $attrCost  = (int) ($pkg['attractions']['cost'] ?? 0);
        $selectedAttraction = !empty($attrItems) ? [
            'name'   => implode(' & ', array_column($attrItems, 0)),
            'price'  => (string) $attrCost,
            'isFree' => $attrCost === 0,
            'image'  => null,
        ] : null;

        session(['wizard_ai_handoff' => [
            'from'          => $this->aiFrom,
            'to'            => $this->aiTo,
            'budget_min'    => $this->aiBudgetMin,
            'budget_max'    => $this->aiBudgetMax,
            'currency'      => $this->aiCurrency,
            'start'         => $start,
            'end'           => $end,
            'flight'        => $selectedFlight,
            'hotel'         => $selectedHotel,
            'venue'         => $selectedVenue,
            'attraction'    => $selectedAttraction,
            'draft_trip_id' => $this->draftTripId,
        ]]);

        AiConversationHistory::create([
            'user_id'       => auth()->id(),
            'messages'      => $this->messages,
            'ai_from'       => $this->aiFrom,
            'ai_to'         => $this->aiTo,
            'ai_budget_min' => $this->aiBudgetMin,
            'ai_budget_max' => $this->aiBudgetMax,
            'ai_date_from'  => $this->aiDateFrom,
            'ai_date_to'    => $this->aiDateTo,
            'ai_days'       => $this->aiDays,
            'ai_travelers'  => $this->aiTravelers,
            'ai_currency'   => $this->aiCurrency ?: 'PHP',
            'ai_package'    => $this->aiPackage,
        ]);

        AiConversationDraft::where('user_id', auth()->id())->delete();
        $this->messages = [];

        return $this->redirect(route('trips.plan'), navigate: true);
    }

    public function backToConversation(): void
    {
        $this->aiStep = '';
    }

    public function editWithWizard(?string $section = null): mixed
    {
        $year = date('Y');
        if ($this->aiDateTo && preg_match('/(\d{4})$/', $this->aiDateTo, $ym)) {
            $year = $ym[1];
        }
        $start = $this->aiDateFrom ? date('Y-m-d', strtotime($this->aiDateFrom . ', ' . $year)) : '';
        $end   = $this->aiDateTo   ? date('Y-m-d', strtotime($this->aiDateTo)) : '';

        session(['ai_edit_return' => true, 'ai_edit_section' => $section]);

        return $this->redirect(route('trips.plan', array_filter([
            'from'       => $this->aiFrom,
            'to'         => $this->aiTo,
            'budget_min' => $this->aiBudgetMin ?: null,
            'budget_max' => $this->aiBudgetMax ?: null,
            'start'      => $start ?: null,
            'end'        => $end ?: null,
        ])), navigate: true);
    }

    private const MAX_BUDGET = 10_000_000;

    private function parseDateRange(string $text): ?array
    {
        $monthMap = [
            'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
            'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12,
            'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'jun'=>6,

            'jul'=>7,'aug'=>8,'sep'=>9,'sept'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
        ];
        $mp = implode('|', array_keys($monthMap));

        if (preg_match('/\b(' . $mp . ')\.?\s+(\d{1,2})\s*(?:[-–]|to)\s+(' . $mp . ')\.?\s+(\d{1,2})(?:,?\s*(\d{4}))?\b/i', $text, $m)) {
            $mon1  = $monthMap[strtolower($m[1])];
            $mon2  = $monthMap[strtolower($m[3])];
            $year  = !empty($m[5]) ? (int)$m[5] : (int)date('Y');
            $d1    = (int)$m[2]; $d2 = (int)$m[4];

            if (checkdate($mon1, $d1, $year) && checkdate($mon2, $d2, $year)) {
                $ts1 = mktime(0,0,0,$mon1,$d1,$year);
                $ts2 = mktime(0,0,0,$mon2,$d2,$year);
                return [
                    'from'      => date('M j', $ts1),
                    'to'        => date('M j, Y', $ts2),
                    'days'      => (int)ceil(abs($ts2-$ts1)/86400)+1,
                    'remainder' => str_replace($m[0], '', $text),
                ];
            }

        } elseif (preg_match('/\b(' . $mp . ')\.?\s+(\d{1,2})\s*(?:[-–]|to)\s*(\d{1,2})(?:,?\s*(\d{4}))?\b/i', $text, $m)) {
            $mon  = $monthMap[strtolower($m[1])];
            $year = !empty($m[4]) ? (int)$m[4] : (int)date('Y');
            $d1   = (int)$m[2]; $d2 = (int)$m[3];
            if (checkdate($mon, $d1, $year) && checkdate($mon, $d2, $year)) {
                return [
                    'from'      => date('M j', mktime(0,0,0,$mon,$d1,$year)),
                    'to'        => date('M j, Y', mktime(0,0,0,$mon,$d2,$year)),
                    'days'      => abs($d2-$d1)+1,
                    'remainder' => str_replace($m[0], '', $text),
                ];
            }

        } elseif (preg_match('/\b(' . $mp . ')\.?\s+(\d{1,2})(?:,?\s*(\d{4}))?\b/i', $text, $m)) {
            $mon  = $monthMap[strtolower($m[1])];
            $year = !empty($m[3]) ? (int)$m[3] : (int)date('Y');
            $day  = (int)$m[2];
            if (checkdate($mon, $day, $year)) {
                $ts1 = mktime(0,0,0,$mon,$day,$year);
                return [
                    'from'      => date('M j', $ts1),
                    'to'        => date('M j, Y', $ts1 + 5*86400),
                    'days'      => 6,
                    'remainder' => str_replace($m[0], '', $text),
                ];
            }

        } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{2,4})\s*(?:to|[-–])\s*(\d{1,2})\/(\d{1,2})\/(\d{2,4})\b/', $text, $m)) {
            $y1  = strlen($m[3]) === 2 ? (int)('20'.$m[3]) : (int)$m[3];
            $y2  = strlen($m[6]) === 2 ? (int)('20'.$m[6]) : (int)$m[6];
            $mo1 = (int)$m[1]; $da1 = (int)$m[2];
            $mo2 = (int)$m[4]; $da2 = (int)$m[5];
            if (checkdate($mo1, $da1, $y1) && checkdate($mo2, $da2, $y2)) {
                $ts1 = mktime(0,0,0,$mo1,$da1,$y1);
                $ts2 = mktime(0,0,0,$mo2,$da2,$y2);
                return [
                    'from'      => date('M j', $ts1),
                    'to'        => date('M j, Y', $ts2),
                    'days'      => (int)ceil(abs($ts2-$ts1)/86400)+1,
                    'remainder' => str_replace($m[0], '', $text),
                ];
            }

        } elseif (preg_match('/\b(\d{1,2})\/(\d{1,2})\/(\d{2,4})\b/', $text, $m)) {
            $y   = strlen($m[3]) === 2 ? (int)('20'.$m[3]) : (int)$m[3];
            $mo  = (int)$m[1]; $da = (int)$m[2];
            if (checkdate($mo, $da, $y)) {
                $ts1 = mktime(0,0,0,$mo,$da,$y);
                return [
                    'from'      => date('M j', $ts1),
                    'to'        => date('M j, Y', $ts1 + 5*86400),
                    'days'      => 6,
                    'remainder' => str_replace($m[0], '', $text),
                ];
            }

        } elseif (preg_match('/\b(a|an|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|\d{1,2})\s*-?\s*(days?|weeks?)\b/i', $text, $m)) {
            $wordNums = [
                'a' => 1, 'an' => 1, 'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4,
                'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
                'eleven' => 11, 'twelve' => 12, 'thirteen' => 13, 'fourteen' => 14,
            ];
            $n    = is_numeric($m[1]) ? (int) $m[1] : ($wordNums[strtolower($m[1])] ?? 0);
            $days = str_starts_with(strtolower($m[2]), 'week') ? $n * 7 : $n;

            if ($days > 0 && $days <= 60) {
                $anchorTs = strtotime('today');
                if ($this->aiDateFrom !== '') {
                    $existing = strtotime($this->aiDateFrom . ' ' . date('Y'));
                    if ($existing !== false && $existing >= strtotime('today')) {
                        $anchorTs = $existing;
                    }
                }

                return [
                    'from'      => date('M j', $anchorTs),
                    'to'        => date('M j, Y', $anchorTs + ($days - 1) * 86400),
                    'days'      => $days,
                    'remainder' => str_replace($m[0], '', $text),
                ];
            }
        }

        return null;
    }

    private function parseAiPrompt(): void
    {
        $raw = $this->aiPrompt;

        $withoutDate = $raw;
        $dateRange = $this->parseDateRange($raw);
        if ($dateRange !== null) {
            $this->aiDateFrom = $dateRange['from'];
            $this->aiDateTo   = $dateRange['to'];
            $this->aiDays     = $dateRange['days'];
            $withoutDate      = $dateRange['remainder'];
        }

        if (preg_match('/\bsolo\b|\bjust me\b|\balone\b|\bby myself\b/i', $withoutDate)
            && !preg_match('/\band\b|\bwith\b|\+/i', $withoutDate)) {
            $this->aiTravelers = 1;
        } elseif (preg_match('/\b(\d{1,2})\s*(?:people|persons?|pax|travelers?|adults?|of us)\b/i', $withoutDate, $m)) {
            $v = (int) $m[1];
            if ($v > 0) $this->aiTravelers = $v;
        }

        $big = '(?:\d{1,3}(?:,\d{3})+|\d{4,}|\d+\s*[kK](?:\b|(?=(?:' . $this->currencySuffixWords() . ')\b)))';

        if (($unsupported = $this->detectUnsupportedCurrency($withoutDate)) !== null) {
            $this->unsupportedCurrencyNotice = "I couldn't recognize the selected currency. Please choose one of the supported currencies from the list.";

        } elseif ($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0
            && ($conversion = $this->detectAndConvertCurrency($withoutDate)) !== null) {
            if ($conversion === false) {
                $this->currencyRateUnavailableNotice = "I couldn't fetch the live exchange rate just now — please try entering your budget again in a moment.";
            } else {
                $this->aiBudgetMin = $this->aiBudgetMax = min(self::MAX_BUDGET, $conversion['pesoAmount']);
                $this->currencyNoticeLabel      = $conversion['displayLabel'];
                $this->currencyNoticePesoAmount = $conversion['pesoAmount'];
            }

        } elseif (
               preg_match('/daily\s*budget\D{0,15}?[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)
            || preg_match('/[₱P]?\s*(' . $big . ')\D{0,15}?\bdaily\b/ui', $withoutDate, $m)
            || preg_match('/[₱P]?\s*(' . $big . ')\s*(?:per\s*day|\/\s*day|a\s*day)\b/ui', $withoutDate, $m)
        ) {
            $v = $this->parseMoneyToken($m[1]);
            $this->applyLocalBudget($v, $v);
            $this->aiBudgetIsDaily = true;

        } elseif (preg_match('/[₱P]?\s*(' . $big . ')\s*(?:[-–]|to)\s*[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)) {
            $a = $this->parseMoneyToken($m[1]);
            $b = $this->parseMoneyToken($m[2]);
            $this->applyLocalBudget(min($a, $b), max($a, $b));

        } elseif (preg_match('/budget\s*(?:is|of|:)?\s*[₱P]?\s*(' . $big . ')/ui', $withoutDate, $m)) {
            $v = $this->parseMoneyToken($m[1]);
            $this->applyLocalBudget($v, $v);

        } elseif (preg_match('/[₱]\s*(' . $big . ')/u', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        } elseif (preg_match('/(' . $big . ')\s*(?:pesos?|php)\b/ui', $withoutDate, $m)) {
            $this->aiBudgetMin = $this->aiBudgetMax = $this->parseMoneyToken($m[1]);

        } elseif ((($this->aiBudgetMin === 0 && $this->aiBudgetMax === 0) || $this->looksLikeCorrection($withoutDate))
            && preg_match('/\b(' . $big . ')\b/', $withoutDate, $m)) {
            $v = $this->parseMoneyToken($m[1]);
            $this->applyLocalBudget($v, $v);

        }

        $months = 'january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|oct|nov|dec';

        $notTrigger = '(?:to|in|at|from|go(?:ing)?|travel(?:l?ing)?|visit(?:ing)?|fly(?:ing)?|stay(?:ing)?|heading)';
        $city   = '(?!(?:' . $months . ')\b)(?!(?i:' . $notTrigger . ')\b)[A-Z][a-z]+(?:\s+(?!(?i:' . $notTrigger . ')\b)[A-Z][a-z]+){0,2}';

        $ambiguousTo = preg_match(
            '/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $city . ')\s+or\s+(' . $city . ')\b/u',
            $withoutDate
        );
        $ambiguousFrom = preg_match('/\bfrom\s+(' . $city . ')\s+or\s+(' . $city . ')\b/u', $withoutDate);

        $hasFrom = !$ambiguousFrom && preg_match('/\bfrom\s+(' . $city . ')\b/u', $withoutDate, $mf);
        $hasTo   = !$ambiguousTo && preg_match('/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $city . ')\b/u', $withoutDate, $mt);

        $hasTwoCities = !$ambiguousFrom && !$ambiguousTo
            && preg_match('/(' . $city . ')\s+to\s+(' . $city . ')/u', $withoutDate, $m2);

        if ($hasFrom && $hasTo) {
            $resolvedFrom = $this->knownPlaceName($this->cleanCityName(trim($mf[1])), 'origin');
            $resolvedTo   = $this->knownPlaceName($this->cleanCityName(trim($mt[1])), 'destination');
            if ($resolvedFrom !== '') $this->aiFrom = $resolvedFrom;
            if ($resolvedTo !== '')   $this->aiTo   = $resolvedTo;
        } elseif ($hasTwoCities) {
            $resolvedFrom = $this->knownPlaceName($this->cleanCityName(trim($m2[1])), 'origin');
            $resolvedTo   = $this->knownPlaceName($this->cleanCityName(trim($m2[2])), 'destination');
            if ($resolvedFrom !== '' && $resolvedTo !== '' && strtolower($resolvedFrom) !== strtolower($resolvedTo)) {
                $this->aiFrom = $resolvedFrom;
                $this->aiTo   = $resolvedTo;
            }
        } elseif ($hasFrom) {
            $resolved = $this->knownPlaceName($this->cleanCityName(trim($mf[1])), 'origin');
            if ($resolved !== '') $this->aiFrom = $resolved;
        } elseif ($hasTo) {
            $resolved = $this->knownPlaceName($this->cleanCityName(trim($mt[1])), 'destination');
            if ($resolved !== '') $this->aiTo = $resolved;
        }

        if ($ambiguousTo || $ambiguousFrom) {
            $this->ambiguityNotice = "Looks like you named more than one option there — could you tell me just the one you'd like to go with?";
        }

        $anyCase = '(?!' . $notTrigger . '\b)[A-Za-z]+(?:\s+(?!' . $notTrigger . '\b)[A-Za-z]+){0,2}';

        if ($this->aiFrom === '' && $this->aiTo === '' && !$ambiguousFrom && !$ambiguousTo
            && preg_match('/\b(' . $anyCase . ')\s+to\s+(' . $anyCase . ')\b/iu', $withoutDate, $m2l)) {
            $fromCandidate = $this->knownPlaceName($this->cleanCityName($m2l[1]), 'origin');
            $toCandidate   = $this->knownPlaceName($this->cleanCityName($m2l[2]), 'destination');
            if ($fromCandidate !== '' && $toCandidate !== '' && strtolower($fromCandidate) !== strtolower($toCandidate)) {
                $this->aiFrom = $fromCandidate;
                $this->aiTo   = $toCandidate;
            }
        }

        if ($this->aiTo === '' && !$ambiguousTo
            && preg_match('/\b(?:travel(?:l?ing)?\s+(?:to|in)|go(?:ing)?\s+(?:to|in)|visit(?:ing)?|fly(?:ing)?\s+to|heading\s+to|stay(?:ing)?\s+(?:in|at)|to|in|at)\s+(' . $anyCase . ')\b/iu', $withoutDate, $mtl)) {
            $resolved = $this->knownPlaceName($this->cleanCityName($mtl[1]), 'destination');
            if ($resolved !== '') {
                $this->aiTo = $resolved;
            }
        }
        if ($this->aiFrom === '' && !$ambiguousFrom
            && preg_match('/\bfrom\s+(' . $anyCase . ')\b/iu', $withoutDate, $mfl)) {
            $resolved = $this->knownPlaceName($this->cleanCityName($mfl[1]), 'origin');
            if ($resolved !== '') {
                $this->aiFrom = $resolved;
            }
        }

        if ($this->aiBudgetIsDaily && $this->aiDays > 0) {
            $this->aiBudgetMin *= $this->aiDays;
            $this->aiBudgetMax *= $this->aiDays;
            $this->aiBudgetIsDaily = false;
        }
    }

    private function generateAiPackage(): void
    {
        $dest    = strtolower($this->aiTo);
        $days    = max(1, $this->aiDays);
        $budget  = $this->aiBudgetMax ?: $this->aiBudgetMin ?: 30000;

        $lookup = PlaceCatalog::PACKAGE_DATA;

        $data = null;
        foreach ($lookup as $key => $d) {
            if (str_contains($dest, $key)) { $data = $d; break; }
        }

        if (!$data) {
            $cityName = ucwords($this->aiTo ?: 'Destination');
            $isIntl   = $budget >= 50000;
            $mealDay  = $isIntl ? 3000 : 700;
            $data = [
                'code'        => $isIntl ? 'INTL' : 'DOM',
                'airline'     => $isIntl ? 'International Carrier · Direct Flight' : 'Cebu Pacific · Direct Flight',
                'hotel'       => "Grand Hotel {$cityName}",
                'hotel_stars' => $isIntl ? 4 : 3,
                'hotel_type'  => 'Deluxe Room',
                'hotel_city'  => $cityName,
                'restaurant'  => "Local Dining at {$cityName}",
                'meal_plan'   => 'Breakfast, Lunch, & Dinner',
                'meal_city'   => $cityName,
                'meal_day'    => $mealDay,
                'attractions' => [
                    ["{$cityName} City Tour", '₱300'],
                    ['Local Market Visit', 'Free'],
                    ['Heritage & Culture Walk', 'Free'],
                ],
            ];
        }

        $travelers     = max(1, $this->aiTravelers);
        $transport     = (int) round($budget * 0.18);
        $accommodation = (int) round(($budget * 0.50 / $days)) * $days;
        $foodTotal     = $data['meal_day'] * $days * $travelers;
        $attrTotal     = array_sum(array_map(fn($a) => is_numeric(str_replace(['₱',','], '', $a[1])) ? (int)str_replace(['₱',','], '', $a[1]) : 0, $data['attractions']));

        $this->aiPackage = $this->capPackageToBudget([
            'transport'     => ['label'=>'TRANSPORTATION','icon'=>'fa-solid fa-plane','from_code'=>$this->resolveCode($this->aiFrom ?: 'Manila'),'to_code'=>$data['code'],'detail'=>$data['airline'].' · Direct Flight · Round Trip','cost'=>$transport],
            'accommodation' => ['label'=>'ACCOMMODATION','icon'=>'fa-solid fa-bed','name'=>$data['hotel'],'stars'=>$data['hotel_stars'],'detail'=>$days.' Nights · '.$data['hotel_type'].' · '.$data['hotel_city'],'cost'=>$accommodation],
            'food'          => ['label'=>'FOOD & DINING','icon'=>'fa-solid fa-utensils','name'=>$data['restaurant'],'detail'=>$days.' Days · '.$data['meal_plan'].' · '.$data['meal_city'],'cost'=>$foodTotal],
            'attractions'   => ['label'=>'ATTRACTIONS','icon'=>'fa-solid fa-landmark','items'=>$data['attractions'],'cost'=>$attrTotal],
        ], $budget);
    }

    public function render()
    {
        return view('livewire.traveler.llm');
    }
}
