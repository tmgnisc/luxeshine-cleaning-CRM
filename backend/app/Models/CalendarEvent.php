<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'calendar_feed_id', 'uid', 'summary', 'description', 'location',
    'starts_on', 'ends_on', 'status',
])]
class CalendarEvent extends Model
{
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(CalendarFeed::class, 'calendar_feed_id');
    }
}
