<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppAccountStatus;
use App\Models\UserWhatsAppAccount;

class WahaSessionService
{
    public function __construct(private readonly WahaClient $client) {}

    /** @return array<string, mixed> */
    public function provision(UserWhatsAppAccount $account): array
    {
        $config = $this->webhookConfig();

        $response = $this->client->createSession($account->session_name, $config);
        $account->update([
            'status' => WhatsAppAccountStatus::Disconnected,
            'metadata' => ['provisioned_at' => now()->toIso8601String()],
            'last_error' => null,
        ]);

        return $response;
    }

    /** @return array<string, mixed> */
    public function syncWebhooks(UserWhatsAppAccount $account): array
    {
        $config = $this->webhookConfig();
        if ($config === []) {
            throw new \RuntimeException('URL dan secret webhook WAHA belum dikonfigurasi.');
        }

        $response = $this->client->updateSession($account->session_name, $config);
        $metadata = $account->metadata ?? [];
        $metadata['webhooks_synced_at'] = now()->toIso8601String();
        $account->update(['metadata' => $metadata, 'last_error' => null]);

        return $response;
    }

    /** @return array<string, mixed> */
    public function start(UserWhatsAppAccount $account): array
    {
        $account->update(['status' => WhatsAppAccountStatus::Connecting, 'last_error' => null]);

        return $this->client->startSession($account->session_name);
    }

    /** @return array<string, mixed> */
    public function restart(UserWhatsAppAccount $account): array
    {
        $account->update(['status' => WhatsAppAccountStatus::Connecting, 'last_error' => null]);

        return $this->client->restartSession($account->session_name);
    }

    public function stop(UserWhatsAppAccount $account): array
    {
        $response = $this->client->stopSession($account->session_name);
        $account->update([
            'status' => WhatsAppAccountStatus::Disconnected,
            'last_error' => null,
            'last_seen_at' => now(),
        ]);

        return $response;
    }

    /** @return array<string, mixed> */
    public function refresh(UserWhatsAppAccount $account): array
    {
        $response = $this->client->getSession($account->session_name);
        $status = strtoupper((string) data_get($response, 'status'));
        $account->update([
            'status' => match ($status) {
                'WORKING' => WhatsAppAccountStatus::Working,
                'STARTING', 'SCAN_QR_CODE' => WhatsAppAccountStatus::Connecting,
                'FAILED' => WhatsAppAccountStatus::Failed,
                default => WhatsAppAccountStatus::Disconnected,
            },
            'waha_me_id' => data_get($response, 'me.id'),
            'last_seen_at' => now(),
        ]);

        return $response;
    }

    /** @return array<string, mixed> */
    private function webhookConfig(): array
    {
        $url = config('services.waha.webhook_url');
        $secret = config('services.waha.webhook_hmac_secret');

        if (blank($url) || blank($secret)) {
            return [];
        }

        return ['webhooks' => [[
            'url' => $url,
            'events' => ['message', 'message.any', 'message.ack', 'session.status'],
            'hmac' => ['key' => $secret],
            'retries' => ['policy' => 'linear', 'delaySeconds' => 2, 'attempts' => 8],
        ]]];
    }
}
