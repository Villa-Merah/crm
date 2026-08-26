<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LeadInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil calon siswa')->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('nama')->label('Nama lengkap')->weight('bold'),
                TextEntry::make('no_wa')->label('Nomor WhatsApp')->copyable()->icon('heroicon-o-phone'),
                TextEntry::make('sekolah.nama_sekolah')->label('Sekolah')->placeholder('-'),
                TextEntry::make('minatProgram.nama_program')->label('Program yang diminati')->placeholder('-'),
            ]),
            Section::make('Status penanganan')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('sumber_lead')->label('Sumber leads')->placeholder('-')->badge()->color(fn (?string $state): string => LeadSource::color($state)),
                TextEntry::make('status_lead')->label('Status')->badge()->color(fn (string $state): string => LeadStatus::color($state)),
                TextEntry::make('cabang.nama')->label('Cabang'),
                TextEntry::make('diserahkanKe.name')->label('PIC')->placeholder('-'),
                TextEntry::make('tanggal_masuk')->label('Tanggal masuk')->date('d F Y'),
                TextEntry::make('diinputOleh.name')->label('Diinput oleh'),
                TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y, H:i'),
                TextEntry::make('kanal_masuk')->label('Kanal masuk')->badge(),
                TextEntry::make('whatsapp_last_seen_at')->label('Chat WhatsApp terakhir')->dateTime('d M Y, H:i')->placeholder('Belum ada'),
            ]),
        ]);
    }
}
