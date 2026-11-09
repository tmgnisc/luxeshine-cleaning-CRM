<?php

namespace App\Http\Controllers\Api\V1\Cleaner;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Cleaner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    use ApiResponses;

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_available' => ['required', 'boolean'],
        ]);

        $cleaner = $this->cleaner($request);
        $cleaner->update(['is_available' => $validated['is_available']]);

        return $this->ok(
            ['is_available' => $cleaner->is_available],
            $cleaner->is_available ? 'You are now marked as available.' : 'You are now marked as unavailable.',
        );
    }

    private function cleaner(Request $request): Cleaner
    {
        $cleaner = $request->user()->cleaner;

        abort_if($cleaner === null, 403, 'No cleaner profile found for this account.');

        return $cleaner;
    }
}
