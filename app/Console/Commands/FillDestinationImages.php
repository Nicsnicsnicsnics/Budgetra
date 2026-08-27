<?php

namespace App\Console\Commands;

use App\Models\Destination;
use App\Services\PlaceImageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:fill-destination-images {--limit=20}')]
#[Description('Fetch a photo via SerpAPI for destinations that do not have one yet, capped daily so live trip-planning traffic still has quota left')]
class FillDestinationImages extends Command
{
    // SerpApiService enforces a shared 80/day cap across flights, hotels,
    // restaurants, attractions, AND this backfill — reserve only a slice of
    // that for photo backfilling so a bulk run never starves real traffic.
    private const DAILY_BUDGET = 20;

    public function handle(): void
    {
        $todayUsage = DB::table('serpapi_usage')->where('usage_date', now()->toDateString())->value('request_count') ?? 0;
        $budgetLeft = self::DAILY_BUDGET - $todayUsage;

        if ($budgetLeft <= 0) {
            $this->info('Daily image-fetch budget already used today; skipping.');
            return;
        }

        $toFetch = min((int) $this->option('limit'), $budgetLeft);
        $destinations = Destination::whereNull('image')->orderBy('name')->limit($toFetch)->get();

        if ($destinations->isEmpty()) {
            $this->info('No destinations missing images.');
            return;
        }

        $service = new PlaceImageService();
        $fetched = 0;
        foreach ($destinations as $destination) {
            if ($service->fetchForDestination($destination)) {
                $fetched++;
            }
        }

        $this->info("Fetched {$fetched} of {$destinations->count()} attempted destination photo(s).");
    }
}
