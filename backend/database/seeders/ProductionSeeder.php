<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\CalendarFeed;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the minimum a fresh production environment needs: core settings, the
 * service catalogue, the first super admin, and the initial Airbnb calendar
 * feed. Safe to run more than once — it never clobbers an existing admin's
 * password or a feed that has already been added.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            ServiceSeeder::class,
        ]);

        $this->seedSuperAdmin();
        $this->seedAirbnbFeed();
    }

    private function seedSuperAdmin(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'prabin@luxeshineprofessional.co.nz');
        $name = env('SUPERADMIN_NAME', 'Prabin');
        $password = env('SUPERADMIN_PASSWORD', 'Newone22');

        $existing = User::where('email', $email)->first();

        if ($existing !== null) {
            // Keep whatever password the admin may have already set from the panel.
            $existing->update([
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
                'email_verified_at' => $existing->email_verified_at ?? now(),
            ]);

            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => UserRole::SuperAdmin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function seedAirbnbFeed(): void
    {
        $url = env('AIRBNB_ICAL_URL', 'https://www.airbnb.com/calendar/ical/1198707641304650761.ics?t=e6b2bd39c188406bbcb1ab5efc266d4c&locale=en-AU');

        if ($url === '' || CalendarFeed::where('url', $url)->exists()) {
            return;
        }

        CalendarFeed::create([
            'name' => env('AIRBNB_ICAL_NAME', 'Airbnb Listing'),
            'source' => 'airbnb',
            'url' => $url,
            'is_active' => true,
        ]);
    }
}
