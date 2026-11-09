<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Cleaning' => 1,
            'Kitchen' => 2,
            'Laundry' => 3,
            'Specialty' => 4,
        ];

        $categoryModels = [];
        foreach ($categories as $name => $order) {
            $categoryModels[$name] = ServiceCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $order],
            );
        }

        $services = [
            [
                'category' => 'Cleaning',
                'name' => 'Home Cleaning',
                'short_description' => 'General home cleaning for a fresh, tidy space.',
                'price_per_hour' => 45,
                'default_duration_hours' => 2,
                'min_duration_hours' => 1,
                'max_duration_hours' => 6,
                'is_live' => true,
            ],
            [
                'category' => 'Kitchen',
                'name' => 'Dishwashing',
                'short_description' => 'A sparkling kitchen and dishes done for you.',
                'price_per_hour' => 35,
                'default_duration_hours' => 1,
                'min_duration_hours' => 1,
                'max_duration_hours' => 4,
                'is_live' => true,
            ],
            [
                'category' => 'Cleaning',
                'name' => 'Deep Clean',
                'short_description' => 'An intensive, top-to-bottom deep clean of your home.',
                'price_per_hour' => 65,
                'default_duration_hours' => 4,
                'min_duration_hours' => 3,
                'max_duration_hours' => 8,
                'is_live' => true,
            ],
            [
                'category' => 'Laundry',
                'name' => 'Laundry & Fold',
                'short_description' => 'Wash, dry and neatly fold your laundry.',
                'price_per_hour' => 40,
                'default_duration_hours' => 2,
                'min_duration_hours' => 1,
                'max_duration_hours' => 5,
                'is_live' => true,
            ],
            [
                'category' => 'Specialty',
                'name' => 'Sofa Shampoo',
                'short_description' => 'Deep shampoo and refresh for sofas and upholstery.',
                'price_per_hour' => 90,
                'default_duration_hours' => 2,
                'min_duration_hours' => 1,
                'max_duration_hours' => 4,
                'is_live' => false,
            ],
            [
                'category' => 'Specialty',
                'name' => 'Window Cleaning',
                'short_description' => 'Streak-free interior and exterior window cleaning.',
                'price_per_hour' => 55,
                'default_duration_hours' => 2,
                'min_duration_hours' => 1,
                'max_duration_hours' => 5,
                'is_live' => true,
            ],
        ];

        foreach ($services as $index => $service) {
            Service::updateOrCreate(
                ['slug' => Str::slug($service['name'])],
                [
                    'category_id' => $categoryModels[$service['category']]->id,
                    'name' => $service['name'],
                    'short_description' => $service['short_description'],
                    'description' => $service['short_description'].' Our professional cleaners bring their own supplies and equipment, and every job is backed by our satisfaction guarantee. Book in minutes and relax while we take care of the rest.',
                    'price_per_hour' => $service['price_per_hour'],
                    'default_duration_hours' => $service['default_duration_hours'],
                    'min_duration_hours' => $service['min_duration_hours'],
                    'max_duration_hours' => $service['max_duration_hours'],
                    'is_live' => $service['is_live'],
                    'sort_order' => $index + 1,
                ],
            );
        }
    }
}
