<?php

namespace App\Filament\Resources\FollowupRiwayats\Schemas;

use App\Models\Lead;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FollowupRiwayatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Catatan follow-up')->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('lead_id')->label('Lead')->options(Lead::query()->visibleTo(auth()->user())->orderBy('nama')->pluck('nama', 'lead_id'))->searchable()->preload()->required(),
                DateTimePicker::make('tanggal_followup')->label('Waktu follow-up')->default(now())->required()->seconds(false),
                Select::make('metode_followup')->label('Metode')->options(['WhatsApp' => 'WhatsApp', 'Telepon' => 'Telepon', 'Tatap Muka' => 'Tatap muka', 'Email' => 'Email'])->required(),
                Select::make('status_kontak')->label('Status kontak')->options(['Terhubung' => 'Terhubung', 'Tidak Terhubung' => 'Tidak terhubung', 'Dijadwalkan Ulang' => 'Dijadwalkan ulang'])->required(),
                DateTimePicker::make('tanggal_followup_berikutnya')->label('Follow-up berikutnya')->seconds(false)->after('tanggal_followup'),
                Hidden::make('user_id')->default(auth()->id()),
                Textarea::make('catatan')->label('Catatan hasil')->rows(4)->maxLength(1000)->columnSpanFull(),
            ]),
        ]);
    }
}
