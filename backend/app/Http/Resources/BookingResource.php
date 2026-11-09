<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'source' => $this->source->value,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'duration_hours' => (float) $this->duration_hours,
            'price_per_hour' => (float) $this->price_per_hour,
            'total_price' => (float) $this->total_price,
            'price_overridden' => $this->price_overridden,
            'is_paid' => $this->is_paid,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'customer_notes' => $this->customer_notes,
            'internal_notes' => $this->when(
                $request->user()?->isAdministrative() ?? false,
                fn () => $this->internal_notes,
            ),
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'address_snapshot' => $this->address_snapshot,

            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'cleaner_id' => $this->cleaner_id,
            'address_id' => $this->address_id,

            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer?->id,
                'name' => $this->customer?->user?->name,
                'phone' => $this->customer?->user?->phone,
                'email' => $this->customer?->user?->email,
            ]),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
                'slug' => $this->service?->slug,
            ]),
            'cleaner' => $this->whenLoaded('cleaner', fn () => $this->cleaner ? [
                'id' => $this->cleaner->id,
                'name' => $this->cleaner->user?->name,
                'phone' => $this->cleaner->user?->phone,
            ] : null),
            'address' => new AddressResource($this->whenLoaded('address')),
            'review' => new ReviewResource($this->whenLoaded('review')),
            'status_history' => BookingStatusHistoryResource::collection($this->whenLoaded('statusHistory')),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
