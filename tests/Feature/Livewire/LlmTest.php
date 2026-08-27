<?php
namespace Tests\Feature\Livewire;

use App\Livewire\Traveler\Llm;
use App\Models\AiConversationDraft;
use App\Models\AiConversationHistory;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            // Mistral/OpenRouter/Groq are tried before Gemini in the real
            // fallback chain (see Llm::processAiTrip()) — without these, a
            // real API key present in .env makes this test hit the live
            // internet instead of the fake data below, since Http::fake()
            // only intercepts the specific URL patterns it's given. Empty
            // 200s here make all three return null so the chain actually
            // reaches the Gemini fake this test is meant to exercise.
            'api.mistral.ai/*'                    => Http::response([], 200),
            'openrouter.ai/*'                     => Http::response([], 200),
            'api.groq.com/*'                      => Http::response([], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($package)]]]]],
            ], 200),
        ]);
    }

    // Fakes extractWithAi()'s classification call (Mistral is tried
    // first in that chain) to return a controlled, deterministic result
    // instead of hitting a live model — used by tests where a message
    // reaches extractWithAi() but the test only cares about routing
    // behavior, not what the AI itself would extract.
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

    // "1 week" (or any relative duration) has nothing to be relative TO
    // unless the AI is told what today is — without that anchor, it can
    // hallucinate a date from its own training-data era. Even with the
    // anchor now in place, this confirms the receiving code independently
    // rejects a past date rather than trusting the AI got it right.
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

    // The AI free-generates transport.from_code independently of the "from"
    // field in the same JSON response — it can (and does) name the origin
    // correctly while still defaulting the flight route to Manila, since
    // the prompt's own "default Manila if not mentioned" instruction bleeds
    // into the transport leg even when a different origin was clearly given.
    public function test_processAiTrip_derives_transport_codes_from_resolved_cities_not_the_ais_own(): void
    {
        $user = User::factory()->create();
        $this->fakeGeminiPackage([
            'from'      => 'Cebu City',
            'to'        => 'Davao',
            // The AI hallucinated MNL here despite correctly naming Cebu
            // City as the origin above — this is the exact bug reported.
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

    // proceedToWizardItinerary() is the only point a conversation ever
    // actually ends (there's no separate "start a new chat" reset), so
    // it's the one place a finished conversation gets archived.
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

    public function test_history_panel_lists_past_conversations_newest_first(): void
    {
        $user = User::factory()->create();
        // created_at isn't fillable (Eloquent manages timestamps itself),
        // so backdating it has to happen after creation, not via create().
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

    // The id is a plain public property, settable directly over the wire
    // protocol like any other — the computed property backing it must
    // independently re-check ownership rather than trusting the id alone.
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

    // The core of this feature: once every slot is filled, the very next
    // message must show a summary + confirmation question instead of
    // immediately kicking off generation.
    public function test_confirmation_summary_shown_once_all_slots_are_filled(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('aiStep', ''); // NOT 'loading' — generation must not have started
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertSame('assistant', $lastMessage['role']);
        $this->assertStringContainsString('Manila', $lastMessage['text']); // origin, not just the destination
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

    // Declining (or anything that isn't a clear "yes") must never be
    // silently treated as approval — no plan should be generated, and the
    // conversation should still be waiting for an actual confirmation.
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

    // The exact bug reported live: asking "what's my budget?" while
    // reviewing the confirmation summary used to get misread as a "change
    // my budget" edit request with no value given — which then asked the
    // traveler to re-enter their budget from scratch, and trapped every
    // later reply (including a plain "yes") as a failed attempt to answer
    // THAT, with no way back to actually confirming the trip.
    public function test_asking_a_status_question_during_confirmation_answers_it_without_getting_stuck(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', "what's my budget?")->call('automateTrip');

        // Answered directly — not misread as an edit request.
        $component->assertSet('awaitingSlot', 'confirmation');
        $component->assertSet('pendingEditSlot', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('30,000', $lastMessage);
        $this->assertStringNotContainsString("What's your budget", $lastMessage);

        // The conversation must still be answerable afterward — the whole
        // point of the bug was that it got permanently stuck here.
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
        $component->assertSet('aiFrom', 'Manila');       // untouched
        $component->assertSet('aiBudgetMax', 30000);      // untouched
        $component->assertSet('awaitingSlot', 'confirmation'); // still reviewing, not generating
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
        $component->assertSet('aiTo', 'Boracay'); // untouched
        $component->assertSet('awaitingSlot', 'confirmation');
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

    // Dates used to be the one slot that couldn't be edited during
    // confirmation at all (destination/origin/budget/travelers all could)
    // — this is the one-shot case, where the new value has only a single
    // "to" in it so the connector-based value extraction isn't ambiguous.
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

    // "Sept" is a very common informal abbreviation that the month list
    // was missing (only "Sep"/"September" were recognized) — found live
    // while testing the date-editing feature above.
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

    // A date RANGE naturally contains its own "to" ("Aug 20 to 25"), which
    // collides with applySlotEdit()'s "value follows the last connector
    // word" heuristic — "change dates to Aug 20 to 25" ends up extracting
    // just "25" as the value, which isn't a parseable date on its own.
    // Rather than silently applying a wrong date (or failing forever),
    // this must gracefully fall back to asking for the value directly —
    // and that follow-up answer must actually work, since the whole
    // point of this feature is that dates are no longer a dead end during
    // confirmation.
    // Previously this fell back to asking again — "change dates to August
    // 20 to 25" has a "to" INSIDE the range itself, which collided with
    // applySlotEdit()'s "value follows the last connector word" search
    // and only extracted "25". Fixed by trying the date range against the
    // whole message directly (applySlotEdit()'s dates special-case)
    // instead of relying on that connector search for dates.
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

    // The exact bug reported live: unlike destination/budget/travelers, a
    // bare date range with NO "dates" keyword at all ("August 20 to 25"
    // by itself) used to be silently discarded with the generic decline
    // message — even though destination/budget/etc. also require their
    // own keyword, a real date range is unambiguous enough to not need one.
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

    // A reply that doesn't name any of the editable slots (and isn't a
    // "yes") must fall through to the plain decline message, not silently
    // do nothing or crash.
    public function test_unrecognized_reply_during_confirmation_falls_back_to_decline_message(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'hmm let me think about it')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');      // untouched
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('/reset', $lastMessage['text']);
    }

    // The exact bug reported live: naming a slot without a value ("also
    // the budget") was falling through to the generic decline message
    // instead of asking what the new value should be.
    public function test_naming_a_slot_without_a_value_asks_for_the_value_instead_of_declining(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');

        $component->assertSet('pendingEditSlot', 'budget');
        $component->assertSet('aiBudgetMax', 30000); // untouched so far
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringNotContainsString('/reset', $lastMessage['text']); // not the generic decline
    }

    // The exact bug reported live: "other destination" names the field
    // but gives no value, setting pendingEditSlot='destination' — the
    // very next reply, "give me top 5 destination", used to get tried as
    // a literal (and inevitably failing) place name instead of being
    // recognized as a request for alternatives, because the
    // pendingEditSlot handler ran before the options-request check ever
    // got a chance to see it.
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

        $component->assertSet('aiBudgetMax', 30000); // unchanged
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last();
        $this->assertStringContainsString('No changes made', $lastMessage['text']);
    }

    // An unparseable reply while a value is pending must re-ask, not
    // silently drop the edit or crash.
    public function test_unparseable_reply_while_pending_edit_asks_again(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'also the budget')->call('automateTrip');
        $component->set('aiPrompt', 'banana')->call('automateTrip');

        $component->assertSet('aiBudgetMax', 30000); // unchanged
        $component->assertSet('pendingEditSlot', 'budget'); // still waiting
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    // The exact bug reported live: editing the destination to the same
    // place as the (untouched) origin must be rejected the same way the
    // normal first-time flow already rejects it — not silently accepted
    // just because it came through the edit path instead.
    public function test_editing_destination_to_match_existing_origin_is_rejected(): void
    {
        $user = User::factory()->create();

        // withAllSlotsFilled() sets aiFrom = 'Manila'.
        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my destination to manila')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay'); // unchanged — edit rejected
        $component->assertSet('aiFrom', 'Manila');
        $component->assertSet('pendingEditSlot', 'destination'); // asked to try again
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    public function test_editing_origin_to_match_existing_destination_is_rejected(): void
    {
        $user = User::factory()->create();

        // withAllSlotsFilled() sets aiTo = 'Boracay'.
        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my origin to boracay')->call('automateTrip');

        $component->assertSet('aiFrom', 'Manila'); // unchanged — edit rejected
        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('pendingEditSlot', 'origin');
    }

    // The other bug reported live: the retry question repeated the exact
    // same first-time phrasing forever instead of escalating, like every
    // other slot in this app already does after a second miss.
    public function test_retry_message_escalates_after_repeated_invalid_destination_edits(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        // "xxxxxxxx" is caught by the deterministic gibberish check (no AI
        // call needed), so this stays fast and fully offline.
        $component->set('aiPrompt', 'change my destination to xxxxxxxx')->call('automateTrip');
        $firstReply = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Sure! Where would you like to go?', $firstReply);

        $component->set('aiPrompt', 'xxxxxxxx')->call('automateTrip');
        $secondReply = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString("didn't quite catch", $secondReply);
        $this->assertNotSame($firstReply, $secondReply);

        $component->assertSet('pendingEditSlot', 'destination'); // still waiting
    }

    // The exact bug reported live: /reset used to pre-seed a "Conversation
    // reset — where would you like to go?" chat bubble and jump straight
    // to awaitingSlot='destination', which skipped right past the actual
    // blank landing screen ("Good day, {name}!" + composer) a genuinely
    // new conversation shows — so /reset looked different from opening
    // TARA fresh. Reset now clears messages back to empty (same as
    // mount() with no saved draft) so that screen shows again, and lets
    // the very next message go through the exact same first-message path
    // a brand new conversation would — which already handles a bad first
    // answer (e.g. gibberish) correctly on its own: not misread as
    // off-topic, not accepted as a place, and it's what naturally sets
    // awaitingSlot to 'destination' once it's asked the follow-up
    // question. Uses gibberish ("xxxxxxxx") rather than a real word like
    // "dota" so the place check is rejected by the deterministic
    // gibberish filter with no AI call needed. Mistral (tried first in
    // extractWithAi()'s chain) is faked to return a definite "not
    // off-topic" so the classification step is deterministic too.
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
        $this->assertStringNotContainsString("Travel Assistant", $lastMessage); // not the off-topic message
        $component->assertSet('aiTo', ''); // still correctly not accepted as a place
        $component->assertSet('awaitingSlot', 'destination');
    }

    // The exact bug reported live: "I want to change my destination in
    // Bohol" has an unrelated "to" in "want to", and the real value comes
    // after "in", not "to". The old code only ever looked for "to", so it
    // grabbed the whole garbled tail ("change my destination in Bohol")
    // as the value instead of just "Bohol".
    public function test_editing_a_slot_using_in_as_the_connector_word(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'I want to change my destination in bohol')->call('automateTrip');

        $component->assertSet('aiTo', 'Bohol');
        $component->assertSet('aiFrom', 'Manila'); // untouched
        $component->assertSet('awaitingSlot', 'confirmation');
    }

    // The exact bug reported live: "change my budget into 5000" failed to
    // apply in one shot — the connector search only recognized " to " and
    // " in " as their own space-bound words, and "into" doesn't contain
    // either as a separate word (the "in" is immediately followed by "to",
    // not a space), so no value was ever extracted. It silently fell back
    // to asking "What's your budget?" and waiting for a second message.
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

        // Applied in one shot — and since 5000 is unrealistic for a
        // 3-day international trip, the usual shortfall guard catches it
        // immediately rather than silently accepting it.
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
    }

    // The exact bug reported live: a brand new conversation where the
    // very first message is "Im from Cebu" correctly captures the ORIGIN
    // (aiFrom) — but since a destination still isn't known, TARA needs to
    // ask for one next. The wording was wrong: it showed the RETRY phrase
    // ("Sorry, I didn't quite catch a destination there...") as if the
    // traveler had just tried and failed to name a destination, when
    // really they never attempted one at all — they were only answering
    // origin. Root cause: hasPlaceLikeCandidate() just checks "does this
    // text contain a capitalized word," with no sense of direction, so
    // "Cebu" in "from Cebu" counted as a failed DESTINATION guess.
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

    // Same root cause, worse consequence: TARA has ALREADY explicitly
    // asked "where would you like to go?" (awaitingSlot='destination'),
    // and the traveler replies "Im from Cebu" — clearly origin info, not
    // an answer to that question. Before this fix, the destination slot's
    // raw-text matcher didn't check for "from"-style phrasing at all, so
    // it happily resolved "Cebu" out of the sentence and set it as the
    // DESTINATION too — silently producing a nonsensical Cebu-to-Cebu
    // trip that the usual same-place guard never got a chance to catch.
    public function test_answering_a_destination_question_with_from_phrasing_updates_origin_not_destination(): void
    {
        $this->fakeExtraction();
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'destination')
            ->set('aiPrompt', "Im from Cebu")->call('automateTrip');

        $component->assertSet('aiFrom', 'Cebu');
        $component->assertSet('aiTo', ''); // NOT wrongly set to Cebu too
        $component->assertSet('awaitingSlot', 'destination'); // still needs one
    }

    // The exact bug reported live: mid-confirmation, "change my travel
    // date to 3days" was rejected with "I still need actual travel
    // dates" — this local (no-AI) date parser only ever recognized real
    // calendar dates/ranges, never a pure duration like "3 days" or
    // "1 week", even though the AI-assisted first-message path already
    // handled durations fine. Anchors the new duration on the trip's
    // EXISTING (future) start date rather than today, so "make it 3
    // days" reads as shortening/lengthening the same trip, not moving it.
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

    // Same fix, word-form duration ("1 week") — the traveler's exact
    // follow-up question was "why will '1 week' work [but not '3days']?"
    // It didn't, before this fix; both are handled identically now.
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

    // The exact scenario from the spec: a rejected/unresolved origin must
    // not hijack a later, unrelated question. TARA is still waiting on
    // origin (simulating "I'm from Nicsland" having just been rejected)
    // when the traveler asks about budget instead — that should get
    // answered about budget, not another "where are you from?" nag.
    public function test_asking_about_budget_while_origin_is_still_unresolved_answers_about_budget(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'origin')
            ->set('aiFrom', '') // still unresolved — e.g. "Nicsland" was just rejected
            ->set('aiPrompt', 'what is my budget?')
            ->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('budget', strtolower($lastMessage));
        $this->assertStringNotContainsString('traveling from', $lastMessage); // not the origin re-ask
        $component->assertSet('aiFrom', ''); // origin still untouched, just not re-nagged about right now
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

    // Must not swallow a genuine advice-seeking question just because it
    // contains the word "destination" — only an explicit "my destination"
    // possessive counts as recalling an existing answer.
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
        $this->assertStringContainsString('not set yet', $lastMessage); // origin/budget/etc. still blank
    }

    // The exact bug reported live: answering a completely different slot
    // while travelers is being awaited got its number cannibalized —
    // "My Budget is 50000" contains no travelers info at all, but the
    // old code grabbed the first 1-2 digits ("50") out of "50000" and
    // saved it as 50 travelers.
    public function test_budget_answer_while_awaiting_travelers_does_not_corrupt_travelers_count(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction(); // no travelers/budget in the AI's own read either

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('awaitingSlot', 'travelers')
            ->set('aiPrompt', 'My Budget is 50000')
            ->call('automateTrip');

        $component->assertSet('aiTravelers', 0); // NOT 50
    }

    // Same class of bug, the other direction — a travelers answer must
    // not get cannibalized into the budget while budget is being awaited.
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

    // Regression check: a bare, unlabeled number genuinely is ambiguous
    // and must still answer whatever slot is actually being asked — the
    // fix must only skip extraction when a DIFFERENT slot is explicitly
    // named, not numbers in general.
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

    // The exact reported gap: answering budget while travelers is being
    // asked no longer corrupts data (see the earlier tests above), but it
    // was still silently absorbing the budget with zero acknowledgment —
    // TARA would just re-ask about travelers as if nothing was said.
    public function test_budget_answered_out_of_turn_is_acknowledged(): void
    {
        $user = User::factory()->create();
        $this->fakeExtraction(['budget_min' => 50000, 'budget_max' => 50000]);

        // destination/origin must already be resolved so missingSlotKey()
        // actually lands on 'travelers' (it's checked before budget/dates)
        // instead of re-asking for destination first.
        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiTo', 'Cebu')
            ->set('aiFrom', 'Manila')
            ->set('awaitingSlot', 'travelers')
            ->set('aiPrompt', 'My Budget is 50000')
            ->call('automateTrip');

        $component->assertSet('aiBudgetMin', 50000);
        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('aiTravelers', 0); // still correctly not corrupted
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got your budget', $lastMessage);
        $this->assertStringContainsString('50,000', $lastMessage);
    }

    // The exact bug reported live: a big number appearing anywhere in a
    // message (even one about something completely unrelated) used to
    // silently overwrite an already-set budget with zero acknowledgment.
    public function test_a_stray_number_with_no_correction_cue_does_not_overwrite_an_existing_budget(): void
    {
        $user = User::factory()->create();
        // Dates are still missing, so extractWithAi() (a real AI call) runs
        // unconditionally after the regex pass — must be faked or this
        // silently hits the live network.
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

    // A deliberate correction ("actually make it X") must still work —
    // the fix narrows the loose catch-all, it doesn't remove the ability
    // to correct the budget entirely.
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

    // The exact bug reported live: a genuine travel-related question
    // ("is Cebu safe to visit?") used to get silently treated as a FAILED
    // attempt to answer the currently-missing slot, escalating missCount
    // and just repeating the same prompt with no acknowledgment at all.
    public function test_a_tangential_question_is_acknowledged_without_inflating_miss_count(): void
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
            ->set('missCount', 2)
            ->set('aiPrompt', 'is Cebu safe to visit?')
            ->call('automateTrip');

        $component->assertSet('missCount', 0);
        $component->assertSet('awaitingSlot', 'dates');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Good question', $lastMessage);
    }

    // The acknowledgment must only fire for an OUT-OF-TURN capture — a
    // normal, in-order budget answer already gets acknowledged by the
    // natural flow of moving to the next question, so prefixing "Got your
    // budget!" there would just be noisy and redundant.
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

    // The regex path (parseAiPrompt/detectAndConvertCurrency) already
    // converts a named foreign currency to pesos — this proves the AI
    // extraction fallback (extractWithAi, reached when the regex path
    // finds no budget-shaped match at all) now does the same instead of
    // silently storing the foreign figure as if it were already pesos.
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
            // currencyRate() now tries a live TwelveData lookup before
            // falling back to the hardcoded SUPPORTED_CURRENCIES rate — fake
            // it here so this test stays deterministic instead of depending
            // on the real, ever-changing USD/PHP exchange rate.
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

    // The regex path (detectAndConvertCurrency, reached via parseAiPrompt())
    // now prefers a live TwelveData rate over the hardcoded SUPPORTED_CURRENCIES
    // table — this confirms it actually uses the live figure (61.71, not the
    // static 56) when the API call succeeds.
    public function test_regex_currency_conversion_uses_the_live_twelvedata_rate_when_available(): void
    {
        $user = User::factory()->create();
        // Destination is still missing, so extractWithAi() also runs — fake
        // it too so the test doesn't depend on a real, uncontrolled AI call.
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

    // The exact bug reported live: destination-currency parsing happens
    // LATER in parseAiPrompt() than the currency/budget block, so a
    // one-shot message naming both the destination and a foreign-currency
    // budget ("go to Japan... budget is $500") used to build the "Got it —
    // $500 is about ₱X" acknowledgment before aiTo was resolved, silently
    // dropping the destination-currency mention even though the
    // destination was in the very same message. It's now appended where
    // the notice is actually shown in automateTrip(), after parseAiPrompt()
    // has fully run and aiTo is known. The peso figure itself is no longer
    // shown once a destination conversion is available — pesos is only
    // ever surfaced as the fallback when there's nothing to convert into
    // yet (see the "still missing" test below).
    public function test_currency_conversion_message_mentions_destination_currency_when_named_in_the_same_message(): void
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
        $allText = collect($component->get('messages'))->pluck('text')->implode(' ');
        $this->assertStringNotContainsString('₱30,855', $allText);
        $this->assertStringContainsString('is about', $allText);
        $this->assertStringContainsString('in Japan', $allText);
    }

    // No destination named yet — there's nothing to convert into, so the
    // peso figure is still the only thing worth showing.
    public function test_currency_conversion_message_falls_back_to_pesos_when_no_destination_is_known_yet(): void
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
        $allText = collect($component->get('messages'))->pluck('text')->implode(' ');
        $this->assertStringContainsString('is about ₱30,855', $allText);
    }

    // There is no hardcoded exchange-rate fallback — if TwelveData is
    // unreachable or errors out, the budget must be left unset (not
    // silently misread as pesos, e.g. "$500" treated as ₱500) and the
    // traveler asked to try again, rather than trusting a stale number.
    public function test_regex_currency_conversion_is_left_unset_when_twelvedata_is_unavailable(): void
    {
        $user = User::factory()->create();
        // Destination is still missing after this message, so automateTrip()
        // also calls extractWithAi() — fake it to return nothing so a real,
        // non-deterministic AI call can't separately (mis)interpret "$500"
        // as a plain ₱500 and mask the behavior this test is checking.
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

    // Same no-fallback guarantee through the AI-extraction path
    // (extractWithAi's budget_currency handling) as the regex path above.
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
        // suggestDestinations() tries Mistral first (same chain as
        // extractWithAi()/suggestDestination() elsewhere in this file).
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode(['destinations' => $destinations])]]],
            ], 200),
        ]);
    }

    // The exact bug reported live: at the final confirmation step (every
    // slot already filled), asking "recommend top 5 destination" used to
    // get misread by applySlotEdit() as a failed attempt to name ONE
    // specific place ("destination" is a recognized keyword, and no value
    // followed it), leaving the traveler stuck being asked "which place
    // would you like to go to?" instead of getting the list they asked for.
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
        $component->assertSet('awaitingSlot', 'confirmation'); // still reviewing, not derailed
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Boracay', $lastMessage);
        $this->assertStringContainsString('alternatives', $lastMessage);
    }

    // Continuing from the scenario above: picking a numbered option must
    // actually apply it and return to the (now updated) confirmation
    // summary, not get misread as a failed "type the place name by hand"
    // attempt just because it's a bare number.
    public function test_picking_a_recommended_destination_during_confirmation_updates_and_shows_summary(): void
    {
        $user = User::factory()->create();
        $this->fakeDestinationSuggestions(['Boracay', 'Siargao', 'Bohol', 'Palawan', 'Cebu']);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you recommend top 5 destination')->call('automateTrip');
        $component->set('aiPrompt', '2')->call('automateTrip'); // picks "Siargao"

        $component->assertSet('aiTo', 'Siargao');
        $component->assertSet('pendingEditSlot', '');
        $component->assertSet('aiDestinationChoices', []);
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Siargao', $lastMessage);
        $this->assertStringContainsString('Would you like me to proceed', $lastMessage);
    }

    // The single-suggestion path ("recommend me somewhere" with no count)
    // must work too, not just the "top N" list version.
    public function test_single_recommendation_during_confirmation_updates_and_shows_summary(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'api.mistral.ai/*' => Http::response([
                // Deliberately different from withAllSlotsFilled()'s
                // pre-set 'Boracay' — proves the value actually changes,
                // not just that it coincidentally matches what was
                // already there.
                'choices' => [['message' => ['content' => json_encode(['destination' => 'Bohol'])]]],
            ], 200),
        ]);

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'can you recommend a destination for me')->call('automateTrip');

        // Not committed yet — the traveler hasn't accepted it.
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

        // The original destination must survive an explicit decline —
        // and generation must never start off the back of it.
        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('aiStep', '');
    }

    // A recommendation-trigger message like "anywhere" also makes
    // isRecommendationRequest()'s handler try knownPlaceName($userText)
    // first (is "anywhere" itself literally a place?) — which runs the
    // full two-provider place-verification pipeline before ever reaching
    // the actual suggestDestination() call. All three OpenAI-compatible
    // providers (Mistral/Groq/OpenRouter) need faking, distinguished by
    // prompt content, or this silently falls through to live network
    // calls and the test becomes slow and non-deterministic.
    private function fakeSingleDestinationSuggestion(string $destination): void
    {
        $respond = function ($request) use ($destination) {
            $promptText = $request->body();
            if (str_contains($promptText, 'an actual city, town, or island')) {
                // The place-verification prompt — the trigger phrase
                // itself ("anywhere", "idk", ...) is never a real place.
                return Http::response(['choices' => [['message' => ['content' =>
                    json_encode(['is_real_place' => false, 'name' => null, 'iata_code' => null]),
                ]]]], 200);
            }
            // The actual recommendation-generation prompt.
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

    // The exact bug reported live: saying "anywhere" used to commit the
    // AI's suggestion straight to aiTo with zero confirmation — a real
    // trip could reach the final summary carrying a destination the
    // traveler never actually agreed to.
    public function test_recommendation_is_not_committed_until_accepted(): void
    {
        $user = User::factory()->create();
        $this->fakeSingleDestinationSuggestion('Siargao');

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'anywhere')->call('automateTrip');

        $component->assertSet('aiTo', ''); // NOT committed yet
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

    // The exact bug reported live: "I'm not interested in Siargao" was
    // silently ignored — it doesn't start with "no"/"not" the way the old
    // regex required, so it fell through to normal processing, and
    // Siargao (never actually accepted) survived all the way to the
    // final trip confirmation.
    public function test_rejecting_a_recommendation_with_natural_phrasing_clears_it(): void
    {
        $user = User::factory()->create();
        $this->fakeSingleDestinationSuggestion('Siargao');

        $component = Livewire::actingAs($user)->test(Llm::class)
            ->set('aiPrompt', 'anywhere')->call('automateTrip');
        $component->set('aiPrompt', "I'm not interested in Siargao")->call('automateTrip');

        $component->assertSet('aiTo', ''); // never committed, still empty
        $component->assertSet('pendingPlaceSuggestion', null);
        $component->assertSet('rejectedDestinations', ['Siargao']);
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('Siargao', $lastMessage); // re-asks cleanly, doesn't repeat the rejected name
    }

    // A short, direct decline ("no") must still work exactly as before —
    // the broadened rejection check must not have narrowed anything.
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

    // The exact bug reported live: replying "more" to a single suggestion
    // used to silently fall through and get misread as a failed attempt
    // to NAME a destination ("Sorry, I didn't quite catch a destination
    // there..."), instead of being recognized as an obvious request for
    // alternatives — which "more options" (with the extra word) already
    // handled correctly before this fix.
    public function test_replying_more_to_a_single_recommendation_shows_alternatives(): void
    {
        $user = User::factory()->create();

        // A single Http::fake() covering all three prompt shapes this
        // scenario can reach, distinguished by content — calling
        // Http::fake() a second time mid-test to swap the response (as an
        // earlier draft of this test did) does NOT reliably override the
        // first registration for the same URL pattern; confirmed via
        // Http::recorded() showing the OLD fake still being served after
        // a second fake() call. One combined fake avoids that pitfall.
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

    // The exact bug reported live: replying "other" to an ALREADY-shown
    // list of alternatives (not a single suggestion — a numbered list of
    // several) used to silently clear the list and fall through, landing
    // on "I didn't quite catch a destination there" — same bug as the
    // single-suggestion "more" case, one level up.
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

    // Once rejected, the same destination must never be suggested again —
    // verified by inspecting the actual prompt sent to the AI, not just
    // the final result (which could coincidentally avoid it anyway).
    // Unit-level check on suggestDestination() itself (via reflection,
    // since it's private) rather than through the full chat flow — a live
    // "anywhere" message can also trigger an unrelated place-verification
    // AI call (checking whether "anywhere" itself is a real place), which
    // competes for the same fake HTTP queue and makes the full round-trip
    // version of this test unreliable. This isolates exactly the one
    // thing that needs checking: does the exclude list actually reach
    // the prompt.
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

    // Recommendations used to be generated with zero knowledge of the
    // traveler's budget — only their interests — so they could suggest
    // something like Italy or the Maldives regardless of what the
    // traveler said they could actually spend.
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

    // No budget known yet — the prompt must not fabricate a constraint
    // that was never actually given.
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

    // The exact bug reported live: when a recommendation attempt itself
    // fails (every AI provider unavailable, or nothing it suggested
    // resolved to a known place), this used to silently fall through to
    // the missing-slot logic, which showed "I didn't quite catch a
    // destination there" — wrongly implying the TRAVELER's wording was
    // the problem, when the recommendation step was what actually failed.
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

    // Requested directly: on a budget that clearly can't cover an
    // international trip, TARA should say so plainly instead of silently
    // substituting a domestic destination with no explanation. No HTTP
    // fake needed — the shortfall check short-circuits before any AI call.
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

    // The same request on a realistic international budget must proceed
    // normally — this check should only ever block a CLEAR mismatch.
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

    // Same shortfall check, reached through the confirmation-time
    // recommendation path (tryDestinationAlternatives()) rather than the
    // main pre-confirmation flow — both call sites share the same guard.
    public function test_international_shortfall_message_also_applies_during_confirmation(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiFrom', 'Cebu')
            ->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDays', 7)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'recommend an international destination for me')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
    }

    // The exact bug reported live: typing a destination directly
    // ("Japan") never went through the recommendation flow at all, so
    // the keyword-based shortfall check never ran — the confirmation
    // summary showed a Japan trip on a ₱15,000 budget with zero warning.
    // This check runs the moment every slot is known, regardless of
    // whether the destination was typed directly, AI-suggested, or edited.
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

        // Destination is KEPT — only budget gets cleared, since the
        // message now only offers "increase your budget" as the fix.
        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
        $this->assertStringContainsString('15,000', $lastMessage);
        $this->assertStringNotContainsString('more affordable destination', $lastMessage);
    }

    // A domestic destination on the exact same low budget must never be
    // blocked — this check only ever applies to international destinations.
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

    // An international destination on a genuinely sufficient budget must
    // proceed normally — this check only blocks a clear mismatch.
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

    // The exact bug reported live: the shortfall check above only ran
    // when every slot FIRST became known — changing the destination
    // later, during an already-active confirmation ("change the
    // destination to japan"), went through applySlotEdit() directly and
    // committed Japan with zero check, right past the confirmation
    // summary and a bare "yes" away from actually generating the trip.
    public function test_editing_destination_to_an_unaffordable_international_place_during_confirmation_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change the destination to japan')->call('automateTrip');

        // Destination is KEPT as the edited value — only budget clears,
        // since the message now only offers "increase your budget".
        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);

        // A stray "yes" afterward must never slip through to generation.
        $component->set('aiPrompt', 'yes')->call('automateTrip');
        $component->assertSet('aiStep', '');
    }

    // Same guard, reached through the OTHER edit path — a
    // pendingEditSlot follow-up (e.g. "other destination" asked "where
    // would you like to go?", then the traveler names a place directly)
    // instead of a one-shot "change destination to X".
    public function test_pending_edit_slot_follow_up_to_an_unaffordable_international_place_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiBudgetMin', 15000)
            ->set('aiBudgetMax', 15000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        // Simulates having just been asked "where would you like to go?"
        // (e.g. from "other destination" naming the field with no value).
        $component->set('pendingEditSlot', 'destination')
            ->set('aiPrompt', 'japan')
            ->call('automateTrip');

        // Destination is KEPT as the named value — only budget clears,
        // since the message now only offers "increase your budget".
        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $component->assertSet('pendingEditSlot', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);
    }

    // The exact bug reported live: the shortfall guard above only ever
    // checked DESTINATION edits — lowering the BUDGET instead (via
    // "change my budget" → a too-low number) on an already-set
    // international destination sailed through with no check at all,
    // over and over, however low the traveler typed.
    public function test_editing_budget_to_an_unaffordable_amount_on_an_international_destination_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 45000)
            ->set('aiBudgetMax', 45000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget')->call('automateTrip');
        $component->assertSet('pendingEditSlot', 'budget');

        $component->set('aiPrompt', '15000')->call('automateTrip');

        // Destination is KEPT — only the too-low budget attempt is
        // rejected, resetting back to awaitingSlot='budget' so the next
        // reply is read as a fresh (hopefully sufficient) budget answer.
        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 0);
        $component->assertSet('aiBudgetMax', 0);
        $component->assertSet('awaitingSlot', 'budget');
        $component->assertSet('pendingEditSlot', '');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('too low', $lastMessage);

        // A stray "yes" afterward must never slip through to generation.
        $component->set('aiPrompt', 'yes')->call('automateTrip');
        $component->assertSet('aiStep', '');
    }

    // Same guard, reached through the ONE-SHOT edit path — "change budget
    // to X" in a single message instead of a pendingEditSlot follow-up.
    public function test_one_shot_budget_edit_to_an_unaffordable_amount_on_an_international_destination_is_blocked(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 45000)
            ->set('aiBudgetMax', 45000)
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

    // A SUFFICIENT budget edit on an international destination must still
    // proceed normally — this guard only blocks a clear mismatch.
    public function test_editing_budget_to_a_sufficient_amount_on_an_international_destination_proceeds_normally(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiTo', 'Japan')
            ->set('aiBudgetMin', 45000)
            ->set('aiBudgetMax', 45000)
            ->set('aiDays', 8)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change budget to 50000')->call('automateTrip');

        $component->assertSet('aiTo', 'Japan');
        $component->assertSet('aiBudgetMin', 50000);
        $component->assertSet('aiBudgetMax', 50000);
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got it, updated!', $lastMessage);
    }

    // The exact bug reported live: blockUnaffordableSlotEdit() only ever
    // checked the INTERNATIONAL shortfall case, so editing the budget down
    // to an absurdly low amount (₱1,000) on a DOMESTIC destination (Boracay
    // here, Davao in the live report) sailed through with no floor check
    // at all — even though the same ₱10,000 minimum already blocks a too-low
    // budget the first time it's entered (see the "too low to plan a real
    // trip" check earlier in automateTrip()). The edit path now enforces
    // that same flat floor regardless of destination.
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

        // A stray "yes" afterward must never slip through to generation.
        $component->set('aiPrompt', 'yes')->call('automateTrip');
        $component->assertSet('aiStep', '');
    }

    // A sufficient budget edit on a domestic destination must still
    // proceed normally — the new floor check only blocks a clear mismatch.
    public function test_editing_budget_to_a_sufficient_amount_on_a_domestic_destination_proceeds_normally(): void
    {
        $user = User::factory()->create();

        $component = $this->withAllSlotsFilled(
            Livewire::actingAs($user)->test(Llm::class)
        )->set('aiPrompt', 'please continue')->call('automateTrip');

        $component->set('aiPrompt', 'change my budget to 15000')->call('automateTrip');

        $component->assertSet('aiTo', 'Boracay');
        $component->assertSet('aiBudgetMin', 15000);
        $component->assertSet('aiBudgetMax', 15000);
        $component->assertSet('awaitingSlot', 'confirmation');
        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringContainsString('Got it, updated!', $lastMessage);
    }

    // buildSerpApiPackage()'s three real search calls (flights/hotels,
    // restaurants, attractions) all hit https://serpapi.com/search with
    // different "engine" query params — flights/hotels are distinguished
    // by engine alone, but restaurants and attractions both use
    // engine=google_maps, so those two are distinguished by the search
    // phrase in the "q" param instead. Also blocks the SerperService
    // fallback (google.serper.dev) and the AI enrichment chain, so a test
    // gets exactly the raw values it configured here — nothing swapped in
    // by a fallback or an enrichment pass it didn't ask for.
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

        // Transport/food costs are the flight price / food budget share
        // multiplied by travelers (2); accommodation is the hotel's total
        // stay cost as-is, not multiplied — only those two get scaled per
        // traveler in the real code.
        $this->assertSame(6000, $result['transport']['cost']);
        $this->assertStringContainsString('Cebu Pacific', $result['transport']['detail']);
        $this->assertSame('Test Hotel Boracay', $result['accommodation']['name']);
        $this->assertSame(4, $result['accommodation']['stars']);
        $this->assertSame(10000, $result['accommodation']['cost']);
        $this->assertSame('Test Restaurant', $result['food']['name']);
        $this->assertSame(12000, $result['food']['cost']);
        $this->assertCount(3, $result['attractions']['items']);
        // searchAttractions() always marks its results "Free" regardless
        // of any price data — a real, existing quirk of that method, not
        // something this test is asserting as ideal behavior.
        $this->assertSame(0, $result['attractions']['cost']);
        $this->assertSame(28000, $result['total']);
        $this->assertSame(30000, $result['budget']);
        $this->assertSame(93, $result['pct']);
    }

    // Transport/accommodation/food all have their own budget-based caps
    // or fixed allocations that keep them from ever exceeding their
    // share — the ONLY way the assembled total can land over budget is
    // through the attractions default fallback (a flat ₱300 "City Tour"
    // item, unscaled to budget size). This test forces exactly that: a
    // small budget, a cheap flight (so transport/accommodation/food all
    // land under their caps), and no live attractions data at all —
    // confirming the priciest attraction item gets trimmed until the
    // package is back at or under budget.
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

    // generateAiPackage() is the last-resort, fully offline fallback (no
    // network calls at all) — used when both the AI free-generation and
    // SerpAPI live-search paths fail. Entirely deterministic, so this
    // needs no HTTP faking, just checking its math and data lookup.
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
        // Raw figures (5400 + 15001 + 21000 + 800 = 42201) blow the
        // ₱30,000 budget by ₱12,201 — capPackageToBudget() squeezes this
        // back down to fit: first the one paid attraction (Paraw Sailing,
        // ₱800) is dropped, leaving only the two free items, then food is
        // reduced the rest of the way (21000 - 11401 = 9599), which is
        // still comfortably above the ₱300/day/traveler floor (₱4,200).
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

    // A profile saved via Profile Builder with a foreign starting point
    // stores daily_budget already converted to pesos, plus the traveler's
    // real original number/currency untouched (daily_budget_local/
    // daily_budget_currency). The offer message should describe the
    // budget using that real currency, not the pesos figure underneath.
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

    public function test_mount_does_not_offer_when_no_profile_exists(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Llm::class);

        $component->assertSet('messages', []);
        $component->assertSet('pendingProfileOffer', false);
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

    // The exact bug reported live: a traveler's account had an unrelated
    // "display currency" account setting (USD, from Settings). Accepting
    // saved preferences correctly applied the peso-converted budget, but
    // never updated aiCurrency to match the profile's real local currency
    // (CAD) — so the very next message re-displayed the peso figure
    // converted into the account's USD setting instead ("$361"), a third,
    // unrelated number that didn't match the 500 CAD the traveler actually
    // saved. Confirms accepting now correctly carries the local currency
    // forward so it's echoed back consistently.
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

    public function test_confirmation_summary_shows_destination_currency_for_an_international_destination(): void
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
        $this->assertStringContainsString('Destination budget: ¥118,421', $lastMessage);
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
            ->set('aiBudgetMin', 45000)
            ->set('aiBudgetMax', 45000)
            ->set('aiPrompt', 'please continue')->call('automateTrip');

        $lastMessage = collect($component->get('messages'))->last()['text'];
        $this->assertStringNotContainsString('Destination budget', $lastMessage);
        $this->assertStringContainsString('Would you like me to proceed', $lastMessage);
    }

    public function test_autosave_draft_persists_destination_currency_and_budget_to_the_trip(): void
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
        $this->assertEquals(118421.05, (float) $trip->destination_budget);
    }
}
