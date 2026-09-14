<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\Llm;
use App\Livewire\Traveler\ProfileBuilder;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CurrencyConverterService;
use App\Support\ProfileCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TARA can collect a travel profile conversationally, as an alternative to the
 * 7-step ProfileBuilder form. The form still works and is untouched; these
 * cover the chat path producing the same result.
 */
class LlmProfileBuilderTest extends TestCase
{
    use RefreshDatabase;

    /** Stops any unfaked provider call reaching the real internet. */
    private function fakeAllHttp(array $specific = []): void
    {
        Http::fake($specific + ['*' => Http::response([], 200)]);
    }

    private function say($component, string ...$answers)
    {
        foreach ($answers as $answer) {
            $component->set('aiPrompt', $answer)->call('automateTrip');
        }
        return $component;
    }

    private function lastMessage($component): string
    {
        return collect($component->get('messages'))->last()['text'] ?? '';
    }

    private function allMessages($component): string
    {
        return collect($component->get('messages'))->pluck('text')->implode("\n");
    }

    /** Fakes the provider chain so processAiTrip() produces a package. */
    private function fakeTripPackage(array $overrides = []): void
    {
        $package = array_merge([
            'from' => 'Manila', 'to' => 'Cebu',
            'budget_min' => 30000, 'budget_max' => 30000,
            'date_from' => 'Aug 3', 'date_to' => 'Aug 10, 2026', 'days' => 8,
            'transport'     => ['from_code' => 'MNL', 'to_code' => 'CEB', 'detail' => 'Cebu Pacific', 'cost' => 3000],
            'accommodation' => ['name' => 'Hotel', 'stars' => 3, 'detail' => '7 nights', 'cost' => 15000],
            'food'          => ['name' => 'Restaurant', 'detail' => '7 days', 'cost' => 8000],
            'attractions'   => ['items' => [], 'cost' => 0],
        ], $overrides);

        Http::fake([
            'api.mistral.ai/*' => Http::response([], 200),
            'openrouter.ai/*'  => Http::response([], 200),
            'api.groq.com/*'   => Http::response([], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($package)]]]]],
            ], 200),
            '*' => Http::response([], 200),
        ]);
    }

    // ─── Offered after a trip is planned ─────────────────────────────────

    // By results time the traveller has already given the two answers a
    // profile most needs, so this is the moment the offer costs nothing.
    public function test_a_profile_is_offered_once_a_trip_package_exists(): void
    {
        $this->fakeTripPackage();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Manila to Cebu, 30000 budget, Aug 3 to Aug 10']])
            ->call('processAiTrip');

        $this->assertStringContainsString('travel profile', $this->lastMessage($component));
        $component->assertSet('aiStep', 'results');
    }

    public function test_no_profile_is_offered_when_one_already_exists(): void
    {
        $this->fakeTripPackage();
        $user = User::factory()->create(['country' => 'Philippines']);
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Manila', 'daily_budget' => 1500,
            'travel_style' => 'Solo', 'group_member_emails' => [],
            'interests' => ['Beach'], 'sub_interests' => [],
            'preferred_transportation' => 'Flight', 'preferred_accommodation' => 'Hotel',
        ]);

        $component = Livewire::actingAs(User::find($user->id))->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Manila to Cebu']])
            ->call('processAiTrip');

        $this->assertStringNotContainsString('save Manila', $this->lastMessage($component));
    }

    public function test_accepting_the_offer_carries_the_trip_answers_across(): void
    {
        $this->fakeTripPackage();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Manila to Cebu, 30000 budget']])
            ->call('processAiTrip');

        $this->say($component, 'yes');

        $component->assertSet('buildingProfile', true);
        // Home city carried over, so the first question asked is the next one.
        $this->assertStringContainsString('solo, or with a group', $this->lastMessage($component));
    }

    // Declining, or simply carrying on talking, must not be swallowed by the
    // offer — it falls through to the normal results reply.
    public function test_ignoring_the_offer_falls_through_to_normal_chat(): void
    {
        $this->fakeTripPackage();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Manila to Cebu']])
            ->call('processAiTrip');

        $this->say($component, 'this looks great');

        $component->assertSet('buildingProfile', false);
        $this->assertStringContainsString('Glad you like it', $this->lastMessage($component));
    }

    // A trip budget is in pesos; a profile budget is in the home city's own
    // currency. Carrying it across to a foreign home city would get it
    // re-converted on save and inflated by the whole exchange rate.
    public function test_a_foreign_home_city_does_not_inherit_the_peso_trip_budget(): void
    {
        $this->fakeTripPackage(['from' => 'Tokyo', 'to' => 'Cebu']);
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Tokyo to Cebu, 30000 budget']])
            ->call('processAiTrip');

        $this->say($component, 'yes');

        // Still ASKS for the budget rather than assuming the peso figure is yen.
        // A Manila home city would have inherited it and moved on to travel style.
        $this->assertStringContainsString('How much do you usually budget for a trip?', $this->lastMessage($component));
        $component->assertSet('buildingProfile', true);
    }

    // ─── Entering and leaving ────────────────────────────────────────────

    public function test_an_explicit_request_starts_the_profile_conversation(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile');

        $component->assertSet('buildingProfile', true);
        $this->assertStringContainsString('What city are you traveling from?', $this->lastMessage($component));
    }

    // Nothing may nag a traveller who only wants to plan a trip: profile mode
    // is entered on request, never automatically.
    public function test_an_ordinary_trip_message_does_not_start_the_profile_conversation(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $component->assertSet('buildingProfile', false);

        $this->say($component, 'I want to go to Cebu');
        $component->assertSet('buildingProfile', false);
    }

    public function test_cancelling_leaves_profile_mode_without_saving(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', 'cancel');

        $component->assertSet('buildingProfile', false);
        $this->assertNull(UserProfile::where('user_id', $user->id)->first());
    }

    // Profile mode must not disturb a trip already half-planned in the same
    // chat — every profile branch returns before the trip logic runs.
    public function test_building_a_profile_leaves_trip_progress_untouched(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTo', 'Boracay')
            ->set('aiBudgetMax', 30000)
            ->set('aiTravelers', 2);

        $this->say($component, 'help me set up my profile', 'Cebu City', '900', 'solo');

        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('aiBudgetMax', 30000);
        $component->assertSet('aiTravelers', 2);
    }

    // ─── The full conversation ───────────────────────────────────────────

    public function test_a_full_conversation_saves_a_complete_profile(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile',
            'Manila',
            '25000',
            'solo',
            'beach and food trip',
            'yes',
        );

        $component->assertSet('buildingProfile', false);

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('Manila', $profile->home_city);
        $this->assertSame(25000.0, $profile->daily_budget);
        $this->assertSame('Solo', $profile->travel_style);
        $this->assertSame(['Beach', 'Food Trip'], $profile->interests);

        // The three never asked about are filled in, always with values the
        // form itself offers.
        $this->assertNotEmpty($profile->sub_interests);
        $this->assertEmpty(array_diff($profile->sub_interests, array_merge(
            ProfileCatalog::INTERESTS['Beach'], ProfileCatalog::INTERESTS['Food Trip']
        )));
        $this->assertArrayHasKey($profile->preferred_transportation, ProfileCatalog::TRANSPORTATION_OPTIONS);
        $this->assertArrayHasKey($profile->preferred_accommodation, ProfileCatalog::ACCOMMODATION_OPTIONS);
    }

    // ─── The three the AI fills in ───────────────────────────────────────

    // Exactly four questions, then the review — the traveller is never asked
    // about sub-interests, transport or where they stay.
    public function test_only_four_questions_are_asked_before_the_review(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);

        $this->say($component, 'help me set up my profile');
        $this->assertStringContainsString('What city are you traveling from?', $this->lastMessage($component));

        $this->say($component, 'Manila');
        $this->assertStringContainsString('How much do you usually budget for a trip?', $this->lastMessage($component));

        $this->say($component, '25000');
        $this->assertStringContainsString('solo, or with a group', $this->lastMessage($component));

        $this->say($component, 'solo');
        $last = $this->lastMessage($component);
        $this->assertStringContainsString('What do you enjoy doing?', $last);
        // Just the question — the nine options live in the card picker the
        // button opens, not repeated as cramped text in the message.
        $this->assertStringNotContainsString('1. Beach', $last);
        $this->assertStringNotContainsString('Tell me the numbers', $last);

        $this->say($component, 'beach');
        $this->assertStringContainsString('Should I save this', $this->lastMessage($component));
        $this->assertStringContainsString('filled in the rest', $this->allMessages($component));
    }

    public function test_the_ais_choices_are_used_when_they_are_valid(): void
    {
        $this->fakeAllHttp([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'sub_interests'  => ['Snorkeling', 'Island Hopping'],
                    'transportation' => 'Flight',
                    'accommodation'  => 'Resort',
                ])]]],
            ], 200),
        ]);
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo', 'beach', 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame(['Snorkeling', 'Island Hopping'], $profile->sub_interests);
        $this->assertSame('Resort', $profile->preferred_accommodation);
    }

    // The AI must not be able to invent an option the form doesn't offer.
    public function test_an_ai_value_outside_the_catalog_is_rejected(): void
    {
        $this->fakeAllHttp([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'sub_interests'  => ['Skydiving off a volcano'],
                    'transportation' => 'Teleportation',
                    'accommodation'  => 'Treehouse',
                ])]]],
            ], 200),
        ]);
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo', 'beach', 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertNotContains('Skydiving off a volcano', $profile->sub_interests);
        $this->assertArrayHasKey($profile->preferred_transportation, ProfileCatalog::TRANSPORTATION_OPTIONS);
        $this->assertArrayHasKey($profile->preferred_accommodation, ProfileCatalog::ACCOMMODATION_OPTIONS);
    }

    // The guard that matters most: every provider down must still produce a
    // saveable profile, not a stuck conversation.
    public function test_the_profile_still_saves_when_every_provider_is_down(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '30000', 'solo', 'beach', 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('Flight', $profile->preferred_transportation);
        // Beach plus a comfortable daily budget falls back to Resort.
        $this->assertSame('Resort', $profile->preferred_accommodation);
        $this->assertNotEmpty($profile->sub_interests);
    }

    public function test_a_beach_traveller_on_a_tight_budget_falls_back_to_hotel_not_resort(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '15000', 'solo', 'beach', 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        // Above the ₱10,000 floor but below twice it, so a resort is not a
        // realistic suggestion — this is the branch that could never run while
        // the threshold sat at ₱2,000, under the floor itself.
        $this->assertSame('Hotel', $profile->preferred_accommodation);
    }

    public function test_an_auto_filled_choice_can_still_be_overridden_from_the_review(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo', 'beach');

        $this->say($component, 'change where I stay', 'Inn', 'yes');

        $this->assertSame('Inn', UserProfile::where('user_id', $user->id)->first()->preferred_accommodation);
    }

    public function test_a_budget_range_keeps_the_higher_figure(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '20000-30000', 'solo', 'beach', 'yes');

        $this->assertSame(30000.0, UserProfile::where('user_id', $user->id)->first()->daily_budget);
    }

    // ─── Individual slots ────────────────────────────────────────────────

    // The trip planner refuses anything under MINIMUM_TOTAL_BUDGET, and
    // offerSavedPreferencesIfAny() feeds the profile figure straight back into
    // it — so a budget accepted here was offered back and then rejected, after
    // the traveller had answered every other question.
    public function test_a_budget_below_the_trip_minimum_is_refused_at_the_question(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '5000');

        $this->assertStringContainsString('10,000', $this->lastMessage($component));
        $this->assertSame(0.0, (float) $component->get('profileDraft')['daily_budget']);

        // And it still accepts a workable one straight after.
        $this->say($component, '25000');
        $this->assertSame(25000.0, (float) $component->get('profileDraft')['daily_budget']);
    }

    // A foreign budget is typed in the home city's currency, so the floor has
    // to be compared in pesos — $500 is a fine trip budget even though the
    // number itself is under ₱10,000.
    public function test_a_foreign_budget_is_judged_against_the_floor_in_pesos(): void
    {
        $this->fakeAllHttp([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 58.0], 200),
        ]);
        $user = User::factory()->create(['country' => 'United States']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'New York', '500');

        // 500 USD is ~₱29,000 — accepted, and the conversation moves on.
        $this->assertSame(500.0, (float) $component->get('profileDraft')['daily_budget']);
        $this->assertStringContainsString('solo, or with a group', $this->lastMessage($component));
    }

    public function test_a_numeric_home_city_is_rejected_and_re_asked(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', '5000');

        $this->assertStringContainsString("doesn't look like a city", $this->lastMessage($component));
    }

    // The form's city field is pick-only, so it can only ever produce a city the
    // app knows. TARA takes free text, which made it the one door a made-up
    // place could walk through — and a place with no country has no currency,
    // so the budget that follows would have been stored as pesos in silence.
    public function test_a_made_up_city_is_rejected_and_re_asked(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'awdaksdjakwdwa');

        $this->assertStringContainsString("doesn't look like a city", $this->lastMessage($component));
        $this->assertSame('', $component->get('profileDraft')['home_city']);
    }

    // Refusing without saying what would work is a dead end, so the re-ask
    // carries the same three cities the form offers as "Suggested cities".
    public function test_the_city_re_ask_names_real_cities(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Nicsland');

        $this->assertStringContainsString('Manila', $this->lastMessage($component));
    }

    public function test_a_known_city_is_accepted_whatever_the_casing(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'manila');

        // Stored the way the form would have stored it, so a profile built in
        // chat and one built in the form read identically on the Profile page.
        $this->assertSame('Manila', $component->get('profileDraft')['home_city']);
        $this->assertStringContainsString('How much do you usually budget for a trip?', $this->lastMessage($component));
    }

    // Guards against the gate being drawn too tight: the catalogue is not just
    // Philippine cities, and someone living abroad must still get through.
    public function test_a_foreign_home_city_is_accepted(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Tokyo');

        $this->assertSame('Tokyo', $component->get('profileDraft')['home_city']);
        $this->assertStringContainsString('How much do you usually budget for a trip?', $this->lastMessage($component));
    }

    // The trip offer pre-fills the home city from the trip's origin, which
    // skips the typed path entirely. An origin we can't place has to leave the
    // slot empty and be asked for — a filled slot is never revisited.
    public function test_a_trip_origin_we_cannot_place_is_still_asked_for(): void
    {
        $this->fakeTripPackage(['from' => 'Nicsland', 'to' => 'Cebu']);
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Nicsland to Cebu, 30000 budget']])
            ->call('processAiTrip');

        $this->say($component, 'yes');

        $component->assertSet('buildingProfile', true);
        $this->assertSame('', $component->get('profileDraft')['home_city']);
        $this->assertStringContainsString('What city are you traveling from?', $this->lastMessage($component));
    }

    // The question itself carries no currency hint any more, but the figure is
    // still stored in the home city's own currency — so the review summary is
    // where that gets confirmed, before anything is saved.
    public function test_the_review_shows_the_home_citys_own_currency_symbol(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Tokyo');

        // Asked plainly, with no "(per day, in ¥)" tacked on.
        $this->assertStringContainsString('How much do you usually budget for a trip?', $this->lastMessage($component));
        $this->assertStringNotContainsString('per day', $this->lastMessage($component));

        $this->say($component, '50000', 'solo', 'beach');

        $this->assertStringContainsString('¥', $this->lastMessage($component));
    }

    public function test_a_k_shorthand_budget_expands_to_thousands(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Manila', '25k', 'solo', 'beach', 'yes');

        $this->assertSame(25000.0, UserProfile::where('user_id', $user->id)->first()->daily_budget);
    }

    public function test_travelling_with_friends_asks_for_companion_emails(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'with my friends');

        $this->assertStringContainsString('email', strtolower($this->lastMessage($component)));
    }

    public function test_group_style_requires_at_least_one_valid_email(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'group', 'not-an-email');

        $this->assertStringContainsString('email', strtolower($this->lastMessage($component)));
        $component->assertSet('buildingProfile', true);
    }

    public function test_group_emails_are_parsed_deduplicated_and_saved(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Manila', '25000', 'group',
            'a@example.com, b@example.com, a@example.com',
            'beach', 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame('Group', $profile->travel_style);
        $this->assertSame(['a@example.com', 'b@example.com'], $profile->group_member_emails);
    }

    public function test_interests_can_be_picked_by_number(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Manila', '25000', 'solo', '1, 3', 'yes');

        // 1 = Beach, 3 = Food Trip, in ProfileCatalog::INTERESTS order.
        $this->assertSame(['Beach', 'Food Trip'], UserProfile::where('user_id', $user->id)->first()->interests);
    }

    // Free text that matches no category directly falls back to one AI call
    // constrained to the fixed list, rather than a hand-rolled synonym table.
    public function test_unmatched_interest_text_falls_back_to_the_ai(): void
    {
        $this->fakeAllHttp([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['interests' => ['Relaxation']])]]],
            ], 200),
        ]);
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Manila', '25000', 'solo', 'somewhere quiet to unwind', 'yes');

        $this->assertSame(['Relaxation'], UserProfile::where('user_id', $user->id)->first()->interests);
    }

    // Re-picking interests from the review replaces the sub-interests too, so
    // they always belong to the categories actually chosen.
    public function test_changing_interests_refreshes_the_auto_filled_sub_interests(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo', 'beach');

        $this->say($component, 'change my interests', 'museums', 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame(['Museums'], $profile->interests);
        $this->assertNotEmpty($profile->sub_interests);
        $this->assertEmpty(array_diff($profile->sub_interests, ProfileCatalog::INTERESTS['Museums']));
    }

    // ─── Handing off to the form's card picker and back ──────────────────

    public function test_the_picker_button_shows_only_while_interests_are_being_asked(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);

        // Not offered before the conversation reaches interests.
        $this->say($component, 'help me set up my profile', 'Manila');
        $this->assertStringNotContainsString('pickInterestsInForm', $component->html());

        $this->say($component, '25000', 'solo');
        $this->assertStringContainsString('pickInterestsInForm', $component->html());
    }

    public function test_the_picker_button_sends_the_traveller_to_the_forms_interests_step(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo');

        $component->call('pickInterestsInForm')
            ->assertRedirect(route('profile.setup', ['step' => 4, 'return' => 'trips.plan.ai']));

        $this->assertTrue(session('tara_interests_return'));
    }

    // The guard on this whole design: a traveller mid-conversation has no
    // UserProfile yet, so without the draft overlay the form would save an
    // empty home city and a zero budget over the answers they just gave.
    public function test_the_form_prefills_from_the_conversation_draft(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo');
        $component->call('pickInterestsInForm');

        $this->assertNull(UserProfile::where('user_id', $user->id)->first(), 'guard: nothing saved yet');

        $form = Livewire::actingAs(User::find($user->id))->test(ProfileBuilder::class);

        $form->assertSet('fromTara', true);
        $form->assertSet('step', 4);
        $form->assertSet('homeCity', 'Manila');
        $form->assertSet('dailyBudget', 25000.0);
        $form->assertSet('travelStyle', 'Solo');
    }

    public function test_saving_from_the_picker_keeps_the_answers_given_in_chat(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo');
        $component->call('pickInterestsInForm');

        Livewire::actingAs(User::find($user->id))->test(ProfileBuilder::class)
            ->set('selectedInterests', ['Beach', 'Food Trip'])
            ->call('saveAndReturn')
            ->assertRedirect(route('trips.plan.ai'));

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('Manila', $profile->home_city);   // not ''
        $this->assertSame(25000.0, $profile->daily_budget); // not 0
        $this->assertSame('Solo', $profile->travel_style);
        $this->assertSame(['Beach', 'Food Trip'], $profile->interests);
    }

    public function test_returning_from_the_picker_lands_on_the_review(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo');
        $component->call('pickInterestsInForm');

        Livewire::actingAs(User::find($user->id))->test(ProfileBuilder::class)
            ->set('selectedInterests', ['Beach', 'Food Trip'])
            ->call('saveAndReturn');

        // Re-mounting is what a real redirect back to the planner does.
        $back = Livewire::actingAs(User::find($user->id))->test(Llm::class);

        $last = $this->lastMessage($back);
        $this->assertStringContainsString('Should I save this', $last);
        $this->assertStringContainsString('Beach', $last);

        $this->say($back, 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertArrayHasKey($profile->preferred_accommodation, ProfileCatalog::ACCOMMODATION_OPTIONS);
        $this->assertSame('Flight', $profile->preferred_transportation);
    }

    // mount() is allowed to run more than once for one arrival — a navigate
    // prefetch, a refresh mid-flight — and dehydrate() persists both the
    // messages and the draft on every render. So the resumption has to be a
    // no-op the second time rather than replaying against advanced state,
    // which posted the review twice with nothing in between.
    public function test_returning_from_the_picker_posts_the_review_only_once(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo');
        $component->call('pickInterestsInForm');

        Livewire::actingAs(User::find($user->id))->test(ProfileBuilder::class)
            ->set('selectedInterests', ['Beach', 'Food Trip'])
            ->call('saveAndReturn');

        // Two renders of the one arrival, the flag still set on both.
        Livewire::actingAs(User::find($user->id))->test(Llm::class);

        session(['tara_interests_picked' => true]);
        $back = Livewire::actingAs(User::find($user->id))->test(Llm::class);

        $this->assertSame(
            1,
            substr_count($this->allMessages($back), "Here's your travel profile"),
            'the review must be posted once, however many times mount() runs'
        );
    }

    // Opening the form the ordinary way must be completely unaffected.
    public function test_the_form_opened_normally_is_not_treated_as_a_handoff(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $form = Livewire::actingAs($user)->test(ProfileBuilder::class);

        $form->assertSet('fromTara', false);
        $form->assertSet('step', 1);
        $form->assertSet('homeCity', '');
    }

    // ─── The join back into trip planning ────────────────────────────────

    // The saved-preferences offer only fires on a draft-less mount, so the one
    // trip it never applied to was the one planned right after the profile was
    // built — the moment the traveller has just said both answers out loud.
    public function test_the_trip_uses_the_profile_saved_moments_earlier(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '30000', 'solo', 'beach', 'yes');

        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('aiBudgetMax', 30000);
        $this->assertStringContainsString('Manila', $this->lastMessage($component));

        // Origin and budget are already known, so the next gap is travellers.
        $this->say($component, 'I want to go to Cebu');
        $this->assertStringContainsString('How many people', $this->lastMessage($component));
    }

    // Someone who was already mid-trip when they built the profile must keep
    // what they typed — the profile fills gaps, it does not overrule answers.
    public function test_seeding_never_overwrites_a_trip_already_under_way(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Davao')
            ->set('aiBudgetMin', 80000)
            ->set('aiBudgetMax', 80000);

        $this->say($component, 'help me set up my profile', 'Manila', '30000', 'solo', 'beach', 'yes');

        $component->assertSet('aiFrom', 'Davao');
        $component->assertSet('aiBudgetMax', 80000);
    }

    // ─── Editing from the review ─────────────────────────────────────────

    // "change where I stay from hotel to resort" is an accommodation edit, but
    // the home-city pattern also matches the bare word "from" — so it has to be
    // the last resort, not the first test.
    public function test_an_edit_is_routed_by_its_own_keyword_not_the_word_from(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo', 'beach');

        $this->say($component, 'change where I stay from hotel to resort');
        $this->assertStringContainsString('where do you like to stay', $this->lastMessage($component));

        // "from" on its own still means the home city, with nothing more specific.
        $this->say($component, 'Resort', 'change where I travel from');
        $this->assertStringContainsString('What city are you traveling from?', $this->lastMessage($component));
    }

    // The confirmation used to match "yes" at the front and save immediately,
    // dropping the rest of the sentence — so the change was silently ignored.
    public function test_yes_carrying_an_edit_request_changes_instead_of_saving(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'help me set up my profile', 'Manila', '25000', 'solo', 'beach');

        $this->say($component, 'yes but change my budget');

        $this->assertStringContainsString('How much do you usually budget for a trip?', $this->lastMessage($component));
        $this->assertNull(UserProfile::where('user_id', $user->id)->first(), 'nothing saved yet');

        // A plain acceptance must still save.
        $this->say($component, '30000', 'yes');
        $this->assertNotNull(UserProfile::where('user_id', $user->id)->first());
    }

    // ─── Currency, through the shared saver ──────────────────────────────

    public function test_a_foreign_home_city_budget_is_converted_and_the_original_preserved(): void
    {
        $this->fakeAllHttp([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.38], 200),
        ]);
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Tokyo', '50000', 'solo', 'beach', 'yes');

        $profile = UserProfile::where('user_id', $user->id)->first();
        $this->assertSame(19000.0, $profile->daily_budget);
        $this->assertSame('JPY', $profile->daily_budget_currency);
        $this->assertSame(50000.0, $profile->daily_budget_local);
    }

    // The refuse-rather-than-guess rule reaches the chat: nothing is written,
    // the traveller is told, and the answers already given are not lost.
    public function test_the_save_is_refused_when_no_rate_is_available_and_can_be_retried(): void
    {
        $this->fakeAllHttp([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Tokyo', '50000', 'solo', 'beach', 'yes');

        $this->assertNull(UserProfile::where('user_id', $user->id)->first());
        $this->assertStringContainsString('retry', strtolower($this->lastMessage($component)));
        // Still in profile mode with everything collected, ready to try again.
        $component->assertSet('buildingProfile', true);
    }

    // ─── Editing an existing profile ─────────────────────────────────────

    public function test_an_existing_profile_is_prefilled_and_goes_straight_to_review(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Cebu City', 'daily_budget' => 2000,
            'travel_style' => 'Solo', 'group_member_emails' => [],
            'interests' => ['Nature'], 'sub_interests' => ['Waterfalls'],
            'preferred_transportation' => 'Flight', 'preferred_accommodation' => 'Inn',
        ]);

        $component = Livewire::actingAs(User::find($user->id))->test(Llm::class);
        $this->say($component, 'update my profile');

        $last = $this->lastMessage($component);
        $this->assertStringContainsString('Cebu City', $last);
        $this->assertStringContainsString('Should I save this', $last);
    }

    // Mirrors ProfileBuilder's own rule: the editable figure is the amount as
    // typed, never the already-converted peso ledger value.
    public function test_editing_prefills_the_local_budget_not_the_peso_ledger_value(): void
    {
        $this->fakeAllHttp([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'CAD/PHP', 'rate' => 44.49445], 200),
        ]);
        $user = User::factory()->create(['country' => 'Canada']);
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Vancouver',
            'daily_budget' => 22247.23, 'daily_budget_currency' => 'CAD', 'daily_budget_local' => 500,
            'travel_style' => 'Solo', 'group_member_emails' => [],
            'interests' => ['Nature'], 'sub_interests' => [],
            'preferred_transportation' => 'Flight', 'preferred_accommodation' => 'Hotel',
        ]);

        $component = Livewire::actingAs(User::find($user->id))->test(Llm::class);
        $this->say($component, 'update my profile');

        // C$500 as typed, not the ₱22,247 ledger figure.
        $this->assertStringContainsString('500', $this->lastMessage($component));
        $this->assertStringNotContainsString('22,247', $this->lastMessage($component));
    }

    public function test_the_deep_link_jumps_straight_to_the_interests_question(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->call('startProfileConversation', 'interests');

        $component->assertSet('buildingProfile', true);
        $this->assertStringContainsString('What do you enjoy doing?', $this->lastMessage($component));
    }

    // The welcome screen only renders while there are no messages, so it is now
    // reached by someone whose profile exists but holds nothing worth offering
    // back (no home city, no budget). Its form link stays the only profile link
    // there — the conversational builder is reached by asking, not by a button.
    public function test_the_welcome_screen_offers_only_the_form_link(): void
    {
        $this->fakeAllHttp();

        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => '', 'daily_budget' => 0,
            'travel_style' => 'Solo', 'group_member_emails' => [],
            'interests' => ['Beach'], 'sub_interests' => [],
            'preferred_transportation' => 'Flight', 'preferred_accommodation' => 'Hotel',
        ]);

        $html = Livewire::actingAs(User::find($user->id))->test(Llm::class)->html();

        $this->assertStringContainsString('profile/setup', $html);
        $this->assertStringNotContainsString('startProfileConversation', $html);
    }

    // A traveller with no profile is greeted by the offer instead of the
    // welcome screen — worth pinning down, since it changes what a brand new
    // user sees first.
    public function test_a_profile_less_traveller_is_greeted_by_the_offer(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);

        $this->assertStringContainsString("set up your travel profile", $this->lastMessage($component));
    }

    public function test_a_bare_yes_starts_the_profile_conversation(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'yes');

        $component->assertSet('buildingProfile', true);
        $this->assertStringContainsString('What city are you traveling from?', $this->lastMessage($component));
    }

    // The exact bug that made this feature fail 45 tests on the first attempt:
    // a yes-pattern that merely STARTED with an affirmative read "please
    // continue" as acceptance and hijacked the trip conversation.
    public function test_please_continue_does_not_accept_the_offer(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'please continue');

        $component->assertSet('buildingProfile', false);
    }

    public function test_declining_the_arrival_offer_returns_to_trip_talk(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component, 'no');

        $component->assertSet('buildingProfile', false);
        $this->assertStringContainsString('tell me about the trip', $this->lastMessage($component));
    }

    public function test_no_arrival_offer_when_a_profile_already_exists(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Manila', 'daily_budget' => 1500,
            'travel_style' => 'Solo', 'group_member_emails' => [],
            'interests' => ['Beach'], 'sub_interests' => [],
            'preferred_transportation' => 'Flight', 'preferred_accommodation' => 'Hotel',
        ]);

        $component = Livewire::actingAs(User::find($user->id))->test(Llm::class);

        $this->assertStringNotContainsString("set up your travel profile", $this->allMessages($component));
    }

    public function test_a_named_slot_can_be_changed_from_the_review(): void
    {
        $this->fakeAllHttp();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Manila', '25000', 'solo', 'beach');

        $this->say($component, 'change my budget', '30000', 'yes');

        $this->assertSame(30000.0, UserProfile::where('user_id', $user->id)->first()->daily_budget);
    }

    // ─── Equivalence with the form ───────────────────────────────────────

    // The guard against the two save paths drifting. Compares only what the
    // traveller actually answers — the AI-filled fields are chosen, not given,
    // so the chat legitimately differs there and comparing them would just
    // assert the fallback's taste. The currency columns are the real point:
    // both paths run through UserProfileSaver, and this proves it.
    public function test_the_chat_and_the_form_store_answered_fields_identically(): void
    {
        $this->fakeAllHttp();

        $viaForm = User::factory()->create(['country' => 'Philippines']);
        Livewire::actingAs($viaForm)->test(ProfileBuilder::class)
            ->set('homeCity', 'Manila')
            ->set('dailyBudget', 25000)
            ->set('travelStyle', 'Solo')
            ->set('selectedInterests', ['Beach', 'Food Trip'])
            ->set('selectedSubInterests', [])
            ->set('preferredTransportation', 'Flight')
            ->set('preferredAccommodation', 'Hotel')
            ->call('confirmProfile');

        $viaChat = User::factory()->create(['country' => 'Philippines']);
        $component = Livewire::actingAs($viaChat)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Manila', '25000', 'solo', 'beach and food trip', 'yes');

        $answered = fn (UserProfile $p) => [
            $p->home_city, $p->daily_budget, $p->daily_budget_currency, $p->daily_budget_local,
            $p->travel_style, $p->group_member_emails, $p->interests,
        ];

        $this->assertSame(
            $answered(UserProfile::where('user_id', $viaForm->id)->first()),
            $answered(UserProfile::where('user_id', $viaChat->id)->first()),
        );
    }

    // Same guard for the path that actually diverges most: a foreign home city
    // must convert identically whichever way the profile was built.
    public function test_the_chat_and_the_form_convert_a_foreign_budget_identically(): void
    {
        $this->fakeAllHttp([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.38], 200),
        ]);

        $viaForm = User::factory()->create(['country' => 'Japan']);
        Livewire::actingAs($viaForm)->test(ProfileBuilder::class)
            ->set('homeCity', 'Tokyo')
            ->set('dailyBudget', 50000)
            ->set('travelStyle', 'Solo')
            ->set('selectedInterests', ['Beach'])
            ->set('preferredTransportation', 'Flight')
            ->set('preferredAccommodation', 'Hotel')
            ->call('confirmProfile');

        $viaChat = User::factory()->create(['country' => 'Japan']);
        $component = Livewire::actingAs($viaChat)->test(Llm::class);
        $this->say($component,
            'help me set up my profile', 'Tokyo', '50000', 'solo', 'beach', 'yes');

        $money = fn (UserProfile $p) => [
            $p->daily_budget, $p->daily_budget_currency, $p->daily_budget_local,
        ];

        $this->assertSame(
            $money(UserProfile::where('user_id', $viaForm->id)->first()),
            $money(UserProfile::where('user_id', $viaChat->id)->first()),
        );
    }
}
