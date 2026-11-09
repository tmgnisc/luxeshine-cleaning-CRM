<?php

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

class CleanerAssignmentTest extends TestCase
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

    private function bookingFor($service, $customer, array $overrides = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'cleaner_id' => null,
            'address_id' => $customer->addresses->first()->id,
            'scheduled_date' => '2026-06-15',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'duration_hours' => 2,
            'price_per_hour' => 50,
            'total_price' => 100,
            'status' => BookingStatus::Pending,
        ], $overrides));
    }

    public function test_available_cleaners_endpoint_lists_only_eligible_cleaners(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->makeService();
        $eligible = $this->makeCleaner($service);
        $this->makeCleaner($service, ['verification_status' => VerificationStatus::Pending]); // not verified

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/available-cleaners?service_id='.$service->id.'&date=2026-06-15&start_time=10:00&duration=2')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($eligible->id));
        $this->assertCount(1, $ids);
    }

    public function test_assigning_a_cleaner_without_the_skill_fails(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->makeService();
        $otherService = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $cleaner = $this->makeCleaner($otherService); // skilled in a different service
        $booking = $this->bookingFor($service, $customer);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/bookings/{$booking->id}/assign", ['cleaner_id' => $cleaner->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cleaner_id');
    }

    public function test_a_cleaner_cannot_be_double_booked_on_overlapping_times(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $cleaner = $this->makeCleaner($service);

        // Existing confirmed booking 10:00–12:00.
        $this->bookingFor($service, $customer, [
            'cleaner_id' => $cleaner->id,
            'status' => BookingStatus::Confirmed,
        ]);

        // New booking 11:00–13:00 overlaps.
        $overlapping = $this->bookingFor($service, $customer, [
            'start_time' => '11:00',
            'end_time' => '13:00',
        ]);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/bookings/{$overlapping->id}/assign", ['cleaner_id' => $cleaner->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cleaner_id');
    }

    public function test_an_eligible_cleaner_can_be_assigned(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $cleaner = $this->makeCleaner($service);
        $booking = $this->bookingFor($service, $customer);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/bookings/{$booking->id}/assign", ['cleaner_id' => $cleaner->id])
            ->assertOk()
            ->assertJsonPath('data.cleaner_id', $cleaner->id);
    }
}
