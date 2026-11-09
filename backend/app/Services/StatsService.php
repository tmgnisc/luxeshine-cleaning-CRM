<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Customer;
use App\Models\Review;
use Illuminate\Support\Collection;

class StatsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $completed = Booking::where('status', BookingStatus::Completed->value);

        return [
            'total_bookings' => Booking::count(),
            'today_scheduled' => Booking::whereDate('scheduled_date', today())
                ->whereNotIn('status', [BookingStatus::Cancelled->value])
                ->count(),
            'revenue' => round((float) (clone $completed)->sum('total_price'), 2),
            'collected' => round((float) (clone $completed)->where('is_paid', true)->sum('total_price'), 2),
            'active_jobs' => Booking::where('status', BookingStatus::InProgress->value)->count(),
            'customers' => Customer::count(),
            'staff_total' => Cleaner::count(),
            'staff_available' => Cleaner::where('is_available', true)
                ->where('verification_status', VerificationStatus::Verified->value)
                ->count(),
        ];
    }

    /**
     * Monthly revenue and booking counts for the last N months.
     *
     * @return array<int, array{month: string, label: string, revenue: float, bookings: int}>
     */
    public function revenueChart(int $months = 6): array
    {
        $start = now()->copy()->subMonths($months - 1)->startOfMonth();

        $bookings = Booking::where('scheduled_date', '>=', $start->toDateString())
            ->get(['scheduled_date', 'status', 'total_price']);

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $inMonth = $bookings->filter(fn (Booking $b) => $b->scheduled_date->format('Y-m') === $key);

            $series[] = [
                'month' => $key,
                'label' => $month->format('M Y'),
                'revenue' => round((float) $inMonth->where('status', BookingStatus::Completed->value)->sum('total_price'), 2),
                'bookings' => $inMonth->count(),
            ];
        }

        return $series;
    }

    /**
     * @return Collection<int, array{service: string, bookings: int, revenue: float}>
     */
    public function bookingsByService(): Collection
    {
        return Booking::query()
            ->with('service')
            ->get()
            ->groupBy('service_id')
            ->map(fn (Collection $group) => [
                'service' => $group->first()->service?->name ?? 'Unknown',
                'bookings' => $group->count(),
                'revenue' => round((float) $group->where('status', BookingStatus::Completed->value)->sum('total_price'), 2),
            ])
            ->sortByDesc('bookings')
            ->values();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Booking>
     */
    public function todayJobs(): \Illuminate\Database\Eloquent\Collection
    {
        return Booking::query()
            ->with(['service', 'customer.user', 'cleaner.user'])
            ->whereDate('scheduled_date', today())
            ->whereNot('status', BookingStatus::Cancelled->value)
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @return Collection<int, array{id: int, name: string|null, completed_jobs: int, rating: float}>
     */
    public function topCleaners(int $limit = 5): Collection
    {
        return Cleaner::query()
            ->with('user')
            ->orderByDesc('completed_jobs_count')
            ->orderByDesc('rating_avg')
            ->limit($limit)
            ->get()
            ->map(fn (Cleaner $c) => [
                'id' => $c->id,
                'name' => $c->user?->name,
                'completed_jobs' => $c->completed_jobs_count,
                'rating' => (float) $c->rating_avg,
            ]);
    }

    /**
     * Bookings needing attention: unassigned within 48h, reviews awaiting moderation.
     *
     * @return array<string, int>
     */
    public function alerts(): array
    {
        return [
            'unassigned_soon' => Booking::whereNull('cleaner_id')
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
                ->whereBetween('scheduled_date', [today()->toDateString(), today()->addDays(2)->toDateString()])
                ->count(),
            'reviews_awaiting' => Review::where('is_published', false)->count(),
            'overdue' => Booking::where('status', BookingStatus::Confirmed->value)
                ->whereDate('scheduled_date', '<', today())
                ->count(),
        ];
    }
}
