<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\PlaceCatalog;

/**
 * The one place a UserProfile is written.
 *
 * Lifted verbatim out of ProfileBuilder::persistProfile() so the AI planner can
 * save a conversationally-built profile through the identical code path. The
 * foreign-currency handling below is subtle — it deliberately refuses to save
 * rather than guess a rate — and a second, independently-written copy of that
 * rule inside the chat flow is exactly how the two would drift apart.
 */
class UserProfileSaver
{
    /**
     * The currency a home city's budget is typed in.
     *
     * Note this is currency-by-CITY, not currency-named-in-free-text: a profile
     * budget is always entered in the traveller's own home currency, so it is
     * derived from where they live rather than parsed out of what they typed.
     * (The trip planner's detectAndConvertCurrency() solves the different
     * problem of a traveller naming an arbitrary currency inline.)
     */
    public static function currencyForHomeCity(string $homeCity): ?string
    {
        if (trim($homeCity) === '') return null;

        foreach (config('country_cities') as $country => $cities) {
            if (in_array($homeCity, $cities, true)) {
                return PlaceCatalog::COUNTRY_CURRENCIES[$country] ?? null;
            }
        }

        // Trips derive a currency from a different table, keyed by city rather
        // than country. A city listed there but not in country_cities used to
        // yield no currency here at all, so the same place could be foreign to
        // the planner and peso-denominated to the profile.
        return PlaceCatalog::DESTINATION_CURRENCIES[strtolower(trim($homeCity))] ?? null;
    }

    /**
     * The symbol a home city's budget field should be labelled with.
     */
    public static function budgetSymbolForHomeCity(string $homeCity): string
    {
        $code = self::currencyForHomeCity($homeCity);
        return PlaceCatalog::CURRENCY_SYMBOLS[$code ?? 'PHP'] ?? '₱';
    }

    /**
     * Writes the profile, converting a foreign daily budget into pesos first.
     *
     * $attributes takes the traveller's raw answers: home_city, daily_budget
     * (as typed, in the home city's own currency), travel_style,
     * group_member_emails, interests, sub_interests, preferred_transportation,
     * preferred_accommodation.
     *
     * Returns ['ok' => bool, 'profile' => ?UserProfile, 'error' => ?string].
     * ok === false means the save was REFUSED — currently only when a foreign
     * daily budget can't be turned into pesos by any means. Callers must not
     * navigate away in that case.
     */
    public function save(User $user, array $attributes): array
    {
        $homeCity    = (string) ($attributes['home_city'] ?? '');
        $dailyBudget = (float) ($attributes['daily_budget'] ?? 0);
        $travelStyle = (string) ($attributes['travel_style'] ?? '');
        $groupEmails = (array) ($attributes['group_member_emails'] ?? []);

        // Captured before the write so only genuinely new companions get a
        // notification — re-saving the profile must not re-notify everyone.
        // Read straight from the table rather than $user->userProfile: that
        // relation is cached on the User instance, so a second save in the
        // same request would still see the pre-save list and notify twice.
        $existing = UserProfile::where('user_id', $user->id)->first();
        $previousEmails = (array) ($existing?->group_member_emails ?? []);

        $pesoBudget    = $dailyBudget;
        $localCurrency = null;
        $localBudget   = null;

        $currencyCode = self::currencyForHomeCity($homeCity);
        if ($currencyCode !== null && $currencyCode !== 'PHP' && $dailyBudget > 0) {
            $converter = new CurrencyConverterService();

            // Recorded whether or not a rate is found, so the amount the
            // traveller actually typed and the currency it was typed in are
            // never lost and the pesos can always be re-derived later.
            $localCurrency = $currencyCode;
            $localBudget   = $dailyBudget;

            $liveRate = $converter->rateToPhp($currencyCode);
            $sameAsSaved = $existing
                && $existing->daily_budget_currency === $currencyCode
                && (float) $existing->daily_budget_local === (float) $dailyBudget;

            if ($liveRate !== null) {
                $pesoBudget = round($dailyBudget * $liveRate, 2);
            } elseif ($sameAsSaved) {
                // Same foreign amount as the last save and no live rate right
                // now: keep the figure that WAS converted from a live rate.
                // Not a guessed rate — a previously live-derived one — and it
                // is what stops a provider outage from rewriting a correct
                // budget, which it used to do with the raw foreign number.
                $pesoBudget = (float) $existing->daily_budget;
            } else {
                // No rate, and nothing previously converted to fall back on.
                // Refuse rather than store a foreign amount as pesos. Matches
                // confirmEmergencyFund() in the trip planner, which already
                // blocks on this same condition.
                return [
                    'ok'      => false,
                    'profile' => null,
                    'error'   => "I couldn't convert your {$currencyCode} budget into pesos just now — please try again in a moment.",
                ];
            }
        }

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'home_city'      => $homeCity,
                'daily_budget'   => $pesoBudget,
                'daily_budget_currency' => $localCurrency,
                'daily_budget_local'    => $localBudget,
                'travel_style'         => $travelStyle,
                'group_member_emails'  => $travelStyle === 'Group' ? $groupEmails : [],
                'interests'      => (array) ($attributes['interests'] ?? []),
                'sub_interests'  => (array) ($attributes['sub_interests'] ?? []),
                'preferred_transportation' => (string) ($attributes['preferred_transportation'] ?? ''),
                'preferred_accommodation'  => (string) ($attributes['preferred_accommodation'] ?? ''),
            ]
        );

        if ($travelStyle === 'Group') {
            $this->notifyNewCompanions($user, $groupEmails, $previousEmails);
        }

        return ['ok' => true, 'profile' => $profile, 'error' => null];
    }

    /**
     * Tells anyone newly listed as a travel companion. Only registered
     * accounts can be notified; an unknown email is simply skipped, matching
     * how the group-member picker already behaves.
     */
    private function notifyNewCompanions(User $user, array $groupEmails, array $previousEmails): void
    {
        $added = array_diff($groupEmails, $previousEmails);
        if (!$added) return;

        $inviter = $user->full_name ?: 'A fellow traveler';

        User::whereIn('email', $added)
            ->where('id', '!=', $user->id)
            ->get()
            ->each(fn (User $u) => Notification::create([
                'user_id' => $u->id,
                'trip_id' => null,
                'type'    => 'group_member_added',
                'message' => "{$inviter} added you as a travel companion. You'll be included on their group trips.",
                'is_read' => false,
            ]));
    }
}
