<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Enums\LeadStatus;
use App\Models\Cabang;
use App\Models\CrmSetting;
use App\Models\Lead;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\SumberLead;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('tanggal_masuk', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('nama')->label('Calon siswa')->description(fn ($record) => $record->no_wa)->searchable(['nama', 'no_wa'])->weight('semibold'),
                TextColumn::make('kota_asal')->label('Kota asal')->placeholder('Belum diisi')->searchable()->sortable()->toggleable(),
                TextColumn::make('kelas_sekolah')->label('Kelas sekolah')->placeholder('Belum diisi')->searchable()->sortable()->toggleable(),
                TextColumn::make('sekolah.nama_sekolah')->label('Sekolah')->placeholder('Belum diisi')->searchable()->toggleable(),
                TextColumn::make('minatProgram.nama_program')->label('Minat program')->placeholder('Belum dipilih')->wrap()->toggleable(),
                TextColumn::make('sumberLeadMaster.nama')->label('Sumber')->placeholder('Belum diisi')->badge()->color(fn ($record): string => $record->sumberLeadMaster?->warna ?? 'gray')->toggleable(),
                TextColumn::make('status_lead')->label('Status')->badge()->color(fn (string $state): string => LeadStatus::color($state)),
                TextColumn::make('diserahkanKe.name')->label('PIC')->placeholder('Belum ditugaskan')->searchable(),
                TextColumn::make('cabang.nama')->label('Cabang')->visible(fn () => auth()->user()->hasGlobalBranchAccess())->toggleable(),
                TextColumn::make('followups_count')->label('Follow-up')->badge()->color('gray')->sortable(),
                TextColumn::make('whatsapp_conversations_count')->label('WhatsApp')->badge()->color(fn (int $state) => $state > 0 ? 'success' : 'gray')->icon('heroicon-o-chat-bubble-left-right')->sortable(),
                TextColumn::make('tanggal_masuk')->label('Tanggal masuk')->date('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status_lead')->label('Status')->options(LeadStatus::options()),
                SelectFilter::make('sumber_lead_id')->label('Sumber leads')->options(fn (): array => SumberLead::options()),
                SelectFilter::make('kota_asal')
                    ->label('Kota asal')
                    ->options(fn (): array => Lead::query()
                        ->visibleTo(auth()->user())
                        ->whereNotNull('kota_asal')
                        ->where('kota_asal', '<>', '')
                        ->distinct()
                        ->orderBy('kota_asal')
                        ->pluck('kota_asal', 'kota_asal')
                        ->all())
                    ->searchable(),
                SelectFilter::make('sekolah_id')->label('Sekolah')->options(fn (): array => Sekolah::query()->orderBy('nama_sekolah')->pluck('nama_sekolah', 'sekolah_id')->all())->searchable()->preload(),
                SelectFilter::make('kelas_sekolah')->label('Kelas sekolah')->options(fn (): array => collect(CrmSetting::getArray('school_class_levels'))->mapWithKeys(fn (string $class): array => [$class => $class])->all()),
                SelectFilter::make('minat_program_id')->label('Program yang diminati')->options(fn (): array => ProgramBimbel::query()->orderBy('nama_program')->pluck('nama_program', 'program_bimbel_id')->all())->searchable()->preload(),
                SelectFilter::make('cabang_id')->label('Cabang')->options(Cabang::where('aktif', true)->orderBy('nama')->pluck('nama', 'cabang_id'))->visible(fn () => auth()->user()->hasGlobalBranchAccess()),
                SelectFilter::make('diserahkan_ke_user_id')->label('PIC')->options(User::where('aktif', true)->when(! auth()->user()->hasGlobalBranchAccess(), fn ($q) => $q->where('cabang_id', auth()->user()->cabang_id))->orderBy('name')->pluck('name', 'id'))->searchable(),
                TernaryFilter::make('has_whatsapp')->label('Masuk melalui WhatsApp')->queries(
                    true: fn ($query) => $query->whereHas('whatsappConversations'),
                    false: fn ($query) => $query->whereDoesntHave('whatsappConversations'),
                ),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()->visible(fn () => auth()->user()->canManageOperationalData())])
            ->emptyStateHeading('Belum ada lead')
            ->emptyStateDescription('Tambahkan calon siswa pertama untuk mulai melakukan follow-up.')
            ->emptyStateIcon('heroicon-o-user-plus');
    }
}
