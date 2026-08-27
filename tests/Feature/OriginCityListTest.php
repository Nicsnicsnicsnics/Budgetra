<?php

namespace Tests\Feature;

use App\Support\PlaceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The trip planner's From/To pickers are built from the country the traveller
 * chose at registration. "Local" used to be hardcoded to Philippine cities, so a
 * Canadian was asked which Philippine city they were leaving from.
 */
class OriginCityListTest extends TestCase
{
    use RefreshDatabase;

    private function names(array $options, string $group): array
    {
        return array_values(array_map(
            fn ($o) => $o['name'],
            array_filter($options, fn ($o) => $o['group'] === $group)
        ));
    }

    public function test_local_cities_are_the_travellers_own_country(): void
    {
        $canada = PlaceCatalog::cityOptionsFor('Canada');

        $this->assertSame(['Toronto', 'Vancouver', 'Montreal'], $this->names($canada, 'Local'));
        // A Canadian does not "leave from" Manila.
        $this->assertNotContains('Manila', $this->names($canada, 'Local'));

        $japan = PlaceCatalog::cityOptionsFor('Japan');
        $this->assertSame(['Tokyo', 'Osaka', 'Sapporo'], $this->names($japan, 'Local'));
    }

    public function test_a_city_is_never_both_local_and_international(): void
    {
        // Toronto is on the popular-destinations list, but for a Canadian it
        // belongs under Local and must not also appear under International.
        $canada = PlaceCatalog::cityOptionsFor('Canada');

        $this->assertContains('Toronto', $this->names($canada, 'Local'));
        $this->assertNotContains('Toronto', $this->names($canada, 'International'));

        $all = array_map(fn ($o) => strtolower($o['name']), $canada);
        $this->assertSame(count($all), count(array_unique($all)), 'a city is listed twice');
    }

    public function test_a_foreign_traveller_can_still_reach_the_philippines(): void
    {
        $intl = $this->names(PlaceCatalog::cityOptionsFor('Canada'), 'International');

        // Philippine cities are this app's speciality; they must not vanish
        // from a foreign traveller's picker just because they aren't local.
        $this->assertContains('Manila', $intl);
        $this->assertContains('Cebu City', $intl);
    }

    public function test_filipino_travellers_keep_every_domestic_destination(): void
    {
        $local = $this->names(PlaceCatalog::cityOptionsFor('Philippines'), 'Local');

        // The planner offered 25 domestic places before the list became
        // country-aware; none of them may be lost to the switch.
        $this->assertCount(25, $local);
        foreach (['Manila', 'El Nido', 'Coron', 'Baguio', 'Batanes', 'Siquijor', 'Legazpi'] as $city) {
            $this->assertContains($city, $local);
        }
    }

    public function test_an_unknown_or_missing_country_falls_back_to_the_home_market(): void
    {
        // country is nullable at registration, and a country we stock no cities
        // for behaves the same way.
        foreach ([null, 'Atlantis'] as $country) {
            $local = $this->names(PlaceCatalog::cityOptionsFor($country), 'Local');
            $this->assertContains('Manila', $local);
        }
    }

    /** Every offered city needs an IATA code, or it can't be searched for flights. */
    public function test_every_offered_city_has_a_flight_code(): void
    {
        foreach (array_keys(config('country_cities')) as $country) {
            foreach (PlaceCatalog::cityOptionsFor($country) as $option) {
                $this->assertNotSame('', $option['code'],
                    "{$option['name']} (offered to {$country}) has no IATA code");
            }
        }
    }

    public function test_the_origin_picker_offers_only_the_travellers_own_country(): void
    {
        // You depart from where you live: the From dropdown renders
        // originGroups, which is Local only. The To dropdown still renders both.
        $wizard = file_get_contents(resource_path('views/livewire/traveler/trip-planner-wizard.blade.php'));

        $this->assertSame(2, substr_count($wizard, 'x-for="grp in originGroups"'),
            'both From pickers (step 1 and step 2) should be local-only');
        $this->assertSame(2, substr_count($wizard, "originGroups: ['Local'],"),
            'originGroups must be Local-only in both Alpine components');

        // Three remain unrestricted: step 1 To, step 2 To, step 2 multi-city.
        $this->assertSame(3, substr_count($wizard, "grp in ['Local','International']"),
            'destination pickers must still offer international destinations');
    }

    public function test_the_origin_heading_names_the_country(): void
    {
        $this->assertSame('Canada', PlaceCatalog::originCountryFor('Canada'));
        $this->assertSame('Japan', PlaceCatalog::originCountryFor('Japan'));
        // Unset or unknown falls back to the home market, matching cityOptionsFor().
        $this->assertSame('Philippines', PlaceCatalog::originCountryFor(null));
        $this->assertSame('Philippines', PlaceCatalog::originCountryFor('Atlantis'));
    }

    public function test_foreign_destinations_are_still_reachable(): void
    {
        // Restricting origins must not strand a Canadian at home — the whole
        // Canada -> Japan flow depends on Tokyo staying selectable as a TO.
        $intl = $this->names(PlaceCatalog::cityOptionsFor('Canada'), 'International');

        $this->assertContains('Tokyo', $intl);
        $this->assertContains('Singapore', $intl);
        $this->assertGreaterThan(20, count($intl));
    }
}
