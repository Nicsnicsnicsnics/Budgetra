<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\PlaceCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Destinations are shown with the country they sit in — "Toronto, Canada"
 * rather than a bare city name that means nothing to anyone who doesn't already
 * know the place.
 */
class DestinationCountryTest extends TestCase
{
    use RefreshDatabase;

    /** Every place the app can name must resolve to a country. */
    public function test_every_known_place_resolves_to_a_country(): void
    {
        $places = array_unique(array_merge(
            array_keys(PlaceCatalog::DESTINATION_CURRENCIES),
            array_keys(PlaceCatalog::PACKAGE_DATA),
            array_keys(PlaceCatalog::IATA_CODES),
        ));

        $unresolved = array_values(array_filter(
            $places,
            fn ($p) => PlaceCatalog::countryFor($p) === null
        ));

        $this->assertSame([], $unresolved, 'these places have no country: ' . implode(', ', $unresolved));
        $this->assertGreaterThan(200, count($places));
    }

    public function test_countries_are_resolved_regardless_of_casing_or_padding(): void
    {
        $this->assertSame('Canada', PlaceCatalog::countryFor('Toronto'));
        $this->assertSame('Canada', PlaceCatalog::countryFor('  toRONto '));
        $this->assertSame('Japan', PlaceCatalog::countryFor('Tokyo'));
        $this->assertSame('Switzerland', PlaceCatalog::countryFor('Zurich'));
        $this->assertSame('Philippines', PlaceCatalog::countryFor('Cebu'));
        $this->assertSame('United States', PlaceCatalog::countryFor('nyc'));
    }

    public function test_unknown_and_empty_places_resolve_to_nothing(): void
    {
        $this->assertNull(PlaceCatalog::countryFor('Nowhereville'));
        $this->assertNull(PlaceCatalog::countryFor(''));
        $this->assertNull(PlaceCatalog::countryFor(null));
        $this->assertNull(PlaceCatalog::withCountry(''));
    }

    public function test_a_destination_that_is_itself_a_country_is_not_repeated(): void
    {
        // "Japan, Japan" would be daft.
        $this->assertSame('Japan', PlaceCatalog::withCountry('Japan'));
        $this->assertSame('Singapore', PlaceCatalog::withCountry('Singapore'));
        $this->assertSame('Tokyo, Japan', PlaceCatalog::withCountry('Tokyo'));
        // Unknown places pass through rather than vanishing.
        $this->assertSame('Nowhereville', PlaceCatalog::withCountry('Nowhereville'));
    }

    public function test_the_profile_address_card_shows_the_country(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id'      => $user->id,
            'home_city'    => 'Toronto',
            'daily_budget' => 5000,
        ]);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Toronto')
            ->assertSee('Canada');
    }

    public function test_a_trip_card_shows_its_destinations_country(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create([
            'user_id'     => $user->id,
            'destination' => 'Tokyo',
            'trip_name'   => null,
        ]);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Tokyo, Japan');
    }
}
