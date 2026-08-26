<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppAccountStatus;
use App\Models\UserWhatsAppAccount;
use App\Services\WhatsApp\WahaSessionService;
use Illuminate\Console\Command;
use Throwable;

class SyncWahaSessions extends Command
{
    protected $signature = 'crm:sync-whatsapp-sessions';

    protected $description = 'Sinkronkan status session WhatsApp pengguna dari WAHA';

    public function handle(WahaSessionService $sessions): int
    {
        $failed = 0;
        UserWhatsAppAccount::query()->where('aktif', true)->orderBy('id')->each(function (UserWhatsAppAccount $account) use ($sessions, &$failed): void {
            try {
                $sessions->refresh($account);
            } catch (Throwable $exception) {
                $failed++;
                $account->update([
                    'status' => WhatsAppAccountStatus::Failed,
                    'last_error' => str($exception->getMessage())->limit(2000)->toString(),
                    'last_seen_at' => now(),
                ]);
                report($exception);
            }
        });

        $this->info('Sinkronisasi session selesai; gagal: '.$failed.'.');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
