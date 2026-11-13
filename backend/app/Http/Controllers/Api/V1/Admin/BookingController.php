<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesBulkDeletion;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignCleanerRequest;
use App\Http\Requests\Admin\ChangeStatusRequest;
use App\Http\Requests\Admin\StoreBookingRequest;
use App\Http\Requests\Admin\UpdateBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Service;
use App\Services\ActivityLogger;
use App\Services\BookingService;
use App\Services\CleanerAvailabilityService;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    use ApiResponses, HandlesBulkDeletion, HandlesResourceQueries;

    public function __construct(
        private readonly BookingService $bookings,
        private readonly CleanerAvailabilityService $availability,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->filtered($request)->with(['customer.user', 'service', 'cleaner.user']);
        $this->applySort($query, $request, ['scheduled_date', 'created_at', 'total_price', 'status'], '-scheduled_date');

        return $this->paginated($query->paginate($this->perPage($request)), BookingResource::class);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = $this->bookings->create($request->validated(), $request->user(), BookingSource::Admin, enforceCustomerRules: false);
        $this->activity->log('booking.created', $booking, ['reference' => $booking->reference]);

        return $this->created(new BookingResource($booking), 'Booking created.');
    }

    public function show(Booking $booking): JsonResponse
    {
        $booking->load(['customer.user', 'service', 'cleaner.user', 'address', 'review', 'statusHistory.changedBy']);

        return $this->ok(new BookingResource($booking));
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['start_time'])) {
            $data['start_time'] = $this->normalizeTime($data['start_time']);
        }

        $duration = isset($data['duration_hours']) ? (float) $data['duration_hours'] : (float) $booking->duration_hours;
        $start = $data['start_time'] ?? (string) $booking->start_time;

        if (isset($data['start_time']) || isset($data['duration_hours'])) {
            $data['end_time'] = $this->endTime($start, $duration);
        }

        if (array_key_exists('total_price', $data) && $data['total_price'] !== null) {
            $data['price_overridden'] = true;
        } elseif (isset($data['duration_hours']) && ! $booking->price_overridden) {
            $data['total_price'] = round((float) $booking->price_per_hour * $duration, 2);
        }

        $booking->update($data);
        $this->activity->log('booking.updated', $booking, $booking->getChanges());

        return $this->ok(new BookingResource($booking->load(['customer.user', 'service', 'cleaner.user'])), 'Booking updated.');
    }

    public function changeStatus(ChangeStatusRequest $request, Booking $booking): JsonResponse
    {
        $booking = $this->bookings->changeStatus(
            $booking,
            BookingStatus::from($request->string('status')->toString()),
            $request->user(),
            $request->input('note'),
            $request->input('cancellation_reason'),
        );

        $this->activity->log('booking.status_changed', $booking, ['status' => $booking->status->value]);

        return $this->ok(new BookingResource($booking), 'Booking status updated.');
    }

    public function assign(AssignCleanerRequest $request, Booking $booking): JsonResponse
    {
        $cleanerId = $request->input('cleaner_id') !== null ? (int) $request->input('cleaner_id') : null;
        $booking = $this->bookings->assignCleaner($booking, $cleanerId, $request->user());
        $this->activity->log('booking.assigned', $booking, ['cleaner_id' => $cleanerId]);

        return $this->ok(new BookingResource($booking), $cleanerId ? 'Cleaner assigned.' : 'Cleaner unassigned.');
    }

    public function paid(Request $request, Booking $booking): JsonResponse
    {
        $paid = $request->has('is_paid') ? $request->boolean('is_paid') : ! $booking->is_paid;
        $booking = $this->bookings->markPaid($booking, $paid);
        $this->activity->log('booking.paid', $booking, ['is_paid' => $paid]);

        return $this->ok(new BookingResource($booking->load(['customer.user', 'service', 'cleaner.user'])), $paid ? 'Marked as paid.' : 'Marked as unpaid.');
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();
        $this->activity->log('booking.deleted', $booking);

        return $this->message('Booking deleted.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $this->validateBulkIds($request, 'bookings');

        $result = $this->runBulkDelete(
            Booking::whereIn('id', $ids)->get(),
            function (Booking $booking): ?string {
                $booking->delete();

                return null;
            },
        );

        $this->activity->log('booking.bulk_deleted', null, $result);

        return $this->ok($result, "Deleted {$result['deleted']} booking(s).");
    }

    public function statusCounts(Request $request): JsonResponse
    {
        $counts = (clone $this->filtered($request))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->ok([
            'pending' => (int) ($counts[BookingStatus::Pending->value] ?? 0),
            'confirmed' => (int) ($counts[BookingStatus::Confirmed->value] ?? 0),
            'in_progress' => (int) ($counts[BookingStatus::InProgress->value] ?? 0),
            'completed' => (int) ($counts[BookingStatus::Completed->value] ?? 0),
            'cancelled' => (int) ($counts[BookingStatus::Cancelled->value] ?? 0),
            'total' => (int) $counts->sum(),
        ]);
    }

    public function availableCleaners(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'exclude_booking_id' => ['nullable', 'integer'],
            'show_all' => ['nullable', 'boolean'],
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $date = Carbon::parse($validated['date']);
        $showAll = (bool) ($validated['show_all'] ?? false);
        $excludeId = $validated['exclude_booking_id'] ?? null;

        $pool = $showAll
            ? Cleaner::with(['user', 'services', 'workingHours', 'timeOff'])->get()
            : $this->availability->candidatePool($service);

        $result = $pool->map(function ($cleaner) use ($service, $date, $validated, $excludeId) {
            $verdict = $this->availability->evaluate($cleaner, $service, $date, $validated['start_time'], (float) $validated['duration'], $excludeId);

            return [
                'id' => $cleaner->id,
                'name' => $cleaner->user?->name,
                'rating_avg' => (float) $cleaner->rating_avg,
                'eligible' => $verdict['eligible'],
                'reasons' => $verdict['reasons'],
            ];
        });

        if (! $showAll) {
            $result = $result->where('eligible', true)->values();
        }

        return $this->ok($result);
    }

    public function export(Request $request): StreamedResponse
    {
        $bookings = $this->filtered($request)->with(['customer.user', 'service', 'cleaner.user'])->orderByDesc('scheduled_date')->get();

        $filename = 'bookings-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($bookings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Reference', 'Date', 'Start', 'Duration (h)', 'Service', 'Customer', 'Cleaner', 'Status', 'Total', 'Paid']);

            foreach ($bookings as $b) {
                fputcsv($handle, [
                    $b->reference,
                    $b->scheduled_date->toDateString(),
                    substr((string) $b->start_time, 0, 5),
                    (float) $b->duration_hours,
                    $b->service?->name,
                    $b->customer?->user?->name,
                    $b->cleaner?->user?->name ?? 'Unassigned',
                    $b->status->value,
                    (float) $b->total_price,
                    $b->is_paid ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): Builder
    {
        return Booking::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('service_id'), fn ($q) => $q->where('service_id', $request->integer('service_id')))
            ->when($request->filled('cleaner_id'), fn ($q) => $q->where('cleaner_id', $request->integer('cleaner_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('scheduled_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('scheduled_date', '<=', $request->string('date_to')))
            ->when($request->boolean('unassigned'), fn ($q) => $q->whereNull('cleaner_id'))
            ->when($request->filled('is_paid'), fn ($q) => $q->where('is_paid', $request->boolean('is_paid')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $term)
                    ->orWhereHas('customer.user', fn ($u) => $u->where('name', 'like', $term)));
            });
    }

    private function normalizeTime(string $time): string
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return sprintf('%02d:%02d', (int) $h, (int) $m);
    }

    private function endTime(string $startTime, float $duration): string
    {
        [$h, $m] = array_pad(explode(':', $startTime), 2, '0');
        $end = ((int) $h * 60) + (int) $m + (int) round($duration * 60);

        return sprintf('%02d:%02d', intdiv($end, 60), $end % 60);
    }
}
