<?php

namespace App\Filament\Widgets;

use App\Enums\WhatsAppAccountStatus;
use App\Models\UserWhatsAppAccount;
use App\Models\WahaWebhookEvent;
use App\Services\WhatsApp\WahaClient;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WhatsAppHealthOverview extends Widget
{
    protected string $view = 'filament.widgets.whatsapp-health-overview';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $viewer = auth()->user();
        $accounts = UserWhatsAppAccount::query()
            ->visibleTo($viewer)
            ->where('aktif', true)
            ->get(['id', 'session_name', 'status', 'last_seen_at']);
        $sessions = $accounts->pluck('session_name');
        $working = $accounts->where('status', WhatsAppAccountStatus::Working)->count();
        $failed = $accounts->where('status', WhatsAppAccountStatus::Failed)->count();
        $pendingWebhooks = $sessions->isEmpty() ? 0 : WahaWebhookEvent::query()
            ->whereIn('session_name', $sessions)
            ->whereIn('status', ['Pending', 'Failed'])
            ->count();

        [$serviceStatus, $serviceMessage] = $this->serviceHealth();

        return [
            'healthItems' => [
                [
                    'label' => 'Service WAHA',
                    'value' => $serviceStatus === true ? 'Sehat' : ($serviceStatus === false ? 'Tidak terhubung' : 'Belum diuji'),
                    'description' => $serviceMessage,
                    'tone' => $serviceStatus === true ? 'success' : ($serviceStatus === false ? 'danger' : 'neutral'),
                    'icon' => 'heroicon-o-server-stack',
                ],
                [
                    'label' => 'Session WhatsApp',
                    'value' => $working.' dari '.$accounts->count().' terhubung',
                    'description' => $failed > 0 ? $failed.' session perlu ditangani' : 'Tidak ada session gagal',
                    'tone' => $failed > 0 ? 'warning' : ($working > 0 ? 'success' : 'neutral'),
                    'icon' => 'heroicon-o-device-phone-mobile',
                ],
                [
                    'label' => 'Webhook CRM',
                    'value' => $pendingWebhooks === 0 ? 'Normal' : 'Perlu ditinjau',
                    'description' => $pendingWebhooks === 0 ? 'Tidak ada event tertunda atau gagal' : $pendingWebhooks.' event belum selesai',
                    'tone' => $pendingWebhooks === 0 ? 'success' : 'danger',
                    'icon' => 'heroicon-o-arrows-right-left',
                ],
                [
                    'label' => 'Sinkronisasi terakhir',
                    'value' => $accounts->max('last_seen_at')?->diffForHumans() ?? 'Belum pernah',
                    'description' => 'Pembaruan otomatis setiap 30 detik',
                    'tone' => $accounts->isEmpty() ? 'neutral' : 'info',
                    'icon' => 'heroicon-o-arrow-path',
                ],
            ],
        ];
    }

    /** @return array{bool|null, string} */
    private function serviceHealth(): array
    {
        if (app()->environment('testing')) {
            return [null, 'Pemeriksaan service dinonaktifkan saat testing'];
        }

        try {
            $health = Cache::remember('waha-control-center-health', 15, fn (): array => app(WahaClient::class)->health());
            $healthy = strtolower((string) data_get($health, 'status')) === 'ok';

            return [$healthy, $healthy ? 'Endpoint health merespons normal' : 'Respons health tidak normal'];
        } catch (Throwable $exception) {
            report($exception);

            return [false, 'Periksa container, URL, dan API key WAHA'];
        }
    }
}
