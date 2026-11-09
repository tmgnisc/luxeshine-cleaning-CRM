<?php

namespace App\Http\Controllers\Api\V1\Cleaner;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cleaner\StoreTimeOffRequest;
use App\Http\Resources\CleanerTimeOffResource;
use App\Models\Cleaner;
use App\Models\CleanerTimeOff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeOffController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $cleaner = $this->cleaner($request);

        return $this->ok(CleanerTimeOffResource::collection(
            $cleaner->timeOff()->orderByDesc('start_date')->get(),
        ));
    }

    public function store(StoreTimeOffRequest $request): JsonResponse
    {
        $cleaner = $this->cleaner($request);
        $timeOff = $cleaner->timeOff()->create($request->validated());

        return $this->created(new CleanerTimeOffResource($timeOff), 'Time off added.');
    }

    public function destroy(Request $request, CleanerTimeOff $timeOff): JsonResponse
    {
        $cleaner = $this->cleaner($request);

        abort_unless($timeOff->cleaner_id === $cleaner->id, 403, 'This action is unauthorized.');

        $timeOff->delete();

        return $this->message('Time off removed.');
    }

    private function cleaner(Request $request): Cleaner
    {
        $cleaner = $request->user()->cleaner;

        abort_if($cleaner === null, 403, 'No cleaner profile found for this account.');

        return $cleaner;
    }
}
