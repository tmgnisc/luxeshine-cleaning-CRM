<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->user?->phone,
            'avatar_url' => $this->user?->avatar_path ? Storage::url($this->user->avatar_path) : null,
            'initials' => $this->user?->initials(),
            'is_active' => $this->user?->is_active,
            'customer_since' => $this->customer_since?->toDateString(),
            'notes' => $this->notes,
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'bookings_count' => $this->when(isset($this->bookings_count), fn () => (int) $this->bookings_count),
            'lifetime_spend' => isset($this->lifetime_spend) ? (float) $this->lifetime_spend : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
