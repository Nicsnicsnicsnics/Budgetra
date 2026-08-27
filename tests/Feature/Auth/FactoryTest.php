<?php
namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_traveler_with_correct_fields(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'traveler']);
        $this->assertNotNull($user->full_name);
        $this->assertNotNull($user->email);
    }

    public function test_factory_admin_state_creates_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }

    public function test_factory_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => 'secret123']);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('secret123', $user->password));
    }
}
