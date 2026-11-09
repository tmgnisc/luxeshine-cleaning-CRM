<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookingResource;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly StatsService $stats) {}

    public function stats(): JsonResponse
    {
        return $this->ok([
            ...$this->stats->overview(),
            'alerts' => $this->stats->alerts(),
        ]);
    }

    public function revenueChart(Request $request): JsonResponse
    {
        $months = (int) $request->integer('months', 6);
        $months = in_array($months, [3, 6, 12], true) ? $months : 6;

        return $this->ok($this->stats->revenueChart($months));
    }

    public function bookingsByService(): JsonResponse
    {
        return $this->ok($this->stats->bookingsByService());
    }

    public function todayJobs(): JsonResponse
    {
        return $this->ok(BookingResource::collection($this->stats->todayJobs()));
    }

    public function topCleaners(Request $request): JsonResponse
    {
        return $this->ok($this->stats->topCleaners((int) $request->integer('limit', 5)));
    }
}
