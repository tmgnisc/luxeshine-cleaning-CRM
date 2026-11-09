<?php

namespace Tests\Concerns;

use App\Enums\VerificationStatus;
use App\Models\Address;
use App\Models\Cleaner;
use App\Models\CleanerWorkingHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\SettingSeeder;

trait BuildsBookingScenario
{
    protected function seedSettings(): void
    {
        $this->seed(SettingSeeder::class);
    }

    protected function makeService(array $overrides = []): Service
    {
        return Service::factory()->create(array_merge([
            'price_per_hour' => 50,
            'default_duration_hours' => 2,
            'min_duration_hours' => 1,
            'max_duration_hours' => 8,
            'is_live' => true,
        ], $overrides));
    }

    protected function makeCustomerWithAddress(): Customer
    {
        $user = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);
        Address::factory()->default()->create(['customer_id' => $customer->id]);

        return $customer->load('addresses', 'user');
    }

    /**
     * A verified, available cleaner skilled in the service, working 08:00–20:00 every day.
     */
    protected function makeCleaner(Service $service, array $overrides = []): Cleaner
    {
        $user = User::factory()->cleaner()->create();
        $cleaner = Cleaner::factory()->create(array_merge([
            'user_id' => $user->id,
            'verification_status' => VerificationStatus::Verified,
            'is_available' => true,
        ], $overrides));

        $cleaner->services()->sync([$service->id]);

        for ($day = 0; $day <= 6; $day++) {
            CleanerWorkingHour::create([
                'cleaner_id' => $cleaner->id,
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '20:00',
                'is_off' => false,
            ]);
        }

        return $cleaner->load('user', 'services', 'workingHours', 'timeOff');
    }
}
