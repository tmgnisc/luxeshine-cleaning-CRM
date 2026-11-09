<?php

namespace Tests\Feature\Cleaner;

use App\Models\Cleaner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanerSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cleanerUser(): array
    {
        $user = User::factory()->cleaner()->create();
        $cleaner = Cleaner::factory()->create(['user_id' => $user->id, 'is_available' => true]);

        return [$user, $cleaner];
    }

    public function test_a_cleaner_can_toggle_their_availability(): void
    {
        [$user, $cleaner] = $this->cleanerUser();

        $this->actingAs($user)
            ->putJson('/api/v1/cleaner/availability', ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);

        $this->assertFalse($cleaner->fresh()->is_available);
    }

    public function test_a_cleaner_can_set_their_weekly_working_hours(): void
    {
        [$user, $cleaner] = $this->cleanerUser();

        $hours = [];
        for ($day = 0; $day <= 6; $day++) {
            $hours[] = [
                'day_of_week' => $day,
                'is_off' => $day === 5,
                'start_time' => $day === 5 ? null : '09:00',
                'end_time' => $day === 5 ? null : '17:00',
            ];
        }

        $this->actingAs($user)
            ->putJson('/api/v1/cleaner/working-hours', ['hours' => $hours])
            ->assertOk();

        $this->assertDatabaseCount('cleaner_working_hours', 7);
        $this->assertDatabaseHas('cleaner_working_hours', [
            'cleaner_id' => $cleaner->id, 'day_of_week' => 5, 'is_off' => true,
        ]);
    }

    public function test_working_hours_reject_an_end_before_the_start(): void
    {
        [$user] = $this->cleanerUser();

        $hours = [];
        for ($day = 0; $day <= 6; $day++) {
            $hours[] = ['day_of_week' => $day, 'is_off' => false, 'start_time' => '17:00', 'end_time' => '09:00'];
        }

        $this->actingAs($user)
            ->putJson('/api/v1/cleaner/working-hours', ['hours' => $hours])
            ->assertStatus(422);
    }

    public function test_a_cleaner_can_request_and_remove_time_off(): void
    {
        [$user, $cleaner] = $this->cleanerUser();

        $response = $this->actingAs($user)
            ->postJson('/api/v1/cleaner/time-off', [
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addWeek()->addDays(2)->toDateString(),
                'reason' => 'Holiday',
            ])
            ->assertCreated();

        $timeOffId = $response->json('data.id');

        $this->actingAs($user)
            ->deleteJson("/api/v1/cleaner/time-off/{$timeOffId}")
            ->assertOk();

        $this->assertDatabaseMissing('cleaner_time_offs', ['id' => $timeOffId]);
    }

    public function test_a_customer_cannot_access_cleaner_endpoints(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->putJson('/api/v1/cleaner/availability', ['is_available' => true])
            ->assertForbidden();
    }
}
