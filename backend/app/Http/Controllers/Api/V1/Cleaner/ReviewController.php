<?php

namespace App\Http\Controllers\Api\V1\Cleaner;

use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Cleaner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponses, HandlesResourceQueries;

    public function index(Request $request): JsonResponse
    {
        $cleaner = $request->user()->cleaner;
        abort_if($cleaner === null, 403, 'No cleaner profile found for this account.');

        $reviews = Cleaner::query()
            ->whereKey($cleaner->id)
            ->first()
            ->reviews()
            ->with(['booking.service', 'customer.user'])
            ->where('is_published', true)
            ->latest()
            ->paginate($this->perPage($request));

        return $this->paginated($reviews, ReviewResource::class);
    }
}
