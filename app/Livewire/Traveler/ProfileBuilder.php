<?php

namespace App\Livewire\Traveler;

use Livewire\Component;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CurrencyConverterService;
use App\Support\PlaceCatalog;

class ProfileBuilder extends Component
{
    public int    $step       = 1;
    public string $returnTo   = '';
    public string $homeCity   = '';
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

    public const INTERESTS = [
        'Beach'           => ['Surfing', 'Snorkeling', 'Island Hopping', 'Swimming', 'Diving', 'Beach Camping', 'Kayaking', 'Sunset Watching'],
        'Nature'          => ['Mountains', 'Waterfalls', 'Wildlife', 'Forests', 'Camping', 'Bird Watching', 'National Parks', 'Caves'],
        'Food Trip'       => ['Street Food', 'Fine Dining', 'Local Delicacies', 'Cafes', 'Food Tours', 'Cooking Classes', 'Farmers Markets', 'Food Festivals'],
        'Adventure'       => ['Hiking', 'Diving', 'Ziplining', 'Canyoneering', 'Rock Climbing', 'Whitewater Rafting', 'Paragliding', 'ATV Rides'],
        'Historical Sites'=> ['Churches', 'Ruins', 'Forts', 'Heritage Towns', 'Monuments', 'Ancestral Houses', 'War Memorials', 'Archaeological Sites'],
        'Shopping'        => ['Malls', 'Night Markets', 'Pasalubong', 'Thrift Shops', 'Local Crafts', 'Souvenir Shops', 'Boutiques', 'Flea Markets'],
        'Museums'         => ['Art', 'History', 'Science', 'Culture', 'Natural History', 'Interactive Exhibits', 'Local Heritage', 'Photography'],
        'Nightlife'       => ['Bars', 'Clubs', 'Live Music', 'Night Markets', 'Rooftop Bars', 'Karaoke', 'Night Tours', 'Cultural Shows'],
        'Relaxation'      => ['Spa', 'Beach Resort', 'Hot Springs', 'Wellness', 'Yoga Retreats', 'Massage', 'Meditation', 'Quiet Cafes'],
    ];

    public const ICONS = [
        'Beach'           => 'fa-umbrella-beach',
        'Nature'          => 'fa-leaf',
        'Food Trip'       => 'fa-utensils',
        'Adventure'       => 'fa-person-hiking',
        'Historical Sites'=> 'fa-landmark',
        'Shopping'        => 'fa-bag-shopping',
        'Museums'         => 'fa-building-columns',
        'Nightlife'       => 'fa-moon',
        'Relaxation'      => 'fa-spa',
    ];

    public const IMAGES = [
        'Beach'           => 'beach.jpg',
        'Nature'          => 'nature.jpg',
        'Food Trip'       => 'foodtrip.jpg',
        'Adventure'       => 'adventure.jpg',
        'Historical Sites'=> 'historical.jpg',
        'Shopping'        => 'shopping.jpg',
        'Museums'         => 'museums.jpg',
        'Nightlife'       => 'nightlife.jpg',
        'Relaxation'      => 'relaxation.jpg',
    ];

    public const TRAVEL_STYLES = [
        'Solo'  => ['icon' => 'fa-user', 'desc' => 'Travel at your own pace with a budget built for one.', 'image' => 'solo.jpg'],
        'Group' => ['icon' => 'fa-user-group',      'desc' => 'Divide expenses and explore together, no one overpays.', 'image' => 'group.jpg'],
    ];

    public const TRANSPORTATION_OPTIONS = [
        'Flight' => 'fa-plane',
    ];

    public const TRANSPORTATION_IMAGES = [
        'Flight' => 'flights.jpg',
    ];

    public const ACCOMMODATION_OPTIONS = [
        'Hotel'     => 'fa-hotel',
        'Apartment' => 'fa-building',
        'Inn'       => 'fa-house-chimney',
        'Resort'    => 'fa-umbrella-beach',
    ];

    public const ACCOMMODATION_IMAGES = [
        'Hotel'     => 'hotel.jpg',
        'Apartment' => 'apartment.png',
        'Inn'       => 'inn.jpg',
        'Resort'    => 'resort.jpg',
    ];

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

    private function persistProfile(): void
    {
        // Captured before the write so only genuinely new companions get a
        // notification — re-saving the profile must not re-notify everyone.
        // Read straight from the table rather than auth()->user()->userProfile:
        // that relation is cached on the User instance, so a second save in
        // the same request would still see the pre-save list and notify twice.
        $previousEmails = (array) (UserProfile::where('user_id', auth()->id())
            ->value('group_member_emails') ?? []);

        $pesoBudget    = $this->dailyBudget;
        $localCurrency = null;
        $localBudget   = null;

        $currencyCode = $this->currencyForHomeCity($this->homeCity);
        if ($currencyCode !== null && $currencyCode !== 'PHP' && $this->dailyBudget > 0) {
            $rate = (new CurrencyConverterService())->rateToPhp($currencyCode);
            if ($rate !== null) {
                $localCurrency = $currencyCode;
                $localBudget   = $this->dailyBudget;
                $pesoBudget    = round($this->dailyBudget * $rate, 2);
            }
        }

        UserProfile::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'home_city'      => $this->homeCity,
                'daily_budget'   => $pesoBudget,
                'daily_budget_currency' => $localCurrency,
                'daily_budget_local'    => $localBudget,
                'travel_style'         => $this->travelStyle,
                'group_member_emails'  => $this->travelStyle === 'Group' ? $this->groupMemberEmails : [],
                'interests'      => $this->selectedInterests,
                'sub_interests'  => $this->selectedSubInterests,
                'preferred_transportation' => $this->preferredTransportation,
                'preferred_accommodation'  => $this->preferredAccommodation,
            ]
        );

        if ($this->travelStyle === 'Group') {
            $this->notifyNewCompanions($previousEmails);
        }
    }

    /**
     * Tells anyone newly listed as a travel companion. Only registered
     * accounts can be notified; an unknown email is simply skipped, matching
     * how the group-member picker already behaves.
     */
    private function notifyNewCompanions(array $previousEmails): void
    {
        $added = array_diff($this->groupMemberEmails, $previousEmails);
        if (!$added) return;

        $inviter = auth()->user()->full_name ?: 'A fellow traveler';

        User::whereIn('email', $added)
            ->where('id', '!=', auth()->id())
            ->get()
            ->each(fn (User $u) => Notification::create([
                'user_id' => $u->id,
                'trip_id' => null,
                'type'    => 'group_member_added',
                'message' => "{$inviter} added you as a travel companion. You'll be included on their group trips.",
                'is_read' => false,
            ]));
    }

    public function confirmProfile(): void
    {
        $this->persistProfile();
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
        $this->persistProfile();
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

    private function currencyForHomeCity(string $homeCity): ?string
    {
        if (trim($homeCity) === '') return null;

        foreach (config('country_cities') as $country => $cities) {
            if (in_array($homeCity, $cities, true)) {
                return PlaceCatalog::COUNTRY_CURRENCIES[$country] ?? null;
            }
        }

        return null;
    }

    private function budgetDisplaySymbol(): string
    {
        $code = $this->currencyForHomeCity($this->homeCity);
        return PlaceCatalog::CURRENCY_SYMBOLS[$code ?? 'PHP'] ?? '₱';
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
