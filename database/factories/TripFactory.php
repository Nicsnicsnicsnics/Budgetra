<?php
namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+3 months');
        $endDate = clone $start;
        $endDate->modify('+' . fake()->numberBetween(1, 14) . ' days');

        return [
            'user_id'       => User::factory(),
            'destination'   => fake()->city() . ', ' . fake()->country(),
            'start_date'    => $start->format('Y-m-d'),
            'end_date'      => $endDate->format('Y-m-d'),
            'num_travelers' => fake()->numberBetween(1, 6),
            'budget_limit'  => fake()->randomFloat(2, 5000, 100000),
            'travel_type'   => fake()->randomElement(['Solo', 'Family', 'Couple', 'Friends']),
            'notes'         => fake()->optional()->sentence(),
        ];
    }
}
