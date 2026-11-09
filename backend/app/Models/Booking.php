<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'reference', 'customer_id', 'service_id', 'cleaner_id', 'address_id', 'address_snapshot',
    'scheduled_date', 'start_time', 'duration_hours', 'end_time', 'price_per_hour', 'total_price',
    'price_overridden', 'status', 'is_paid', 'paid_at', 'customer_notes', 'internal_notes',
    'cancellation_reason', 'cancelled_by', 'cancelled_at', 'started_at', 'completed_at',
    'reminder_sent_at', 'source', 'created_by',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'address_snapshot' => 'array',
            'scheduled_date' => 'date',
            'duration_hours' => 'decimal:1',
            'price_per_hour' => 'decimal:2',
            'total_price' => 'decimal:2',
            'price_overridden' => 'boolean',
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'is_paid' => 'boolean',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function cleaner(): BelongsTo
    {
        return $this->belongsTo(Cleaner::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->orderByDesc('created_at');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Normalise the "H:i" start time for comparisons.
     */
    public function startMinutes(): int
    {
        return $this->timeToMinutes((string) $this->start_time);
    }

    public function endMinutes(): int
    {
        return $this->timeToMinutes((string) $this->end_time);
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return ((int) $h * 60) + (int) $m;
    }
}
