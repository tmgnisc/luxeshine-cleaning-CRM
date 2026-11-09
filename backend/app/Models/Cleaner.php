<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\CleanerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'verification_status', 'verification_notes', 'is_available', 'joined_at', 'bio',
    'rating_avg', 'rating_count', 'completed_jobs_count',
])]
class Cleaner extends Model
{
    /** @use HasFactory<CleanerFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'verification_status' => VerificationStatus::class,
            'is_available' => 'boolean',
            'joined_at' => 'date',
            'rating_avg' => 'decimal:2',
            'rating_count' => 'integer',
            'completed_jobs_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'cleaner_skills');
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(CleanerWorkingHour::class);
    }

    public function timeOff(): HasMany
    {
        return $this->hasMany(CleanerTimeOff::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isEligibleBaseline(): bool
    {
        return $this->verification_status === VerificationStatus::Verified
            && $this->is_available
            && $this->user?->is_active === true;
    }
}
