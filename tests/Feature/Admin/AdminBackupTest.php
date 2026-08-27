<?php
namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class AdminBackupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User { return User::factory()->admin()->create(); }

    public function test_backup_page_loads(): void
    {
        $this->actingAs($this->admin())->get('/admin/backup')->assertStatus(200);
    }

    public function test_non_admin_cannot_access_backup(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/admin/backup')->assertStatus(403);
    }

    public function test_backup_download_triggers_process(): void
    {
        Process::fake([
            'mysqldump*' => Process::result('-- MySQL dump', '', 0),
        ]);

        $admin = $this->admin();
        $response = $this->actingAs($admin)->post('/admin/backup/download');
        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_restore_rejects_non_sql_file(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/backup/restore', [
            'sql_file' => UploadedFile::fake()->create('backup.txt', 100),
        ])->assertSessionHasErrors('sql_file');
    }
}
