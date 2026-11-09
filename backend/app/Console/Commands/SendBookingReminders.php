<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders';

    protected $description = 'Send 24-hour reminders to customers and cleaners for upcoming confirmed bookings.';

    public function handle(BookingNotifier $notifier): int
    {
        $windowEnd = now()->addHours(24);

        $bookings = Booking::query()
            ->with(['customer.user', 'service', 'cleaner.user'])
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->whereNull('reminder_sent_at')
            ->whereIn('scheduled_date', [today()->toDateString(), today()->addDay()->toDateString()])
            ->get()
            ->filter(function (Booking $booking) use ($windowEnd) {
                $start = Carbon::parse($booking->scheduled_date->toDateString().' '.$booking->start_time);

                return $start->isFuture() && $start->lte($windowEnd);
            });

        foreach ($bookings as $booking) {
            $notifier->customer($booking, 'booking_reminder');

            if ($booking->cleaner_id !== null) {
                $notifier->cleaner($booking, 'job_reminder');
            }

            $booking->forceFill(['reminder_sent_at' => now()])->save();
        }

        $this->info("Sent reminders for {$bookings->count()} booking(s).");

        return self::SUCCESS;
    }
}
