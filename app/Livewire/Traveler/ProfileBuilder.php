<?php

namespace App\Livewire\Traveler;

use Livewire\Component;
use App\Models\AiConversationDraft;
use App\Services\UserProfileSaver;
use App\Support\ProfileCatalog;

class ProfileBuilder extends Component
{
    public int    $step       = 1;
    public string $returnTo   = '';
    // Arrived here from TARA's interests question rather than the normal form.
    public bool   $fromTara   = false;
    public string $homeCity   = '';
    // Set when a save is refused because the budget could not be converted.
    public string $saveError  = '';
    public string $dailyBudgetDisplay = '';
    public float  $dailyBudget = 0;
    public string $travelStyle = '';
    public array  $memberEmailInputs = [''];
    public array  $groupMemberEmails = [];

    public array  $selectedInterests    = [];
    public array  $selectedSubInterests = [];
    public string $expandedInterest     = '';
    public string $preferredTransportation = '';
    public string $preferredAccommodation  = '';

    // The lists themselves now live in ProfileCatalog, so the AI planner can
    // resolve free-text answers against the same canonical labels this form
    // offers as buttons. Kept as aliases because profile/edit.blade.php reads
    // ProfileBuilder::ICONS / ::TRAVEL_STYLES / ::INTERESTS directly.
    public const INTERESTS              = ProfileCatalog::INTERESTS;
    public const ICONS                  = ProfileCatalog::ICONS;
    public const IMAGES                 = ProfileCatalog::IMAGES;
    public const TRAVEL_STYLES          = ProfileCatalog::TRAVEL_STYLES;
    public const TRANSPORTATION_OPTIONS = ProfileCatalog::TRANSPORTATION_OPTIONS;
    public const TRANSPORTATION_IMAGES  = ProfileCatalog::TRANSPORTATION_IMAGES;
    public const ACCOMMODATION_OPTIONS  = ProfileCatalog::ACCOMMODATION_OPTIONS;
    public const ACCOMMODATION_IMAGES   = ProfileCatalog::ACCOMMODATION_IMAGES;

    public function mount(): void
    {
        $profile = auth()->user()->userProfile;
        if ($profile) {
            $city = $profile->home_city ?? '';
            $this->homeCity = is_numeric(preg_replace('/[\s,₱]/', '', $city)) ? '' : $city;
            // The field must be re-editable in the same unit it was typed
            // in — showing the peso ledger value here would make the very
            // next save re-convert an already-converted number.
            $this->dailyBudget         = $profile->daily_budget_local ?? $profile->daily_budget ?? 0;
            $this->dailyBudgetDisplay  = $this->dailyBudget ? number_format($this->dailyBudget) : '';
            $this->travelStyle         = $profile->travel_style  ?? '';
            $this->groupMemberEmails   = $profile->group_member_emails ?? [];
            $this->selectedInterests   = $profile->interests     ?? [];
            $this->selectedSubInterests= $profile->sub_interests ?? [];
            $this->preferredTransportation = $profile->preferred_transportation ?? '';
            $this->preferredAccommodation  = $profile->preferred_accommodation  ?? '';
        }

        // Allow deep-linking straight to a specific step (e.g. "Edit" from
        // the AI planner jumps right to the interests step) instead of
        // always starting the whole wizard over from step 1.
        $requestedStep = (int) request()->query('step', 0);
        if ($requestedStep >= 1 && $requestedStep <= 7) {
            $this->step = $requestedStep;
        }

        // Whitelist which "come back to" routes are allowed, so this can't
        // be abused as an open redirect via the query string.
        $requestedReturn = (string) request()->query('return', '');
        if (in_array($requestedReturn, ['trips.plan.ai', 'profile.edit'], true)) {
            $this->returnTo = $requestedReturn;
        }

        $this->applyTaraDraftIfHandedOver();
    }

    /**
     * Sent here mid-conversation by Llm::pickInterestsInForm().
     *
     * Overlays the answers TARA has collected but not yet saved. Without this
     * the save below would write an empty home city and a zero budget over
     * them, because a traveller building a profile through chat has no
     * UserProfile row for mount() to load from yet.
     *
     * The session key is pulled once into a component property so it can't
     * linger and mis-flag a later, unrelated visit to this form.
     */
    private function applyTaraDraftIfHandedOver(): void
    {
        if (! session()->pull('tara_interests_return')) return;

        $this->fromTara = true;
        $this->step     = 4;
        // Set here rather than relying on ?return= surviving the round trip:
        // the handoff already knows where it came from.
        $this->returnTo = 'trips.plan.ai';

        $draft = AiConversationDraft::where('user_id', auth()->id())->first();
        if (! $draft) return;

        $d = (array) ($draft->profile_draft ?? []);
        if (! $d) return;

        $this->homeCity           = (string) ($d['home_city'] ?? $this->homeCity);
        $this->dailyBudget        = (float)  ($d['daily_budget'] ?? $this->dailyBudget);
        $this->dailyBudgetDisplay = $this->dailyBudget ? number_format($this->dailyBudget) : '';
        $this->travelStyle        = (string) ($d['travel_style'] ?? $this->travelStyle);
        $this->groupMemberEmails  = (array)  ($d['group_member_emails'] ?? $this->groupMemberEmails);
        $this->selectedInterests  = (array)  ($d['interests'] ?? $this->selectedInterests);
        $this->selectedSubInterests = (array) ($d['sub_interests'] ?? $this->selectedSubInterests);
    }

    // Every step is checked for its own required field(s) before advancing.
    // Missing ones used to be listed in a modal; now their keys go back to the
    // browser and the fields themselves shake with a red border, so the
    // traveler is looking at the thing that needs fixing.
    /**
     * Required-field keys still missing on the given step, in the same
     * vocabulary the browser's shake handler expects.
     *
     * Extracted from nextStep() so saveAndReturn() can run the identical
     * check — editing a single step from the Profile page never went through
     * nextStep(), so a field cleared there used to save empty with no shake
     * and no complaint.
     */
    private function missingForStep(int $step): array
    {
        $missing = [];

        if ($step === 1) {
            $city = trim($this->homeCity);
            if (empty($city)) {
                $missing[] = 'home';
            } elseif (is_numeric(preg_replace('/[\s,₱]/', '', $city))) {
                // This one has a real @error slot under the field, so keep the
                // message — just shake it too.
                $this->addError('homeCity', 'Please enter a city name (e.g. "Manila"), not a number.');
                $missing[] = 'home';
            }
        }

        if ($step === 2 && $this->dailyBudget <= 0) {
            $missing[] = 'budget';
        }

        if ($step === 3) {
            if ($this->travelStyle === '') {
                $missing[] = 'style';
            } elseif ($this->travelStyle === 'Group' && empty($this->groupMemberEmails)) {
                $missing[] = 'members';
            }
        }

        if ($step === 4 && empty($this->selectedInterests)) {
            $missing[] = 'interests';
        }

        // Transport and stay are separate steps now, so each is gated on its
        // own — otherwise step 5 would demand an accommodation the traveler
        // hasn't been shown yet.
        if ($step === 5 && $this->preferredTransportation === '') {
            $missing[] = 'transportation';
        }

        if ($step === 6 && $this->preferredAccommodation === '') {
            $missing[] = 'accommodation';
        }

        return $missing;
    }

    public function nextStep(): void
    {
        if ($missing = $this->missingForStep($this->step)) {
            $this->dispatch('profile-missing', fields: $missing);
            return;
        }

        $this->resetErrorBag();
        $this->step++;
    }

    public function prevStep(): void
    {
        if ($this->step > 1) $this->step--;
    }

    public function selectTravelStyle(string $style): void
    {
        $this->travelStyle = ($this->travelStyle === $style) ? '' : $style;
    }

    public function selectTransportation(string $option): void
    {
        $this->preferredTransportation = ($this->preferredTransportation === $option) ? '' : $option;
    }

    public function selectAccommodation(string $option): void
    {
        $this->preferredAccommodation = ($this->preferredAccommodation === $option) ? '' : $option;
    }

    public function addGroupMember(int $index): void
    {
        $email = trim($this->memberEmailInputs[$index] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError("memberEmailInputs.$index", 'Please enter a valid email address.');
            return;
        }

        if (in_array($email, $this->groupMemberEmails)) {
            $this->addError("memberEmailInputs.$index", 'This member has already been added.');
            return;
        }

        $this->resetErrorBag("memberEmailInputs.$index");
        $this->groupMemberEmails[] = $email;
        $this->memberEmailInputs[$index] = '';
    }

    public function addMemberRow(): void
    {
        $this->memberEmailInputs[] = '';
    }

    public function removeMemberRow(int $index): void
    {
        unset($this->memberEmailInputs[$index]);
        $this->memberEmailInputs = array_values($this->memberEmailInputs);

        if (empty($this->memberEmailInputs)) {
            $this->memberEmailInputs = [''];
        }
    }

    public function removeGroupMember(string $email): void
    {
        $this->groupMemberEmails = array_values(array_filter(
            $this->groupMemberEmails, fn ($e) => $e !== $email
        ));
    }

    /**
     * The whole step-4 selection, not a delta.
     *
     * Those cards are an Alpine island (wire:ignore), so the browser holds the
     * live selection and tells the server what it now is. Sending the entire
     * list on every change means a request that never lands is repaired by the
     * next one; the per-click toggles this replaced desynced the two for good,
     * and the traveler was then left on a screen full of ticks that Next Step
     * refused to accept.
     *
     * Anything the browser sends that isn't in INTERESTS is dropped, and the
     * canonical order is imposed here so two equal selections always store
     * identically.
     */
    public function syncInterests(array $interests = [], array $subs = []): void
    {
        // Diving and Night Markets each sit under two interests, so the flat
        // list of every sub-interest has to be de-duplicated before it can be
        // used as a filter.
        $knownSubs = array_values(array_unique(array_merge(...array_values(self::INTERESTS))));

        $this->selectedInterests    = array_values(array_intersect(array_keys(self::INTERESTS), $interests));
        $this->selectedSubInterests = array_values(array_intersect($knownSubs, $subs));
    }

    public function toggleInterest(string $interest): void
    {
        if (in_array($interest, $this->selectedInterests)) {
            $this->selectedInterests = array_values(array_filter(
                $this->selectedInterests, fn($i) => $i !== $interest
            ));
            // remove associated sub-interests
            $subs = self::INTERESTS[$interest] ?? [];
            $this->selectedSubInterests = array_values(array_filter(
                $this->selectedSubInterests, fn($s) => !in_array($s, $subs)
            ));
            if ($this->expandedInterest === $interest) {
                $this->expandedInterest = '';
            }
        } else {
            $this->selectedInterests[] = $interest;
            $this->expandedInterest = $interest;
        }
    }

    public function toggleSubInterest(string $sub): void
    {
        if (in_array($sub, $this->selectedSubInterests)) {
            $this->selectedSubInterests = array_values(array_filter(
                $this->selectedSubInterests, fn($s) => $s !== $sub
            ));
        } else {
            $this->selectedSubInterests[] = $sub;
        }
    }

    /**
     * Returns false when the save was refused — currently only when a foreign
     * daily budget can't be turned into pesos by any means. Callers must not
     * navigate away in that case.
     *
     * The write itself, including the refuse-rather-than-guess currency rule,
     * lives in UserProfileSaver so the AI planner saves through the identical
     * path rather than a second copy of it.
     */
    private function persistProfile(): bool
    {
        $result = (new UserProfileSaver())->save(auth()->user(), [
            'home_city'                => $this->homeCity,
            'daily_budget'             => $this->dailyBudget,
            'travel_style'             => $this->travelStyle,
            'group_member_emails'      => $this->groupMemberEmails,
            'interests'                => $this->selectedInterests,
            'sub_interests'            => $this->selectedSubInterests,
            'preferred_transportation' => $this->preferredTransportation,
            'preferred_accommodation'  => $this->preferredAccommodation,
        ]);

        if (! $result['ok']) {
            $this->saveError = $result['error'];
            return false;
        }

        $this->saveError = '';

        return true;
    }

    public function confirmProfile(): void
    {
        if (! $this->persistProfile()) {
            $this->dispatch('profile-save-failed');
            return;   // stay put, saveError is shown
        }

        $this->redirect(route($this->returnTo ?: 'trips.plan'), navigate: true);
    }

    // Quick-edit path: arrived here via "Edit" from the AI planner or the
    // profile page to change just one step. Saves immediately and returns,
    // instead of forcing the traveler through the rest of the wizard again.
    public function saveAndReturn(): void
    {
        // Editing one step from the Profile page bypasses nextStep() entirely,
        // so without this a cleared field saved as empty in silence.
        if ($missing = $this->missingForStep($this->step)) {
            $this->dispatch('profile-missing', fields: $missing);
            return;
        }

        $this->resetErrorBag();

        if (! $this->persistProfile()) {
            $this->dispatch('profile-save-failed');
            return;   // stay put, saveError is shown
        }

        // Tells the waiting profile conversation to pick the interests up and
        // move on to its review, instead of re-asking what was just answered.
        if ($this->fromTara) session(['tara_interests_picked' => true]);

        $this->redirect(route($this->returnTo ?: 'dashboard'), navigate: true);
    }

    private function citiesForCurrentUser(): array
    {
        $country = auth()->user()->country ?? null;
        $countryCities = config('country_cities');

        return ($country !== null && isset($countryCities[$country]))
            ? $countryCities[$country]
            : $countryCities['Philippines'];
    }

    private function suggestedCitiesForCurrentUser(): array
    {
        return array_slice(array_values($this->citiesForCurrentUser()), 0, 3);
    }

    private function budgetDisplaySymbol(): string
    {
        return UserProfileSaver::budgetSymbolForHomeCity($this->homeCity);
    }

    public function render()
    {
        return view('livewire.traveler.profile-builder', [
            'interests'    => self::INTERESTS,
            'icons'        => self::ICONS,
            'images'       => self::IMAGES,
            'suggested'    => $this->suggestedCitiesForCurrentUser(),
            'localDestinations' => $this->citiesForCurrentUser(),
            'budgetSymbol' => $this->budgetDisplaySymbol(),
            'travelStyles' => self::TRAVEL_STYLES,
            'transportationOptions' => self::TRANSPORTATION_OPTIONS,
            'transportationImages'  => self::TRANSPORTATION_IMAGES,
            'accommodationOptions'  => self::ACCOMMODATION_OPTIONS,
            'accommodationImages'   => self::ACCOMMODATION_IMAGES,
        ])->layout('layouts.app');
    }
}
