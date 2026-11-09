<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    /**
     * Create a review for a customer's own completed booking (once only).
     *
     * @param  array<string, mixed>  $data
     */
    public function createForBooking(Booking $booking, array $data): Review
    {
        if ($booking->status !== BookingStatus::Completed) {
            throw ValidationException::withMessages([
                'booking' => ['You can only review completed bookings.'],
            ]);
        }

        if ($booking->cleaner_id === null) {
            throw ValidationException::withMessages([
                'booking' => ['This booking has no assigned cleaner to review.'],
            ]);
        }

        if ($booking->review()->exists()) {
            throw ValidationException::withMessages([
                'booking' => ['You have already reviewed this booking.'],
            ]);
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'cleaner_id' => $booking->cleaner_id,
            'rating' => $data['rating'],
            'comment' => $data['comment'],
            'is_published' => false,
        ]);

        $this->notifier->admins($booking, 'admin_new_review');

        return $review;
    }

    public function setPublished(Review $review, bool $published, User $moderator): Review
    {
        $review->update([
            'is_published' => $published,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ]);

        $this->recalculateCleanerRating($review->cleaner);

        return $review;
    }

    public function delete(Review $review): void
    {
        $cleaner = $review->cleaner;
        $review->delete();
        $this->recalculateCleanerRating($cleaner);
    }

    /**
     * Recompute a cleaner's published rating average and count.
     */
    public function recalculateCleanerRating(?Cleaner $cleaner): void
    {
        if ($cleaner === null) {
            return;
        }

        $published = Review::where('cleaner_id', $cleaner->id)->where('is_published', true);
        $count = (clone $published)->count();

        $cleaner->update([
            'rating_count' => $count,
            'rating_avg' => $count > 0 ? round((clone $published)->avg('rating'), 2) : 0,
        ]);
    }
}
