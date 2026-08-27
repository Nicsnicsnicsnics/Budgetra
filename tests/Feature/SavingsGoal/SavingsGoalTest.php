<?php
namespace Tests\Feature\SavingsGoal;

use App\Models\SavingsGoal;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavingsGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_savings_index_loads(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/savings')->assertStatus(200);
    }

    public function test_user_can_create_savings_goal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/savings', [
            'goal_name'       => 'Boracay Trip Fund',
            'target_amount'   => 50000,
            'current_savings' => 5000,
            'deadline'        => '2030-12-31',
        ])->assertRedirect(route('savings.index'));

        $this->assertDatabaseHas('savings_goals', [
            'goal_name'    => 'Boracay Trip Fund',
            'user_id'      => $user->id,
            'target_amount' => 50000,
        ]);
    }

    public function test_user_can_make_deposit(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::create([
            'user_id'         => $user->id,
            'goal_name'       => 'Test Fund',
            'target_amount'   => 10000,
            'current_savings' => 1000,
            'deadline'        => '2030-12-31',
        ]);

        $this->actingAs($user)->patch("/savings/{$goal->id}/deposit", ['amount' => 500])->assertRedirect();
        $this->assertDatabaseHas('savings_goals', ['id' => $goal->id, 'current_savings' => 1500]);
    }

    public function test_user_cannot_access_others_goal(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $goal  = SavingsGoal::create([
            'user_id' => $other->id, 'goal_name' => 'Other', 'target_amount' => 1000, 'current_savings' => 0, 'deadline' => '2030-12-31',
        ]);

        $this->actingAs($user)->get("/savings/{$goal->id}/edit")->assertStatus(403);
    }

    public function test_user_can_delete_goal(): void
    {
        $user = User::factory()->create();
        $goal = SavingsGoal::create([
            'user_id' => $user->id, 'goal_name' => 'Delete me', 'target_amount' => 1000, 'current_savings' => 0, 'deadline' => '2030-12-31',
        ]);

        $this->actingAs($user)->delete("/savings/{$goal->id}")->assertRedirect(route('savings.index'));
        $this->assertDatabaseMissing('savings_goals', ['id' => $goal->id]);
    }

    public function test_savings_requires_auth(): void
    {
        $this->get('/savings')->assertRedirect(route('login'));
    }
}
