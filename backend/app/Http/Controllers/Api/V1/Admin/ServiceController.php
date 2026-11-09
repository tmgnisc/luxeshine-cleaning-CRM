<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    use ApiResponses, HandlesResourceQueries;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(Request $request): JsonResponse
    {
        $query = Service::query()
            ->with('category')
            ->withCount('bookings')
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('is_live'), fn ($q) => $q->where('is_live', $request->boolean('is_live')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('short_description', 'like', $term));
            });

        $this->applySort($query, $request, ['name', 'price_per_hour', 'sort_order', 'created_at', 'bookings_count'], 'sort_order');

        return $this->paginated($query->paginate($this->perPage($request)), ServiceResource::class);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);

        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('services', 'public')
            : null;

        $service = Service::create([
            ...collect($data)->except('image')->all(),
            'image_path' => $imagePath,
        ]);

        $this->activity->log('service.created', $service, $service->only(['name', 'price_per_hour', 'is_live']));

        return $this->created(new ServiceResource($service->load('category')), 'Service created.');
    }

    public function show(Service $service): JsonResponse
    {
        return $this->ok(new ServiceResource($service->load('category')->loadCount('bookings')));
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?: $service->name, $service->id);
        }

        if ($request->hasFile('image')) {
            if ($service->image_path) {
                Storage::disk('public')->delete($service->image_path);
            }
            $data['image_path'] = $request->file('image')->store('services', 'public');
        }

        unset($data['image']);
        $service->update($data);

        $this->activity->log('service.updated', $service, $service->getChanges());

        return $this->ok(new ServiceResource($service->load('category')), 'Service updated.');
    }

    public function destroy(Service $service): JsonResponse
    {
        if ($service->bookings()->exists()) {
            return $this->message('This service has bookings and cannot be deleted. Set it to not live instead.', 409);
        }

        $service->delete();
        $this->activity->log('service.deleted', $service);

        return $this->message('Service deleted.');
    }

    public function toggleLive(Service $service): JsonResponse
    {
        $service->update(['is_live' => ! $service->is_live]);
        $this->activity->log('service.toggled_live', $service, ['is_live' => $service->is_live]);

        return $this->ok(new ServiceResource($service->load('category')), 'Service visibility updated.');
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:services,id'],
        ]);

        foreach ($validated['ids'] as $position => $id) {
            Service::where('id', $id)->update(['sort_order' => $position + 1]);
        }

        return $this->message('Services reordered.');
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $i = 1;

        while (Service::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
