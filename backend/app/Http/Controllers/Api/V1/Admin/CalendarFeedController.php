<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarFeedResource;
use App\Models\CalendarFeed;
use App\Services\ActivityLogger;
use App\Services\IcalSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CalendarFeedController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly IcalSyncService $sync,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(): JsonResponse
    {
        $feeds = CalendarFeed::query()->orderBy('name')->get();

        return $this->ok(CalendarFeedResource::collection($feeds)->resolve());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateFeed($request);

        $feed = CalendarFeed::create($data);
        $feed->refresh();
        $this->activity->log('calendar_feed.created', $feed, $feed->only(['name', 'source']));

        // Pull events immediately so the admin sees results without waiting for the scheduler.
        if ($feed->is_active) {
            $this->sync->syncFeed($feed);
            $feed->refresh();
        }

        return $this->created(new CalendarFeedResource($feed), 'Calendar feed added.');
    }

    public function update(Request $request, CalendarFeed $calendarFeed): JsonResponse
    {
        $data = $this->validateFeed($request, $calendarFeed);

        $calendarFeed->update($data);
        $this->activity->log('calendar_feed.updated', $calendarFeed, $calendarFeed->getChanges());

        return $this->ok(new CalendarFeedResource($calendarFeed), 'Calendar feed updated.');
    }

    public function destroy(CalendarFeed $calendarFeed): JsonResponse
    {
        $calendarFeed->delete();
        $this->activity->log('calendar_feed.deleted', $calendarFeed);

        return $this->message('Calendar feed removed.');
    }

    public function sync(CalendarFeed $calendarFeed): JsonResponse
    {
        $result = $this->sync->syncFeed($calendarFeed);
        $this->activity->log('calendar_feed.synced', $calendarFeed, $result);
        $calendarFeed->refresh();

        $status = $result['status'] === 'success' ? 200 : 422;

        return $this->ok(new CalendarFeedResource($calendarFeed), $result['message'], $status);
    }

    public function syncAll(): JsonResponse
    {
        $result = $this->sync->syncAll();
        $this->activity->log('calendar_feed.synced_all', null, $result);

        return $this->ok(
            CalendarFeedResource::collection(CalendarFeed::orderBy('name')->get())->resolve(),
            "Synced {$result['feeds']} feed(s); imported {$result['imported']} reservation(s).",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validateFeed(Request $request, ?CalendarFeed $feed = null): array
    {
        return $request->validate([
            'name' => [$feed ? 'sometimes' : 'required', 'string', 'max:120'],
            'source' => ['sometimes', Rule::in(['airbnb', 'booking_com', 'vrbo', 'google', 'ical', 'other'])],
            'url' => [$feed ? 'sometimes' : 'required', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
