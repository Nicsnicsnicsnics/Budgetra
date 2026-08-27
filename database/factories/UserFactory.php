<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name'       => fake()->name(),
            'email'           => fake()->unique()->safeEmail(),
            'password'        => 'password',
            'phone'           => fake()->numerify('09#########'),
            // Deterministic, and one of the 29 countries the registration
            // dropdown actually offers. fake()->country() returned any country
            // on earth, most of them unknown to PlaceCatalog — harmless while
            // country was decorative, but it now decides which currency a trip
            // budget is typed in, so a random draw made budget assertions pass
            // or fail depending on the seed. Tests that care set it explicitly.
            'country'         => 'Philippines',
            'currency_code'   => 'PHP',
            'currency_symbol' => '₱',
            'role'            => 'traveler',
            'remember_token'  => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }
}
