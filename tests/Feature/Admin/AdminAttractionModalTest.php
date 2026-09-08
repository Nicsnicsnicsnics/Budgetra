<?php
namespace Tests\Feature\Admin;

use App\Models\Attraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attractions: Add, Edit and Delete all live in dialogs on the index.
 *
 * Add used to navigate to its own page while Edit was already a dialog, and
 * the two form definitions drifted — the dialog never grew an Estimated Cost
 * field, so every edit made through the UI wiped that column. One form now
 * serves both, which is what stops it happening again.
 */
class AdminAttractionModalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function attraction(array $attrs = []): Attraction
    {
        return Attraction::create(array_merge([
            'name'        => 'Kawasan Falls',
            'destination' => 'Cebu',
            'region'      => 'local',
            'category'    => 'Nature',
            'rating'      => 4.5,
        ], $attrs));
    }

    /** The six fields the dialog posts — deliberately without estimated_cost. */
    private function modalPayload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Kawasan Falls',
            'destination' => 'Cebu',
            'category'    => 'Nature',
            'region'      => 'local',
            'rating'      => 4.5,
            'description' => 'A waterfall.',
        ], $overrides);
    }

    public function test_add_opens_a_dialog_instead_of_navigating_to_a_page(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->get('/admin/attractions');

        $res->assertStatus(200);
        $res->assertSee('js-add-attraction-btn', false);
        $res->assertDontSee('href="' . url('admin/attractions/create') . '"', false);
    }

    public function test_the_dialog_carries_the_estimated_cost_field(): void
    {
        $admin = $this->admin();

        // Its absence is the whole reason the column was being wiped.
        $this->actingAs($admin)->get('/admin/attractions')
            ->assertSee('id="attrModalEstimatedCost"', false)
            ->assertSee('name="estimated_cost"', false);
    }

    public function test_adding_through_the_dialog_creates_the_attraction(): void
    {
        $admin = $this->admin();

        // The dialog spoofs _method back to POST so one form serves both modes.
        $this->actingAs($admin)->post('/admin/attractions', $this->modalPayload([
            'name'           => 'Chocolate Hills',
            'destination'    => 'Bohol',
            '_method'        => 'POST',
            'estimated_cost' => 250,
        ]))->assertRedirect(route('admin.attractions.index'));

        $this->assertDatabaseHas('attractions', [
            'name'           => 'Chocolate Hills',
            'estimated_cost' => 250,
        ]);
    }

    public function test_editing_does_not_wipe_an_estimated_cost_it_never_asked_for(): void
    {
        $admin = $this->admin();
        $attr  = $this->attraction(['estimated_cost' => 500]);

        // Exactly what the old dialog submitted: no estimated_cost at all.
        $this->actingAs($admin)
            ->put("/admin/attractions/{$attr->id}", $this->modalPayload(['rating' => 4.8]))
            ->assertRedirect(route('admin.attractions.index'));

        $this->assertSame('500.00', (string) $attr->fresh()->estimated_cost);
    }

    public function test_editing_with_a_cost_writes_the_new_value(): void
    {
        $admin = $this->admin();
        $attr  = $this->attraction(['estimated_cost' => 500]);

        $this->actingAs($admin)->put("/admin/attractions/{$attr->id}",
            $this->modalPayload(['estimated_cost' => 750]));

        $this->assertSame('750.00', (string) $attr->fresh()->estimated_cost);
    }

    public function test_an_explicitly_emptied_cost_still_clears(): void
    {
        $admin = $this->admin();
        $attr  = $this->attraction(['estimated_cost' => 500]);

        // Submitted-but-blank has to stay distinguishable from not-submitted,
        // or the field could never be cleared once set.
        $this->actingAs($admin)->put("/admin/attractions/{$attr->id}",
            $this->modalPayload(['estimated_cost' => '']));

        $this->assertNull($attr->fresh()->estimated_cost);
    }

    public function test_a_rejected_submit_keeps_the_input_for_the_reopened_dialog(): void
    {
        $admin = $this->admin();

        // The page this replaced kept its input via old(); a dialog that
        // reopened blank would be a step backwards.
        $this->actingAs($admin)
            ->from('/admin/attractions')
            ->post('/admin/attractions', $this->modalPayload([
                'name'   => 'Rejected Attraction',
                'rating' => 99,
            ]))
            ->assertRedirect('/admin/attractions')
            ->assertSessionHasErrors('rating');

        $this->assertSame('Rejected Attraction', session()->getOldInput('name'));
    }

    public function test_the_dialogs_have_no_redundant_corner_close_button(): void
    {
        $admin = $this->admin();

        // Cancel closes them, and so do Escape and a scrim click — both from
        // the admin layout, not from this page.
        $res = $this->actingAs($admin)->get('/admin/attractions');

        $res->assertDontSee('admin-modal-close', false);
        $res->assertSee('admin-modal-btn-cancel', false);
    }

    public function test_the_modal_chrome_carries_no_hardcoded_colours(): void
    {
        $css = file_get_contents(public_path('css/style.css'));

        // A brown scrim read as a reddish haze on every theme.
        $this->assertStringNotContainsString('background: rgba(16,10,5,.55)', $css);
        $this->assertStringContainsString('.admin-modal-icon-danger { background: color-mix(in srgb, var(--admin-danger) 16%, transparent); }', $css);
    }

    public function test_the_standalone_create_and_edit_pages_are_gone(): void
    {
        $this->assertFalse(\Route::has('admin.attractions.create'));
        $this->assertFalse(\Route::has('admin.attractions.edit'));
        $this->assertFileDoesNotExist(resource_path('views/admin/attractions/create.blade.php'));
        $this->assertFileDoesNotExist(resource_path('views/admin/attractions/edit.blade.php'));
    }
}
