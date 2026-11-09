<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplyReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Services\ActivityLogger;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponses, HandlesResourceQueries;

    public function __construct(
        private readonly ReviewService $reviews,
        private readonly ActivityLogger $activity,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Review::query()
            ->with(['booking.service', 'customer.user', 'cleaner.user'])
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->integer('rating')))
            ->when($request->filled('is_published'), fn ($q) => $q->where('is_published', $request->boolean('is_published')))
            ->when($request->filled('cleaner_id'), fn ($q) => $q->where('cleaner_id', $request->integer('cleaner_id')));

        $this->applySort($query, $request, ['rating', 'created_at'], '-created_at');

        return $this->paginated($query->paginate($this->perPage($request)), ReviewResource::class);
    }

    public function stats(): JsonResponse
    {
        $published = Review::where('is_published', true);

        return $this->ok([
            'average_rating' => round((float) (clone $published)->avg('rating'), 2),
            'published' => (clone $published)->count(),
            'awaiting' => Review::where('is_published', false)->count(),
            'total' => Review::count(),
        ]);
    }

    public function publish(Request $request, Review $review): JsonResponse
    {
        $review = $this->reviews->setPublished($review, ! $review->is_published, $request->user());
        $this->activity->log('review.publish_toggled', $review, ['is_published' => $review->is_published]);

        return $this->ok(
            new ReviewResource($review->load(['booking.service', 'customer.user', 'cleaner.user'])),
            $review->is_published ? 'Review published.' : 'Review unpublished.',
        );
    }

    public function reply(ReplyReviewRequest $request, Review $review): JsonResponse
    {
        $review->update(['admin_reply' => $request->input('admin_reply')]);
        $this->activity->log('review.replied', $review);

        return $this->ok(new ReviewResource($review->load(['booking.service', 'customer.user', 'cleaner.user'])), 'Reply saved.');
    }

    public function destroy(Review $review): JsonResponse
    {
        $this->reviews->delete($review);
        $this->activity->log('review.deleted', $review);

        return $this->message('Review deleted.');
    }
}
