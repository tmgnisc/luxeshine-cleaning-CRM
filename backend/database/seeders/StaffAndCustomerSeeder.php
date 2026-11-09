<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Address;
use App\Models\Cleaner;
use App\Models\CleanerWorkingHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffAndCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $services = Service::pluck('id', 'slug');

        // --- Admin accounts ---------------------------------------------------
        User::updateOrCreate(
            ['email' => 'superadmin@dusttodone.test'],
            [
                'name' => 'Sara Admin',
                'phone' => '+971500000001',
                'password' => $password,
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'admin@dusttodone.test'],
            [
                'name' => 'Daniel Ops',
                'phone' => '+971500000002',
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        // --- Cleaners ---------------------------------------------------------
        $cleaners = [
            ['name' => 'Amira Khalid', 'email' => 'cleaner@dusttodone.test', 'status' => VerificationStatus::Verified, 'available' => true, 'skills' => ['home-cleaning', 'deep-clean', 'window-cleaning']],
            ['name' => 'Rashed Mansour', 'email' => 'rashed@dusttodone.test', 'status' => VerificationStatus::Verified, 'available' => true, 'skills' => ['home-cleaning', 'dishwashing', 'deep-clean']],
            ['name' => 'Lena Souza', 'email' => 'lena@dusttodone.test', 'status' => VerificationStatus::Verified, 'available' => true, 'skills' => ['home-cleaning', 'laundry-fold', 'dishwashing']],
            ['name' => 'Omar Jaber', 'email' => 'omar@dusttodone.test', 'status' => VerificationStatus::Verified, 'available' => false, 'skills' => ['deep-clean', 'window-cleaning', 'sofa-shampoo']],
            ['name' => 'Zara Hussein', 'email' => 'zara@dusttodone.test', 'status' => VerificationStatus::Pending, 'available' => true, 'skills' => ['home-cleaning', 'laundry-fold']],
            ['name' => 'Idris Bello', 'email' => 'idris@dusttodone.test', 'status' => VerificationStatus::Rejected, 'available' => true, 'skills' => ['home-cleaning', 'window-cleaning']],
        ];

        foreach ($cleaners as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => '+9715'.fake()->numerify('########'),
                    'password' => $password,
                    'role' => UserRole::Cleaner,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $cleaner = Cleaner::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'verification_status' => $data['status'],
                    'verification_notes' => $data['status'] === VerificationStatus::Rejected ? 'References could not be verified.' : null,
                    'is_available' => $data['available'],
                    'joined_at' => now()->subMonths(fake()->numberBetween(2, 20))->toDateString(),
                    'bio' => 'Experienced, detail-oriented cleaner serving across Dubai.',
                ],
            );

            $skillIds = collect($data['skills'])->map(fn (string $slug) => $services[$slug] ?? null)->filter()->all();
            $cleaner->services()->sync($skillIds);

            $this->seedWorkingHours($cleaner);
        }

        // --- Customers --------------------------------------------------------
        $customers = [
            ['name' => 'Fatima Al Marri', 'email' => 'customer@dusttodone.test', 'area' => 'Dubai Marina'],
            ['name' => 'Youssef Rahman', 'email' => 'youssef@example.test', 'area' => 'Downtown'],
            ['name' => 'Noor Salem', 'email' => 'noor@example.test', 'area' => 'JLT'],
            ['name' => 'Karim Toufik', 'email' => 'karim@example.test', 'area' => 'Palm Jumeirah'],
            ['name' => 'Layla Hamdan', 'email' => 'layla@example.test', 'area' => 'Arabian Ranches'],
        ];

        foreach ($customers as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => '+9715'.fake()->numerify('########'),
                    'password' => $password,
                    'role' => UserRole::Customer,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $customer = Customer::updateOrCreate(
                ['user_id' => $user->id],
                ['customer_since' => now()->subMonths(fake()->numberBetween(3, 24))->toDateString()],
            );

            if ($customer->addresses()->count() === 0) {
                Address::create([
                    'customer_id' => $customer->id,
                    'label' => 'Home',
                    'line1' => fake()->buildingNumber().' '.fake()->streetName(),
                    'area' => $data['area'],
                    'city' => 'Dubai',
                    'is_default' => true,
                ]);

                if (fake()->boolean(50)) {
                    Address::create([
                        'customer_id' => $customer->id,
                        'label' => 'Office',
                        'line1' => fake()->buildingNumber().' '.fake()->streetName(),
                        'area' => 'Business Bay',
                        'city' => 'Dubai',
                        'is_default' => false,
                    ]);
                }
            }
        }
    }

    private function seedWorkingHours(Cleaner $cleaner): void
    {
        if ($cleaner->workingHours()->count() > 0) {
            return;
        }

        // 0=Mon ... 6=Sun. Work Mon-Sat 08:00-18:00, Sunday off.
        for ($day = 0; $day <= 6; $day++) {
            $isOff = $day === 6;
            CleanerWorkingHour::create([
                'cleaner_id' => $cleaner->id,
                'day_of_week' => $day,
                'start_time' => $isOff ? null : '08:00',
                'end_time' => $isOff ? null : '18:00',
                'is_off' => $isOff,
            ]);
        }
    }
}
