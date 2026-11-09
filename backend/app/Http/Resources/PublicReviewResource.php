<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Review
 */
class PublicReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $cleanerName = $this->cleaner?->user?->name;
        $customerName = $this->customer?->user?->name;

        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'customer_first_name' => $customerName ? Str::of($customerName)->explode(' ')->first() : 'Customer',
            'cleaner_first_name' => $cleanerName ? Str::of($cleanerName)->explode(' ')->first() : null,
            'service' => $this->booking?->service?->name,
            'admin_reply' => $this->admin_reply,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
