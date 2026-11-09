<?php

namespace Tests\Feature\Staff;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Service;
use App\Models\User;
use App\Notifications\AccountInviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_cleaner_and_an_invite_is_sent(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/cleaners', [
                'name' => 'New Cleaner',
                'email' => 'newcleaner@example.com',
                'phone' => '+971500000009',
                'skills' => [$service->id],
                'send_invite' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Cleaner');

        $user = User::where('email', 'newcleaner@example.com')->firstOrFail();
        $this->assertSame(UserRole::Cleaner, $user->role);
        $this->assertDatabaseHas('cleaners', ['user_id' => $user->id]);
        $this->assertDatabaseCount('cleaner_working_hours', 7);
        Notification::assertSentTo($user, AccountInviteNotification::class);
    }

    public function test_an_admin_can_update_a_cleaners_verification_status(): void
    {
        $admin = User::factory()->admin()->create();
        $cleaner = Cleaner::factory()->verification(VerificationStatus::Pending)->create();

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/cleaners/{$cleaner->id}/verification", [
                'verification_status' => 'verified',
                'verification_notes' => 'Documents checked.',
            ])
            ->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');
    }

    public function test_a_cleaner_with_upcoming_bookings_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $cleaner = Cleaner::factory()->create();
        Booking::factory()->for($cleaner)->status(BookingStatus::Confirmed)->create([
            'scheduled_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/cleaners/{$cleaner->id}")
            ->assertStatus(409)
            ->assertJsonStructure(['message', 'bookings']);

        $this->assertNull($cleaner->fresh()->deleted_at);
    }

    public function test_verification_counts_are_grouped_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        Cleaner::factory()->verification(VerificationStatus::Verified)->count(3)->create();
        Cleaner::factory()->verification(VerificationStatus::Pending)->count(2)->create();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/cleaners/verification-counts')
            ->assertOk()
            ->assertJsonPath('data.verified', 3)
            ->assertJsonPath('data.pending', 2);
    }

    public function test_an_admin_can_create_a_customer_with_addresses(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/customers', [
                'name' => 'Walk In',
                'email' => 'walkin@example.com',
                'addresses' => [
                    ['label' => 'Home', 'line1' => '1 Beach Rd', 'area' => 'JBR', 'city' => 'Dubai', 'is_default' => true],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Walk In');

        $this->assertDatabaseHas('addresses', ['line1' => '1 Beach Rd']);
    }
}
