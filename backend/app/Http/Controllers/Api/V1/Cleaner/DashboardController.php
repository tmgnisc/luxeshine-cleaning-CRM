<?php

namespace App\Http\Controllers\Api\V1\Cleaner;

use App\Enums\BookingStatus;
use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Models\Cleaner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $cleaner = $this->cleaner($request);

        $todayJobs = $cleaner->bookings()
            ->with(['service', 'customer.user', 'address'])
            ->whereDate('scheduled_date', today())
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value, BookingStatus::Completed->value])
            ->orderBy('start_time')
            ->get();

        $upcomingCount = $cleaner->bookings()
            ->whereDate('scheduled_date', '>', today())
            ->whereIn('status', [BookingStatus::Pending->value, BookingStatus::Confirmed->value])
            ->count();

        return $this->ok([
            'is_available' => $cleaner->is_available,
            'rating_avg' => (float) $cleaner->rating_avg,
            'rating_count' => $cleaner->rating_count,
            'completed_jobs_count' => $cleaner->completed_jobs_count,
            'today_jobs_count' => $todayJobs->count(),
            'upcoming_count' => $upcomingCount,
            'today_jobs' => BookingResource::collection($todayJobs)->resolve(),
        ]);
    }

    private function cleaner(Request $request): Cleaner
    {
        $cleaner = $request->user()->cleaner;
        abort_if($cleaner === null, 403, 'No cleaner profile found for this account.');

        return $cleaner;
    }
}
