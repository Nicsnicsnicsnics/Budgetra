<?php
namespace Tests\Feature\UI;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseCreateUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_expense_create_page_loads(): void
    {
        $user = User::factory()->create();
        Trip::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/expenses/create')
            ->assertStatus(200)
            ->assertSee('Add Expense')
            ->assertSee('Upload a receipt to auto-fill the details below', false);
    }

    public function test_expense_create_prompts_to_plan_a_trip_when_there_are_none(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/expenses/create')
            ->assertStatus(200)
            ->assertSee('No trips planned yet');
    }

    public function test_expense_create_preselects_trip_from_query_param(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->get("/expenses/create?trip_id={$trip->id}")
            ->assertStatus(200)
            ->assertSee($trip->destination);
    }

    public function test_ocr_endpoint_returns_json(): void
    {
        Storage::fake('public');
        Http::fake([
            'api.ocr.space/*' => Http::response([
                'ParsedResults' => [[
                    'ParsedText' => "Restaurant ABC\nTotal: ₱350.00\nDate: 2026-07-02",
                ]],
                'OCRExitCode' => 1,
            ], 200),
        ]);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('receipt.jpg');
        $response = $this->actingAs($user)->post('/expenses/ocr', ['receipt' => $file]);
        $response->assertStatus(200)->assertJsonStructure(['amount', 'date', 'description']);
    }
}
