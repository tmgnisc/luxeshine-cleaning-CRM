<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\VerificationStatus;
use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCleanerRequest;
use App\Http\Requests\Admin\UpdateCleanerRequest;
use App\Http\Requests\Admin\UpdateVerificationRequest;
use App\Http\Resources\CleanerListResource;
use App\Http\Resources\CleanerResource;
use App\Models\Cleaner;
use App\Services\ActivityLogger;
use App\Services\CleanerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CleanerController extends Controller
{
    use ApiResponses, HandlesResourceQueries;

    public function __construct(
        private readonly CleanerService $cleaners,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Cleaner::query()
            ->with(['user', 'services'])
            ->withCount(['bookings as upcoming_jobs_count' => function ($q) {
                $q->whereDate('scheduled_date', '>=', today())
                    ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::InProgress->value]);
            }])
            ->when($request->filled('verification_status'), fn ($q) => $q->where('verification_status', $request->string('verification_status')))
            ->when($request->filled('is_available'), fn ($q) => $q->where('is_available', $request->boolean('is_available')))
            ->when($request->filled('service_id'), fn ($q) => $q->whereHas('services', fn ($s) => $s->where('services.id', $request->integer('service_id'))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
            });

        $this->applySort($query, $request, ['joined_at', 'rating_avg', 'completed_jobs_count', 'created_at'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), CleanerListResource::class);
    }

    public function store(StoreCleanerRequest $request): JsonResponse
    {
        $data = $request->validated();
        $avatarPath = $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null;

        $cleaner = $this->cleaners->create($data, $avatarPath);

        if ($request->boolean('send_invite', true)) {
            $this->cleaners->sendInvite($cleaner->user, 'cleaner');
        }

        $this->activity->log('cleaner.created', $cleaner, ['name' => $cleaner->user->name]);

        return $this->created(
            new CleanerResource($cleaner->load(['user', 'services', 'workingHours'])),
            'Cleaner created'.($request->boolean('send_invite', true) ? ' and invitation sent.' : '.'),
        );
    }

    public function show(Cleaner $cleaner): JsonResponse
    {
        $cleaner->load(['user', 'services', 'workingHours', 'timeOff']);

        $upcomingJobs = $cleaner->bookings()
            ->with('service', 'customer.user')
            ->whereDate('scheduled_date', '>=', today())
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->orderBy('scheduled_date')
            ->limit(10)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'reference' => $b->reference,
                'scheduled_date' => $b->scheduled_date->toDateString(),
                'start_time' => substr((string) $b->start_time, 0, 5),
                'service' => $b->service?->name,
                'customer' => $b->customer?->user?->name,
                'status' => $b->status->value,
            ]);

        $recentReviews = $cleaner->reviews()
            ->where('is_published', true)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'rating' => $r->rating,
                'comment' => $r->comment,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return $this->ok([
            'cleaner' => new CleanerResource($cleaner),
            'upcoming_jobs' => $upcomingJobs,
            'recent_reviews' => $recentReviews,
        ]);
    }

    public function update(UpdateCleanerRequest $request, Cleaner $cleaner): JsonResponse
    {
        $data = $request->validated();
        $user = $cleaner->user;

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($v) => $v !== null));

        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        }
        $user->save();

        $cleaner->fill(array_filter([
            'joined_at' => $data['joined_at'] ?? null,
            'bio' => $data['bio'] ?? null,
        ], fn ($v) => $v !== null));
        if (array_key_exists('is_available', $data)) {
            $cleaner->is_available = $data['is_available'];
        }
        $cleaner->save();

        if (array_key_exists('skills', $data)) {
            $cleaner->services()->sync($data['skills']);
        }

        $this->activity->log('cleaner.updated', $cleaner, $cleaner->getChanges());

        return $this->ok(new CleanerResource($cleaner->load(['user', 'services', 'workingHours', 'timeOff'])), 'Cleaner updated.');
    }

    public function availability(Request $request, Cleaner $cleaner): JsonResponse
    {
        $validated = $request->validate(['is_available' => ['required', 'boolean']]);
        $cleaner->update($validated);
        $this->activity->log('cleaner.availability', $cleaner, $validated);

        return $this->ok(new CleanerResource($cleaner->load('user')), 'Availability updated.');
    }

    public function verification(UpdateVerificationRequest $request, Cleaner $cleaner): JsonResponse
    {
        $cleaner->update($request->validated());
        $this->activity->log('cleaner.verification', $cleaner, $request->validated());

        return $this->ok(new CleanerResource($cleaner->load('user')), 'Verification status updated.');
    }

    public function destroy(Cleaner $cleaner): JsonResponse
    {
        $upcoming = $cleaner->bookings()
            ->whereDate('scheduled_date', '>=', today())
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->get(['id', 'reference', 'scheduled_date']);

        if ($upcoming->isNotEmpty()) {
            return response()->json([
                'message' => 'This cleaner has upcoming bookings that must be reassigned before deletion.',
                'bookings' => $upcoming->map(fn ($b) => [
                    'id' => $b->id,
                    'reference' => $b->reference,
                    'scheduled_date' => $b->scheduled_date->toDateString(),
                ]),
            ], 409);
        }

        $cleaner->user?->update(['is_active' => false]);
        $cleaner->delete();
        $this->activity->log('cleaner.deleted', $cleaner);

        return $this->message('Cleaner removed.');
    }

    public function verificationCounts(): JsonResponse
    {
        return $this->ok([
            'verified' => Cleaner::where('verification_status', VerificationStatus::Verified->value)->count(),
            'pending' => Cleaner::where('verification_status', VerificationStatus::Pending->value)->count(),
            'rejected' => Cleaner::where('verification_status', VerificationStatus::Rejected->value)->count(),
        ]);
    }
}
