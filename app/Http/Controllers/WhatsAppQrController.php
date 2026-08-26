<?php

namespace App\Http\Controllers;

use App\Enums\WhatsAppAccountStatus;
use App\Models\UserWhatsAppAccount;
use App\Services\WhatsApp\WahaClient;
use Illuminate\Http\Response;
use Throwable;

class WhatsAppQrController extends Controller
{
    public function __invoke(UserWhatsAppAccount $account, WahaClient $client): Response
    {
        abort_unless(auth()->user()?->canManageOperationalData(), 403);
        $allowed = UserWhatsAppAccount::query()->visibleTo(auth()->user())->whereKey($account)->exists();
        abort_unless($allowed, 403);

        try {
            $status = strtoupper((string) data_get($client->getSession($account->session_name), 'status'));

            if (in_array($status, ['FAILED', 'STOPPED'], true)) {
                $account->update(['status' => WhatsAppAccountStatus::Connecting, 'last_error' => null]);
                $client->restartSession($account->session_name);
                $status = $this->waitForQr($client, $account->session_name);
            } elseif ($status === 'STARTING') {
                $status = $this->waitForQr($client, $account->session_name);
            }

            if ($status !== 'SCAN_QR_CODE') {
                return $this->unavailable($account, $status);
            }

            $qr = $client->qrCode($account->session_name);
            $account->update(['status' => WhatsAppAccountStatus::Connecting, 'last_error' => null, 'last_seen_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
            $account->update([
                'status' => WhatsAppAccountStatus::Failed,
                'last_error' => str($exception->getMessage())->limit(2000)->toString(),
            ]);

            return $this->unavailable($account, 'FAILED');
        }

        return response($qr['body'], 200, [
            'Content-Type' => $qr['content_type'],
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }

    private function waitForQr(WahaClient $client, string $session): string
    {
        for ($attempt = 0; $attempt < 12; $attempt++) {
            usleep(250_000);
            $status = strtoupper((string) data_get($client->getSession($session), 'status'));

            if ($status !== 'STARTING') {
                return $status;
            }
        }

        return 'STARTING';
    }

    private function unavailable(UserWhatsAppAccount $account, string $status): Response
    {
        return response()->view('integrations.waha.qr-unavailable', [
            'account' => $account,
            'status' => $status,
        ], 503);
    }
}
