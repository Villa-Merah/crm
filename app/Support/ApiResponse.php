<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    public static function error(Request $request, string $code, string $message, int $status, array $fields = []): JsonResponse
    {
        $response = response()->json([
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'fields' => $fields ?: null,
                'request_id' => $request->attributes->get('api_request_id'),
            ], static fn (mixed $value): bool => $value !== null),
        ], $status);

        if ($requestId = $request->attributes->get('api_request_id')) {
            $response->headers->set('X-Request-ID', $requestId);
        }

        return $response;
    }
}
