<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrepareProductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_wipes_demo_data_and_seeds_the_super_admin(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'prabin@luxeshineprofessional.co.nz');

        // Demo data that must be removed.
        $booking = Booking::factory()->create();
        User::factory()->customer()->create();
        User::factory()->admin()->create();

        $this->artisan('app:prepare-production --force')->assertSuccessful();

        // All demo people and bookings gone.
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);

        // Only the production super admin remains.
        $this->assertDatabaseCount('users', 1);
        $admin = User::sole();
        $this->assertSame($email, $admin->email);
        $this->assertSame(UserRole::SuperAdmin, $admin->role);

        // Catalogue + Airbnb feed seeded.
        $this->assertGreaterThan(0, Service::count());
        $this->assertDatabaseHas('calendar_feeds', ['source' => 'airbnb']);
    }

    public function test_it_preserves_an_existing_super_admin_password(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'prabin@luxeshineprofessional.co.nz');
        $admin = User::factory()->superAdmin()->create([
            'email' => $email,
            'password' => bcrypt('ChangedFromPanel1'),
        ]);
        $originalHash = $admin->password;

        $this->artisan('app:prepare-production --force')->assertSuccessful();

        $this->assertSame($originalHash, User::whereKey($admin->id)->value('password'));
    }
}
