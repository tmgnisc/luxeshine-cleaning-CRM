<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Customer;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'customer_id' => Customer::factory(),
            'cleaner_id' => Cleaner::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(14),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => true,
            'moderated_at' => now(),
        ]);
    }
}
