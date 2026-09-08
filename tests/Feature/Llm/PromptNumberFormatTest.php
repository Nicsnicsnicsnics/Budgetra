<?php
namespace Tests\Feature\Llm;

use App\Livewire\Traveler\Llm;
use Tests\TestCase;

/**
 * The AI prompt box groups a bare number as it is typed, so a budget answer
 * reaches the parser as "35,325,234" rather than "35325234". The slot parser
 * has to read it identically either way.
 */
class PromptNumberFormatTest extends TestCase
{
    private function parseBudget(string $userText): array
    {
        $llm = new Llm();
        $llm->awaitingSlot = 'budget';
        $llm->aiBudgetMin  = 0;
        $llm->aiBudgetMax  = 0;

        $method = new \ReflectionMethod($llm, 'applyDirectAnswerFallback');
        $method->setAccessible(true);
        $method->invoke($llm, $userText);

        return [$llm->aiBudgetMin, $llm->aiBudgetMax];
    }

    public function test_a_grouped_budget_parses_the_same_as_a_bare_one(): void
    {
        $this->assertSame($this->parseBudget('50000'), $this->parseBudget('50,000'));
        $this->assertSame([50000, 50000], $this->parseBudget('50,000'));
    }

    public function test_a_grouped_range_still_parses(): void
    {
        [$min, $max] = $this->parseBudget('50,000 to 80,000');

        $this->assertSame(50000, $min);
        $this->assertSame(80000, $max);
    }
}
