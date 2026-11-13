<?php

namespace App\Http\Resources;

use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarEvent
 */
class CalendarEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'feed_id' => $this->calendar_feed_id,
            'feed_name' => $this->whenLoaded('feed', fn () => $this->feed->name),
            'source' => $this->whenLoaded('feed', fn () => $this->feed->source),
            'uid' => $this->uid,
            'summary' => $this->summary,
            'location' => $this->location,
            'status' => $this->status,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
        ];
    }
}
