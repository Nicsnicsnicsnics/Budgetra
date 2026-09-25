<?php
namespace Tests\Feature\Alert;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The notifications list scrolls inside its card.
 *
 * The card was already flex-column with a flex-shrink:0 header, but nothing
 * bounded the rows, so a full page of 20 notifications ran off the bottom of
 * the viewport with no way to reach the last of them.
 */
class NotificationsScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_notification_list_has_a_bounded_scroll_container(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 25) as $i) {
            Notification::create([
                'user_id' => $user->id,
                'type'    => 'trip_saved',
                'message' => "Notification {$i}",
                'is_read' => false,
            ]);
        }

        $html = $this->actingAs($user)->get('/notifications')->assertStatus(200)->getContent();

        $this->assertStringContainsString('class="notif-scroll"', $html);
        $this->assertStringContainsString('overflow-y: auto', $html);
        // The card must shrink, not grow: min-height:0 without flex:1 lets
        // .dash-content squeeze it back to the viewport when the rows overflow,
        // while leaving a short list at its own content height.
        $this->assertStringContainsString('flex-direction:column;min-height:0;', $html);
        $this->assertStringNotContainsString('flex-direction:column;flex:1;', $html);
        // No fixed cap — how many rows fit follows the window.
        $this->assertStringNotContainsString('max-height: calc', $html);
        // The list itself still claims the leftover height and can shrink.
        $this->assertStringContainsString('flex: 1; min-height: 0;', $html);
        // The header must sit outside the scrolling area so it stays put.
        $this->assertStringContainsString('flex-shrink:0', $html);
    }
}
