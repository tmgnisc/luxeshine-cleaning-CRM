<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Service;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CleanerAvailabilityService
{
    /**
     * Evaluate whether a cleaner may take a job, returning the verdict plus the
     * reasons any rule failed (used by the admin "show all with reasons" view).
     *
     * @return array{eligible: bool, reasons: array<int, string>}
     */
    public function evaluate(
        Cleaner $cleaner,
        Service $service,
        CarbonInterface $date,
        string $startTime,
        float $duration,
        ?int $excludeBookingId = null,
    ): array {
        $reasons = [];

        if ($cleaner->verification_status !== VerificationStatus::Verified) {
            $reasons[] = 'Cleaner is not verified.';
        }

        if (! $cleaner->is_available) {
            $reasons[] = 'Cleaner is marked as unavailable.';
        }

        if ($cleaner->relationLoaded('user') ? ($cleaner->user?->is_active === false) : (! $cleaner->user()->where('is_active', true)->exists())) {
            $reasons[] = 'Cleaner account is deactivated.';
        }

        if (! $this->hasSkill($cleaner, $service)) {
            $reasons[] = 'Cleaner does not offer this service.';
        }

        [$startMin, $endMin] = $this->range($startTime, $duration);

        if (! $this->withinWorkingHours($cleaner, $date, $startMin, $endMin)) {
            $reasons[] = 'The time falls outside the cleaner\'s working hours.';
        }

        if ($this->onTimeOff($cleaner, $date)) {
            $reasons[] = 'The cleaner has booked time off on this date.';
        }

        if ($this->hasOverlap($cleaner, $date, $startMin, $endMin, $excludeBookingId)) {
            $reasons[] = 'The cleaner already has an overlapping booking.';
        }

        return ['eligible' => $reasons === [], 'reasons' => $reasons];
    }

    public function isEligible(
        Cleaner $cleaner,
        Service $service,
        CarbonInterface $date,
        string $startTime,
        float $duration,
        ?int $excludeBookingId = null,
    ): bool {
        return $this->evaluate($cleaner, $service, $date, $startTime, $duration, $excludeBookingId)['eligible'];
    }

    /**
     * All cleaners eligible for a given slot.
     *
     * @return Collection<int, Cleaner>
     */
    public function eligibleCleaners(
        Service $service,
        CarbonInterface $date,
        string $startTime,
        float $duration,
        ?int $excludeBookingId = null,
    ): Collection {
        return $this->candidatePool($service)
            ->filter(fn (Cleaner $cleaner) => $this->isEligible($cleaner, $service, $date, $startTime, $duration, $excludeBookingId))
            ->values();
    }

    /**
     * Candidate cleaners who could ever take this service (verified, active,
     * available and skilled) — the pool availability checks iterate over.
     *
     * @return Collection<int, Cleaner>
     */
    public function candidatePool(Service $service): Collection
    {
        return Cleaner::query()
            ->with(['user', 'services', 'workingHours', 'timeOff'])
            ->where('verification_status', VerificationStatus::Verified->value)
            ->where('is_available', true)
            ->whereHas('user', fn ($u) => $u->where('is_active', true))
            ->whereHas('services', fn ($s) => $s->where('services.id', $service->id))
            ->get();
    }

    /**
     * Start-time slots on a day where at least one eligible cleaner is free.
     *
     * @return array<int, array{start_time: string, available: bool}>
     */
    public function daySlots(Service $service, CarbonInterface $date, float $duration, int $openMin, int $closeMin, int $stepMin = 60): array
    {
        $pool = $this->candidatePool($service);
        $slots = [];

        for ($start = $openMin; $start + ($duration * 60) <= $closeMin; $start += $stepMin) {
            $startTime = $this->minutesToTime($start);
            $available = $pool->contains(
                fn (Cleaner $cleaner) => $this->isEligible($cleaner, $service, $date, $startTime, $duration)
            );

            if ($available) {
                $slots[] = ['start_time' => $startTime, 'available' => true];
            }
        }

        return $slots;
    }

    private function hasSkill(Cleaner $cleaner, Service $service): bool
    {
        if ($cleaner->relationLoaded('services')) {
            return $cleaner->services->contains('id', $service->id);
        }

        return $cleaner->services()->where('services.id', $service->id)->exists();
    }

    private function withinWorkingHours(Cleaner $cleaner, CarbonInterface $date, int $startMin, int $endMin): bool
    {
        $dayOfWeek = $date->dayOfWeekIso - 1; // 0 = Mon ... 6 = Sun

        $hours = $cleaner->relationLoaded('workingHours')
            ? $cleaner->workingHours->firstWhere('day_of_week', $dayOfWeek)
            : $cleaner->workingHours()->where('day_of_week', $dayOfWeek)->first();

        if ($hours === null || $hours->is_off || $hours->start_time === null || $hours->end_time === null) {
            return false;
        }

        return $this->toMinutes((string) $hours->start_time) <= $startMin
            && $this->toMinutes((string) $hours->end_time) >= $endMin;
    }

    private function onTimeOff(Cleaner $cleaner, CarbonInterface $date): bool
    {
        $day = $date->toDateString();

        $collection = $cleaner->relationLoaded('timeOff')
            ? $cleaner->timeOff
            : $cleaner->timeOff()->get();

        return $collection->contains(
            fn ($off) => $off->start_date->toDateString() <= $day && $off->end_date->toDateString() >= $day
        );
    }

    private function hasOverlap(Cleaner $cleaner, CarbonInterface $date, int $startMin, int $endMin, ?int $excludeBookingId): bool
    {
        return Booking::query()
            ->where('cleaner_id', $cleaner->id)
            ->whereDate('scheduled_date', $date->toDateString())
            ->where('status', '!=', BookingStatus::Cancelled->value)
            ->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))
            ->where('start_time', '<', $this->minutesToTime($endMin).':00')
            ->where('end_time', '>', $this->minutesToTime($startMin).':00')
            ->exists();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function range(string $startTime, float $duration): array
    {
        $startMin = $this->toMinutes($startTime);

        return [$startMin, $startMin + (int) round($duration * 60)];
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
