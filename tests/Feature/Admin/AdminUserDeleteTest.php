<?php
namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting a user has to survive being asked twice.
 *
 * A delete round-trip against the hosted database runs well over a second,
 * during which the button gives no sign of working, so it gets clicked again.
 * Route-model binding firstOrFail()s, so that second request used to answer
 * 404 — the admin saw a blank error page even though the delete had worked.
 */
class AdminUserDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->admin()->create(); }

    public function test_deleting_a_user_removes_them(): void
    {
        $admin  = $this->admin();
        $victim = User::factory()->create();

        $this->actingAs($admin)
            ->delete("/admin/users/{$victim->id}")
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }

    public function test_a_second_delete_of_the_same_user_is_not_a_404(): void
    {
        $admin  = $this->admin();
        $victim = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/users/{$victim->id}")->assertRedirect();

        // The double-click. Already gone is the outcome that was asked for.
        $this->actingAs($admin)
            ->delete("/admin/users/{$victim->id}")
            ->assertStatus(302)
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_deleting_a_user_that_never_existed_is_not_a_404_either(): void
    {
        $this->actingAs($this->admin())
            ->delete('/admin/users/99999999')
            ->assertRedirect(route('admin.users.index'));
    }

    public function test_an_admin_still_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_the_delete_button_locks_after_the_first_click(): void
    {
        $res = $this->actingAs($this->admin())->get('/admin/users');

        $res->assertSee('budgetraLockDeleteUser', false);
        $res->assertSee('deleteUserSubmitting', false);
        $res->assertSee('fa-spinner fa-spin', false);
    }
}
