<?php

namespace App\Http\Resources;

use App\Models\CalendarFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarFeed
 */
class CalendarFeedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source' => $this->source,
            'url' => $this->url,
            'is_active' => $this->is_active,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_sync_status' => $this->last_sync_status,
            'last_sync_message' => $this->last_sync_message,
            'events_count' => $this->events_count,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
