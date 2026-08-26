<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWahaWebhook;
use App\Models\WahaWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WahaWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event' => ['required', 'string', 'max:80'],
            'session' => ['required', 'string', 'max:100'],
            'payload' => ['required', 'array'],
        ]);

        $event = WahaWebhookEvent::firstOrCreate(
            ['payload_hash' => hash('sha256', $request->getContent())],
            [
                'event_name' => $data['event'],
                'session_name' => $data['session'],
                'status' => 'Pending',
                'payload' => $data,
            ],
        );

        if ($event->wasRecentlyCreated || $event->status === 'Failed') {
            ProcessWahaWebhook::dispatch($event->getKey());
        }

        return response()->json([
            'accepted' => true,
            'duplicate' => ! $event->wasRecentlyCreated,
        ], 202);
    }
}
