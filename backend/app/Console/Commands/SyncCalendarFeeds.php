<?php

namespace App\Console\Commands;

use App\Services\IcalSyncService;
use Illuminate\Console\Command;

class SyncCalendarFeeds extends Command
{
    protected $signature = 'calendar:sync';

    protected $description = 'Fetch and import reservations from all active Airbnb / iCal calendar feeds';

    public function handle(IcalSyncService $sync): int
    {
        $result = $sync->syncAll();

        $this->info("Synced {$result['feeds']} active feed(s); imported {$result['imported']} reservation(s).");

        return self::SUCCESS;
    }
}
