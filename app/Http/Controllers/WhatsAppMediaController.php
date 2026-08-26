<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Storage;

class WhatsAppMediaController extends Controller
{
    public function __invoke(WhatsAppMessage $message)
    {
        abort_unless(
            $message->has_media
            && $message->media_path
            && $message->conversation()
                ->visibleTo(auth()->user())
                ->whereKey($message->conversation_id)
                ->exists(),
            404,
        );

        abort_unless(Storage::disk('local')->exists($message->media_path), 404);

        $mimeType = (string) data_get($message->raw_payload, 'media.mimetype', 'application/octet-stream');
        $filename = (string) data_get($message->raw_payload, 'media.filename', basename($message->media_path));

        return Storage::disk('local')->response($message->media_path, $filename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $filename).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
