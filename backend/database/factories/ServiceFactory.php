<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $default = fake()->randomElement([1, 2, 3, 4]);

        return [
            'category_id' => ServiceCategory::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(8),
            'description' => fake()->paragraphs(3, true),
            'image_path' => null,
            'price_per_hour' => fake()->randomElement([35, 40, 45, 55, 65, 90]),
            'default_duration_hours' => $default,
            'min_duration_hours' => 1,
            'max_duration_hours' => 8,
            'is_live' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function notLive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_live' => false,
        ]);
    }
}
