<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Models\ApiIdempotencyKey;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || ! preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            return ApiResponse::error($request, 'idempotency_key_required', 'Header Idempotency-Key wajib diisi (8–100 karakter aman).', 400);
        }

        /** @var ApiClient $client */
        $client = $request->attributes->get('api_client');
        $requestHash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $record = ApiIdempotencyKey::query()->firstOrCreate(
            ['api_client_id' => $client->getKey(), 'key' => $key],
            [
                'request_hash' => $requestHash,
                'expires_at' => now()->addHours(config('public_api.idempotency_ttl_hours')),
            ],
        );

        if (! $record->wasRecentlyCreated) {
            if (! hash_equals($record->request_hash, $requestHash)) {
                return ApiResponse::error($request, 'idempotency_conflict', 'Idempotency-Key pernah digunakan untuk payload berbeda.', 409);
            }

            if ($record->response_status && is_array($record->response_body)) {
                $body = $record->response_body;
                if (isset($body['meta'])) {
                    $body['meta']['request_id'] = $request->attributes->get('api_request_id');
                } elseif (isset($body['error'])) {
                    $body['error']['request_id'] = $request->attributes->get('api_request_id');
                }

                return response()->json($body, $record->response_status, ['X-Idempotent-Replay' => 'true']);
            }

            return ApiResponse::error($request, 'request_in_progress', 'Request dengan Idempotency-Key ini masih diproses.', 409);
        }

        try {
            $response = $next($request);
            if ($response instanceof JsonResponse && $response->getStatusCode() < 500) {
                $record->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
                ]);
            } else {
                $record->delete();
            }

            return $response;
        } catch (\Throwable $exception) {
            $record->delete();
            throw $exception;
        }
    }
}
