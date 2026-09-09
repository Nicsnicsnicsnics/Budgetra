<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\Llm;
use App\Models\AiConversationDraft;
use App\Models\AiConversationHistory;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class LlmTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGeminiPackage(array $overrides = []): void
    {
        $package = array_merge([
            'from'           => 'Manila',
            'to'             => 'Cebu',
            'budget_min'     => 20000,
            'budget_max'     => 30000,
            'date_from'      => 'Jan 1',
            'date_to'        => 'Jan 8, 2024',
            'days'           => 7,
            'transport'      => ['from_code' => 'MNL', 'to_code' => 'CEB', 'detail' => 'Cebu Pacific', 'cost' => 3000],
            'accommodation'  => ['name' => 'Hotel', 'stars' => 4, 'detail' => '7 nights', 'cost' => 15000],
            'food'           => ['name' => 'Restaurant', 'detail' => '7 days', 'cost' => 8000],
            'attractions'    => ['items' => [], 'cost' => 0],
            'total'          => 26000,
            'budget'         => 30000,
            'pct'            => 86,
        ], $overrides);

        Http::fake([
            'api.mistral.ai/*'                    => Http::response([], 200),
            'openrouter.ai/*'                     => Http::response([], 200),
            'api.groq.com/*'                      => Http::response([], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($package)]]]]],
            ], 200),
        ]);
    }

    private function fakeExtraction(array $overrides = []): void
    {
        $data = array_merge([
            'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
            'origin' => null, 'destination' => null, 'travelers' => null,
            'budget_min' => null, 'budget_max' => null, 'date_from' => null, 'date_to' => null,
        ], $overrides);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($data)]]],
            ], 200),
        ]);
    }

    public function test_processAiTrip_rejects_a_past_date_from_the_ai_package(): void
    {
        $user = User::factory()->create();
        $this->fakeGeminiPackage(['date_from' => 'Jan 1', 'date_to' => 'Jan 8, 2024']);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Cebu, 1 week, 30000 budget']])
            ->call('processAiTrip')
            ->assertSet('aiDateFrom', '');
    }

    public function test_processAiTrip_accepts_a_valid_future_date(): void
    {
        $user   = User::factory()->create();
        $future = now()->addWeek();
        $this->fakeGeminiPackage([
            'date_from' => $future->format('M j'),
            'date_to'   => $future->copy()->addDays(6)->format('M j, Y'),
        ]);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Cebu, 1 week, 30000 budget']])
            ->call('processAiTrip')
            ->assertSet('aiDateFrom', $future->format('M j'));
    }

    public function test_mount_seeds_currency_from_the_users_own_country(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->assertSet('aiCurrency', 'JPY');
    }

    public function test_mount_still_defaults_a_philippines_user_to_pesos(): void
    {
        $user = User::factory()->create(['country' => 'Philippines']);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->assertSet('aiCurrency', 'PHP');
    }

    public function test_an_explicit_currency_overrides_the_country_default(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $component->assertSet('aiCurrency', 'JPY');

        $component->set('aiPrompt', '$500')->call('automateTrip');

        $component->assertSet('aiCurrency', 'USD');
    }

    public function test_reset_conversation_restores_the_users_own_currency(): void
    {
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class);
        $component->assertSet('aiCurrency', 'JPY');

        $component->set('aiCurrency', 'USD')
            ->set('aiPrompt', '/reset')->call('automateTrip');

        $component->assertSet('aiCurrency', 'JPY');
    }

    public function test_processAiTrip_derives_transport_codes_from_resolved_cities_not_the_ais_own(): void
    {
        $user = User::factory()->create();
        $this->fakeGeminiPackage([
            'from'      => 'Cebu City',
            'to'        => 'Davao',
            'transport' => ['from_code' => 'MNL', 'to_code' => 'MNL', 'detail' => 'Cebu Pacific', 'cost' => 3000],
        ]);

        $component = Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Cebu City to Davao, 30000 budget, Aug 3 to Aug 10']])
            ->call('processAiTrip');

        $component->assertSet('aiFrom', 'Cebu City');
        $this->assertSame('CEB', $component->get('aiPackage')['transport']['from_code']);
        $this->assertSame('DVO', $component->get('aiPackage')['transport']['to_code']);
    }

    public function test_conversation_summary_sent_to_the_planner_labels_origin_and_destination(): void
    {
        $user = User::factory()->create();
        $from = 'Cebu City';
        $to   = 'Japan';
        $this->fakeGeminiPackage(['from' => $from, 'to' => $to]);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [
                ['role' => 'user', 'text' => 'I want to travel in japan'],
                ['role' => 'user', 'text' => 'Cebu city'],
                ['role' => 'user', 'text' => 'Only Solo'],
                ['role' => 'user', 'text' => '75000'],
                ['role' => 'user', 'text' => 'give me 1week'],
            ])
            ->set('aiFrom', $from)
            ->set('aiTo', $to)
            ->call('processAiTrip');

        Http::assertSent(function ($request) use ($from, $to) {
            if (!str_contains($request->url(), 'generativelanguage.googleapis.com')) {
                return false;
            }
            $body = json_encode($request->data());
            return str_contains($body, "Destination city: {$to}")
                && str_contains($body, "Origin city: {$from}");
        });
    }

    public function test_proceeding_to_the_wizard_archives_the_conversation_into_history(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [
                ['role' => 'user', 'text' => 'Cebu to Davao, 30000 budget'],
                ['role' => 'assistant', 'text' => 'Great, when would you like to travel?'],
            ])
            ->set('aiFrom', 'Cebu')
            ->set('aiTo', 'Davao')
            ->set('aiBudgetMin', 30000)
            ->set('aiBudgetMax', 30000)
            ->set('aiDateFrom', 'Aug 3')
            ->set('aiDateTo', 'Aug 10, 2026')
            ->set('aiDays', 8)
            ->set('aiPackage', ['transport' => ['from_code' => 'CEB', 'to_code' => 'DVO', 'cost' => 3000]])
            ->call('proceedToWizardItinerary');

        $this->assertDatabaseHas('ai_conversation_histories', [
            'user_id' => $user->id,
            'ai_from' => 'Cebu',
            'ai_to'   => 'Davao',
        ]);
        $saved = AiConversationHistory::where('user_id', $user->id)->first();
        $this->assertCount(2, $saved->messages);
    }

    public function test_proceeding_to_the_wizard_keeps_a_new_year_trip_in_the_right_years(): void
    {
        $user     = User::factory()->create();
        $thisYear = (int) date('Y');
        $nextYear = $thisYear + 1;

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Manila to Baguio for New Year']])
            ->set('aiFrom', 'Manila')
            ->set('aiTo', 'Baguio')
            ->set('aiDateFrom', 'Dec 28')
            ->set('aiDateTo', "Jan 3, {$nextYear}")
            ->set('aiDays', 7)
            ->set('aiPackage', ['transport' => ['from_code' => 'MNL', 'to_code' => 'BAG', 'cost' => 3000]])
            ->call('proceedToWizardItinerary');

        $handoff = session('wizard_ai_handoff');

        $this->assertSame("{$thisYear}-12-28", $handoff['start']);
        $this->assertSame("{$nextYear}-01-03", $handoff['end']);
        $this->assertTrue(
            strtotime($handoff['start']) < strtotime($handoff['end']),
            'The trip start must fall before its end.'
        );
    }

    public function test_proceeding_to_the_wizard_archives_the_currency_it_was_planned_in(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'I have $2000 for Tokyo']])
            ->set('aiFrom', 'Manila')
            ->set('aiTo', 'Tokyo')
            ->set('aiBudgetMin', 112000)
            ->set('aiBudgetMax', 112000)
            ->set('aiCurrency', 'USD')
            ->set('aiDateFrom', 'Aug 3')
            ->set('aiDateTo', 'Aug 10, 2026')
            ->set('aiDays', 8)
            ->set('aiPackage', ['transport' => ['from_code' => 'MNL', 'to_code' => 'HND', 'cost' => 30000]])
            ->call('proceedToWizardItinerary');

        $this->assertDatabaseHas('ai_conversation_histories', [
            'user_id'     => $user->id,
            'ai_to'       => 'Tokyo',
            'ai_currency' => 'USD',
        ]);
    }

    public function test_history_panel_lists_past_conversations_newest_first(): void
    {
        $user = User::factory()->create();
        $older = AiConversationHistory::create(['user_id' => $user->id, 'messages' => [], 'ai_from' => 'Manila', 'ai_to' => 'Boracay']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        AiConversationHistory::create(['user_id' => $user->id, 'messages' => [], 'ai_from' => 'Manila', 'ai_to' => 'Palawan']);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->call('openHistory')
            ->assertSet('showHistory', true)
            ->assertSeeInOrder(['Palawan', 'Boracay']);
    }

    public function test_viewing_a_history_entry_shows_its_transcript(): void
    {
        $user  = User::factory()->create();
        $entry = AiConversationHistory::create([
            'user_id'  => $user->id,
            'messages' => [['role' => 'user', 'text' => 'Take me to Siargao next month']],
            'ai_from'  => 'Manila',
            'ai_to'    => 'Siargao',
        ]);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->call('viewHistoryEntry', $entry->id)
            ->assertSee('Take me to Siargao next month');
    }

    public function test_cannot_view_another_users_history_entry_by_tampering_with_the_id(): void
    {
        $owner    = User::factory()->create();
        $attacker = User::factory()->create();
        $entry    = AiConversationHistory::create([
            'user_id'  => $owner->id,
            'messages' => [['role' => 'user', 'text' => "Owner's private trip idea"]],
            'ai_from'  => 'Manila',
            'ai_to'    => 'Boracay',
        ]);

        Livewire::actingAs($attacker)
            ->test(Llm::class)
            ->set('showHistory', true)
            ->set('viewingHistoryId', $entry->id)
            ->assertDontSee("Owner's private trip idea");
    }

    private function withAllSlotsFilled(\Livewire\Features\SupportTesting\Testable $component): \Livewire\Features\SupportTesting\Testable
    {
        return $component
            ->set('aiFrom', 'Manila')
            ->set('aiTo', 'Boracay')
            ->set('aiTravelers', 2)
            ->set('aiBudgetMin', 30000)
            ->set('aiBudgetMax', 30000)
            ->set('aiDateFrom', 'Aug 3')
            ->set('aiDateTo', 'Aug 10, 2026');
    }

    public function test_confirmation_summary_shown_once_all_slots_are_filled(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('aiStep', '');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertSame('assistant', $lastMessage['role']);
        $this->assertStringContainsString('Manila', $lastMessage['text']);
        $this->assertStringContainsString('Boracay', $lastMessage['text']);
        $this->assertStringContainsString('Would you like me to proceed with this plan?', $lastMessage['text']);
    }

    public function test_confirming_the_plan_starts_generation(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'yes')->call('automateTrip');

        $component->assertSet('awaitingSlot', '');
        $component->assertSet('aiStep', 'loading');
    }

    public function test_declining_the_plan_does_not_start_generation(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'no')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('aiStep', '');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('/reset', $lastMessage['text']);
    }

    public function test_asking_a_status_question_during_confirmation_answers_it_without_getting_stuck(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', "what's my budget?")->call('automateTrip');

        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('pendingEditSlot', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('30,000', $lastMessage);
        $this->assertStringNotContainsString("What's your budget", $lastMessage);

        $component->set('aiPrompt', 'yes')->call('automateTrip');
        $component->assertSet('awaitingSlot', '');
        $component->assertSet('aiStep', 'loading');
    }

    public function test_editing_destination_during_confirmation_updates_just_that_slot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you change my destination to bohol')->call('automateTrip');

        $component->assertSet('aiTo', 'Bohol');
        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('aiBudgetMax', 30000);
        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('aiStep', '');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('Bohol', $lastMessage['text']);
    }

    public function test_editing_budget_during_confirmation_updates_just_that_slot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 50000')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 50000);
        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_budget_during_confirmation_to_a_foreign_currency_amount_is_accepted(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to $500')->call('automateTrip');

        $component->assertSet('aiCurrency', 'USD');
        $component->assertSet('aiBudgetMin', 30855);
        $component->assertSet('aiBudgetMax', 30855);
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_budget_to_a_plain_number_after_a_foreign_currency_resets_to_pesos(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to $500')->call('automateTrip');
        $component->assertSet('aiCurrency', 'USD');

        $component->set('aiPrompt', 'change my budget to 40000')->call('automateTrip');

        $component->assertSet('aiCurrency', 'PHP');
        $component->assertSet('aiBudgetMax', 40000);
    }

    public function test_editing_budget_with_a_glued_php_suffix_still_expands_to_thousands(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 50kphp')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 50000);
    }

    public function test_a_budget_edited_to_a_foreign_currency_is_archived_with_that_currency(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to $500')->call('automateTrip');
        $component->set('aiPackage', ['transport' => ['from_code' => 'MNL', 'to_code' => 'MPH', 'cost' => 3000]])
            ->call('proceedToWizardItinerary');

        $this->assertDatabaseHas('ai_conversation_histories', [
            'user_id'       => $user->id,
            'ai_currency'   => 'USD',
            'ai_budget_max' => 30855,
        ]);
    }

    public function test_a_budget_written_with_a_k_suffix_expands_to_thousands(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 100k')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 100000);
        $component->assertSet('aiBudgetMax', 100000);
    }

    public function test_a_k_suffix_budget_is_case_insensitive(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 50K')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 50000);
    }

    public function test_budgets_without_a_k_suffix_are_parsed_literally(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 100,000')->call('automateTrip');
        $component->assertSet('aiBudgetMax', 100000);

        $component->set('aiPrompt', 'change my budget to 45000')->call('automateTrip');
        $component->assertSet('aiBudgetMax', 45000);
    }

    public function test_a_budget_with_a_glued_currency_suffix_still_expands_to_thousands(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '75kphp')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMin', 75000);
        $component->assertSet('aiBudgetMax', 75000);
    }

    public function test_a_budget_with_a_glued_currency_suffix_does_not_trigger_the_too_low_gate(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTo', 'Boracay')
            ->set('aiTravelers', 2)
            ->set('aiDateFrom', 'Aug 3')
            ->set('aiDateTo', 'Aug 10, 2026')
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '75kphp')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMax', 75000);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('too low', $lastMessage);
    }

    public function test_a_realistic_budget_is_not_called_tight_against_a_whole_trip_profile_figure(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Cebu City', 'daily_budget' => 25000,
        ]);

        $component = Livewire::actingAs(User::find($user->id))->test(Llm::class)
            ->set('aiTo', 'Palawan')
            ->set('aiBudgetMax', 30000)
            ->set('aiDays', 5)
            ->set('aiPrompt', 'is that enough?')
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('should be enough', $lastMessage);
        $this->assertStringNotContainsString('might be tight', $lastMessage);
    }

    public function test_a_budget_with_a_spaced_currency_suffix_still_works(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '75k php')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMax', 75000);
    }

    public function test_editing_travelers_during_confirmation_updates_just_that_slot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my travelers to 4')->call('automateTrip');

        $component->assertSet('aiTravelers', 4);
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_dates_during_confirmation_updates_just_that_slot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change dates to September 15')->call('automateTrip');

        $component->assertSet('aiDateFrom', 'Sep 15');
        $component->assertSet('aiDateTo', 'Sep 20, 2026');
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got it, updated!', $lastMessage);
    }

    public function test_editing_dates_recognizes_the_sept_abbreviation(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change dates to Sept 15')->call('automateTrip');

        $component->assertSet('aiDateFrom', 'Sep 15');
        $component->assertSet('aiDateTo', 'Sep 20, 2026');
    }

    public function test_editing_dates_with_a_range_updates_in_one_shot(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change dates to August 20 to 25')->call('automateTrip');

        $component->assertSet('aiDateFrom', 'Aug 20');
        $component->assertSet('aiDateTo', 'Aug 25, 2026');
        $component->assertSet('pendingEditSlot', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got it, updated!', $lastMessage);
    }

    public function test_editing_dates_with_a_bare_range_and_no_dates_keyword_still_works(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'August 20 to 25')->call('automateTrip');

        $component->assertSet('aiDateFrom', 'Aug 20');
        $component->assertSet('aiDateTo', 'Aug 25, 2026');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got it, updated!', $lastMessage);
        $this->assertStringNotContainsString('No worries, take your time', $lastMessage);
    }

    public function test_unrecognized_reply_during_confirmation_falls_back_to_decline_message(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'hmm let me think about it')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('/reset', $lastMessage['text']);
    }

    public function test_naming_a_slot_without_a_value_asks_for_the_value_instead_of_declining(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');

        $component->assertSet('pendingEditSlot', 'budget');
        $component->assertSet('aiBudgetMax', 30000);
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringNotContainsString('/reset', $lastMessage['text']);
    }

    public function test_options_request_is_recognized_while_waiting_for_a_pending_edit_value(): void
    {
        $user = User::factory()->create();
        $this->fakeDestinationSuggestions(['Siargao', 'El Nido', 'Bohol']);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'other destination')->call('automateTrip');
        $component->assertSet('pendingEditSlot', 'destination');

        $component->set('aiPrompt', 'give me top 5 destination')->call('automateTrip');

        $component->assertSet('aiDestinationChoices', ['Siargao', 'El Nido', 'Bohol']);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Siargao', $lastMessage);
        $this->assertStringNotContainsString("didn't quite catch", $lastMessage);
    }

    public function test_answering_a_pending_edit_slot_applies_the_value(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');
        $component->set('aiPrompt', '50000')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 50000);
        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_cancelling_a_pending_edit_leaves_the_original_value_untouched(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');
        $component->set('aiPrompt', 'same budget')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 30000);
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('No changes made', $lastMessage['text']);
    }

    public function test_unparseable_reply_while_pending_edit_asks_again(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');
        $component->set('aiPrompt', 'banana')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 30000);
        $component->assertSet('pendingEditSlot', 'budget');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_destination_to_match_existing_origin_is_rejected(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my destination to manila')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('pendingEditSlot', 'destination');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_origin_to_match_existing_destination_is_rejected(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my origin to boracay')->call('automateTrip');

        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('pendingEditSlot', 'origin');
    }

    public function test_retry_message_escalates_after_repeated_invalid_destination_edits(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my destination to xxxxxxxx')->call('automateTrip');
        $firstReply = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Sure! Where would you like to go?', $firstReply);

        $component->set('aiPrompt', 'xxxxxxxx')->call('automateTrip');
        $secondReply = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString("didn't quite catch", $secondReply);
        $this->assertNotSame($firstReply, $secondReply);

        $component->assertSet('pendingEditSlot', 'destination');
    }

    public function test_reset_returns_to_the_blank_landing_state_and_a_bad_first_reply_still_gets_the_destination_retry(): void
    {
        $this->fakeExtraction();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '/reset')->call('automateTrip');

        $component->assertSet('messages', []);
        $component->assertSet('awaitingSlot', '');

        $component->set('aiPrompt', 'xxxxxxxx')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString("Travel Assistant", $lastMessage);
        $component->assertSet('aiTo', '');
        $component->assertSet('awaitingSlot', 'destination');
    }

    public function test_editing_a_slot_using_in_as_the_connector_word(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'I want to change my destination in bohol')->call('automateTrip');

        $component->assertSet('aiTo', 'Bohol');
        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_a_slot_using_into_as_the_connector_word(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 30000)
            ->set('aiBudgetMax', 30000)
            ->set('aiDays', 3)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget into 5000')->call('automateTrip');

        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
    }

    public function test_stating_origin_only_on_the_first_message_asks_cleanly_for_destination(): void
    {
        $this->fakeExtraction(['origin' => 'Cebu']);
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', "Im from Cebu")->call('automateTrip');

        $component->assertSet('aiFrom', 'Cebu');
        $component->assertSet('aiTo', '');
        $component->assertSet('awaitingSlot', 'destination');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertSame('Sure! Where would you like to go?', $lastMessage);
    }

    public function test_answering_a_destination_question_with_from_phrasing_updates_origin_not_destination(): void
    {
        $this->fakeExtraction();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', "Im from Cebu")->call('automateTrip');

        $component->assertSet('aiFrom', 'Cebu');
        $component->assertSet('aiTo', '');
        $component->assertSet('awaitingSlot', 'destination');
    }

    public function test_editing_dates_to_a_duration_anchors_on_the_existing_start_date(): void
    {
        $user = User::factory()->create();
        $futureStart = now()->addDays(30);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiDateFrom', $futureStart->format('M j'))
            ->set('aiDateTo', $futureStart->copy()->addDays(6)->format('M j, Y'))
            ->set('aiDays', 7)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my travel date to 3days')->call('automateTrip');

        $component->assertSet('aiDateFrom', $futureStart->format('M j'));
        $component->assertSet('aiDateTo', $futureStart->copy()->addDays(2)->format('M j, Y'));
        $component->assertSet('aiDays', 3);
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_dates_to_a_word_form_week_duration_is_accepted(): void
    {
        $user = User::factory()->create();
        $futureStart = now()->addDays(30);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiDateFrom', $futureStart->format('M j'))
            ->set('aiDateTo', $futureStart->copy()->addDays(2)->format('M j, Y'))
            ->set('aiDays', 3)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my travel date to 1 week')->call('automateTrip');

        $component->assertSet('aiDateFrom', $futureStart->format('M j'));
        $component->assertSet('aiDateTo', $futureStart->copy()->addDays(6)->format('M j, Y'));
        $component->assertSet('aiDays', 7);
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_asking_about_budget_while_origin_is_still_unresolved_answers_about_budget(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'origin')
            ->set('aiFrom', '')
            ->set('aiPrompt', 'what is my budget?')
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('budget', strtolower($lastMessage));
        $this->assertStringNotContainsString('traveling from', $lastMessage);
        $component->assertSet('aiFrom', '');
    }

    public function test_asking_about_a_budget_that_was_already_given_recalls_it(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiBudgetMin', 30000)
            ->set('aiBudgetMax', 30000)
            ->set('aiPrompt', "what's my budget?")
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('30,000', $lastMessage);
    }

    public function test_asking_for_destination_advice_is_not_mistaken_for_a_status_question(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', "what's a good destination for beaches?")
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString("haven't told me your destination", $lastMessage);
    }

    public function test_asking_for_a_recap_shows_everything_known_so_far(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiTo', 'Cebu')
            ->set('aiFrom', '')
            ->set('aiPrompt', 'what have you got so far?')
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Cebu', $lastMessage);
        $this->assertStringContainsString('not set yet', $lastMessage);
    }

    public function test_budget_answer_while_awaiting_travelers_does_not_corrupt_travelers_count(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'travelers')
            ->set('aiPrompt', 'My Budget is 50000')
            ->call('automateTrip');

        $component->assertSet('aiTravelers', 0);
    }

    public function test_travelers_answer_while_awaiting_budget_does_not_corrupt_budget(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '4 travelers')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
    }

    public function test_bare_unlabeled_number_still_answers_the_awaited_slot(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'travelers')
            ->set('aiPrompt', '3')
            ->call('automateTrip');

        $component->assertSet('aiTravelers', 3);
    }

    public function test_budget_answered_out_of_turn_is_acknowledged(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction(['budget_min' => 50000, 'budget_max' => 50000]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiTo', 'Cebu')
            ->set('aiFrom', 'Manila')
            ->set('awaitingSlot', 'travelers')
            ->set('aiPrompt', 'My Budget is 50000')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMin', 50000);
        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('aiTravelers', 0);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got your budget', $lastMessage);
        $this->assertStringContainsString('50,000', $lastMessage);
    }

    public function test_a_stray_number_with_no_correction_cue_does_not_overwrite_an_existing_budget(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiTo', 'Cebu')
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('awaitingSlot', 'dates')
            ->set('aiPrompt', 'my flight number is 24509, see you there')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMin', 15000);
        $component->assertSet('aiBudgetMax', 15000);
    }

    public function test_a_correction_with_a_cue_word_does_update_an_existing_budget(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiTo', 'Cebu')
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('awaitingSlot', 'dates')
            ->set('aiPrompt', 'wait, make it 25000 instead')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMin', 25000);
        $component->assertSet('aiBudgetMax', 25000);
    }

    public function test_budget_answered_in_turn_is_not_redundantly_acknowledged(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiTo', 'Cebu')
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 2)
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '50000')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMin', 50000);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('Got your budget', $lastMessage);
    }

    public function test_extractWithAi_converts_a_foreign_currency_budget_to_pesos(): void
    {
        $user = User::factory()->create();
        auth()->login($user);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => 500, 'budget_max' => 500, 'budget_currency' => 'USD',
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 56], 200),
        ]);

        $llm = new Llm();
        $method = (new \ReflectionClass($llm))->getMethod('extractWithAi');
        $method->setAccessible(true);
        $method->invoke($llm, 'budget around 500 bucks');

        $this->assertSame(28000, $llm->aiBudgetMin);
        $this->assertSame(28000, $llm->aiBudgetMax);
        $this->assertSame('USD', $llm->aiCurrency);
    }

    public function test_regex_currency_conversion_uses_the_live_twelvedata_rate_when_available(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null,
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '$500')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 30855);
        $component->assertSet('aiBudgetMax', 30855);
        $component->assertSet('aiCurrency', 'USD');
    }

    public function test_a_dollar_budget_with_a_k_suffix_expands_to_thousands(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null,
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '$1500K')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 10000000);
        $component->assertSet('aiBudgetMax', 10000000);
        $component->assertSet('aiCurrency', 'USD');
    }

    public function test_a_euro_budget_with_a_k_suffix_expands_to_thousands(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'EUR/PHP', 'rate' => 65], 200),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null,
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '€2K')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 130000);
        $component->assertSet('aiBudgetMax', 130000);
        $component->assertSet('aiCurrency', 'EUR');
    }

    public function test_a_k_suffix_budget_works_with_the_currency_code_after_the_number(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null,
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '2K USD')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 123420);
        $component->assertSet('aiBudgetMax', 123420);
    }

    public function test_a_plain_dollar_amount_followed_by_an_unrelated_k_word_is_not_multiplied(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null,
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '$500 kg of luggage allowance')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 30855);
        $component->assertSet('aiBudgetMax', 30855);
    }

    public function test_currency_conversion_updates_state_and_does_not_announce_any_currency_in_chat(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'Im from Canada and I want to go in the Japan and my budget is 500$ and 3days travel')
            ->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 30855);
        $component->assertSet('aiCurrency', 'USD');

        $allText = collect($component->get('messages'))->pluck('text')->implode(' ');
        $this->assertStringNotContainsString('is about', $allText);
        $this->assertStringNotContainsString('¥', $allText);
    }

    public function test_currency_conversion_updates_state_without_announcing_it_in_chat(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'USD/PHP', 'rate' => 61.71], 200),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null,
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '$500')->call('automateTrip');

        $component->assertSet('aiTo', '');
        $component->assertSet('aiBudgetMin', 30855);
        $component->assertSet('aiCurrency', 'USD');

        $allText = collect($component->get('messages'))->pluck('text')->implode(' ');
        $this->assertStringNotContainsString('is about', $allText);
        $this->assertStringContainsString('$500', $allText);
    }

    public function test_regex_currency_conversion_is_left_unset_when_twelvedata_is_unavailable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => null, 'budget_max' => null,
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', '$500')->call('automateTrip');

        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $allText = collect($component->get('messages'))->pluck('text')->implode(' ');
        $this->assertStringContainsString("couldn't fetch the live exchange rate", $allText);
    }

    public function test_ai_extracted_currency_conversion_is_left_unset_when_twelvedata_is_unavailable(): void
    {
        $user = User::factory()->create();
        auth()->login($user);

        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'off_topic' => false, 'is_greeting' => false, 'is_inappropriate' => false,
                    'origin' => null, 'destination' => null, 'travelers' => null,
                    'budget_min' => 500, 'budget_max' => 500, 'budget_currency' => 'USD',
                    'date_from' => null, 'date_to' => null,
                ])]]],
            ], 200),
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $llm = new Llm();
        $method = (new \ReflectionClass($llm))->getMethod('extractWithAi');
        $method->setAccessible(true);
        $method->invoke($llm, 'budget around 500 bucks');

        $this->assertSame(0, $llm->aiBudgetMin);
        $this->assertSame(0, $llm->aiBudgetMax);
    }

    private function fakeDestinationSuggestions(array $destinations): void
    {
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['destinations' => $destinations])]]],
            ], 200),
        ]);
    }

    public function test_multi_option_recommendation_during_confirmation_shows_choices(): void
    {
        $user = User::factory()->create();
        $this->fakeDestinationSuggestions(['Boracay', 'Siargao', 'Bohol', 'Palawan', 'Cebu']);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you recommend top 5 destination')->call('automateTrip');

        $component->assertSet('aiDestinationChoices', ['Boracay', 'Siargao', 'Bohol', 'Palawan', 'Cebu']);
        $component->assertSet('pendingEditSlot', 'destination');
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Boracay', $lastMessage);
        $this->assertStringContainsString('alternatives', $lastMessage);
    }

    public function test_picking_a_recommended_destination_during_confirmation_updates_and_shows_summary(): void
    {
        $user = User::factory()->create();
        $this->fakeDestinationSuggestions(['Boracay', 'Siargao', 'Bohol', 'Palawan', 'Cebu']);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you recommend top 5 destination')->call('automateTrip');
        $component->set('aiPrompt', '2')->call('automateTrip');

        $component->assertSet('aiTo', 'Siargao');
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('aiDestinationChoices', []);
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Siargao', $lastMessage);
        $this->assertStringContainsString('Would you like me to proceed', $lastMessage);
    }

    public function test_single_recommendation_during_confirmation_updates_and_shows_summary(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['destination' => 'Bohol'])]]],
            ], 200),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you recommend a destination for me')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $firstReply = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Bohol', $firstReply);
        $this->assertStringNotContainsString('Would you like me to proceed', $firstReply);

        $component->set('aiPrompt', 'yes')->call('automateTrip');

        $component->assertSet('aiTo', 'Bohol');
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Bohol', $lastMessage);
        $this->assertStringContainsString('Would you like me to proceed', $lastMessage);
    }

    public function test_declining_a_single_recommendation_during_confirmation_does_not_commit_it(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['destination' => 'Bohol'])]]],
            ], 200),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you recommend a destination for me')->call('automateTrip');
        $component->set('aiPrompt', 'no')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('aiStep', '');
    }

    private function fakeSingleDestinationSuggestion(string $destination): void
    {
        $respond = function ($request) use ($destination) {
            $promptText = $request->body();
            if (str_contains($promptText, 'an actual city, town, or island')) {
                return Http::response(['choices' => [['message' => ['content' =>
                    json_encode(['is_real_place' => false, 'name' => null, 'iata_code' => null]),
                ]]]], 200);
            }
            return Http::response(['choices' => [['message' => ['content' =>
                json_encode(['destination' => $destination]),
            ]]]], 200);
        };

        Http::fake([
            'api.mistral.ai/*' => $respond,
            'api.groq.com/*'   => $respond,
            'openrouter.ai/*'  => $respond,
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' =>
                    json_encode(['is_real_place' => false, 'name' => null, 'iata_code' => null]),
                ]]]]],
            ], 200),
        ]);
    }

    public function test_recommendation_is_not_committed_until_accepted(): void
    {
        $user = User::factory()->create();
        $this->fakeSingleDestinationSuggestion('Siargao');

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'anywhere')->call('automateTrip');

        $component->assertSet('aiTo', '');
        $component->assertSet('pendingPlaceSuggestion', 'Siargao');
        $component->assertSet('pendingPlaceSuggestionSlot', 'destination');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Siargao', $lastMessage);
    }

    public function test_accepting_a_recommendation_commits_it(): void
    {
        $user = User::factory()->create();
        $this->fakeSingleDestinationSuggestion('Siargao');

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'anywhere')->call('automateTrip');
        $component->set('aiPrompt', 'yes')->call('automateTrip');

        $component->assertSet('aiTo', 'Siargao');
    }

    public function test_rejecting_a_recommendation_with_natural_phrasing_clears_it(): void
    {
        $user = User::factory()->create();
        $this->fakeSingleDestinationSuggestion('Siargao');

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'anywhere')->call('automateTrip');
        $component->set('aiPrompt', "I'm not interested in Siargao")->call('automateTrip');

        $component->assertSet('aiTo', '');
        $component->assertSet('pendingPlaceSuggestion', null);
        $component->assertSet('rejectedDestinations', ['Siargao']);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('Siargao', $lastMessage);
    }

    public function test_rejecting_a_recommendation_with_a_short_no_still_works(): void
    {
        $user = User::factory()->create();
        $this->fakeSingleDestinationSuggestion('Siargao');

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'anywhere')->call('automateTrip');
        $component->set('aiPrompt', 'no')->call('automateTrip');

        $component->assertSet('aiTo', '');
        $component->assertSet('rejectedDestinations', ['Siargao']);
    }

    public function test_replying_more_to_a_single_recommendation_shows_alternatives(): void
    {
        $user = User::factory()->create();

        $respond = function ($request) {
            $promptText = $request->body();
            if (str_contains($promptText, 'an actual city, town, or island')) {
                return Http::response(['choices' => [['message' => ['content' =>
                    json_encode(['is_real_place' => false, 'name' => null, 'iata_code' => null]),
                ]]]], 200);
            }
            if (str_contains($promptText, 'DIFFERENT travel destinations')) {
                return Http::response(['choices' => [['message' => ['content' =>
                    json_encode(['destinations' => ['El Nido', 'Siargao', 'Boracay']]),
                ]]]], 200);
            }
            return Http::response(['choices' => [['message' => ['content' =>
                json_encode(['destination' => 'Siargao']),
            ]]]], 200);
        };
        Http::fake([
            'api.mistral.ai/*' => $respond,
            'api.groq.com/*'   => $respond,
            'openrouter.ai/*'  => $respond,
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' =>
                json_encode(['is_real_place' => false, 'name' => null, 'iata_code' => null]),
            ]]]]]], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'anywhere')->call('automateTrip');

        $component->set('aiPrompt', 'more')->call('automateTrip');

        $component->assertSet('aiTo', '');
        $component->assertSet('aiDestinationChoices', ['El Nido', 'Siargao', 'Boracay']);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('El Nido', $lastMessage);
        $this->assertStringNotContainsString("didn't quite catch", $lastMessage);
    }

    public function test_replying_other_to_a_multi_option_list_shows_fresh_alternatives(): void
    {
        $user = User::factory()->create();
        $this->fakeDestinationSuggestions(['Boracay', 'Coron', 'Palawan']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 2)
            ->set('aiDestinationChoices', ['El Nido', 'Siargao', 'Bohol'])
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'other')
            ->call('automateTrip');

        $component->assertSet('aiDestinationChoices', ['Boracay', 'Coron', 'Palawan']);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Boracay', $lastMessage);
        $this->assertStringNotContainsString("didn't quite catch", $lastMessage);
    }

    public function test_replying_other_when_regeneration_fails_gives_an_honest_message(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.mistral.ai/*'                    => Http::response([], 200),
            'openrouter.ai/*'                     => Http::response([], 200),
            'api.groq.com/*'                       => Http::response([], 200),
            'generativelanguage.googleapis.com/*'  => Http::response([], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 2)
            ->set('aiDestinationChoices', ['El Nido', 'Siargao', 'Bohol'])
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'other')
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString("couldn't come up with more alternatives", $lastMessage);
        $this->assertStringNotContainsString("didn't quite catch", $lastMessage);
    }

    public function test_suggestDestination_includes_the_exclude_list_in_its_prompt(): void
    {
        $this->fakeSingleDestinationSuggestion('Bohol');
        $user = User::factory()->create();
        auth()->login($user);

        $llm = new Llm();
        $method = (new \ReflectionClass($llm))->getMethod('suggestDestination');
        $method->setAccessible(true);
        $result = $method->invoke($llm, 'anywhere', ['Siargao']);

        $this->assertSame('Bohol', $result);
        Http::assertSent(function ($request) {
            $body = $request->data();
            $content = $body['messages'][0]['content'] ?? '';
            return str_contains($content, 'Siargao') && str_contains($content, 'turned them down');
        });
    }

    public function test_suggestDestination_includes_the_budget_in_its_prompt(): void
    {
        $this->fakeSingleDestinationSuggestion('Baguio');
        $user = User::factory()->create();
        auth()->login($user);

        $llm = new Llm();
        $llm->aiBudgetMin = 30000;
        $llm->aiBudgetMax = 30000;
        $llm->aiTravelers = 2;

        $method = (new \ReflectionClass($llm))->getMethod('suggestDestination');
        $method->setAccessible(true);
        $result = $method->invoke($llm, 'recommend something', []);

        $this->assertSame('Baguio', $result);
        Http::assertSent(function ($request) {
            $body = $request->data();
            $content = $body['messages'][0]['content'] ?? '';
            return str_contains($content, '30,000') && str_contains($content, '2 travelers');
        });
    }

    public function test_suggestDestination_omits_budget_from_prompt_when_unset(): void
    {
        $this->fakeSingleDestinationSuggestion('Baguio');
        $user = User::factory()->create();
        auth()->login($user);

        $llm = new Llm();

        $method = (new \ReflectionClass($llm))->getMethod('suggestDestination');
        $method->setAccessible(true);
        $method->invoke($llm, 'recommend something', []);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $content = $body['messages'][0]['content'] ?? '';
            return !str_contains($content, 'total trip budget');
        });
    }

    public function test_a_failed_recommendation_gets_an_honest_message_not_the_generic_retry(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.mistral.ai/*'                    => Http::response([], 200),
            'openrouter.ai/*'                     => Http::response([], 200),
            'api.groq.com/*'                       => Http::response([], 200),
            'generativelanguage.googleapis.com/*'  => Http::response([], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 2)
            ->set('aiBudgetMin', 30000)
            ->set('aiBudgetMax', 30000)
            ->set('awaitingSlot', 'destination')
            ->set('missCount', 1)
            ->set('aiPrompt', 'Recommend me base from my budget')
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString("couldn't come up with a recommendation", $lastMessage);
        $this->assertStringNotContainsString("didn't quite catch", $lastMessage);
    }

    public function test_international_request_on_a_too_low_budget_gets_an_explicit_shortfall_message(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Cebu')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'recommend me an international destination based on my budget')
            ->call('automateTrip');

        $component->assertSet('aiTo', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
        $this->assertStringContainsString('15,000', $lastMessage);
        $this->assertStringContainsString('7-day', $lastMessage);
        $this->assertStringContainsString('Cebu', $lastMessage);
    }

    public function test_international_request_on_a_sufficient_budget_proceeds_normally(): void
    {
        $user = User::factory()->create();
        $this->fakeSingleDestinationSuggestion('Bangkok');

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Cebu')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 60000)
            ->set('aiBudgetMax', 60000)
            ->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'recommend me an international destination based on my budget')
            ->call('automateTrip');

        $component->assertSet('pendingPlaceSuggestion', 'Bangkok');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('too low', $lastMessage);
    }

    public function test_international_shortfall_message_also_applies_during_confirmation(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiFrom', 'Cebu')

            ->set('aiBudgetMin', 25000)
            ->set('aiBudgetMax', 25000)
            ->set('aiDays', 7)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'recommend an international destination for me')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
    }

    public function test_directly_naming_an_unaffordable_international_destination_shows_the_shortfall(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDateFrom', 'Aug 21')
            ->set('aiDateTo', 'Aug 22, 2026')
            ->set('aiDays', 2)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'japan')
            ->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
        $this->assertStringContainsString('15,000', $lastMessage);
        $this->assertStringNotContainsString('more affordable destination', $lastMessage);
    }

    public function test_directly_naming_a_domestic_destination_is_never_blocked_by_the_shortfall_check(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDateFrom', 'Aug 21')
            ->set('aiDateTo', 'Aug 22, 2026')
            ->set('aiDays', 2)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'boracay')
            ->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_a_long_domestic_trip_needs_more_than_a_short_one(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')->set('aiTravelers', 1)
            ->set('aiBudgetMin', 12000)->set('aiBudgetMax', 12000)
            ->set('aiDateFrom', 'Aug 3')->set('aiDateTo', 'Aug 16, 2026')
            ->set('aiDays', 14)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'boracay')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'budget');
        $this->assertStringContainsString('too low', collect($component->get('messages'))->last()['text']);
    }

    public function test_a_short_domestic_trip_is_no_longer_held_to_the_week_long_figure(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')->set('aiTravelers', 1)
            ->set('aiBudgetMin', 6000)->set('aiBudgetMax', 6000)
            ->set('aiDateFrom', 'Aug 21')->set('aiDateTo', 'Aug 22, 2026')
            ->set('aiDays', 2)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'boracay')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_a_week_long_domestic_trip_still_needs_exactly_ten_thousand(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')->set('aiTravelers', 1)
            ->set('aiBudgetMin', 10000)->set('aiBudgetMax', 10000)
            ->set('aiDateFrom', 'Aug 3')->set('aiDateTo', 'Aug 9, 2026')
            ->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'boracay')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_directly_naming_an_affordable_international_destination_proceeds_normally(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 80000)
            ->set('aiBudgetMax', 80000)
            ->set('aiDateFrom', 'Aug 21')
            ->set('aiDateTo', 'Aug 27, 2026')
            ->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'japan')
            ->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_a_bare_budget_is_read_in_the_travellers_own_currency(): void
    {
        Http::fake(['api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.3835], 200)]);
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '50000')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 19175);
        $component->assertSet('aiBudgetLocal', 50000.0);
        $component->assertSet('aiCurrency', 'JPY');
    }

    public function test_a_filipino_bare_budget_is_untouched(): void
    {
        $this->fakeExtraction();
        $user = User::factory()->create(['country' => 'Philippines']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '50000')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('aiCurrency', 'PHP');

        $component->assertSet('aiBudgetLocal', null);
    }

    public function test_an_unconvertible_budget_is_refused_rather_than_stored(): void
    {
        Http::fake(['api.twelvedata.com/*' => Http::response([], 500)]);
        Cache::flush();
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'budget')
            ->set('aiPrompt', '50000')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('aiBudgetLocal', null);
    }

    public function test_the_saved_trip_records_the_travellers_own_figure(): void
    {
        $this->fakeExtraction();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.3835], 200),
            '*' => Http::response([], 200),
        ]);
        $user = User::factory()->create(['country' => 'Japan']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Tokyo')->set('aiTo', 'Bangkok')
            ->set('aiTravelers', 2)
            ->set('aiDateFrom', '2026-08-03')->set('aiDateTo', '2026-08-10')->set('aiDays', 8)
            ->set('awaitingSlot', 'budget')

            ->set('aiPrompt', '200000')->call('automateTrip');

        $component->set('aiStep', 'results')->call('processAiTrip');

        $trip = Trip::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($trip, 'a draft trip was autosaved');
        $this->assertSame('76700.00', (string) $trip->budget_limit);
        $this->assertSame('JPY', $trip->budget_currency);
        $this->assertSame('200000.00', (string) $trip->budget_local);
    }

    public function test_a_domestic_trip_abroad_is_not_treated_as_international(): void
    {
        $user = User::factory()->create(['country' => 'Canada']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Toronto')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDateFrom', 'Aug 21')
            ->set('aiDateTo', 'Aug 27, 2026')
            ->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'vancouver')
            ->call('automateTrip');

        $component->assertSet('aiTo', 'Vancouver');
        $component->assertSet('awaitingSlot', 'confirmation');
        $this->assertStringNotContainsString(
            'international',
            collect($component->get('messages'))->last()['text']
        );
    }

    public function test_a_foreign_traveller_crossing_a_border_is_still_international(): void
    {
        $user = User::factory()->create(['country' => 'Canada']);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Toronto')
            ->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDateFrom', 'Aug 21')
            ->set('aiDateTo', 'Aug 27, 2026')
            ->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'bangkok')
            ->call('automateTrip');

        $component->assertSet('aiTo', 'Bangkok');
        $this->assertStringContainsString(
            'international',
            collect($component->get('messages'))->last()['text']
        );
    }

    public function test_a_philippine_traveller_keeps_the_original_behaviour(): void
    {
        $user  = User::factory()->create(['country' => 'Philippines']);
        $other = User::factory()->create(['country' => 'Philippines']);

        $abroad = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiFrom', 'Manila')->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)->set('aiBudgetMax', 15000)
            ->set('aiDateFrom', 'Aug 21')->set('aiDateTo', 'Aug 27, 2026')->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'japan')->call('automateTrip');

        $this->assertStringContainsString('international', collect($abroad->get('messages'))->last()['text']);

        $home = Livewire::actingAs($other)->test(Llm::class)
            ->set('aiFrom', 'Manila')->set('aiTravelers', 1)
            ->set('aiBudgetMin', 15000)->set('aiBudgetMax', 15000)
            ->set('aiDateFrom', 'Aug 21')->set('aiDateTo', 'Aug 27, 2026')->set('aiDays', 7)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', 'boracay')->call('automateTrip');

        $home->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_destination_to_an_unaffordable_international_place_during_confirmation_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )

            ->set('aiBudgetMin', 25000)
            ->set('aiBudgetMax', 25000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change the destination to japan')->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);

        $component->set('aiPrompt', 'yes')->call('automateTrip');
        $component->assertSet('aiStep', '');
    }

    public function test_pending_edit_slot_follow_up_to_an_unaffordable_international_place_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )

            ->set('aiBudgetMin', 25000)
            ->set('aiBudgetMax', 25000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('pendingEditSlot', 'destination')
            ->set('aiPrompt', 'japan')
            ->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $component->assertSet('pendingEditSlot', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
    }

    public function test_editing_budget_to_an_unaffordable_amount_on_an_international_destination_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')

            ->set('aiBudgetMin', 90000)
            ->set('aiBudgetMax', 90000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget')->call('automateTrip');
        $component->assertSet('pendingEditSlot', 'budget');

        $component->set('aiPrompt', '15000')->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $component->assertSet('pendingEditSlot', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);

        $component->set('aiPrompt', 'yes')->call('automateTrip');
        $component->assertSet('aiStep', '');
    }

    public function test_one_shot_budget_edit_to_an_unaffordable_amount_on_an_international_destination_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 90000)
            ->set('aiBudgetMax', 90000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change budget to 5000')->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
    }

    public function test_editing_budget_to_a_sufficient_amount_on_an_international_destination_proceeds_normally(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 90000)
            ->set('aiBudgetMax', 90000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change budget to 80000')->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 80000);
        $component->assertSet('aiBudgetMax', 80000);
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got it, updated!', $lastMessage);
    }

    public function test_one_shot_budget_edit_to_an_unaffordable_amount_on_a_domestic_destination_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 1000')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);

        $component->set('aiPrompt', 'yes')->call('automateTrip');
        $component->assertSet('aiStep', '');
    }

    public function test_editing_budget_to_a_sufficient_amount_on_a_domestic_destination_proceeds_normally(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 20000')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('aiBudgetMin', 20000);
        $component->assertSet('aiBudgetMax', 20000);
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got it, updated!', $lastMessage);
    }

    private function fakeSerpApi(array $responses): void
    {
        Http::fake([
            'serpapi.com/*' => function ($request) use ($responses) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
                $engine = $query['engine'] ?? '';
                $key = match (true) {
                    $engine === 'google_maps' && str_contains($query['q'] ?? '', 'restaurant') => 'restaurants',
                    $engine === 'google_maps' => 'attractions',
                    default => $engine,
                };
                return $responses[$key] ?? Http::response([], 200);
            },
            'google.serper.dev/*'                 => Http::response([], 404),
            'api.mistral.ai/*'                     => Http::response([], 200),
            'openrouter.ai/*'                      => Http::response([], 200),
            'api.groq.com/*'                       => Http::response([], 200),
            'generativelanguage.googleapis.com/*'  => Http::response([], 200),
        ]);
    }

    public function test_buildSerpApiPackage_assembles_a_package_from_live_search_data(): void
    {
        $this->fakeSerpApi([
            'google_flights' => Http::response([
                'best_flights' => [[
                    'flights' => [['airline' => 'Cebu Pacific', 'flight_number' => '5J 567']],
                    'price'   => 3000,
                ]],
            ], 200),
            'google_hotels' => Http::response([
                'properties' => [[
                    'name'            => 'Test Hotel Boracay',
                    'hotel_class'     => 4,
                    'rate_per_night'  => ['lowest' => '₱1,500'],
                    'total_rate'      => ['lowest' => '₱10,000'],
                    'room_highlights' => ['Deluxe Room'],
                ]],
            ], 200),
            'restaurants' => Http::response([
                'local_results' => [
                    ['title' => 'Test Restaurant', 'address' => 'Station 1, Boracay, Philippines', 'rating' => 4.5],
                ],
            ], 200),
            'attractions' => Http::response([
                'local_results' => [
                    ['title' => 'Attraction A', 'address' => 'Boracay, Philippines', 'rating' => 4.8],
                    ['title' => 'Attraction B', 'address' => 'Boracay, Philippines', 'rating' => 4.6],
                    ['title' => 'Attraction C', 'address' => 'Boracay, Philippines', 'rating' => 4.4],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();
        auth()->login($user);

        $llm = new Llm();
        $llm->aiFrom        = 'Manila';
        $llm->aiTo          = 'Boracay';
        $llm->aiTravelers   = 2;
        $llm->aiBudgetMin   = 30000;
        $llm->aiBudgetMax   = 30000;
        $llm->aiDateFrom    = 'Aug 3';
        $llm->aiDateTo      = 'Aug 10, 2026';
        $llm->aiDays        = 7;

        $method = (new \ReflectionClass($llm))->getMethod('buildSerpApiPackage');
        $method->setAccessible(true);
        $result = $method->invoke($llm);

        $this->assertSame(6000, $result['transport']['cost']);
        $this->assertStringContainsString('Cebu Pacific', $result['transport']['detail']);
        $this->assertSame('Test Hotel Boracay', $result['accommodation']['name']);
        $this->assertSame(4, $result['accommodation']['stars']);
        $this->assertSame(10000, $result['accommodation']['cost']);
        $this->assertSame('Test Restaurant', $result['food']['name']);
        $this->assertSame(12000, $result['food']['cost']);
        $this->assertCount(3, $result['attractions']['items']);
        $this->assertSame(0, $result['attractions']['cost']);
        $this->assertSame(28000, $result['total']);
        $this->assertSame(30000, $result['budget']);
        $this->assertSame(93, $result['pct']);
    }

    public function test_buildSerpApiPackage_trims_the_priciest_attraction_when_over_budget(): void
    {
        $this->fakeSerpApi([
            'google_flights' => Http::response([
                'best_flights' => [[
                    'flights' => [['airline' => 'Test Air', 'flight_number' => 'TA1']],
                    'price'   => 100,
                ]],
            ], 200),
            'google_hotels' => Http::response([], 200),
            'restaurants'   => Http::response([], 200),
            'attractions'   => Http::response([], 200),
        ]);

        $user = User::factory()->create();
        auth()->login($user);

        $llm = new Llm();
        $llm->aiFrom        = 'Manila';
        $llm->aiTo          = 'Boracay';
        $llm->aiTravelers   = 1;
        $llm->aiBudgetMin   = 1000;
        $llm->aiBudgetMax   = 1000;
        $llm->aiDateFrom    = 'Aug 3';
        $llm->aiDateTo      = 'Aug 6, 2026';
        $llm->aiDays        = 3;

        $method = (new \ReflectionClass($llm))->getMethod('buildSerpApiPackage');
        $method->setAccessible(true);
        $result = $method->invoke($llm);

        $this->assertCount(1, $result['attractions']['items']);
        $this->assertSame('Local Market Visit', $result['attractions']['items'][0][0]);
        $this->assertSame(0, $result['attractions']['cost']);
        $this->assertSame(750, $result['total']);
        $this->assertSame(1000, $result['budget']);
        $this->assertSame(75, $result['pct']);
    }

    public function test_generateAiPackage_uses_known_destination_data(): void
    {
        $user = User::factory()->create();
        auth()->login($user);

        $llm = new Llm();
        $llm->aiFrom      = 'Manila';
        $llm->aiTo        = 'Boracay';
        $llm->aiTravelers = 2;
        $llm->aiBudgetMax = 30000;
        $llm->aiDays      = 7;

        $method = (new \ReflectionClass($llm))->getMethod('generateAiPackage');
        $method->setAccessible(true);
        $method->invoke($llm);

        $package = $llm->aiPackage;
        $this->assertSame('KLO', $package['transport']['to_code']);
        $this->assertStringContainsString('Philippine Airlines PR 201', $package['transport']['detail']);
        $this->assertSame(5400, $package['transport']['cost']);
        $this->assertSame('Discovery Shores Boracay', $package['accommodation']['name']);
        $this->assertSame(5, $package['accommodation']['stars']);
        $this->assertSame(15001, $package['accommodation']['cost']);
        $this->assertSame('Aria at Discovery Shores (₱1,500)', $package['food']['name']);
        $this->assertSame(0, $package['attractions']['cost']);
        $this->assertCount(2, $package['attractions']['items']);
        $this->assertSame(9599, $package['food']['cost']);
        $this->assertSame(30000, $package['total']);
        $this->assertSame(30000, $package['budget']);
        $this->assertSame(100, $package['pct']);
    }

    public function test_generateAiPackage_falls_back_to_generic_data_for_unknown_destination(): void
    {
        $user = User::factory()->create();
        auth()->login($user);

        $llm = new Llm();
        $llm->aiFrom      = 'Manila';
        $llm->aiTo        = 'Neverlandia';
        $llm->aiTravelers = 1;
        $llm->aiBudgetMax = 20000;
        $llm->aiDays      = 3;

        $method = (new \ReflectionClass($llm))->getMethod('generateAiPackage');
        $method->setAccessible(true);
        $method->invoke($llm);

        $package = $llm->aiPackage;
        $this->assertSame('DOM', $package['transport']['to_code']);
        $this->assertStringContainsString('Cebu Pacific · Direct Flight', $package['transport']['detail']);
        $this->assertSame('Grand Hotel Neverlandia', $package['accommodation']['name']);
        $this->assertSame(3, $package['accommodation']['stars']);
        $this->assertSame('Local Dining at Neverlandia', $package['food']['name']);
        $this->assertSame(2100, $package['food']['cost']);
        $this->assertCount(3, $package['attractions']['items']);
        $this->assertSame('Neverlandia City Tour', $package['attractions']['items'][0][0]);
        $this->assertSame(300, $package['attractions']['cost']);
        $this->assertSame(15999, $package['total']);
        $this->assertSame(80, $package['pct']);
    }

    public function test_mount_offers_saved_preferences_when_profile_has_origin_and_budget(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Cebu City',
            'daily_budget' => 35000, 'interests' => ['Nature'],
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class);

        $component->assertSet('pendingProfileOffer', true);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Cebu City', $lastMessage);
        $this->assertStringContainsString('35,000', $lastMessage);
        $this->assertStringContainsString('Nature', $lastMessage);
    }

    public function test_mount_offers_saved_preferences_using_the_local_currency_when_available(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Osaka',
            'daily_budget' => 19175, 'daily_budget_currency' => 'JPY', 'daily_budget_local' => 50000,
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class);

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('¥50,000', $lastMessage);
        $this->assertStringNotContainsString('19,175', $lastMessage);
    }

    public function test_mount_offers_saved_preferences_in_pesos_when_no_local_currency_saved(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Manila', 'daily_budget' => 30000,
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class);

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('₱30,000', $lastMessage);
    }

    public function test_mount_offers_to_build_a_profile_when_none_exists(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);

        $component->assertSet('pendingProfileOffer', false);

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString("haven't set up a travel profile", $lastMessage);
    }

    public function test_mount_does_not_offer_when_only_interests_are_saved(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'interests' => ['Nature']]);

        $component = Livewire::actingAs($user)->test(Llm::class);

        $component->assertSet('messages', []);
        $component->assertSet('pendingProfileOffer', false);
    }

    public function test_mount_offer_mentions_only_budget_when_home_city_is_missing(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'daily_budget' => 35000]);

        $component = Livewire::actingAs($user)->test(Llm::class);

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('starting point', $lastMessage);
        $this->assertStringContainsString('35,000', $lastMessage);
    }

    public function test_accepting_the_profile_offer_prefills_origin_and_budget(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Cebu City', 'daily_budget' => 35000]);
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'yes')->call('automateTrip');

        $component->assertSet('aiFrom', 'Cebu City');
        $component->assertSet('aiBudgetMin', 35000);
        $component->assertSet('aiBudgetMax', 35000);
        $component->assertSet('pendingProfileOffer', false);
    }

    public function test_declining_the_profile_offer_leaves_slots_empty(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Cebu City', 'daily_budget' => 35000]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'no')->call('automateTrip');

        $component->assertSet('aiFrom', '');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('pendingProfileOffer', false);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('Cebu City', $lastMessage);
    }

    public function test_an_unrelated_reply_declines_the_offer_and_is_processed_as_a_normal_message(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Cebu City', 'daily_budget' => 35000]);
        $this->fakeExtraction();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'I want to go to Japan')->call('automateTrip');

        $component->assertSet('pendingProfileOffer', false);
        $component->assertSet('aiFrom', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('saved travel preferences', $lastMessage);
    }

    public function test_mount_does_not_re_offer_when_a_draft_already_exists(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Cebu City', 'daily_budget' => 35000]);
        AiConversationDraft::create([
            'user_id' => $user->id,
            'messages' => [['role' => 'user', 'text' => 'Boracay trip']],
            'ai_from' => '', 'ai_to' => '', 'ai_budget_min' => 0, 'ai_budget_max' => 0,
            'ai_date_from' => '', 'ai_date_to' => '', 'ai_days' => 0, 'ai_travelers' => 0,
            'awaiting_slot' => '', 'miss_count' => 0, 'ai_step' => '', 'ai_gen_count' => 0,
            'pending_profile_offer' => false,
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class);

        $component->assertSet('pendingProfileOffer', false);
        $component->assertCount('messages', 1);
    }

    public function test_pending_profile_offer_survives_a_simulated_page_reload(): void
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id, 'home_city' => 'Cebu City', 'daily_budget' => 35000]);
        $this->fakeExtraction();

        Livewire::actingAs($user)->test(Llm::class);

        $draft = AiConversationDraft::where('user_id', $user->id)->first();
        $this->assertNotNull($draft);
        $this->assertTrue((bool) $draft->pending_profile_offer);

        $component2 = Livewire::actingAs($user)->test(Llm::class);
        $component2->assertSet('pendingProfileOffer', true);
        $component2->assertCount('messages', 1);

        $component2->set('aiPrompt', 'yes')->call('automateTrip');
        $component2->assertSet('aiFrom', 'Cebu City');
    }

    public function test_accepting_saved_preferences_carries_the_local_currency_into_the_confirmation_message(): void
    {
        $user = User::factory()->create(['currency_code' => 'USD', 'currency_symbol' => '$']);
        UserProfile::create([
            'user_id' => $user->id, 'home_city' => 'Vancouver',
            'daily_budget' => 20000, 'daily_budget_currency' => 'CAD', 'daily_budget_local' => 500,
        ]);
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'CAD/PHP', 'rate' => 40], 200),
        ]);

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'yes')->call('automateTrip');

        $component->assertSet('aiCurrency', 'CAD');
        $component->assertSet('aiBudgetMin', 20000);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('C$500', $lastMessage);
        $this->assertStringNotContainsString('$361', $lastMessage);
    }

    public function test_confirmation_summary_stays_in_pesos_for_an_international_destination(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.38], 200),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 45000)
            ->set('aiBudgetMax', 45000)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('₱45,000', $lastMessage);
        $this->assertStringNotContainsString('Destination budget', $lastMessage);
        $this->assertStringNotContainsString('¥', $lastMessage);
    }

    public function test_autosaved_draft_trip_still_records_the_destination_currency(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.38], 200),
        ]);

        Livewire::actingAs($user)
            ->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Manila to Japan']])
            ->set('aiStep', 'results')
            ->set('aiFrom', 'Manila')
            ->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 45000)
            ->set('aiBudgetMax', 45000)
            ->set('aiDateFrom', 'Aug 3')
            ->set('aiDateTo', 'Aug 10, 2026')
            ->set('aiDays', 8)
            ->set('aiTravelers', 2)
            ->call('$refresh');

        $this->assertDatabaseHas('trips', [
            'user_id'              => $user->id,
            'destination'          => 'Japan',
            'status'               => 'draft',
            'destination_currency' => 'JPY',
        ]);
    }

    public function test_confirmation_summary_omits_destination_currency_for_a_domestic_destination(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('Destination budget', $lastMessage);
    }

    public function test_confirmation_summary_omits_destination_currency_when_twelvedata_is_unavailable(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')

            ->set('aiBudgetMin', 70000)
            ->set('aiBudgetMax', 70000)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('Destination budget', $lastMessage);
        $this->assertStringContainsString('Would you like me to proceed', $lastMessage);
    }

    public function test_autosave_draft_persists_destination_currency_to_the_trip(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.twelvedata.com/*' => Http::response(['symbol' => 'JPY/PHP', 'rate' => 0.38], 200),
        ]);

        Livewire::actingAs($user)->test(Llm::class)
            ->set('messages', [['role' => 'user', 'text' => 'Japan trip']])
            ->set('aiTo', 'Japan')
            ->set('aiFrom', 'Manila')
            ->set('aiBudgetMin', 45000)
            ->set('aiBudgetMax', 45000)
            ->set('aiDateFrom', '2026-09-01')
            ->set('aiDateTo', '2026-09-05')
            ->set('aiTravelers', 1)
            ->set('aiStep', 'results');

        $trip = Trip::where('user_id', $user->id)->where('status', 'draft')->first();
        $this->assertNotNull($trip);
        $this->assertSame('JPY', $trip->destination_currency);
        $this->assertEquals(45000, (float) $trip->budget_limit);
    }
}
