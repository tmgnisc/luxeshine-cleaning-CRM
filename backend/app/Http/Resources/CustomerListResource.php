<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerListResource extends JsonResource
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
            'is_active' => $this->user?->is_active,
            'customer_since' => $this->customer_since?->toDateString(),
            'addresses_count' => isset($this->addresses_count) ? (int) $this->addresses_count : $this->addresses->count(),
            'bookings_count' => isset($this->bookings_count) ? (int) $this->bookings_count : 0,
            'lifetime_spend' => isset($this->lifetime_spend) ? (float) $this->lifetime_spend : 0.0,
        ];
    }
}
