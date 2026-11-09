<?php

namespace App\Services;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Address;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Cleaner;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CleanerAvailabilityService $availability,
        private readonly BookingNotifier $notifier,
    ) {}

    /**
     * Create a booking from already-validated input.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, BookingSource $source, bool $enforceCustomerRules): Booking
    {
        $service = Service::findOrFail($data['service_id']);
        $date = Carbon::parse($data['scheduled_date']);
        $startTime = $this->normalizeTime($data['start_time']);
        $duration = (float) $data['duration_hours'];

        $this->assertServiceBookable($service, $enforceCustomerRules);
        $this->assertDurationWithinBounds($service, $duration);
        $this->assertWithinBusinessHours($startTime, $duration);

        if ($enforceCustomerRules) {
            $this->assertLeadTime($date, $startTime);
        }

        $address = isset($data['address_id']) ? Address::find($data['address_id']) : null;
        $pricePerHour = (float) $service->price_per_hour;
        $overridden = false;
        $total = round($pricePerHour * $duration, 2);

        if (! $enforceCustomerRules && isset($data['total_price']) && (float) $data['total_price'] !== $total) {
            $total = round((float) $data['total_price'], 2);
            $overridden = true;
        }

        $booking = DB::transaction(function () use ($data, $service, $date, $startTime, $duration, $address, $pricePerHour, $total, $overridden, $actor, $source) {
            $booking = Booking::create([
                'reference' => $this->generateReference(),
                'customer_id' => $data['customer_id'],
                'service_id' => $service->id,
                'cleaner_id' => null,
                'address_id' => $address?->id,
                'address_snapshot' => $address?->toSnapshot(),
                'scheduled_date' => $date->toDateString(),
                'start_time' => $startTime,
                'duration_hours' => $duration,
                'end_time' => $this->endTime($startTime, $duration),
                'price_per_hour' => $pricePerHour,
                'total_price' => $total,
                'price_overridden' => $overridden,
                'status' => BookingStatus::Pending,
                'customer_notes' => $data['customer_notes'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'source' => $source,
                'created_by' => $actor->id,
            ]);

            $this->recordHistory($booking, null, BookingStatus::Pending, $actor, 'Booking created.');

            return $booking;
        });

        // Optional cleaner assignment on admin creation.
        if (! $enforceCustomerRules && ! empty($data['cleaner_id'])) {
            $this->assignCleaner($booking, (int) $data['cleaner_id'], $actor);
        }

        $booking->load(['customer.user', 'service', 'cleaner.user']);
        $this->notifier->customer($booking, 'booking_received');
        $this->notifier->admins($booking, 'admin_new_booking');

        return $booking;
    }

    /**
     * Assign (or unassign with null) a cleaner, enforcing the eligibility rules.
     */
    public function assignCleaner(Booking $booking, ?int $cleanerId, User $actor, bool $validate = true): Booking
    {
        if ($cleanerId === null) {
            $previous = $booking->cleaner;
            $booking->update(['cleaner_id' => null]);

            if ($previous) {
                $booking->setRelation('cleaner', $previous);
                $this->notifier->cleaner($booking, 'job_unassigned');
                $booking->setRelation('cleaner', null);
            }

            return $booking->fresh(['customer.user', 'service', 'cleaner.user']);
        }

        $cleaner = Cleaner::with(['user', 'services', 'workingHours', 'timeOff'])->findOrFail($cleanerId);

        if ($validate) {
            $verdict = $this->availability->evaluate(
                $cleaner,
                $booking->service,
                $booking->scheduled_date,
                (string) $booking->start_time,
                (float) $booking->duration_hours,
                $booking->id,
            );

            if (! $verdict['eligible']) {
                throw ValidationException::withMessages([
                    'cleaner_id' => $verdict['reasons'],
                ]);
            }
        }

        $booking->update(['cleaner_id' => $cleaner->id]);
        $booking->load(['customer.user', 'service', 'cleaner.user']);
        $this->notifier->cleaner($booking, 'job_assigned');

        return $booking;
    }

    /**
     * Move a booking through the status machine, writing history and side effects.
     */
    public function changeStatus(Booking $booking, BookingStatus $to, User $actor, ?string $note = null, ?string $cancellationReason = null): Booking
    {
        $from = $booking->status;

        if ($from === $to) {
            return $booking;
        }

        $isSuperAdminReopen = $actor->isSuperAdmin() && $from->isFinal();

        if (! $from->canTransitionTo($to) && ! $isSuperAdminReopen) {
            throw ValidationException::withMessages([
                'status' => ["A booking cannot move from {$from->value} to {$to->value}."],
            ]);
        }

        if ($to === BookingStatus::Confirmed && $booking->cleaner_id === null) {
            throw ValidationException::withMessages([
                'status' => ['A cleaner must be assigned before confirming a booking.'],
            ]);
        }

        DB::transaction(function () use ($booking, $from, $to, $actor, $note, $cancellationReason) {
            $attributes = ['status' => $to];

            if ($to === BookingStatus::InProgress) {
                $attributes['started_at'] = now();
            }

            if ($to === BookingStatus::Completed) {
                $attributes['completed_at'] = now();
            }

            if ($to === BookingStatus::Cancelled) {
                $attributes['cancelled_at'] = now();
                $attributes['cancelled_by'] = $actor->id;
                $attributes['cancellation_reason'] = $cancellationReason;
            }

            $booking->update($attributes);
            $this->recordHistory($booking, $from, $to, $actor, $note);
        });

        if ($to === BookingStatus::Completed && $booking->cleaner_id !== null) {
            $this->recalculateCompletedJobs((int) $booking->cleaner_id);
        }

        $booking->load(['customer.user', 'service', 'cleaner.user']);
        $this->dispatchStatusNotifications($booking, $to, $actor, $cancellationReason);

        return $booking;
    }

    private function recalculateCompletedJobs(int $cleanerId): void
    {
        $count = Booking::where('cleaner_id', $cleanerId)
            ->where('status', BookingStatus::Completed->value)
            ->count();

        Cleaner::whereKey($cleanerId)->update(['completed_jobs_count' => $count]);
    }

    /**
     * Reschedule a booking, re-validating cleaner availability.
     */
    public function reschedule(Booking $booking, string $date, string $startTime, ?float $duration, User $actor, bool $enforceCustomerRules): Booking
    {
        $newDate = Carbon::parse($date);
        $startTime = $this->normalizeTime($startTime);
        $duration ??= (float) $booking->duration_hours;

        $this->assertDurationWithinBounds($booking->service, $duration);
        $this->assertWithinBusinessHours($startTime, $duration);

        if ($enforceCustomerRules) {
            $this->assertCancellationWindow($booking, 'reschedule');
            $this->assertLeadTime($newDate, $startTime);
        }

        $unassigned = false;

        DB::transaction(function () use ($booking, $newDate, $startTime, $duration, $actor, &$unassigned) {
            $booking->update([
                'scheduled_date' => $newDate->toDateString(),
                'start_time' => $startTime,
                'duration_hours' => $duration,
                'end_time' => $this->endTime($startTime, $duration),
                'total_price' => $booking->price_overridden
                    ? $booking->total_price
                    : round((float) $booking->price_per_hour * $duration, 2),
            ]);

            // If a cleaner was assigned but is no longer free, unassign and reset to pending.
            if ($booking->cleaner_id !== null) {
                $cleaner = $booking->cleaner()->with(['user', 'services', 'workingHours', 'timeOff'])->first();
                $eligible = $cleaner && $this->availability->isEligible(
                    $cleaner, $booking->service, $newDate, $startTime, $duration, $booking->id,
                );

                if (! $eligible) {
                    $booking->update(['cleaner_id' => null, 'status' => BookingStatus::Pending]);
                    $unassigned = true;
                }
            }

            $this->recordHistory($booking, $booking->status, $booking->status, $actor, 'Booking rescheduled.');
        });

        $booking->load(['customer.user', 'service', 'cleaner.user']);
        $this->notifier->customer($booking, 'booking_rescheduled');

        if ($unassigned) {
            // The previously assigned cleaner was notified via the job flow.
        }

        return $booking->fresh(['customer.user', 'service', 'cleaner.user']);
    }

    public function cancel(Booking $booking, ?string $reason, User $actor, bool $enforceCustomerRules): Booking
    {
        if ($enforceCustomerRules) {
            if (! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only pending or confirmed bookings can be cancelled.'],
                ]);
            }
            $this->assertCancellationWindow($booking, 'cancel');
        }

        $booking = $this->changeStatus($booking, BookingStatus::Cancelled, $actor, $reason, $reason);

        if ($enforceCustomerRules) {
            $this->notifier->admins($booking, 'admin_booking_cancelled', ['reason' => $reason ? "Reason: $reason" : '']);
        }

        return $booking;
    }

    public function markPaid(Booking $booking, bool $paid): Booking
    {
        $booking->update([
            'is_paid' => $paid,
            'paid_at' => $paid ? now() : null,
        ]);

        return $booking;
    }

    public function generateReference(): string
    {
        do {
            $candidate = 'DTD-'.random_int(1000, 999999);
        } while (Booking::withTrashed()->where('reference', $candidate)->exists());

        return $candidate;
    }

    private function dispatchStatusNotifications(Booking $booking, BookingStatus $to, User $actor, ?string $reason): void
    {
        match ($to) {
            BookingStatus::Confirmed => $this->notifier->customer($booking, 'booking_confirmed'),
            BookingStatus::Completed => $this->notifier->customer($booking, 'booking_completed'),
            BookingStatus::Cancelled => $this->notifier->customer($booking, 'booking_cancelled', ['reason' => $reason ? "Reason: $reason" : '']),
            default => null,
        };
    }

    private function recordHistory(Booking $booking, ?BookingStatus $from, BookingStatus $to, User $actor, ?string $note): void
    {
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'changed_by' => $actor->id,
            'note' => $note,
        ]);
    }

    private function assertServiceBookable(Service $service, bool $enforceCustomerRules): void
    {
        if ($enforceCustomerRules && ! $service->is_live) {
            throw ValidationException::withMessages([
                'service_id' => ['This service is not currently available for booking.'],
            ]);
        }
    }

    private function assertDurationWithinBounds(Service $service, float $duration): void
    {
        if ($duration < (float) $service->min_duration_hours || $duration > (float) $service->max_duration_hours) {
            throw ValidationException::withMessages([
                'duration_hours' => ["Duration must be between {$service->min_duration_hours} and {$service->max_duration_hours} hours for this service."],
            ]);
        }
    }

    private function assertWithinBusinessHours(string $startTime, float $duration): void
    {
        $open = $this->toMinutes($this->settings->string('business_open_time', '08:00'));
        $close = $this->toMinutes($this->settings->string('business_close_time', '20:00'));
        $start = $this->toMinutes($startTime);
        $end = $start + (int) round($duration * 60);

        if ($start < $open || $end > $close) {
            $openLabel = $this->settings->string('business_open_time', '08:00');
            $closeLabel = $this->settings->string('business_close_time', '20:00');
            throw ValidationException::withMessages([
                'start_time' => ["Bookings must start and finish within business hours ({$openLabel} to {$closeLabel})."],
            ]);
        }
    }

    private function assertLeadTime(CarbonInterface $date, string $startTime): void
    {
        $leadHours = $this->settings->int('booking_lead_time_hours', 24);
        $start = Carbon::parse($date->toDateString().' '.$startTime);

        if ($start->lt(now()->addHours($leadHours))) {
            throw ValidationException::withMessages([
                'scheduled_date' => ["Bookings must be made at least {$leadHours} hours in advance."],
            ]);
        }
    }

    private function assertCancellationWindow(Booking $booking, string $action): void
    {
        $cutoffHours = $this->settings->int('cancellation_cutoff_hours', 12);
        $start = Carbon::parse($booking->scheduled_date->toDateString().' '.$booking->start_time);

        if ($start->lt(now()->addHours($cutoffHours))) {
            $verb = $action === 'cancel' ? 'cancelled' : 'rescheduled';
            throw ValidationException::withMessages([
                'scheduled_date' => ["Bookings can only be {$verb} at least {$cutoffHours} hours before the start time."],
            ]);
        }
    }

    private function endTime(string $startTime, float $duration): string
    {
        $end = $this->toMinutes($startTime) + (int) round($duration * 60);

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }

    private function normalizeTime(string $time): string
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return sprintf('%02d:%02d', (int) $h, (int) $m);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }
}
