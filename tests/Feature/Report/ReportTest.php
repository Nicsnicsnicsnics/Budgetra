<?php
namespace Tests\Feature\Report;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/reports')->assertStatus(200);
    }

    public function test_reports_page_shows_user_trips(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Bohol']);

        $this->actingAs($user)->get('/reports')->assertSee('Bohol');
    }

    public function test_pdf_download_returns_pdf(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/reports/download?trip_id={$trip->id}");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_pdf_download_blocked_for_another_users_trip(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $trip  = Trip::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->get("/reports/download?trip_id={$trip->id}")->assertStatus(403);
    }

    public function test_reports_page_requires_auth(): void
    {
        $this->get('/reports')->assertRedirect(route('login'));
    }
}
