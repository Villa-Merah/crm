<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class WahaClient
{
    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.waha.base_url'), '/'))
            ->acceptJson()
            ->withHeader('X-Api-Key', (string) config('services.waha.api_key'))
            ->timeout((int) config('services.waha.timeout', 15))
            ->retry(2, 300, throw: false);
    }

    /** @return array<string, mixed> */
    public function health(): array
    {
        return Http::baseUrl(rtrim((string) config('services.waha.base_url'), '/'))
            ->acceptJson()
            ->timeout(3)
            ->connectTimeout(2)
            ->get('/health')
            ->throw()
            ->json();
    }

    /** @return array<string, mixed> */
    public function getSession(string $name): array
    {
        return $this->request()->get('/api/sessions/'.rawurlencode($name))->throw()->json();
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function createSession(string $name, array $config = []): array
    {
        return $this->request()->post('/api/sessions', [
            'name' => $name,
            'start' => false,
            'config' => $config,
        ])->throw()->json();
    }

    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function updateSession(string $name, array $config): array
    {
        return $this->request()->put('/api/sessions/'.rawurlencode($name), [
            'config' => $config,
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function startSession(string $name): array
    {
        return $this->request()->post('/api/sessions/'.rawurlencode($name).'/start')->throw()->json();
    }

    /** @return array<string, mixed> */
    public function stopSession(string $name): array
    {
        return $this->request()->post('/api/sessions/'.rawurlencode($name).'/stop')->throw()->json();
    }

    /** @return array<string, mixed> */
    public function restartSession(string $name): array
    {
        return $this->request()->post('/api/sessions/'.rawurlencode($name).'/restart')->throw()->json();
    }

    /** @return array<string, mixed> */
    public function sendText(string $session, string $chatId, string $text): array
    {
        return $this->request()->post('/api/sendText', [
            'session' => $session,
            'chatId' => $chatId,
            'text' => $text,
            'linkPreview' => true,
        ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function sendImage(string $session, string $chatId, string $mimeType, string $filename, string $data, ?string $caption = null): array
    {
        return $this->sendMedia('/api/sendImage', $session, $chatId, $mimeType, $filename, $data, $caption);
    }

    /** @return array<string, mixed> */
    public function sendVideo(string $session, string $chatId, string $mimeType, string $filename, string $data, ?string $caption = null): array
    {
        return $this->sendMedia('/api/sendVideo', $session, $chatId, $mimeType, $filename, $data, $caption, ['convert' => true]);
    }

    /** @return array{body: string, content_type: string} */
    public function downloadMedia(string $url): array
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || ! str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('URL media WAHA tidak valid.');
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $response = $this->request()->get($path.$query)->throw();

        return [
            'body' => $response->body(),
            'content_type' => (string) $response->header('Content-Type', 'application/octet-stream'),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function sendMedia(string $endpoint, string $session, string $chatId, string $mimeType, string $filename, string $data, ?string $caption, array $extra = []): array
    {
        return $this->request()->post($endpoint, [
            'session' => $session,
            'chatId' => $chatId,
            'file' => [
                'mimetype' => $mimeType,
                'filename' => $filename,
                'data' => base64_encode($data),
            ],
            'caption' => $caption ?: null,
            ...$extra,
        ])->throw()->json();
    }

    /** @return array{body: string, content_type: string} */
    public function qrCode(string $name): array
    {
        $response = $this->request()
            ->withHeader('Accept', 'image/png')
            ->get('/api/'.rawurlencode($name).'/auth/qr')
            ->throw();

        return [
            'body' => $response->body(),
            'content_type' => $response->header('Content-Type', 'image/png'),
        ];
    }
}
