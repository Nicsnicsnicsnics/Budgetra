<?php
namespace Tests\Feature\Admin;

use App\Models\AppConfig;
use App\Models\OcrLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->admin()->create(); }

    public function test_admin_can_view_config_page(): void
    {
        $this->actingAs($this->admin())->get('/admin/config')->assertStatus(200)->assertSee('Config');
    }

    public function test_admin_can_save_config_values(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/config', [
            'config_key'   => 'klook_api_key',
            'config_value' => 'test-api-key-123',
        ])->assertRedirect();

        $this->assertDatabaseHas('app_config', [
            'config_key'   => 'klook_api_key',
            'config_value' => 'test-api-key-123',
        ]);
    }

    public function test_admin_can_view_ocr_logs(): void
    {
        $admin = $this->admin();
        OcrLog::create(['user_id' => $admin->id, 'filename' => 'receipt.jpg', 'status' => 'success', 'confidence' => 95.50]);
        $this->actingAs($admin)->get('/admin/ocr-logs')->assertStatus(200)->assertSee('receipt.jpg');
    }
}
