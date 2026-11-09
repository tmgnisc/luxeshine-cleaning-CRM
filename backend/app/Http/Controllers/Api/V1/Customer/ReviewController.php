<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly ReviewService $reviews) {}

    public function store(StoreReviewRequest $request, Booking $booking): JsonResponse
    {
        $customer = $request->user()->customer;
        abort_unless($customer !== null && $booking->customer_id === $customer->id, 404, 'Resource not found.');

        $review = $this->reviews->createForBooking($booking, $request->validated());

        return $this->created(new ReviewResource($review), 'Thanks for your review!');
    }
}
