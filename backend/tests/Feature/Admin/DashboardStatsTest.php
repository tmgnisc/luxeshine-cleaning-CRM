<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use BuildsBookingScenario, RefreshDatabase;

    public function test_dashboard_revenue_sums_only_completed_bookings(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomerWithAddress();
        $service = $this->makeService(['price_per_hour' => 50]);

        Booking::factory()->count(2)->create([
            'customer_id' => $customer->id, 'service_id' => $service->id,
            'status' => BookingStatus::Completed, 'total_price' => 100,
        ]);
        Booking::factory()->create([
            'customer_id' => $customer->id, 'service_id' => $service->id,
            'status' => BookingStatus::Pending, 'total_price' => 999,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.revenue', 200)
            ->assertJsonPath('data.total_bookings', 3);
    }

    public function test_revenue_chart_respects_the_months_parameter(): void
    {
        $admin = User::factory()->admin()->create();

        $data = $this->actingAs($admin)
            ->getJson('/api/v1/admin/dashboard/revenue-chart?months=3')
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $data);
    }

    public function test_global_search_finds_a_booking_by_reference(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomerWithAddress();
        $service = $this->makeService();
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id, 'service_id' => $service->id, 'reference' => 'DTD-55501',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/search?q=DTD-55501')
            ->assertOk()
            ->assertJsonPath('data.bookings.0.reference', 'DTD-55501');
    }

    public function test_only_super_admin_can_manage_admin_users(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/users')->assertForbidden();

        $this->actingAs($superAdmin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'New Admin',
                'email' => 'newadmin@example.com',
                'role' => 'admin',
                'send_invite' => false,
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin');
    }

    public function test_a_super_admin_cannot_delete_their_own_account(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();

        $this->actingAs($superAdmin)
            ->deleteJson("/api/v1/admin/users/{$superAdmin->id}")
            ->assertStatus(422);
    }
}
