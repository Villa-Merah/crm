<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\CrmSetting;
use Filament\Pages\Page;

class Bantuan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Bantuan';

    protected static string|\UnitEnum|null $navigationGroup = 'BANTUAN & DUKUNGAN';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Bantuan';

    protected string $view = 'filament.pages.bantuan';

    public function getRoleGuideProperty(): array
    {
        return match (auth()->user()->role) {
            UserRole::Superadmin => [
                'label' => 'Superadmin',
                'summary' => 'Memantau seluruh cabang serta mengelola pengguna, master data, konfigurasi, integrasi, audit, dan pemeliharaan sistem.',
                'icon_classes' => 'bg-violet-50 text-violet-600',
            ],
            UserRole::AdminCabang => [
                'label' => 'Admin Cabang',
                'summary' => 'Memantau kinerja dan mengelola operasional cabang. Data pendaftaran dan pembayaran tersedia dalam mode lihat.',
                'icon_classes' => 'bg-sky-50 text-sky-600',
            ],
            UserRole::Staff => [
                'label' => 'Staff',
                'summary' => 'Melihat dashboard cabang dan mengelola lead, follow-up, percakapan WhatsApp, serta proses siswa yang menjadi tanggung jawab Anda.',
                'icon_classes' => 'bg-emerald-50 text-emerald-600',
            ],
            UserRole::Keuangan => [
                'label' => 'Keuangan',
                'summary' => 'Mencatat dan memverifikasi pembayaran serta memantau data pendaftaran yang diperlukan untuk proses keuangan.',
                'icon_classes' => 'bg-amber-50 text-amber-600',
            ],
            UserRole::Direksi => [
                'label' => 'Direksi',
                'summary' => 'Melihat dashboard, KPI, laporan, dan ringkasan lintas cabang tanpa mengubah data operasional.',
                'icon_classes' => 'bg-indigo-50 text-indigo-600',
            ],
        };
    }

    public function getQuickLinksProperty(): array
    {
        $user = auth()->user();
        $links = [
            ['label' => 'Dashboard', 'description' => 'Lihat ringkasan kinerja', 'icon' => 'heroicon-o-squares-2x2', 'url' => url('/admin/dashboard')],
        ];

        if ($user->canViewSalesData()) {
            $links[] = ['label' => 'Leads', 'description' => 'Kelola calon siswa', 'icon' => 'heroicon-o-user-group', 'url' => url('/admin/leads')];
            $links[] = ['label' => 'Follow-up', 'description' => 'Catat tindak lanjut', 'icon' => 'heroicon-o-chat-bubble-left-right', 'url' => url('/admin/follow-up')];
        }

        if (in_array($user->role, [UserRole::Superadmin, UserRole::AdminCabang, UserRole::Staff], true)) {
            $links[] = ['label' => 'Inbox WhatsApp', 'description' => 'Tangani percakapan masuk', 'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis', 'url' => url('/admin/inbox-whatsapp')];
        }

        if (in_array($user->role, [UserRole::Superadmin, UserRole::AdminCabang, UserRole::Staff, UserRole::Keuangan], true)) {
            $links[] = ['label' => 'Pendaftaran', 'description' => 'Lihat data siswa', 'icon' => 'heroicon-o-document-check', 'url' => url('/admin/pendaftaran')];
            $links[] = ['label' => 'Pembayaran', 'description' => 'Lihat status pembayaran', 'icon' => 'heroicon-o-banknotes', 'url' => url('/admin/pembayaran')];
        }

        if ($user->role !== UserRole::Keuangan) {
            $links[] = ['label' => 'Laporan', 'description' => 'Analisis capaian', 'icon' => 'heroicon-o-document-chart-bar', 'url' => url('/admin/laporan')];
        }

        return $links;
    }

    public function getSupportProperty(): array
    {
        return [
            'email' => CrmSetting::getValue('support_email'),
            'phone' => CrmSetting::getValue('support_phone'),
        ];
    }
}
