<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'name', 'slug', 'short_description', 'description', 'image_path',
    'price_per_hour', 'default_duration_hours', 'min_duration_hours', 'max_duration_hours',
    'is_live', 'sort_order',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
            'default_duration_hours' => 'decimal:1',
            'min_duration_hours' => 'decimal:1',
            'max_duration_hours' => 'decimal:1',
            'is_live' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function cleaners(): BelongsToMany
    {
        return $this->belongsToMany(Cleaner::class, 'cleaner_skills');
    }
}
