<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'booking_reference' => $this->whenLoaded('booking', fn () => $this->booking?->reference),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->whenLoaded('customer', fn () => $this->customer?->user?->name),
            'cleaner_id' => $this->cleaner_id,
            'cleaner_name' => $this->whenLoaded('cleaner', fn () => $this->cleaner?->user?->name),
            'service_name' => $this->whenLoaded('booking', fn () => $this->booking?->service?->name),
            'rating' => $this->rating,
            'comment' => $this->comment,
            'is_published' => $this->is_published,
            'admin_reply' => $this->admin_reply,
            'moderated_at' => $this->moderated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
