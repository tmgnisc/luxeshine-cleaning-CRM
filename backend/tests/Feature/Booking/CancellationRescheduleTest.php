<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

class CancellationRescheduleTest extends TestCase
{
    use BuildsBookingScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        Notification::fake();
        Carbon::setTestNow('2026-06-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_customer_cannot_cancel_inside_the_cutoff_window(): void
    {
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();

        // Cutoff is 12h; booking starts only 3 hours from "now".
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'address_id' => $customer->addresses->first()->id,
            'scheduled_date' => '2026-06-10',
            'start_time' => '12:00',
            'end_time' => '14:00',
            'duration_hours' => 2,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer->user)
            ->postJson("/api/v1/customer/bookings/{$booking->id}/cancel", ['reason' => 'Changed my mind'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_date');
    }

    public function test_a_customer_can_cancel_outside_the_cutoff_window(): void
    {
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'address_id' => $customer->addresses->first()->id,
            'scheduled_date' => '2026-06-20',
            'start_time' => '12:00',
            'end_time' => '14:00',
            'duration_hours' => 2,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer->user)
            ->postJson("/api/v1/customer/bookings/{$booking->id}/cancel", ['reason' => 'Travelling'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_rescheduling_a_confirmed_booking_away_from_the_cleaner_unassigns_them(): void
    {
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $cleaner = $this->makeCleaner($service);

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'cleaner_id' => $cleaner->id,
            'address_id' => $customer->addresses->first()->id,
            'scheduled_date' => '2026-06-20',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'duration_hours' => 2,
            'status' => BookingStatus::Confirmed,
        ]);

        // Block the cleaner on the new date with another booking 10:00-12:00.
        Booking::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'cleaner_id' => $cleaner->id,
            'address_id' => $customer->addresses->first()->id,
            'scheduled_date' => '2026-06-25',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'duration_hours' => 2,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer->user)
            ->putJson("/api/v1/customer/bookings/{$booking->id}/reschedule", [
                'scheduled_date' => '2026-06-25',
                'start_time' => '10:00',
                'duration_hours' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.cleaner_id', null)
            ->assertJsonPath('data.status', 'pending');
    }
}
