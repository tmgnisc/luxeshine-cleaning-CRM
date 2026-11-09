<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Address;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $service = Service::factory();
        $pricePerHour = fake()->randomElement([35, 40, 45, 55, 65, 90]);
        $duration = fake()->randomElement([1, 2, 3, 4]);
        $startHour = fake()->numberBetween(8, 14);
        $startTime = sprintf('%02d:00', $startHour);
        $endTime = sprintf('%02d:00', $startHour + $duration);

        return [
            'reference' => 'DTD-'.fake()->unique()->numberBetween(1000, 999999),
            'customer_id' => Customer::factory(),
            'service_id' => $service,
            'cleaner_id' => null,
            'address_id' => Address::factory(),
            'address_snapshot' => null,
            'scheduled_date' => fake()->dateTimeBetween('-3 months', '+2 weeks')->format('Y-m-d'),
            'start_time' => $startTime,
            'duration_hours' => $duration,
            'end_time' => $endTime,
            'price_per_hour' => $pricePerHour,
            'total_price' => $pricePerHour * $duration,
            'price_overridden' => false,
            'status' => BookingStatus::Pending,
            'is_paid' => false,
            'customer_notes' => fake()->boolean(40) ? fake()->sentence() : null,
            'source' => BookingSource::Website,
        ];
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
