<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly: send 24-hour reminders to customers and cleaners.
Schedule::command('bookings:send-reminders')->hourly();

// Daily: notify admins of tomorrow's unassigned bookings.
Schedule::command('bookings:notify-unassigned')->dailyAt('18:00');

// Every 30 minutes: pull reservations from active Airbnb / iCal calendar feeds.
Schedule::command('calendar:sync')->everyThirtyMinutes()->withoutOverlapping();

// Process queued notifications/emails continuously in environments without a worker.
// (In production, run `php artisan queue:work` as a daemon instead.)
