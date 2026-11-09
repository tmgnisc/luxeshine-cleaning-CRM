<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyUnassignedBookings extends Command
{
    protected $signature = 'bookings:notify-unassigned';

    protected $description = "Notify admins of tomorrow's unassigned bookings.";

    public function handle(): int
    {
        $bookings = Booking::query()
            ->with(['customer.user', 'service'])
            ->whereNull('cleaner_id')
            ->where('status', BookingStatus::Pending->value)
            ->whereDate('scheduled_date', today()->addDay()->toDateString())
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No unassigned bookings for tomorrow.');

            return self::SUCCESS;
        }

        $admins = User::whereIn('role', [UserRole::Admin->value, UserRole::SuperAdmin->value])
            ->where('is_active', true)
            ->get();

        foreach ($bookings as $booking) {
            Notification::send($admins, new BookingNotification($booking, 'admin_new_booking', [
                'note' => 'This booking is unassigned and scheduled for tomorrow.',
            ]));
        }

        $this->info("Flagged {$bookings->count()} unassigned booking(s) to ".$admins->count().' admin(s).');

        return self::SUCCESS;
    }
}
