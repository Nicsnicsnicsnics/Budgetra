<?php
namespace Tests\Feature\Trip;

use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\User;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private BudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BudgetService();
    }

    public function test_summary_returns_correct_totals(): void
    {
        $trip = Trip::factory()->create();
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Transportation',  'estimated_cost' => 5000, 'actual_spent' => 3000]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Accommodation',   'estimated_cost' => 8000, 'actual_spent' => 8500]);
        TripBudget::create(['trip_id' => $trip->id, 'category' => 'Food',            'estimated_cost' => 3000, 'actual_spent' => 1500]);

        $summary = $this->service->summary($trip->load('budgets'));

        $this->assertEquals(16000, $summary['total_estimated']);
        $this->assertEquals(13000, $summary['total_spent']);
        $this->assertEquals(3000,  $summary['remaining']);
        $this->assertCount(3, $summary['categories']);
    }

    public function test_summary_with_no_budgets_returns_zeros(): void
    {
        $trip = Trip::factory()->create();
        $summary = $this->service->summary($trip->load('budgets'));

        $this->assertEquals(0, $summary['total_estimated']);
        $this->assertEquals(0, $summary['total_spent']);
        $this->assertEquals(0, $summary['remaining']);
    }
}
