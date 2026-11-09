<?php

namespace App\Http\Controllers\Api\V1\Cleaner;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cleaner\UpdateWorkingHoursRequest;
use App\Http\Resources\CleanerWorkingHourResource;
use App\Models\Cleaner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkingHourController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $cleaner = $this->cleaner($request);

        return $this->ok(CleanerWorkingHourResource::collection(
            $cleaner->workingHours()->orderBy('day_of_week')->get(),
        ));
    }

    public function update(UpdateWorkingHoursRequest $request): JsonResponse
    {
        $cleaner = $this->cleaner($request);

        DB::transaction(function () use ($cleaner, $request): void {
            foreach ($request->validated('hours') as $row) {
                $cleaner->workingHours()->updateOrCreate(
                    ['day_of_week' => $row['day_of_week']],
                    [
                        'is_off' => $row['is_off'],
                        'start_time' => $row['is_off'] ? null : ($row['start_time'] ?? null),
                        'end_time' => $row['is_off'] ? null : ($row['end_time'] ?? null),
                    ],
                );
            }
        });

        return $this->ok(
            CleanerWorkingHourResource::collection($cleaner->workingHours()->orderBy('day_of_week')->get()),
            'Working hours updated.',
        );
    }

    private function cleaner(Request $request): Cleaner
    {
        $cleaner = $request->user()->cleaner;

        abort_if($cleaner === null, 403, 'No cleaner profile found for this account.');

        return $cleaner;
    }
}
