<?php

namespace App\Console\Commands;

use App\Models\Attraction;
use App\Services\PlaceImageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:fill-attraction-images {--limit=15} {--refresh : Re-fetch a sharper photo even for attractions that already have one}')]
#[Description('Fetch a photo via SerpAPI for attractions that do not have one yet, capped daily so live trip-planning traffic still has quota left')]
class FillAttractionImages extends Command
{
    // Same shared 80/day SerpAPI cap as FillDestinationImages — this
    // reserves a separate, smaller slice so both backfills together still
    // leave the majority of the daily quota for real trip-planning traffic.
    private const DAILY_BUDGET = 15;

    public function handle(): void
    {
        $todayUsage = DB::table('serpapi_usage')->where('usage_date', now()->toDateString())->value('request_count') ?? 0;
        $budgetLeft = self::DAILY_BUDGET - $todayUsage;

        if ($budgetLeft <= 0) {
            $this->info('Daily image-fetch budget already used today; skipping.');
            return;
        }

        $toFetch = min((int) $this->option('limit'), $budgetLeft);
        $query = $this->option('refresh') ? Attraction::query() : Attraction::whereNull('image');
        $attractions = $query->orderBy('name')->limit($toFetch)->get();

        if ($attractions->isEmpty()) {
            $this->info('No attractions missing images.');
            return;
        }

        $service = new PlaceImageService();
        $fetched = 0;
        foreach ($attractions as $attraction) {
            if ($service->fetchForAttraction($attraction)) {
                $fetched++;
            }
        }

        $this->info("Fetched {$fetched} of {$attractions->count()} attempted attraction photo(s).");
    }
}
