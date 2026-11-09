<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatar_path ? Storage::url($this->avatar_path) : null,
            'initials' => $this->initials(),
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified_at !== null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'cleaner_id' => $this->whenLoaded('cleaner', fn () => $this->cleaner?->id),
            'customer_id' => $this->whenLoaded('customer', fn () => $this->customer?->id),
        ];
    }
}
