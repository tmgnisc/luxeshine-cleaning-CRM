<?php

namespace App\Http\Controllers\Api\V1\Cleaner;

use App\Enums\BookingStatus;
use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    use ApiResponses, HandlesResourceQueries;

    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): JsonResponse
    {
        $cleaner = $this->cleaner($request);

        $query = $cleaner->bookings()
            ->with(['service', 'customer.user', 'address'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('scheduled_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('scheduled_date', '<=', $request->string('date_to')));

        $this->applySort($query, $request, ['scheduled_date', 'start_time'], 'scheduled_date');

        return $this->paginated($query->paginate($this->perPage($request)), BookingResource::class);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureOwns($request, $booking);
        $booking->load(['service', 'customer.user', 'address']);

        return $this->ok(new BookingResource($booking));
    }

    public function start(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureOwns($request, $booking);
        $booking = $this->bookings->changeStatus($booking, BookingStatus::InProgress, $request->user(), 'Job started by cleaner.');

        return $this->ok(new BookingResource($booking->load(['service', 'customer.user'])), 'Job started.');
    }

    public function complete(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureOwns($request, $booking);
        $booking = $this->bookings->changeStatus($booking, BookingStatus::Completed, $request->user(), 'Job completed by cleaner.');

        return $this->ok(new BookingResource($booking->load(['service', 'customer.user'])), 'Job completed.');
    }

    private function cleaner(Request $request): Cleaner
    {
        $cleaner = $request->user()->cleaner;
        abort_if($cleaner === null, 403, 'No cleaner profile found for this account.');

        return $cleaner;
    }

    private function ensureOwns(Request $request, Booking $booking): void
    {
        $cleaner = $request->user()->cleaner;
        abort_unless($cleaner !== null && $booking->cleaner_id === $cleaner->id, 404, 'Resource not found.');
    }
}
