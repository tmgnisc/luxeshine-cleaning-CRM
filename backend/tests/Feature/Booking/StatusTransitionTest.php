<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

class StatusTransitionTest extends TestCase
{
    use BuildsBookingScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        Notification::fake();
    }

    private function pendingBooking(bool $withCleaner = false): Booking
    {
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $cleaner = $withCleaner ? $this->makeCleaner($service) : null;

        return Booking::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'cleaner_id' => $cleaner?->id,
            'address_id' => $customer->addresses->first()->id,
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'duration_hours' => 2,
            'status' => BookingStatus::Pending,
        ]);
    }

    public function test_confirming_requires_an_assigned_cleaner(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->pendingBooking(withCleaner: false);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/bookings/{$booking->id}/status", ['status' => 'confirmed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_a_full_lifecycle_transition_sets_timestamps_and_history(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->pendingBooking(withCleaner: true);

        $this->actingAs($admin)->patchJson("/api/v1/admin/bookings/{$booking->id}/status", ['status' => 'confirmed'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/v1/admin/bookings/{$booking->id}/status", ['status' => 'in_progress'])->assertOk();
        $this->actingAs($admin)->patchJson("/api/v1/admin/bookings/{$booking->id}/status", ['status' => 'completed'])->assertOk();

        $booking->refresh();
        $this->assertNotNull($booking->started_at);
        $this->assertNotNull($booking->completed_at);
        $this->assertSame(BookingStatus::Completed, $booking->status);
        // 1 creation row (factory doesn't add) + 3 transitions recorded by the service.
        $this->assertSame(3, $booking->statusHistory()->count());
    }

    public function test_an_invalid_transition_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->pendingBooking(withCleaner: true);

        // pending -> completed is not allowed.
        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/bookings/{$booking->id}/status", ['status' => 'completed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_a_regular_admin_cannot_reopen_a_cancelled_booking(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->pendingBooking(withCleaner: true);
        $booking->update(['status' => BookingStatus::Cancelled]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/bookings/{$booking->id}/status", ['status' => 'pending'])
            ->assertStatus(422);
    }

    public function test_a_super_admin_can_reopen_a_final_booking(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $booking = $this->pendingBooking(withCleaner: true);
        $booking->update(['status' => BookingStatus::Cancelled]);

        $this->actingAs($superAdmin)
            ->patchJson("/api/v1/admin/bookings/{$booking->id}/status", ['status' => 'pending'])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    }
}
