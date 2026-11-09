<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Cleaner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cleaner>
 */
class CleanerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(UserRole::Cleaner),
            'verification_status' => VerificationStatus::Verified,
            'verification_notes' => null,
            'is_available' => true,
            'joined_at' => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'bio' => fake()->sentence(12),
            'rating_avg' => 0,
            'rating_count' => 0,
            'completed_jobs_count' => 0,
        ];
    }

    public function verification(VerificationStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'verification_status' => $status,
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
