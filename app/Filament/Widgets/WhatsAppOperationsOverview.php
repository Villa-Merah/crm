<?php

namespace App\Filament\Widgets;

use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppMatchStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Filament\Pages\AuditAktivitas;
use App\Filament\Resources\WhatsAppConversations\WhatsAppConversationResource;
use App\Models\ActivityLog;
use App\Models\UserWhatsAppAccount;
use App\Models\WahaWebhookEvent;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Filament\Widgets\Widget;

class WhatsAppOperationsOverview extends Widget
{
    protected string $view = 'filament.widgets.whatsapp-operations-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $viewer = auth()->user();
        $accounts = UserWhatsAppAccount::query()
            ->visibleTo($viewer)
            ->with(['user:id,name', 'cabang:cabang_id,nama'])
            ->latest('last_seen_at')
            ->get(['id', 'user_id', 'cabang_id', 'session_name', 'status', 'last_seen_at', 'last_error', 'metadata']);
        $accountIds = $accounts->pluck('id');
        $sessionNames = $accounts->pluck('session_name');

        $conversationStats = $accountIds->isEmpty()
            ? null
            : WhatsAppConversation::query()->whereIn('whatsapp_account_id', $accountIds)
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COALESCE(SUM(unread_count), 0) AS unread')
                ->selectRaw('COALESCE(SUM(CASE WHEN match_status = ? THEN 1 ELSE 0 END), 0) AS conflicts', [WhatsAppMatchStatus::Conflict->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN match_status = ? THEN 1 ELSE 0 END), 0) AS unresolved', [WhatsAppMatchStatus::Unresolved->value])
                ->first();

        $messageStats = $accountIds->isEmpty()
            ? null
            : WhatsAppMessage::query()->whereIn('whatsapp_account_id', $accountIds)
                ->where('sent_at', '>=', now()->subDays(30))
                ->selectRaw('COUNT(*) AS total')
                ->selectRaw('COALESCE(SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END), 0) AS inbound', [WhatsAppMessageDirection::Inbound->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN direction = ? THEN 1 ELSE 0 END), 0) AS outbound', [WhatsAppMessageDirection::Outbound->value])
                ->selectRaw('COALESCE(SUM(CASE WHEN processing_status = ? THEN 1 ELSE 0 END), 0) AS failed', [WhatsAppMessageStatus::Failed->value])
                ->first();

        $webhookProblems = $sessionNames->isEmpty() ? 0 : WahaWebhookEvent::query()
            ->whereIn('session_name', $sessionNames)
            ->whereIn('status', ['Pending', 'Failed'])
            ->count();

        $issues = collect([
            [
                'label' => 'Session gagal atau terputus',
                'count' => $accounts->whereIn('status', [WhatsAppAccountStatus::Failed, WhatsAppAccountStatus::Disconnected])->count(),
                'description' => 'Jalankan sinkronisasi, restart, atau tampilkan QR.',
                'tone' => 'danger',
            ],
            [
                'label' => 'Percakapan konflik',
                'count' => (int) ($conversationStats?->conflicts ?? 0),
                'description' => 'Nomor cocok dengan lebih dari satu data lead.',
                'tone' => 'warning',
            ],
            [
                'label' => 'Lead belum dicocokkan',
                'count' => (int) ($conversationStats?->unresolved ?? 0),
                'description' => 'Percakapan belum terhubung ke data lead.',
                'tone' => 'info',
            ],
            [
                'label' => 'Pesan atau webhook gagal',
                'count' => (int) ($messageStats?->failed ?? 0) + $webhookProblems,
                'description' => 'Periksa koneksi, queue, dan log integrasi.',
                'tone' => 'danger',
            ],
        ])->filter(fn (array $issue): bool => $issue['count'] > 0)->values();

        $recentWebhooks = $sessionNames->isEmpty() ? collect() : WahaWebhookEvent::query()
            ->whereIn('session_name', $sessionNames)
            ->latest()
            ->limit(6)
            ->get(['event_name', 'session_name', 'status', 'attempts', 'processed_at', 'created_at']);

        $recentActivities = ActivityLog::query()
            ->visibleTo($viewer)
            ->where('subject_type', (new UserWhatsAppAccount)->getMorphClass())
            ->with('user:id,name')
            ->latest('created_at')
            ->limit(6)
            ->get(['activity_log_id', 'user_id', 'event', 'description', 'created_at']);

        return [
            'stats' => [
                ['label' => 'Percakapan', 'value' => (int) ($conversationStats?->total ?? 0), 'description' => (int) ($conversationStats?->unread ?? 0).' belum dibaca', 'icon' => 'heroicon-o-chat-bubble-left-right', 'tone' => 'sky'],
                ['label' => 'Pesan masuk', 'value' => (int) ($messageStats?->inbound ?? 0), 'description' => '30 hari terakhir', 'icon' => 'heroicon-o-arrow-down-left', 'tone' => 'emerald'],
                ['label' => 'Pesan keluar', 'value' => (int) ($messageStats?->outbound ?? 0), 'description' => '30 hari terakhir', 'icon' => 'heroicon-o-arrow-up-right', 'tone' => 'violet'],
                ['label' => 'Total pesan', 'value' => (int) ($messageStats?->total ?? 0), 'description' => '30 hari terakhir', 'icon' => 'heroicon-o-envelope', 'tone' => 'amber'],
            ],
            'issues' => $issues,
            'accounts' => $accounts->take(6),
            'recentWebhooks' => $recentWebhooks,
            'recentActivities' => $recentActivities,
            'inboxUrl' => WhatsAppConversationResource::getUrl(),
            'auditUrl' => AuditAktivitas::getUrl(),
        ];
    }
}
