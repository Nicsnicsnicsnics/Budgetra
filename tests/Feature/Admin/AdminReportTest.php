<?php
namespace Tests\Feature\Admin;

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->admin()->create(); }

    public function test_admin_can_view_reports_page(): void
    {
        $this->actingAs($this->admin())->get('/admin/reports')->assertStatus(200)->assertSee('Reports');
    }

    public function test_reports_page_shows_user_count(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();
        $response = $this->actingAs($admin)->get('/admin/reports');
        $response->assertStatus(200)->assertSee('4'); // 3 + admin = 4
    }

    public function test_reports_page_shows_top_destinations(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();
        Trip::factory()->count(2)->create(['user_id' => $user->id, 'destination' => 'Bohol']);
        Trip::factory()->create(['user_id' => $user->id, 'destination' => 'Palawan']);
        $this->actingAs($admin)->get('/admin/reports')->assertStatus(200)->assertSee('Bohol');
    }
}
