<?php

namespace Tests\Feature\Catalog;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_access_admin_service_endpoints(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->getJson('/api/v1/admin/services')
            ->assertForbidden();
    }

    public function test_an_admin_can_create_a_service(): void
    {
        $admin = User::factory()->admin()->create();
        $category = ServiceCategory::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/services', [
                'category_id' => $category->id,
                'name' => 'Balcony Cleaning',
                'short_description' => 'Sparkling clean balconies.',
                'price_per_hour' => 50,
                'default_duration_hours' => 2,
                'min_duration_hours' => 1,
                'max_duration_hours' => 4,
                'is_live' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'balcony-cleaning')
            ->assertJsonPath('data.name', 'Balcony Cleaning');

        $this->assertDatabaseHas('services', ['slug' => 'balcony-cleaning']);
    }

    public function test_service_creation_rejects_min_duration_above_default(): void
    {
        $admin = User::factory()->admin()->create();
        $category = ServiceCategory::factory()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/services', [
                'category_id' => $category->id,
                'name' => 'Bad Durations',
                'short_description' => 'Nope.',
                'price_per_hour' => 50,
                'default_duration_hours' => 2,
                'min_duration_hours' => 3,
                'max_duration_hours' => 4,
                'is_live' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('min_duration_hours');
    }

    public function test_a_service_with_bookings_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create();
        Booking::factory()->for($service)->status(BookingStatus::Completed)->create();

        $this->actingAs($admin)
            ->deleteJson('/api/v1/admin/services/'.$service->id)
            ->assertStatus(409);

        $this->assertDatabaseHas('services', ['id' => $service->id, 'deleted_at' => null]);
    }

    public function test_an_admin_can_toggle_a_service_live_flag(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create(['is_live' => true]);

        $this->actingAs($admin)
            ->patchJson('/api/v1/admin/services/'.$service->id.'/live')
            ->assertOk()
            ->assertJsonPath('data.is_live', false);
    }

    public function test_an_admin_can_bulk_update_settings_and_cache_refreshes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/settings', [
                'currency' => 'USD',
                'accepting_bookings' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.accepting_bookings', false);

        $this->getJson('/api/v1/public/settings')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.accepting_bookings', false);
    }
}
