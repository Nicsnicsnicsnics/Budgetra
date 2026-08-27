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
            'country'         => fake()->country(),
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
