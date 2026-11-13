<?php

namespace Tests\Feature\Public;

use App\Mail\ContactFormMail;
use App\Models\Review;
use App\Models\Service;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_returns_the_public_payload(): void
    {
        $this->seed(SettingSeeder::class);

        $this->getJson('/api/v1/public/settings')
            ->assertOk()
            ->assertJsonPath('data.site_title', 'LuxeShine Cleaning')
            ->assertJsonPath('data.currency', 'NZD')
            ->assertJsonPath('data.accepting_bookings', true);
    }

    public function test_public_services_only_returns_live_services(): void
    {
        Service::factory()->create(['name' => 'Live Service', 'is_live' => true]);
        Service::factory()->notLive()->create(['name' => 'Hidden Service']);

        $response = $this->getJson('/api/v1/public/services')->assertOk();

        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Live Service'));
        $this->assertFalse($names->contains('Hidden Service'));
    }

    public function test_a_non_live_service_returns_404_on_the_public_detail_endpoint(): void
    {
        $service = Service::factory()->notLive()->create(['slug' => 'hidden']);

        $this->getJson('/api/v1/public/services/'.$service->slug)->assertNotFound();
    }

    public function test_public_reviews_only_returns_published_reviews(): void
    {
        $published = Review::factory()->published()->create(['comment' => 'Published one']);
        Review::factory()->create(['comment' => 'Unpublished one']);

        $response = $this->getJson('/api/v1/public/reviews')->assertOk();

        $comments = collect($response->json('data'))->pluck('comment');
        $this->assertTrue($comments->contains('Published one'));
        $this->assertFalse($comments->contains('Unpublished one'));
    }

    public function test_the_contact_form_queues_an_email(): void
    {
        Mail::fake();
        $this->seed(SettingSeeder::class);

        $this->postJson('/api/v1/public/contact', [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => 'I would like a quote for weekly cleaning.',
        ])->assertStatus(202);

        Mail::assertQueued(ContactFormMail::class);
    }

    public function test_contact_form_validation_fails_without_a_message(): void
    {
        $this->postJson('/api/v1/public/contact', [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('message');
    }
}
