<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Cleaner;
use App\Models\User;
use App\Notifications\AccountInviteNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CleanerService
{
    /**
     * Create a cleaner user + profile, attach skills and default working hours.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $avatarPath = null): Cleaner
    {
        return DB::transaction(function () use ($data, $avatarPath): Cleaner {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Str::password(16),
                'role' => UserRole::Cleaner,
                'is_active' => true,
                'avatar_path' => $avatarPath,
            ]);

            $cleaner = Cleaner::create([
                'user_id' => $user->id,
                'verification_status' => $data['verification_status'] ?? VerificationStatus::Pending->value,
                'verification_notes' => $data['verification_notes'] ?? null,
                'is_available' => $data['is_available'] ?? true,
                'joined_at' => $data['joined_at'] ?? now()->toDateString(),
                'bio' => $data['bio'] ?? null,
            ]);

            if (! empty($data['skills'])) {
                $cleaner->services()->sync($data['skills']);
            }

            $this->seedDefaultWorkingHours($cleaner);

            return $cleaner;
        });
    }

    public function sendInvite(User $user, string $roleLabel = 'cleaner'): void
    {
        $token = Password::broker()->createToken($user);
        $user->notify(new AccountInviteNotification($token, $roleLabel));
    }

    private function seedDefaultWorkingHours(Cleaner $cleaner): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $isOff = $day === 6;
            $cleaner->workingHours()->create([
                'day_of_week' => $day,
                'start_time' => $isOff ? null : '08:00',
                'end_time' => $isOff ? null : '18:00',
                'is_off' => $isOff,
            ]);
        }
    }
}
