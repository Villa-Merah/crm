<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWahaWebhookHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.waha.webhook_hmac_secret');
        if (blank($secret)) {
            return new JsonResponse(['message' => 'Webhook WAHA belum dikonfigurasi.'], 503);
        }

        $algorithm = strtolower((string) $request->header('X-Webhook-Hmac-Algorithm', 'sha512'));
        $signature = (string) $request->header('X-Webhook-Hmac');
        if ($algorithm !== 'sha512' || blank($signature)) {
            return new JsonResponse(['message' => 'Signature webhook tidak tersedia.'], 401);
        }

        $expected = hash_hmac('sha512', $request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            return new JsonResponse(['message' => 'Signature webhook tidak valid.'], 401);
        }

        return $next($request);
    }
}
