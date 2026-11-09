<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(UserRole::Customer),
            'customer_since' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }
}
