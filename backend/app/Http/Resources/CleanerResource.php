<?php

namespace App\Http\Resources;

use App\Models\Cleaner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Cleaner
 */
class CleanerResource extends JsonResource
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
            'verification_status' => $this->verification_status->value,
            'verification_notes' => $this->verification_notes,
            'is_available' => $this->is_available,
            'joined_at' => $this->joined_at?->toDateString(),
            'bio' => $this->bio,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'completed_jobs_count' => $this->completed_jobs_count,
            'upcoming_jobs_count' => $this->when(isset($this->upcoming_jobs_count), fn () => (int) $this->upcoming_jobs_count),
            'skills' => ServiceResource::collection($this->whenLoaded('services')),
            'working_hours' => CleanerWorkingHourResource::collection($this->whenLoaded('workingHours')),
            'time_off' => CleanerTimeOffResource::collection($this->whenLoaded('timeOff')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
