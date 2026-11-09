<?php

namespace Tests\Feature\Review;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Customer;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsBookingScenario;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use BuildsBookingScenario, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSettings();
        Notification::fake();
    }

    private function completedBooking(Customer $customer, Cleaner $cleaner): Booking
    {
        $service = $this->makeService();

        return Booking::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'cleaner_id' => $cleaner->id,
            'address_id' => $customer->addresses->first()->id,
            'status' => BookingStatus::Completed,
        ]);
    }

    public function test_a_customer_can_review_their_completed_booking_once(): void
    {
        $customer = $this->makeCustomerWithAddress();
        $cleaner = Cleaner::factory()->create();
        $booking = $this->completedBooking($customer, $cleaner);

        $this->actingAs($customer->user)
            ->postJson("/api/v1/customer/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Excellent work!'])
            ->assertCreated();

        // Second attempt is rejected.
        $this->actingAs($customer->user)
            ->postJson("/api/v1/customer/bookings/{$booking->id}/review", ['rating' => 4, 'comment' => 'Again'])
            ->assertStatus(422);
    }

    public function test_a_customer_cannot_review_a_non_completed_booking(): void
    {
        $customer = $this->makeCustomerWithAddress();
        $cleaner = Cleaner::factory()->create();
        $service = $this->makeService();
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'cleaner_id' => $cleaner->id,
            'address_id' => $customer->addresses->first()->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $this->actingAs($customer->user)
            ->postJson("/api/v1/customer/bookings/{$booking->id}/review", ['rating' => 5, 'comment' => 'Nice'])
            ->assertStatus(422);
    }

    public function test_publishing_and_unpublishing_recalculates_the_cleaner_rating(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = $this->makeCustomerWithAddress();
        $cleaner = Cleaner::factory()->create(['rating_avg' => 0, 'rating_count' => 0]);

        $review5 = Review::factory()->create(['cleaner_id' => $cleaner->id, 'rating' => 5, 'is_published' => false]);
        $review3 = Review::factory()->create(['cleaner_id' => $cleaner->id, 'rating' => 3, 'is_published' => false]);

        // Publishing the 5-star review -> avg 5, count 1.
        $this->actingAs($admin)->patchJson("/api/v1/admin/reviews/{$review5->id}/publish")->assertOk();
        $cleaner->refresh();
        $this->assertEquals(5.0, (float) $cleaner->rating_avg);
        $this->assertSame(1, $cleaner->rating_count);

        // Publishing the 3-star review -> avg 4, count 2.
        $this->actingAs($admin)->patchJson("/api/v1/admin/reviews/{$review3->id}/publish")->assertOk();
        $cleaner->refresh();
        $this->assertEquals(4.0, (float) $cleaner->rating_avg);
        $this->assertSame(2, $cleaner->rating_count);

        // Unpublishing the 5-star review -> avg 3, count 1.
        $this->actingAs($admin)->patchJson("/api/v1/admin/reviews/{$review5->id}/publish")->assertOk();
        $cleaner->refresh();
        $this->assertEquals(3.0, (float) $cleaner->rating_avg);
        $this->assertSame(1, $cleaner->rating_count);
    }

    public function test_admin_review_stats_are_returned(): void
    {
        $admin = User::factory()->admin()->create();
        Review::factory()->published()->count(2)->create();
        Review::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/reviews/stats')
            ->assertOk()
            ->assertJsonPath('data.published', 2)
            ->assertJsonPath('data.awaiting', 3);
    }
}
