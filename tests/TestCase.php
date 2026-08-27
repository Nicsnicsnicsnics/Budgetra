<?php

namespace Tests;

use App\Services\CurrencyConverterService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CurrencyConverterService memoises rates in a static for the life of a
        // request, which is right in production (one render asks for the same
        // currency dozens of times) but leaks between tests, where the whole
        // suite is one process. Without this, a rate fetched by an earlier test
        // silently satisfies a later one that meant to exercise an outage.
        CurrencyConverterService::forgetMemo();
    }
}
