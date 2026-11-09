<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cleaner_id', 'day_of_week', 'start_time', 'end_time', 'is_off'])]
class CleanerWorkingHour extends Model
{
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_off' => 'boolean',
        ];
    }

    public function cleaner(): BelongsTo
    {
        return $this->belongsTo(Cleaner::class);
    }
}
