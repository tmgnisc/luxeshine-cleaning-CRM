<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactRequest;
use App\Http\Resources\PublicReviewResource;
use App\Http\Resources\ServiceCategoryResource;
use App\Http\Resources\ServiceResource;
use App\Mail\ContactFormMail;
use App\Models\Review;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\CleanerAvailabilityService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PublicController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly CleanerAvailabilityService $availability,
    ) {}

    public function settings(): JsonResponse
    {
        return $this->ok($this->settings->publicPayload());
    }

    public function serviceCategories(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->withCount(['services' => fn ($q) => $q->where('is_live', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->ok(ServiceCategoryResource::collection($categories));
    }

    public function services(Request $request): JsonResponse
    {
        $services = Service::query()
            ->with('category')
            ->where('is_live', true)
            ->when($request->filled('category'), function ($q) use ($request) {
                $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('category')));
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->ok(ServiceResource::collection($services));
    }

    public function service(string $slug): JsonResponse
    {
        $service = Service::with('category')
            ->where('slug', $slug)
            ->where('is_live', true)
            ->firstOrFail();

        return $this->ok(new ServiceResource($service));
    }

    public function reviews(Request $request): JsonResponse
    {
        $reviews = Review::query()
            ->with(['cleaner.user', 'customer.user', 'booking.service'])
            ->where('is_published', true)
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 12), 50));

        return $this->paginated($reviews, PublicReviewResource::class);
    }

    public function availability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'duration' => ['nullable', 'numeric', 'min:0.5', 'max:24'],
        ]);

        $service = Service::where('id', $validated['service_id'])->where('is_live', true)->firstOrFail();
        $date = Carbon::parse($validated['date']);
        $duration = (float) ($validated['duration'] ?? $service->default_duration_hours);

        $openMin = $this->toMinutes($this->settings->string('business_open_time', '08:00'));
        $closeMin = $this->toMinutes($this->settings->string('business_close_time', '20:00'));

        $slots = $this->availability->daySlots($service, $date, $duration, $openMin, $closeMin);

        return $this->ok([
            'date' => $date->toDateString(),
            'duration' => $duration,
            'slots' => $slots,
        ]);
    }

    public function contact(ContactRequest $request): JsonResponse
    {
        $supportEmail = $this->settings->string('support_email', (string) config('mail.from.address'));

        Mail::to($supportEmail)->queue(new ContactFormMail(
            name: $request->string('name'),
            email: $request->string('email'),
            phone: $request->input('phone'),
            enquirySubject: $request->input('subject'),
            messageBody: $request->string('message'),
        ));

        return $this->message('Thanks for reaching out — we will get back to you shortly.', 202);
    }

    private function toMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }
}
