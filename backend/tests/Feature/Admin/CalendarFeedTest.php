<?php

namespace Tests\Feature\Admin;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\User;
use App\Services\IcalSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarFeedTest extends TestCase
{
    use RefreshDatabase;

    private function sampleIcs(): string
    {
        return <<<'ICS'
        BEGIN:VCALENDAR
        PRODID:-//Airbnb Inc//Hosting Calendar 0.8.8//EN
        CALSCALE:GREGORIAN
        VERSION:2.0
        BEGIN:VEVENT
        DTEND;VALUE=DATE:20261118
        DTSTART;VALUE=DATE:20261115
        UID:reservation-abc@airbnb.com
        SUMMARY:Reserved
        DESCRIPTION:Reservation URL: https://www.airbnb.com/hosting/reservations/details/ABC
        LOCATION:Auckland
        END:VEVENT
        BEGIN:VEVENT
        DTEND;VALUE=DATE:20261122
        DTSTART;VALUE=DATE:20261120
        UID:blocked-def@airbnb.com
        SUMMARY:Airbnb (Not available)
        END:VEVENT
        END:VCALENDAR
        ICS;
    }

    public function test_the_ical_parser_reads_dates_uid_and_status(): void
    {
        $events = app(IcalSyncService::class)->parseEvents($this->sampleIcs());

        $this->assertCount(2, $events);
        $this->assertSame('reservation-abc@airbnb.com', $events[0]['uid']);
        $this->assertSame('2026-11-15', $events[0]['starts_on']);
        $this->assertSame('2026-11-18', $events[0]['ends_on']);
        $this->assertSame('reserved', $events[0]['status']);
        $this->assertSame('blocked', $events[1]['status']);
    }

    public function test_a_customer_cannot_manage_calendar_feeds(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer)
            ->getJson('/api/v1/admin/calendar-feeds')
            ->assertForbidden();
    }

    public function test_an_admin_can_add_a_feed_and_it_syncs_reservations(): void
    {
        Http::fake(['*' => Http::response($this->sampleIcs(), 200)]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/calendar-feeds', [
                'name' => 'Beach House',
                'source' => 'airbnb',
                'url' => 'https://www.airbnb.com/calendar/ical/123.ics?t=abc',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Beach House')
            ->assertJsonPath('data.last_sync_status', 'success')
            ->assertJsonPath('data.events_count', 2);

        $this->assertDatabaseCount('calendar_events', 2);
        $this->assertDatabaseHas('calendar_events', [
            'uid' => 'reservation-abc@airbnb.com',
            'status' => 'reserved',
        ]);
    }

    public function test_syncing_prunes_reservations_that_left_the_feed(): void
    {
        $admin = User::factory()->admin()->create();
        $feed = CalendarFeed::create([
            'name' => 'Beach House',
            'source' => 'airbnb',
            'url' => 'https://example.test/feed.ics',
            'is_active' => true,
        ]);

        // Stale event that is NOT present in the feed response.
        CalendarEvent::create([
            'calendar_feed_id' => $feed->id,
            'uid' => 'old-cancelled@airbnb.com',
            'summary' => 'Reserved',
            'starts_on' => '2026-10-01',
            'ends_on' => '2026-10-05',
            'status' => 'reserved',
        ]);

        Http::fake(['*' => Http::response($this->sampleIcs(), 200)]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/calendar-feeds/{$feed->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.events_count', 2);

        $this->assertDatabaseMissing('calendar_events', ['uid' => 'old-cancelled@airbnb.com']);
        $this->assertDatabaseCount('calendar_events', 2);
    }

    public function test_a_failed_fetch_is_recorded_without_touching_events(): void
    {
        $admin = User::factory()->admin()->create();
        $feed = CalendarFeed::create([
            'name' => 'Broken',
            'source' => 'airbnb',
            'url' => 'https://example.test/broken.ics',
            'is_active' => true,
        ]);

        Http::fake(['*' => Http::response('nope', 500)]);

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/calendar-feeds/{$feed->id}/sync")
            ->assertStatus(422)
            ->assertJsonPath('data.last_sync_status', 'failed');
    }

    public function test_the_schedule_overlays_calendar_events(): void
    {
        $admin = User::factory()->admin()->create();
        $feed = CalendarFeed::create([
            'name' => 'Beach House',
            'source' => 'airbnb',
            'url' => 'https://example.test/feed.ics',
            'is_active' => true,
        ]);
        CalendarEvent::create([
            'calendar_feed_id' => $feed->id,
            'uid' => 'reservation-abc@airbnb.com',
            'summary' => 'Reserved',
            'starts_on' => '2026-11-15',
            'ends_on' => '2026-11-18',
            'status' => 'reserved',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/schedule?from=2026-11-01&to=2026-11-30')
            ->assertOk();

        // The reservation spans 15th–18th, so those days carry an event entry.
        $days = collect($response->json('data'));
        $checkin = $days->firstWhere('date', '2026-11-15');
        $checkout = $days->firstWhere('date', '2026-11-18');

        $this->assertNotNull($checkin);
        $this->assertSame('Beach House', $checkin['events'][0]['feed_name']);
        $this->assertTrue($checkin['events'][0]['is_checkin']);
        $this->assertTrue($checkout['events'][0]['is_checkout']);
    }
}
