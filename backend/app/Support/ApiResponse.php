<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResponse
{
    public static function ok(mixed $data = null, ?string $message = null, array $meta = [], int $status = 200): JsonResponse
    {
        if ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        }

        return response()->json([
            'data' => $data,
            'message' => $message,
            'meta' => (object) $meta,
        ], $status);
    }

    public static function paginated(LengthAwarePaginator $paginator, ?string $resource = null, array $meta = []): JsonResponse
    {
        $items = $resource ? $resource::collection($paginator->getCollection())->resolve(request()) : $paginator->items();

        return response()->json([
            'data' => $items,
            'message' => null,
            'meta' => array_merge([
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ], $meta),
        ]);
    }

    public static function error(string $message, int $status = 422, array $errors = []): JsonResponse
    {
        return response()->json([
            'data' => null,
            'message' => $message,
            'meta' => (object) [],
            'errors' => (object) $errors,
        ], $status);
    }
}
