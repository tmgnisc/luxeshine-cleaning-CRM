<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'source', 'url', 'is_active',
    'last_synced_at', 'last_sync_status', 'last_sync_message', 'events_count',
])]
class CalendarFeed extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
            'events_count' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
