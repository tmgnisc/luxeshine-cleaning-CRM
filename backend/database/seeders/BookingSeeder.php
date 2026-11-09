<?php

namespace Database\Seeders;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Cleaner;
use App\Models\Customer;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    /** @var array<string, array<int, array{int, int}>> */
    private array $assignments = [];

    private int $reference = 1001;

    public function run(): void
    {
        $customers = Customer::with('addresses', 'user')->get();
        $services = Service::all();
        $cleaners = Cleaner::with('services', 'user')->get();
        $adminId = User::where('email', 'admin@dusttodone.test')->value('id');

        if ($customers->isEmpty() || $services->isEmpty()) {
            return;
        }

        $created = [];

        for ($i = 0; $i < 42; $i++) {
            $customer = $customers->random();
            $address = $customer->addresses->firstWhere('is_default', true) ?? $customer->addresses->first();
            $service = $services->where('is_live', true)->random();

            $date = $this->randomDate($i);
            $duration = $this->randomDuration($service);
            $start = $this->randomStartHour($duration);
            $startTime = sprintf('%02d:00', $start);
            $endTime = sprintf('%02d:00', $start + $duration);

            $status = $this->decideStatus($date);
            $cleaner = $this->pickCleaner($cleaners, $service, $date, $start, $start + $duration, $status);

            // A booking that needs a cleaner but found none becomes pending & unassigned.
            if ($cleaner === null && in_array($status, [BookingStatus::Confirmed, BookingStatus::InProgress, BookingStatus::Completed], true)) {
                $status = $date->isFuture() ? BookingStatus::Pending : BookingStatus::Cancelled;
            }

            $pricePerHour = (float) $service->price_per_hour;

            $booking = Booking::create([
                'reference' => 'DTD-'.$this->reference++,
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'cleaner_id' => $cleaner?->id,
                'address_id' => $address?->id,
                'address_snapshot' => $address?->toSnapshot(),
                'scheduled_date' => $date->toDateString(),
                'start_time' => $startTime,
                'duration_hours' => $duration,
                'end_time' => $endTime,
                'price_per_hour' => $pricePerHour,
                'total_price' => $pricePerHour * $duration,
                'price_overridden' => false,
                'status' => $status,
                'is_paid' => $status === BookingStatus::Completed ? fake()->boolean(80) : false,
                'paid_at' => null,
                'customer_notes' => fake()->boolean(35) ? fake()->randomElement([
                    'Please ring the bell twice. We have a friendly cat.',
                    'Parking available in visitor bay B2.',
                    'Key with the concierge under my name.',
                    'Allergic to strong scents, please use mild products.',
                ]) : null,
                'source' => fake()->boolean(70) ? BookingSource::Website : BookingSource::Admin,
                'created_by' => $adminId,
                'created_at' => $date->copy()->subDays(fake()->numberBetween(2, 20)),
            ]);

            $this->applyStatusTimestamps($booking, $status, $date, $adminId);

            if ($cleaner !== null && $status !== BookingStatus::Cancelled) {
                $this->remember($cleaner->id, $date->toDateString(), $start, $start + $duration);
            }

            $created[] = $booking;
        }

        $this->seedReviews($created);
        $this->recomputeCleanerStats();
    }

    private function randomDate(int $index): Carbon
    {
        // Roughly two thirds in the past six months, one third in the next two weeks.
        if ($index % 3 === 0) {
            return Carbon::today()->addDays(fake()->numberBetween(0, 14));
        }

        return Carbon::today()->subDays(fake()->numberBetween(1, 180));
    }

    private function randomDuration(Service $service): int
    {
        $min = max(1, (int) $service->min_duration_hours);
        $max = min(6, (int) $service->max_duration_hours);

        return fake()->boolean(60)
            ? (int) $service->default_duration_hours
            : fake()->numberBetween($min, $max);
    }

    private function randomStartHour(int $duration): int
    {
        // Business hours 08:00-20:00; ensure the job finishes by close.
        return fake()->numberBetween(8, max(8, 20 - $duration));
    }

    private function decideStatus(Carbon $date): BookingStatus
    {
        if ($date->isToday()) {
            return fake()->randomElement([BookingStatus::InProgress, BookingStatus::Confirmed, BookingStatus::Completed]);
        }

        if ($date->isFuture()) {
            return fake()->boolean(55) ? BookingStatus::Confirmed : BookingStatus::Pending;
        }

        return fake()->boolean(82) ? BookingStatus::Completed : BookingStatus::Cancelled;
    }

    private function pickCleaner(mixed $cleaners, Service $service, Carbon $date, int $start, int $end, BookingStatus $status): ?Cleaner
    {
        if ($status === BookingStatus::Pending) {
            return fake()->boolean(40) ? $this->findCleaner($cleaners, $service, $date, $start, $end, false) : null;
        }

        // Future bookings require a currently-eligible cleaner; past ones may use any verified cleaner.
        $requireAvailable = ! $date->isPast();

        return $this->findCleaner($cleaners, $service, $date, $start, $end, $requireAvailable);
    }

    private function findCleaner(mixed $cleaners, Service $service, Carbon $date, int $start, int $end, bool $requireAvailable): ?Cleaner
    {
        $eligible = $cleaners->filter(function (Cleaner $cleaner) use ($service, $date, $start, $end, $requireAvailable) {
            if ($cleaner->verification_status !== VerificationStatus::Verified) {
                return false;
            }

            if ($requireAvailable && ! $cleaner->is_available) {
                return false;
            }

            if (! $cleaner->services->contains('id', $service->id)) {
                return false;
            }

            return ! $this->hasOverlap($cleaner->id, $date->toDateString(), $start, $end);
        });

        return $eligible->isNotEmpty() ? $eligible->random() : null;
    }

    private function hasOverlap(int $cleanerId, string $date, int $start, int $end): bool
    {
        foreach ($this->assignments[$cleanerId.'|'.$date] ?? [] as [$s, $e]) {
            if ($start < $e && $end > $s) {
                return true;
            }
        }

        return false;
    }

    private function remember(int $cleanerId, string $date, int $start, int $end): void
    {
        $this->assignments[$cleanerId.'|'.$date][] = [$start, $end];
    }

    private function applyStatusTimestamps(Booking $booking, BookingStatus $status, Carbon $date, ?int $adminId): void
    {
        $this->history($booking, null, BookingStatus::Pending, $adminId, $booking->created_at);

        $startDateTime = $date->copy()->setTimeFromTimeString((string) $booking->start_time);
        $endDateTime = $date->copy()->setTimeFromTimeString((string) $booking->end_time);

        if (in_array($status, [BookingStatus::Confirmed, BookingStatus::InProgress, BookingStatus::Completed], true)) {
            $this->history($booking, BookingStatus::Pending, BookingStatus::Confirmed, $adminId, $date->copy()->subDays(1));
        }

        if (in_array($status, [BookingStatus::InProgress, BookingStatus::Completed], true)) {
            $booking->started_at = $startDateTime;
            $this->history($booking, BookingStatus::Confirmed, BookingStatus::InProgress, $adminId, $startDateTime);
        }

        if ($status === BookingStatus::Completed) {
            $booking->completed_at = $endDateTime;
            if ($booking->is_paid) {
                $booking->paid_at = $endDateTime;
            }
            $this->history($booking, BookingStatus::InProgress, BookingStatus::Completed, $adminId, $endDateTime);
        }

        if ($status === BookingStatus::Cancelled) {
            $booking->cancelled_at = $date->copy()->subDays(1);
            $booking->cancellation_reason = fake()->randomElement([
                'Customer requested cancellation.',
                'Schedule conflict.',
                'No longer needed.',
            ]);
            $booking->cancelled_by = $adminId;
            $this->history($booking, BookingStatus::Pending, BookingStatus::Cancelled, $adminId, $date->copy()->subDays(1));
        }

        $booking->save();
    }

    private function history(Booking $booking, ?BookingStatus $from, BookingStatus $to, ?int $userId, Carbon $at): void
    {
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $userId,
            'created_at' => $at,
        ]);
    }

    /**
     * @param  array<int, Booking>  $bookings
     */
    private function seedReviews(array $bookings): void
    {
        foreach ($bookings as $booking) {
            if ($booking->status !== BookingStatus::Completed || $booking->cleaner_id === null) {
                continue;
            }

            if (! fake()->boolean(70)) {
                continue;
            }

            $published = fake()->boolean(60);

            Review::create([
                'booking_id' => $booking->id,
                'customer_id' => $booking->customer_id,
                'cleaner_id' => $booking->cleaner_id,
                'rating' => fake()->numberBetween(3, 5),
                'comment' => fake()->randomElement([
                    'Fantastic job, the home looked brand new. Will book again!',
                    'Very professional and thorough. Highly recommended.',
                    'Punctual and friendly, did a great job on the kitchen.',
                    'Good service overall, a few spots missed but happy.',
                    'Excellent attention to detail. Thank you!',
                ]),
                'is_published' => $published,
                'moderated_by' => $published ? User::where('email', 'admin@dusttodone.test')->value('id') : null,
                'moderated_at' => $published ? $booking->completed_at?->copy()->addDay() : null,
            ]);
        }
    }

    private function recomputeCleanerStats(): void
    {
        Cleaner::all()->each(function (Cleaner $cleaner) {
            $cleaner->completed_jobs_count = Booking::where('cleaner_id', $cleaner->id)
                ->where('status', BookingStatus::Completed->value)
                ->count();

            $published = Review::where('cleaner_id', $cleaner->id)->where('is_published', true);
            $cleaner->rating_count = (clone $published)->count();
            $cleaner->rating_avg = $cleaner->rating_count > 0 ? round((clone $published)->avg('rating'), 2) : 0;
            $cleaner->save();
        });
    }
}
