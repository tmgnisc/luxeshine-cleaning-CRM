<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all demo / test data (bookings, customers, cleaners, reviews, activity,
 * notifications, imported calendar events and every non–super-admin user) and
 * then reseeds the production essentials via {@see ProductionSeeder}. The
 * service catalogue and settings are preserved.
 */
class PrepareProduction extends Command
{
    protected $signature = 'app:prepare-production {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all test/demo data and seed the production super admin, settings and calendar feed';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This permanently deletes ALL bookings, customers, cleaners, reviews and demo users. Continue?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $keepEmail = env('SUPERADMIN_EMAIL', 'prabin@luxeshineprofessional.co.nz');

        $this->wipe($keepEmail);

        $this->info('Demo data cleared. Seeding production essentials…');
        $this->call('db:seed', ['--class' => ProductionSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Production data is ready.');
        $this->line("Super admin: {$keepEmail}");

        return self::SUCCESS;
    }

    private function wipe(string $keepEmail): void
    {
        $tables = [
            'reviews',
            'booking_status_histories',
            'bookings',
            'addresses',
            'cleaner_skills',
            'cleaner_time_offs',
            'cleaner_working_hours',
            'cleaners',
            'customers',
            'calendar_events',
            'activity_logs',
            'notifications',
        ];

        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        // Remove every user except the production super admin we are about to (re)seed.
        User::query()
            ->where('email', '!=', $keepEmail)
            ->orWhere('role', '!=', UserRole::SuperAdmin->value)
            ->get()
            ->each(function (User $user) use ($keepEmail): void {
                if ($user->email === $keepEmail) {
                    return;
                }
                $user->tokens()->delete();
                $user->forceDelete();
            });

        Schema::enableForeignKeyConstraints();
    }
}
