<?php
namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_view_user_list(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create();
        $this->actingAs($admin)->get('/admin/users')->assertStatus(200)->assertSee('Users');
    }

    public function test_traveler_cannot_access_user_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/users')->assertStatus(403);
    }

    public function test_admin_can_ban_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['role' => 'traveler']);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/ban")->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'banned']);
    }

    public function test_admin_can_unban_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['role' => 'banned']);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}/ban")->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'traveler']);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/users/{$user->id}")->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertStatus(403);
    }

    public function test_user_list_shows_total_and_average_travel_cost(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['full_name' => 'Cost Test Traveler']);
        $trip1 = \App\Models\Trip::factory()->create(['user_id' => $user->id]);
        $trip2 = \App\Models\Trip::factory()->create(['user_id' => $user->id]);
        \App\Models\Expense::create([
            'trip_id' => $trip1->id, 'user_id' => $user->id, 'amount' => 6000,
            'category' => 'Food', 'expense_date' => now(),
        ]);
        \App\Models\Expense::create([
            'trip_id' => $trip2->id, 'user_id' => $user->id, 'amount' => 4000,
            'category' => 'Transportation', 'expense_date' => now(),
        ]);
        // Total = 10,000 across 2 trips -> average = 5,000.

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('Cost Test Traveler')
            ->assertSee('₱10,000.00')
            ->assertSee('₱5,000.00');
    }

    // A user with trips but zero recorded expenses must not crash the
    // average calculation (0 / N is fine) — this instead covers the other
    // edge: a user with NO trips at all, where the divide-by-zero guard
    // actually matters.
    public function test_user_list_shows_dash_for_average_cost_with_no_trips(): void
    {
        $admin = $this->admin();
        User::factory()->create(['full_name' => 'Tripless Traveler']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertSee('Tripless Traveler')
            ->assertSee('—');
    }
}
