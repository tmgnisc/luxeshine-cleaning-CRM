<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class IcalSyncService
{
    /**
     * Sync every active feed.
     *
     * @return array{feeds:int, imported:int}
     */
    public function syncAll(): array
    {
        $feeds = CalendarFeed::where('is_active', true)->get();
        $imported = 0;

        foreach ($feeds as $feed) {
            $result = $this->syncFeed($feed);
            $imported += $result['imported'];
        }

        return ['feeds' => $feeds->count(), 'imported' => $imported];
    }

    /**
     * Fetch, parse and persist a single feed's events.
     *
     * @return array{status:string, imported:int, message:string}
     */
    public function syncFeed(CalendarFeed $feed): array
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders(['User-Agent' => 'LuxeShine-Calendar-Sync/1.0'])
                ->retry(2, 300)
                ->get($feed->url);

            if (! $response->successful()) {
                return $this->markFailed($feed, "Feed responded with HTTP {$response->status()}.");
            }

            $body = $response->body();

            if (! str_contains($body, 'BEGIN:VCALENDAR')) {
                return $this->markFailed($feed, 'The URL did not return a valid iCalendar (.ics) feed.');
            }

            $events = $this->parseEvents($body);

            return $this->persist($feed, $events);
        } catch (Throwable $e) {
            return $this->markFailed($feed, 'Could not reach the feed: '.$e->getMessage());
        }
    }

    /**
     * Persist parsed events and prune ones that dropped out of the feed.
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array{status:string, imported:int, message:string}
     */
    private function persist(CalendarFeed $feed, array $events): array
    {
        $seenUids = [];

        foreach ($events as $event) {
            if ($event['uid'] === null || $event['starts_on'] === null) {
                continue;
            }

            $seenUids[] = $event['uid'];

            CalendarEvent::updateOrCreate(
                ['calendar_feed_id' => $feed->id, 'uid' => $event['uid']],
                [
                    'summary' => $event['summary'],
                    'description' => $event['description'],
                    'location' => $event['location'],
                    'starts_on' => $event['starts_on'],
                    'ends_on' => $event['ends_on'],
                    'status' => $event['status'],
                ],
            );
        }

        // Drop events that are no longer present in the feed (e.g. cancelled reservations).
        $feed->events()
            ->when($seenUids !== [], fn ($q) => $q->whereNotIn('uid', $seenUids))
            ->delete();

        $count = count($seenUids);

        $feed->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'success',
            'last_sync_message' => "Imported {$count} reservation(s).",
            'events_count' => $count,
        ]);

        return ['status' => 'success', 'imported' => $count, 'message' => "Imported {$count} reservation(s)."];
    }

    /**
     * @return array{status:string, imported:int, message:string}
     */
    private function markFailed(CalendarFeed $feed, string $message): array
    {
        $feed->update([
            'last_synced_at' => now(),
            'last_sync_status' => 'failed',
            'last_sync_message' => $message,
        ]);

        return ['status' => 'failed', 'imported' => 0, 'message' => $message];
    }

    /**
     * Parse the raw ICS body into normalised event rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseEvents(string $body): array
    {
        $lines = $this->unfold($body);

        $events = [];
        $current = null;

        foreach ($lines as $line) {
            $trimmed = rtrim($line, "\r\n");

            if ($trimmed === 'BEGIN:VEVENT') {
                $current = [];

                continue;
            }

            if ($trimmed === 'END:VEVENT') {
                if ($current !== null) {
                    $events[] = $this->normaliseEvent($current);
                }
                $current = null;

                continue;
            }

            if ($current === null) {
                continue;
            }

            [$name, $params, $value] = $this->splitProperty($trimmed);

            if ($name === null) {
                continue;
            }

            $current[$name] = ['value' => $value, 'params' => $params];
        }

        return $events;
    }

    /**
     * Apply RFC 5545 line unfolding: a line beginning with a space or tab is a
     * continuation of the previous line.
     *
     * @return array<int, string>
     */
    private function unfold(string $body): array
    {
        $raw = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $out = [];

        foreach ($raw as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $out !== []) {
                $out[count($out) - 1] .= substr($line, 1);
            } else {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * Split a content line into [name, params, value].
     *
     * @return array{0:?string, 1:array<string,string>, 2:string}
     */
    private function splitProperty(string $line): array
    {
        $colon = strpos($line, ':');

        if ($colon === false) {
            return [null, [], ''];
        }

        $head = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);

        $parts = explode(';', $head);
        $name = strtoupper(array_shift($parts));

        $params = [];
        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);
                $params[strtoupper($k)] = $v;
            }
        }

        return [$name, $params, $value];
    }

    /**
     * @param  array<string, array{value:string, params:array<string,string>}>  $props
     * @return array<string, mixed>
     */
    private function normaliseEvent(array $props): array
    {
        $summary = isset($props['SUMMARY']) ? $this->decodeText($props['SUMMARY']['value']) : null;

        return [
            'uid' => isset($props['UID']) ? trim($props['UID']['value']) : null,
            'summary' => $summary,
            'description' => isset($props['DESCRIPTION']) ? $this->decodeText($props['DESCRIPTION']['value']) : null,
            'location' => isset($props['LOCATION']) ? $this->decodeText($props['LOCATION']['value']) : null,
            'starts_on' => isset($props['DTSTART']) ? $this->parseDate($props['DTSTART']['value']) : null,
            'ends_on' => isset($props['DTEND']) ? $this->parseDate($props['DTEND']['value']) : null,
            'status' => $this->deriveStatus($summary),
        ];
    }

    /**
     * Parse an iCal date or date-time value into a Y-m-d string.
     */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m)) {
            try {
                return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3])->toDateString();
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function deriveStatus(?string $summary): string
    {
        $summary = strtolower((string) $summary);

        if ($summary === '' || str_contains($summary, 'not available') || str_contains($summary, 'blocked')) {
            return 'blocked';
        }

        return 'reserved';
    }

    /**
     * Unescape RFC 5545 escaped text (\\, \; \, \n).
     */
    private function decodeText(string $value): string
    {
        return str_replace(
            ['\\n', '\\N', '\\,', '\\;', '\\\\'],
            ["\n", "\n", ',', ';', '\\'],
            $value,
        );
    }
}
