<?php

namespace App\Filament\Resources\FollowupRiwayats\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FollowupRiwayatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_followup', 'desc')
            ->columns([
                TextColumn::make('lead.nama')->label('Lead')->description(fn ($record) => $record->lead?->no_wa)->searchable(),
                TextColumn::make('metode_followup')->label('Metode')->badge()->color('info'),
                TextColumn::make('status_kontak')->label('Status kontak')->badge()->color(fn (string $state) => $state === 'Terhubung' ? 'success' : ($state === 'Tidak Terhubung' ? 'danger' : 'warning')),
                TextColumn::make('user.name')->label('Petugas')->searchable(),
                TextColumn::make('tanggal_followup')->label('Dilakukan')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('tanggal_followup_berikutnya')->label('Jadwal berikutnya')->dateTime('d M Y, H:i')->placeholder('Tidak dijadwalkan')->color(fn ($state) => $state && $state->isPast() ? 'danger' : null)->sortable(),
                TextColumn::make('lead.cabang.nama')->label('Cabang')->visible(fn () => auth()->user()->hasGlobalBranchAccess()),
            ])
            ->filters([
                SelectFilter::make('metode_followup')->label('Metode')->options(['WhatsApp' => 'WhatsApp', 'Telepon' => 'Telepon', 'Tatap Muka' => 'Tatap muka', 'Email' => 'Email']),
                Filter::make('terlambat')->label('Terlambat ditindaklanjuti')->query(fn (Builder $query) => $query->whereNotNull('tanggal_followup_berikutnya')->where('tanggal_followup_berikutnya', '<', now())),
            ])
            ->recordActions([EditAction::make()->visible(fn () => auth()->user()->canManageOperationalData())])
            ->emptyStateHeading('Belum ada aktivitas follow-up')
            ->emptyStateDescription('Catat interaksi pertama dengan calon siswa.')
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right');
    }
}
