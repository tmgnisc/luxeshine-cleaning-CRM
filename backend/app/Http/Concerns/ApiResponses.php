<?php

namespace App\Http\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ApiResponses
{
    /**
     * A successful response wrapping a single resource or arbitrary payload.
     */
    protected function ok(mixed $data, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['data' => $data];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $status);
    }

    protected function created(mixed $data, ?string $message = null): JsonResponse
    {
        return $this->ok($data, $message, 201);
    }

    /**
     * A response carrying only a human-readable message.
     */
    protected function message(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * A paginated collection response with a normalised meta block.
     */
    protected function paginated(LengthAwarePaginator $paginator, ?string $resourceClass = null, ?string $message = null): JsonResponse
    {
        $items = $paginator->getCollection();

        if ($resourceClass !== null) {
            /** @var class-string<JsonResource> $resourceClass */
            $data = $resourceClass::collection($items);
        } else {
            $data = $items;
        }

        $payload = [
            'data' => $data instanceof ResourceCollection ? $data->resolve() : $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload);
    }
}
