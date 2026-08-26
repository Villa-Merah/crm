<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePublicApi
{
    public function handle(Request $request, Closure $next, ?string $ability = null): Response
    {
        $request->attributes->set('api_request_id', (string) Str::uuid());

        if (! config('public_api.enabled')) {
            return ApiResponse::error($request, 'api_disabled', 'API publik belum diaktifkan.', 503);
        }

        $token = $request->bearerToken() ?: $request->header('X-API-Key');

        if (! is_string($token) || strlen($token) < 32) {
            return ApiResponse::error($request, 'unauthenticated', 'API key tidak valid.', 401);
        }

        $client = ApiClient::query()
            ->with('user')
            ->where('key_hash', hash('sha256', $token))
            ->where('active', true)
            ->first();

        if (! $client || ! $client->user?->aktif) {
            return ApiResponse::error($request, 'unauthenticated', 'API key tidak valid.', 401);
        }

        if ($ability && ! $client->can($ability)) {
            return ApiResponse::error($request, 'forbidden', 'API client tidak memiliki ability yang diperlukan.', 403);
        }

        if ($ability && str_ends_with($ability, ':write') && ! $client->user->canManageOperationalData()) {
            return ApiResponse::error($request, 'forbidden', 'Pengguna audit tidak diizinkan mengubah data operasional.', 403);
        }

        $client->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_client', $client);
        Auth::setUser($client->user);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $request->attributes->get('api_request_id'));

        return $response;
    }
}
