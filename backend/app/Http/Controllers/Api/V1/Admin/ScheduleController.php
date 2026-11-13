<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Http\Resources\CalendarEventResource;
use App\Models\Booking;
use App\Models\CalendarEvent;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ScheduleController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'cleaner_id' => ['nullable', 'integer', 'exists:cleaners,id'],
        ]);

        $from = Carbon::parse($validated['from'])->startOfDay();
        $to = Carbon::parse($validated['to'])->endOfDay();

        $bookings = Booking::query()
            ->with(['service', 'customer.user', 'cleaner.user'])
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->when($request->filled('cleaner_id'), fn ($q) => $q->where('cleaner_id', $request->integer('cleaner_id')))
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get();

        $bookingsByDate = $bookings
            ->groupBy(fn (Booking $b) => $b->scheduled_date->toDateString())
            ->map(fn ($group) => BookingResource::collection($group)->resolve());

        // External calendar events (Airbnb / third-party iCal feeds) are overlaid as
        // read-only markers. A cleaner filter hides them, since they are not assigned.
        $eventsByDate = $request->filled('cleaner_id')
            ? collect()
            : $this->eventsByDate($from, $to);

        $dates = $bookingsByDate->keys()
            ->merge($eventsByDate->keys())
            ->unique()
            ->sort()
            ->values();

        $payload = $dates->map(fn (string $date) => [
            'date' => $date,
            'bookings' => $bookingsByDate->get($date, []),
            'events' => $eventsByDate->get($date, []),
        ]);

        return $this->ok($payload);
    }

    /**
     * Expand overlapping calendar events across each day they cover within the range.
     *
     * @return Collection<string, array<int, array<string, mixed>>>
     */
    private function eventsByDate(Carbon $from, Carbon $to): Collection
    {
        $events = CalendarEvent::query()
            ->with('feed')
            ->whereHas('feed', fn ($q) => $q->where('is_active', true))
            ->whereDate('starts_on', '<=', $to->toDateString())
            ->where(function ($q) use ($from) {
                $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $from->toDateString());
            })
            ->orderBy('starts_on')
            ->get();

        $byDate = collect();

        foreach ($events as $event) {
            $start = $event->starts_on->copy()->max($from);
            $end = ($event->ends_on ?? $event->starts_on)->copy()->min($to);

            $base = (new CalendarEventResource($event))->resolve();

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $key = $day->toDateString();
                $entry = $base + [
                    'is_checkin' => $day->isSameDay($event->starts_on),
                    'is_checkout' => $event->ends_on !== null && $day->isSameDay($event->ends_on),
                ];

                $byDate->put($key, array_merge($byDate->get($key, []), [$entry]));
            }
        }

        return $byDate;
    }
}
