<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Service
 */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => new ServiceCategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path ? Storage::url($this->image_path) : null,
            'price_per_hour' => (float) $this->price_per_hour,
            'default_duration_hours' => (float) $this->default_duration_hours,
            'min_duration_hours' => (float) $this->min_duration_hours,
            'max_duration_hours' => (float) $this->max_duration_hours,
            'is_live' => $this->is_live,
            'sort_order' => $this->sort_order,
            'bookings_count' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
