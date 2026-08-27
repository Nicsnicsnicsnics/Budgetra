<?php
namespace App\Services;

use App\Models\AppConfig;

class KlookService
{
    public function getActivities(string $destination, int $limit = 5): array
    {
        $apiKey = AppConfig::where('config_key', 'klook_api_key')->value('config_value');

        // When real Klook API key is configured, integrate here
        // For now, always return mock data
        return $this->mockActivities($destination, $limit);
    }

    private function mockActivities(string $destination, int $limit): array
    {
        $activities = [
            ['name' => "Island Hopping at {$destination}", 'price' => 1200, 'currency' => 'PHP', 'rating' => 4.8],
            ['name' => "City Tour of {$destination}",      'price' => 800,  'currency' => 'PHP', 'rating' => 4.5],
            ['name' => "Sunset Cruise {$destination}",     'price' => 1500, 'currency' => 'PHP', 'rating' => 4.7],
            ['name' => "Snorkeling at {$destination}",     'price' => 950,  'currency' => 'PHP', 'rating' => 4.6],
            ['name' => "Cultural Tour {$destination}",     'price' => 700,  'currency' => 'PHP', 'rating' => 4.3],
        ];
        return array_slice($activities, 0, $limit);
    }
}
