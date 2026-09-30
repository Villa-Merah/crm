<?php

namespace App\Console\Commands;

use App\Models\FollowupRiwayat;
use App\Models\Pembayaran;
use App\Support\CrmNotifier;
use Illuminate\Console\Command;

class SendCrmReminders extends Command
{
    protected $signature = 'crm:send-reminders';

    protected $description = 'Kirim notifikasi follow-up terlambat dan pembayaran yang perlu diperiksa';

    public function handle(CrmNotifier $notifier): int
    {
        $followupsSent = 0;
        FollowupRiwayat::query()->with(['user', 'lead'])->whereNotNull('tanggal_followup_berikutnya')->where('tanggal_followup_berikutnya', '<=', now())
            ->chunkById(100, function ($followups) use ($notifier, &$followupsSent): void {
                foreach ($followups as $followup) {
                    $followupsSent += (int) $notifier->overdueFollowup($followup);
                }
            }, 'followup_riwayat_id');

        $paymentsSent = 0;
        Pembayaran::query()->whereNotIn('status_pembayaran', ['Lunas', 'Terverifikasi'])
            ->chunkById(100, function ($payments) use ($notifier, &$paymentsSent): void {
                foreach ($payments as $payment) {
                    $paymentsSent += $notifier->pendingPayment($payment);
                }
            }, 'pembayaran_id');

        $this->info("Notifikasi terkirim: {$followupsSent} follow-up, {$paymentsSent} pembayaran.");

        return self::SUCCESS;
    }
}
