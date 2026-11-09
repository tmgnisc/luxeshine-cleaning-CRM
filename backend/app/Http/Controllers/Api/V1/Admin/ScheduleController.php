<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $grouped = $bookings
            ->groupBy(fn (Booking $b) => $b->scheduled_date->toDateString())
            ->map(fn ($group, $date) => [
                'date' => $date,
                'bookings' => BookingResource::collection($group)->resolve(),
            ])
            ->values();

        return $this->ok($grouped);
    }
}
