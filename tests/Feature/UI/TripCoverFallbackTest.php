<?php
namespace Tests\Feature\UI;

use App\Models\Trip;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A trip with no photo of its own still gets one.
 *
 * cover_image is captured from the chosen hotel, or failing that the first
 * attraction. Plan a trip without accommodation and it is null, and the card
 * used to fall through to a bare gradient that read as a half-loaded image.
 */
class TripCoverFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function traveler(): User
    {
        $user = User::factory()->create();
        UserProfile::create(['user_id' => $user->id]);

        return $user;
    }

    private function tripWithoutCover(User $user, array $attrs = []): Trip
    {
        return Trip::factory()->create($attrs + [
            'user_id'     => $user->id,
            'cover_image' => null,
            'status'      => 'upcoming',
            'start_date'  => now()->addDays(10)->toDateString(),
            'end_date'    => now()->addDays(14)->toDateString(),
        ]);
    }

    public function test_a_trip_without_a_cover_is_given_one(): void
    {
        $trip = $this->tripWithoutCover($this->traveler());

        $this->assertNotEmpty($trip->coverImageUrl());
        $this->assertStringContainsString('stockimages/', $trip->coverImageUrl());
    }

    public function test_a_trip_that_has_its_own_cover_keeps_it(): void
    {
        // The fallback must never displace a real hotel photo.
        $trip = $this->tripWithoutCover($this->traveler(), [
            'cover_image' => 'https://example.test/the-actual-hotel.jpg',
        ]);

        $this->assertSame('https://example.test/the-actual-hotel.jpg', $trip->coverImageUrl());
    }

    public function test_a_draft_still_gets_the_draft_image(): void
    {
        // The card blurs itself over this one, so a scenic photo would be an
        // odd thing to blur.
        $trip = $this->tripWithoutCover($this->traveler(), ['status' => 'draft']);

        $this->assertStringContainsString('draftimage.jpg', $trip->coverImageUrl());
    }

    public function test_the_same_trip_always_draws_the_same_picture(): void
    {
        // This is the whole reason the pick is keyed off the id. A genuinely
        // random choice would reshuffle the board on every Livewire refresh.
        $trip = $this->tripWithoutCover($this->traveler());

        $first = $trip->coverImageUrl();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($first, $trip->fresh()->coverImageUrl());
        }
    }

    public function test_neighbouring_trips_do_not_draw_the_same_picture(): void
    {
        // Consecutive ids, and the pool is indexed by id — so a board of
        // cards never shows the same photo twice in a row.
        $user = $this->traveler();
        $covers = [];
        for ($i = 0; $i < 6; $i++) {
            $covers[] = $this->tripWithoutCover($user)->coverImageUrl();
        }

        $this->assertSame($covers, array_values(array_unique($covers)));
    }

    public function test_every_picture_in_the_pool_is_actually_on_disk(): void
    {
        // A missing file here is a broken image on a card, and the pool is a
        // hardcoded list that nothing else checks.
        foreach (Trip::FALLBACK_COVERS as $file) {
            $this->assertFileExists(
                public_path("stockimages/{$file}"),
                "{$file} is in the fallback pool but not in public/stockimages"
            );
        }
    }

    public function test_the_pool_offers_no_accommodation(): void
    {
        // A trip lands here precisely because no accommodation was chosen.
        // A hotel lobby would be claiming something the trip does not have.
        foreach (Trip::FALLBACK_COVERS as $file) {
            foreach (['hotel', 'inn', 'apartment', 'resort '] as $bad) {
                $this->assertStringNotContainsString($bad, $file, "{$file} implies lodging");
            }
        }
    }

    public function test_filenames_with_spaces_survive_the_url(): void
    {
        // Ten of the twenty carry a space; an un-encoded one is a broken src.
        $spaced = array_values(array_filter(Trip::FALLBACK_COVERS, fn ($f) => str_contains($f, ' ')));
        $this->assertNotEmpty($spaced, 'the pool no longer exercises this');

        $user = $this->traveler();
        $urls = [];
        for ($i = 0; $i < count(Trip::FALLBACK_COVERS); $i++) {
            $urls[] = $this->tripWithoutCover($user)->coverImageUrl();
        }

        $this->assertNotEmpty(array_filter($urls, fn ($u) => str_contains($u, '%20')));
        foreach ($urls as $u) {
            $this->assertStringNotContainsString(' ', $u, 'a raw space reached the src attribute');
        }
    }

    public function test_the_card_renders_the_fallback_rather_than_a_bare_gradient(): void
    {
        $user = $this->traveler();
        $trip = $this->tripWithoutCover($user);

        $html = $this->actingAs($user)->get('/saved-trips')->getContent();

        $this->assertStringContainsString($trip->coverImageUrl(), $html);
    }
}
