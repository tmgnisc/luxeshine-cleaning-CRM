<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CancelBookingRequest;
use App\Http\Requests\Customer\RescheduleBookingRequest;
use App\Http\Requests\Customer\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Customer;
use App\Services\BookingService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    use ApiResponses, HandlesResourceQueries;

    public function __construct(
        private readonly BookingService $bookings,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $filter = $request->string('filter')->toString();

        $query = $customer->bookings()
            ->with(['service', 'cleaner.user'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($filter === 'upcoming', fn ($q) => $q->whereDate('scheduled_date', '>=', today())
                ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::InProgress->value]))
            ->when($filter === 'past', fn ($q) => $q->where(fn ($w) => $w->where('status', BookingStatus::Completed->value)
                ->orWhereDate('scheduled_date', '<', today())))
            ->when($filter === 'cancelled', fn ($q) => $q->where('status', BookingStatus::Cancelled->value));

        $this->applySort($query, $request, ['scheduled_date', 'created_at'], '-scheduled_date');

        return $this->paginated($query->paginate($this->perPage($request)), BookingResource::class);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        if (! $this->settings->bool('accepting_bookings', true)) {
            return $this->message('We are not accepting new bookings at the moment. Please try again later.', 503);
        }

        $customer = $this->customer($request);
        $data = $request->validated();
        $data['customer_id'] = $customer->id;

        $booking = $this->bookings->create($data, $request->user(), BookingSource::Website, enforceCustomerRules: true);

        return $this->created(new BookingResource($booking), 'Booking placed successfully.');
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        $this->ensureOwns($request, $booking);

        $booking->load(['service', 'cleaner.user', 'address', 'review', 'statusHistory.changedBy']);

        return $this->ok(new BookingResource($booking));
    }

    public function reschedule(RescheduleBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->ensureOwns($request, $booking);

        $booking = $this->bookings->reschedule(
            $booking,
            $request->string('scheduled_date'),
            $request->string('start_time'),
            $request->filled('duration_hours') ? (float) $request->input('duration_hours') : null,
            $request->user(),
            enforceCustomerRules: true,
        );

        return $this->ok(new BookingResource($booking), 'Booking rescheduled.');
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->ensureOwns($request, $booking);

        $booking = $this->bookings->cancel($booking, $request->input('reason'), $request->user(), enforceCustomerRules: true);

        return $this->ok(new BookingResource($booking->load(['service', 'cleaner.user'])), 'Booking cancelled.');
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()->customer;
        abort_if($customer === null, 403, 'No customer profile found for this account.');

        return $customer;
    }

    private function ensureOwns(Request $request, Booking $booking): void
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $booking->customer_id === $customer->id, 404, 'Resource not found.');
    }
}
