<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Customer;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_bulk_delete(): void
    {
        $customer = User::factory()->customer()->create();
        $booking = Booking::factory()->create();

        $this->actingAs($customer)
            ->postJson('/api/v1/admin/bookings/bulk-delete', ['ids' => [$booking->id]])
            ->assertForbidden();
    }

    public function test_an_admin_can_bulk_delete_bookings(): void
    {
        $admin = User::factory()->admin()->create();
        $bookings = Booking::factory()->count(3)->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/bookings/bulk-delete', ['ids' => $bookings->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('data.deleted', 3);

        foreach ($bookings as $booking) {
            $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
        }
    }

    public function test_bulk_delete_requires_at_least_one_valid_id(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/bookings/bulk-delete', ['ids' => []])
            ->assertStatus(422);
    }

    public function test_bulk_deleting_services_skips_those_with_bookings(): void
    {
        $admin = User::factory()->admin()->create();
        $free = Service::factory()->create();
        $booked = Service::factory()->create();
        Booking::factory()->create(['service_id' => $booked->id]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/services/bulk-delete', ['ids' => [$free->id, $booked->id]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1)
            ->assertJsonPath('data.skipped.0.id', $booked->id);

        $this->assertSoftDeleted('services', ['id' => $free->id]);
        $this->assertDatabaseHas('services', ['id' => $booked->id, 'deleted_at' => null]);
    }

    public function test_bulk_deleting_cleaners_skips_those_with_upcoming_bookings(): void
    {
        $admin = User::factory()->admin()->create();
        $free = Cleaner::factory()->create();
        $busy = Cleaner::factory()->create();
        Booking::factory()->create([
            'cleaner_id' => $busy->id,
            'scheduled_date' => now()->addWeek()->toDateString(),
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/cleaners/bulk-delete', ['ids' => [$free->id, $busy->id]])
            ->assertOk()
            ->assertJsonPath('data.deleted', 1)
            ->assertJsonPath('data.skipped.0.id', $busy->id);

        $this->assertSoftDeleted('cleaners', ['id' => $free->id]);
        $this->assertDatabaseHas('cleaners', ['id' => $busy->id, 'deleted_at' => null]);
    }

    public function test_an_admin_can_bulk_delete_customers_and_reviews(): void
    {
        $admin = User::factory()->admin()->create();
        $customers = Customer::factory()->count(2)->create();
        $reviews = Review::factory()->count(2)->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/customers/bulk-delete', ['ids' => $customers->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/reviews/bulk-delete', ['ids' => $reviews->pluck('id')->all()])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);
    }
}
