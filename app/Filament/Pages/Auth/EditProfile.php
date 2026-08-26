<?php

namespace App\Filament\Pages\Auth;

use App\Enums\WhatsAppAccountStatus;
use App\Filament\Resources\WhatsAppAccounts\WhatsAppAccountResource;
use App\Models\UserWhatsAppAccount;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;

class EditProfile extends BaseEditProfile
{
    public static function getLabel(): string
    {
        return 'Profil Saya';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Profil Saya';
    }

    public function getSubheading(): ?string
    {
        return 'Kelola identitas dan keamanan akun Anda. Role, cabang, dan koneksi WhatsApp dikelola sesuai kewenangan administrator.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data pribadi')
                ->description('Informasi yang digunakan untuk mengenali akun Anda di SIVMI CRM.')
                ->icon('heroicon-o-identification')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema([
                    $this->getNameFormComponent()
                        ->label('Nama lengkap')
                        ->autofocus(),
                    $this->getEmailFormComponent()
                        ->label('Alamat email'),
                    TextEntry::make('profile_account_status')
                        ->label('Status akun')
                        ->state(fn (): string => $this->getUser()->aktif ? 'Aktif' : 'Nonaktif')
                        ->badge()
                        ->color(fn (): string => $this->getUser()->aktif ? 'success' : 'danger')
                        ->icon(fn (): string => $this->getUser()->aktif ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                    TextEntry::make('profile_registered_at')
                        ->label('Terdaftar sejak')
                        ->state(fn () => $this->getUser()->created_at)
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Tidak tersedia')
                        ->icon('heroicon-o-calendar-days'),
                ]),

            Section::make('Akses dan penempatan')
                ->description('Informasi ini hanya dapat diubah oleh administrator yang berwenang.')
                ->icon('heroicon-o-shield-check')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema([
                    TextEntry::make('profile_role')
                        ->label('Role')
                        ->state(fn (): string => $this->getUser()->role->label())
                        ->badge()
                        ->color('primary')
                        ->icon('heroicon-o-key'),
                    TextEntry::make('profile_branch')
                        ->label('Cabang')
                        ->state(fn (): string => $this->getUser()->cabang?->nama ?? 'Semua cabang')
                        ->weight(FontWeight::SemiBold)
                        ->icon('heroicon-o-building-office-2'),
                ]),

            Section::make('WhatsApp operasional')
                ->description('Ringkasan nomor dan session WAHA yang ditautkan ke akun Anda.')
                ->icon('heroicon-o-device-phone-mobile')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                    'xl' => 4,
                ])
                ->schema([
                    TextEntry::make('profile_whatsapp_phone')
                        ->label('Nomor WhatsApp')
                        ->state(fn (): ?string => $this->whatsAppAccount()?->phone_e164)
                        ->formatStateUsing(fn (?string $state): string => filled($state) ? '+'.ltrim($state, '+') : '-')
                        ->copyable(fn (): bool => filled($this->whatsAppAccount()?->phone_e164))
                        ->placeholder('Belum ditautkan')
                        ->icon('heroicon-o-phone'),
                    TextEntry::make('profile_whatsapp_session')
                        ->label('Session WAHA')
                        ->state(fn (): ?string => $this->whatsAppAccount()?->session_name)
                        ->placeholder('Belum disiapkan')
                        ->icon('heroicon-o-cloud'),
                    TextEntry::make('profile_whatsapp_status')
                        ->label('Status koneksi')
                        ->state(fn (): string => $this->whatsAppStatusLabel())
                        ->badge()
                        ->color(fn (): string => $this->whatsAppStatusColor()),
                    TextEntry::make('profile_whatsapp_last_seen')
                        ->label('Sinkronisasi terakhir')
                        ->state(fn () => $this->whatsAppAccount()?->last_seen_at)
                        ->dateTime('d M Y, H:i')
                        ->placeholder('Belum pernah')
                        ->icon('heroicon-o-arrow-path'),
                    TextEntry::make('profile_whatsapp_management')
                        ->label('Pengelolaan koneksi')
                        ->state('Buka Koneksi WhatsApp')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('primary')
                        ->url(fn (): string => WhatsAppAccountResource::getUrl())
                        ->visible(fn (): bool => WhatsAppAccountResource::canViewAny())
                        ->columnSpanFull(),
                ]),

            Section::make('Keamanan akun')
                ->description('Kosongkan bagian ini jika Anda tidak ingin mengganti kata sandi.')
                ->icon('heroicon-o-lock-closed')
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema([
                    $this->getPasswordFormComponent()
                        ->label('Kata sandi baru')
                        ->helperText('Gunakan kata sandi yang kuat dan tidak digunakan pada layanan lain.'),
                    $this->getPasswordConfirmationFormComponent()
                        ->label('Konfirmasi kata sandi baru'),
                    $this->getCurrentPasswordFormComponent()
                        ->label('Kata sandi saat ini')
                        ->belowContent('Diperlukan saat mengganti alamat email atau kata sandi.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Profil berhasil diperbarui';
    }

    private function whatsAppAccount(): ?UserWhatsAppAccount
    {
        return $this->getUser()->loadMissing('whatsappAccount')->whatsappAccount;
    }

    private function whatsAppStatusLabel(): string
    {
        return match ($this->whatsAppAccount()?->status) {
            WhatsAppAccountStatus::Working => 'Terhubung',
            WhatsAppAccountStatus::Connecting => 'Menghubungkan',
            WhatsAppAccountStatus::Failed => 'Bermasalah',
            WhatsAppAccountStatus::Disconnected => 'Terputus',
            default => 'Belum ditautkan',
        };
    }

    private function whatsAppStatusColor(): string
    {
        return match ($this->whatsAppAccount()?->status) {
            WhatsAppAccountStatus::Working => 'success',
            WhatsAppAccountStatus::Connecting => 'warning',
            WhatsAppAccountStatus::Failed => 'danger',
            default => 'gray',
        };
    }
}
