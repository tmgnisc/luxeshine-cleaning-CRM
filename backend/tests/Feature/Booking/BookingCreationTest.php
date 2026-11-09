<?php

namespace Tests\Feature\Booking;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

class BookingCreationTest extends TestCase
{
    use BuildsBookingScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        Notification::fake();
        Carbon::setTestNow('2026-06-10 09:00:00'); // Wednesday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_customer_can_create_a_booking(): void
    {
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $address = $customer->addresses->first();

        $this->actingAs($customer->user)
            ->postJson('/api/v1/customer/bookings', [
                'service_id' => $service->id,
                'address_id' => $address->id,
                'scheduled_date' => '2026-06-15',
                'start_time' => '10:00',
                'duration_hours' => 2,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_price', 100)
            ->assertJsonPath('data.end_time', '12:00');

        $this->assertDatabaseHas('bookings', [
            'customer_id' => $customer->id,
            'status' => 'pending',
            'total_price' => 100,
        ]);
    }

    public function test_a_booking_within_the_lead_time_is_rejected(): void
    {
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $address = $customer->addresses->first();

        // Same day, only one hour away — inside the 24h lead time.
        $this->actingAs($customer->user)
            ->postJson('/api/v1/customer/bookings', [
                'service_id' => $service->id,
                'address_id' => $address->id,
                'scheduled_date' => '2026-06-10',
                'start_time' => '10:00',
                'duration_hours' => 2,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_date');
    }

    public function test_a_booking_outside_business_hours_is_rejected(): void
    {
        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $address = $customer->addresses->first();

        $this->actingAs($customer->user)
            ->postJson('/api/v1/customer/bookings', [
                'service_id' => $service->id,
                'address_id' => $address->id,
                'scheduled_date' => '2026-06-15',
                'start_time' => '19:00',
                'duration_hours' => 2, // ends 21:00, past 20:00 close
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_time');
    }

    public function test_booking_is_blocked_when_not_accepting_bookings(): void
    {
        Setting::updateOrCreate(['key' => 'accepting_bookings'], ['value' => '0', 'type' => 'bool']);
        app(SettingsService::class)->flush();

        $service = $this->makeService();
        $customer = $this->makeCustomerWithAddress();
        $address = $customer->addresses->first();

        $this->actingAs($customer->user)
            ->postJson('/api/v1/customer/bookings', [
                'service_id' => $service->id,
                'address_id' => $address->id,
                'scheduled_date' => '2026-06-15',
                'start_time' => '10:00',
                'duration_hours' => 2,
            ])
            ->assertStatus(503);
    }

    public function test_public_availability_returns_slots_when_a_cleaner_is_free(): void
    {
        $service = $this->makeService();
        $this->makeCleaner($service);

        $response = $this->getJson('/api/v1/public/availability?service_id='.$service->id.'&date=2026-06-15&duration=2')
            ->assertOk();

        $this->assertNotEmpty($response->json('data.slots'));
        $this->assertSame('08:00', $response->json('data.slots.0.start_time'));
    }

    public function test_public_availability_is_empty_without_eligible_cleaners(): void
    {
        $service = $this->makeService();

        $this->getJson('/api/v1/public/availability?service_id='.$service->id.'&date=2026-06-15&duration=2')
            ->assertOk()
            ->assertJsonPath('data.slots', []);
    }
}
