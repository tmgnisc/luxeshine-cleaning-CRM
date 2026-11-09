<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceCategoryRequest;
use App\Http\Requests\Admin\UpdateServiceCategoryRequest;
use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ServiceCategoryController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function index(): JsonResponse
    {
        $categories = ServiceCategory::query()
            ->withCount('services')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->ok(ServiceCategoryResource::collection($categories));
    }

    public function store(StoreServiceCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? $data['name']);

        $category = ServiceCategory::create($data);
        $this->activity->log('service_category.created', $category);

        return $this->created(new ServiceCategoryResource($category), 'Category created.');
    }

    public function show(ServiceCategory $category): JsonResponse
    {
        return $this->ok(new ServiceCategoryResource($category->loadCount('services')));
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $category): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug($data['slug'] ?: $category->name, $category->id);
        }

        $category->update($data);
        $this->activity->log('service_category.updated', $category, $category->getChanges());

        return $this->ok(new ServiceCategoryResource($category), 'Category updated.');
    }

    public function destroy(ServiceCategory $category): JsonResponse
    {
        if ($category->services()->exists()) {
            return $this->message('This category still has services and cannot be deleted.', 409);
        }

        $category->delete();
        $this->activity->log('service_category.deleted', $category);

        return $this->message('Category deleted.');
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value);
        $slug = $base;
        $i = 1;

        while (ServiceCategory::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
