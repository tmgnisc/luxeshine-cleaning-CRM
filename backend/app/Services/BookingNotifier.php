<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingNotification;
use Illuminate\Support\Facades\Notification;

class BookingNotifier
{
    public function customer(Booking $booking, string $event, array $context = []): void
    {
        $user = $booking->customer?->user;
        $user?->notify(new BookingNotification($booking, $event, $context));
    }

    public function cleaner(Booking $booking, string $event, array $context = []): void
    {
        $user = $booking->cleaner?->user;
        $user?->notify(new BookingNotification($booking, $event, $context));
    }

    public function admins(Booking $booking, string $event, array $context = []): void
    {
        $admins = User::whereIn('role', [UserRole::Admin->value, UserRole::SuperAdmin->value])
            ->where('is_active', true)
            ->get();

        Notification::send($admins, new BookingNotification($booking, $event, $context));
    }
}
