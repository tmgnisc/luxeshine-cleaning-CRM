<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'label' => fake()->randomElement(['Home', 'Office', 'Apartment']),
            'line1' => fake()->buildingNumber().' '.fake()->streetName(),
            'line2' => fake()->boolean(40) ? 'Apt '.fake()->numberBetween(1, 200) : null,
            'area' => fake()->randomElement(['Dubai Marina', 'Downtown', 'JLT', 'Palm Jumeirah', 'Business Bay', 'Arabian Ranches']),
            'city' => 'Dubai',
            'access_instructions' => fake()->boolean(30) ? fake()->sentence() : null,
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
