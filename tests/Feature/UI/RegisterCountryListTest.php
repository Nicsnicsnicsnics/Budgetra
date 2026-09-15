<?php
namespace Tests\Feature\UI;

use Tests\TestCase;

/**
 * The country dropdown on Create Account.
 *
 * It was a hand-written copy of the 29 keys in config/country_cities.php, in
 * that config's region-grouped order — so it read as unsorted, and the two
 * lists could drift until a traveler picked a home country the Profile Builder
 * had no cities for. It now reads the config and sorts it.
 */
class RegisterCountryListTest extends TestCase
{
    /** @return string[] the option labels, in the order rendered */
    private function renderedCountries(): array
    {
        $html = $this->get('/register')->assertStatus(200)->getContent();

        preg_match('/<select name="country".*?<\/select>/s', $html, $select);
        $this->assertNotEmpty($select, 'country select not found');

        preg_match_all('/<option value="([^"]+)"[^>]*>([^<]*)<\/option>/', $select[0], $m);

        return array_map('trim', $m[2]);
    }

    public function test_the_countries_are_in_alphabetical_order(): void
    {
        $rendered = $this->renderedCountries();
        $sorted   = $rendered;
        sort($sorted);

        $this->assertSame($sorted, $rendered);
    }

    public function test_every_country_comes_from_the_one_list(): void
    {
        // Not a second hand-maintained copy: a country offered here must be one
        // the Profile Builder can then offer home cities for.
        $expected = array_keys(config('country_cities'));
        sort($expected);

        $this->assertSame($expected, $this->renderedCountries());
    }

    public function test_adding_a_country_to_the_config_places_it_by_name(): void
    {
        // The config is grouped by region, so a new entry lands at the end of
        // its group. It has to find its own place in the dropdown.
        config(['country_cities.Iceland' => ['Reykjavik']]);

        $rendered = $this->renderedCountries();
        $iceland  = array_search('Iceland', $rendered, true);

        $this->assertNotFalse($iceland, 'Iceland never reached the dropdown');
        $this->assertSame('India', $rendered[$iceland + 1]);
        $this->assertSame('Germany', $rendered[$iceland - 1]);
    }

    public function test_the_placeholder_option_stays_on_top_and_unselectable(): void
    {
        $html = $this->get('/register')->getContent();

        // It has no value, so the sorted list above must not have displaced it.
        $this->assertStringContainsString(
            '<option value="" disabled selected>Select your country</option>',
            $html
        );
    }

    public function test_the_labels_carry_no_leftover_flag_spacing(): void
    {
        // Each option used to render "{{ $flag }} {{ $name }}" against a map
        // whose flag values had all been emptied, leaving every label with a
        // leading space.
        $html = $this->get('/register')->getContent();

        $this->assertStringNotContainsString('> Philippines<', $html);
        $this->assertStringContainsString('>Philippines</option>', $html);
    }
}
