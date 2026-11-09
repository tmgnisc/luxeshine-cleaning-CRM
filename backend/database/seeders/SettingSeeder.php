<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_title', 'value' => 'LuxeShine Cleaning', 'type' => 'string'],
            ['key' => 'tagline', 'value' => 'Professional home cleaning, booked in minutes.', 'type' => 'string'],
            ['key' => 'hero_headline', 'value' => 'A spotless home, without the hassle', 'type' => 'string'],
            ['key' => 'hero_subline', 'value' => 'Book trusted, vetted cleaners across Dubai in just a few taps.', 'type' => 'string'],
            ['key' => 'support_phone', 'value' => '+971 4 123 4567', 'type' => 'string'],
            ['key' => 'support_email', 'value' => 'support@dusttodone.test', 'type' => 'string'],
            ['key' => 'office_address', 'value' => 'Office 1203, Business Bay, Dubai, UAE', 'type' => 'string'],
            ['key' => 'currency', 'value' => 'NZD', 'type' => 'string'],
            ['key' => 'booking_lead_time_hours', 'value' => '24', 'type' => 'int'],
            ['key' => 'cancellation_cutoff_hours', 'value' => '12', 'type' => 'int'],
            ['key' => 'accepting_bookings', 'value' => '1', 'type' => 'bool'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'bool'],
            ['key' => 'maintenance_message', 'value' => 'We are briefly down for maintenance and will be back shortly.', 'type' => 'string'],
            ['key' => 'business_open_time', 'value' => '08:00', 'type' => 'string'],
            ['key' => 'business_close_time', 'value' => '20:00', 'type' => 'string'],
            ['key' => 'service_areas', 'value' => json_encode([
                'Dubai Marina', 'Downtown', 'JLT', 'Palm Jumeirah', 'Business Bay',
                'Arabian Ranches', 'Jumeirah', 'Deira', 'Al Barsha',
            ]), 'type' => 'json'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
