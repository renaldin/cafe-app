<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

abstract class Controller
{
    protected function ok(mixed $data = null, ?string $message = null, array $meta = [], int $status = 200): JsonResponse
    {
        return ApiResponse::ok($data, $message, $meta, $status);
    }

    protected function created(mixed $data = null, string $message = 'Data berhasil disimpan.'): JsonResponse
    {
        return ApiResponse::ok($data, $message, [], 201);
    }

    protected function paginated(LengthAwarePaginator $paginator, ?string $resource = null, array $meta = []): JsonResponse
    {
        return ApiResponse::paginated($paginator, $resource, $meta);
    }

    protected function perPage(): int
    {
        return min(max((int) request('per_page', 20), 1), 100);
    }
}
