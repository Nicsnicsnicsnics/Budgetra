<?php
namespace Database\Seeders;

use App\Models\DestinationCost;
use Illuminate\Database\Seeder;

class DestinationSeeder extends Seeder
{
    public function run(): void
    {
        $destinations = [
            ['destination' => 'Batanes',                 'cost_level' => 'Pricey',        'multiplier' => 1.200, 'category' => 'Local'],
            ['destination' => 'Boracay',                 'cost_level' => 'Pricey',        'multiplier' => 1.200, 'category' => 'Local'],
            ['destination' => 'Bohol',                   'cost_level' => 'Moderate',      'multiplier' => 1.000, 'category' => 'Local'],
            ['destination' => 'Cebu City',               'cost_level' => 'Moderate',      'multiplier' => 1.000, 'category' => 'Local'],
            ['destination' => 'El Nido, Palawan',        'cost_level' => 'Pricey',        'multiplier' => 1.200, 'category' => 'Local'],
            ['destination' => 'Coron, Palawan',          'cost_level' => 'Pricey',        'multiplier' => 1.200, 'category' => 'Local'],
            ['destination' => 'Siargao Island',          'cost_level' => 'Moderate',      'multiplier' => 1.000, 'category' => 'Local'],
            ['destination' => 'Tagaytay',                'cost_level' => 'Budget-friendly','multiplier' => 0.800,'category' => 'Local'],
            ['destination' => 'Davao City',              'cost_level' => 'Moderate',      'multiplier' => 1.000, 'category' => 'Local'],
            ['destination' => 'Sagada',                  'cost_level' => 'Budget-friendly','multiplier' => 0.800,'category' => 'Local'],
            ['destination' => 'Bali',                    'cost_level' => 'Moderate',      'multiplier' => 1.000, 'category' => 'International'],
            ['destination' => 'Bangkok',                 'cost_level' => 'Budget-friendly','multiplier' => 0.800,'category' => 'International'],
            ['destination' => 'Tokyo',                   'cost_level' => 'Very Expensive','multiplier' => 1.500, 'category' => 'International'],
            ['destination' => 'Singapore',               'cost_level' => 'Very Expensive','multiplier' => 1.500, 'category' => 'International'],
            ['destination' => 'Kuala Lumpur',            'cost_level' => 'Budget-friendly','multiplier' => 0.800,'category' => 'International'],
            ['destination' => 'Seoul',                   'cost_level' => 'Pricey',        'multiplier' => 1.200, 'category' => 'International'],
            ['destination' => 'Ho Chi Minh City',        'cost_level' => 'Budget-friendly','multiplier' => 0.800,'category' => 'International'],
            ['destination' => 'Phuket',                  'cost_level' => 'Moderate',      'multiplier' => 1.000, 'category' => 'International'],
        ];

        foreach ($destinations as $d) {
            DestinationCost::firstOrCreate(
                ['destination' => $d['destination']],
                $d
            );
        }
    }
}
