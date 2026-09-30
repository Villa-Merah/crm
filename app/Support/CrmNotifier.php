<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\FollowupRiwayat;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\TargetKinerjaUser;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Filament\Actions\Action;
use Filament\Notifications\DatabaseNotification;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class CrmNotifier
{
    public function incomingWhatsAppMessage(WhatsAppConversation $conversation, WhatsAppMessage $message): bool
    {
        $recipient = $conversation->account()->with('user')->first()?->user;
        if (! $recipient || ! $recipient->aktif) {
            return false;
        }

        $contact = $conversation->contact_name ?: ($conversation->contact_phone_e164 ? '+'.$conversation->contact_phone_e164 : 'Kontak WhatsApp');
        $preview = filled($message->body) ? str($message->body)->squish()->limit(120)->toString() : '[Media/tanpa teks]';

        return $this->sendOnce(
            $recipient,
            'whatsapp-incoming-'.$message->getKey(),
            'Pesan WhatsApp baru',
            $contact.': '.$preview,
            url('/admin/inbox-whatsapp/'.$conversation->getKey()),
            'heroicon-o-chat-bubble-left-right',
            'success',
        );
    }

    public function whatsAppSendFailed(User $recipient, WhatsAppConversation $conversation, WhatsAppMessage $message): bool
    {
        return $this->sendOnce(
            $recipient,
            'whatsapp-send-failed-'.$message->getKey(),
            'Pesan WhatsApp gagal dikirim',
            'Pesan ke '.($conversation->contact_name ?: $conversation->contact_phone_e164 ?: 'kontak').' belum terkirim. Periksa koneksi WAHA.',
            url('/admin/inbox-whatsapp/'.$conversation->getKey()),
            'heroicon-o-exclamation-triangle',
            'danger',
        );
    }

    public function leadAssigned(Lead $lead): void
    {
        $recipient = $lead->diserahkanKe()->where('aktif', true)->first();
        if (! $recipient || $recipient->id === auth()->id()) {
            return;
        }

        $this->sendOnce($recipient, 'lead-assigned-'.$lead->getKey().'-'.$recipient->id, 'Lead baru ditugaskan', $lead->nama.' telah ditugaskan kepada Anda.', url('/admin/leads/'.$lead->getKey()), 'heroicon-o-user-plus', 'primary');
    }

    public function targetAssigned(TargetKinerjaUser $target): void
    {
        $recipient = $target->user()->where('aktif', true)->first();
        $period = $target->targetCabang()->first();
        if (! $recipient || ! $period) {
            return;
        }

        $this->sendOnce($recipient, 'target-assigned-'.$target->getKey(), 'Target kinerja ditetapkan', 'Target '.$period->bulan.'/'.$period->tahun.' telah dialokasikan kepada Anda.', url('/admin/target-kinerja'), 'heroicon-o-chart-bar-square', 'success');
    }

    public function pendingPayment(Pembayaran $payment): int
    {
        if (in_array($payment->status_pembayaran, ['Lunas', 'Terverifikasi'], true)) {
            return 0;
        }
        $branchId = $payment->pendaftaran()->value('cabang_id');
        if (! $branchId) {
            return 0;
        }

        $recipients = User::query()->where('aktif', true)->where(fn ($query) => $query
            ->where(fn ($branch) => $branch->where('role', UserRole::AdminCabang->value)->where('cabang_id', $branchId))
            ->orWhere('role', UserRole::Superadmin->value))->get();
        $sent = 0;
        foreach ($recipients as $recipient) {
            $sent += (int) $this->sendOnce($recipient, 'payment-pending-'.$payment->getKey(), 'Pembayaran perlu diperiksa', 'Pembayaran baru berstatus '.$payment->status_pembayaran.'.', url('/admin/pembayaran'), 'heroicon-o-banknotes', 'warning');
        }

        return $sent;
    }

    public function overdueFollowup(FollowupRiwayat $followup): bool
    {
        $recipient = $followup->user()->where('aktif', true)->first();
        if (! $recipient || ! $followup->tanggal_followup_berikutnya) {
            return false;
        }
        $key = 'followup-overdue-'.$followup->getKey().'-'.$followup->tanggal_followup_berikutnya->format('YmdHi');

        return $this->sendOnce($recipient, $key, 'Follow-up melewati jadwal', 'Tindak lanjut lead '.$followup->lead()->value('nama').' memerlukan perhatian.', url('/admin/follow-up'), 'heroicon-o-chat-bubble-left-right', 'danger');
    }

    private function sendOnce(User $recipient, string $key, string $title, string $body, string $url, string $icon, string $color): bool
    {
        $message = Notification::make()
            ->title($title)->body($body)->icon($icon)->iconColor($color)
            ->viewData(['dedupeKey' => $key])
            ->actions([Action::make('view')->label('Tinjau')->button()->url($url)->markAsRead()])
            ->getDatabaseMessage();
        $hash = md5($recipient->getMorphClass().'|'.$recipient->getKey().'|'.$key);
        $id = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-'.substr($hash, 12, 4).'-'.substr($hash, 16, 4).'-'.substr($hash, 20, 12);

        return DB::table('notifications')->insertOrIgnore([
            'id' => $id,
            'type' => DatabaseNotification::class,
            'notifiable_type' => $recipient->getMorphClass(),
            'notifiable_id' => $recipient->getKey(),
            'data' => json_encode($message, JSON_THROW_ON_ERROR),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }
}
