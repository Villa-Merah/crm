<?php

namespace App\Filament\Resources\Leads\RelationManagers;

use App\Enums\UserRole;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FollowupsRelationManager extends RelationManager
{
    protected static string $relationship = 'followups';

    protected static ?string $title = 'Riwayat follow-up';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DateTimePicker::make('tanggal_followup')->label('Waktu follow-up')->default(now())->seconds(false)->required(),
            Select::make('metode_followup')->label('Metode')->options(['WhatsApp' => 'WhatsApp', 'Telepon' => 'Telepon', 'Tatap Muka' => 'Tatap muka', 'Email' => 'Email'])->required(),
            Select::make('status_kontak')->label('Status kontak')->options(['Terhubung' => 'Terhubung', 'Tidak Terhubung' => 'Tidak terhubung', 'Dijadwalkan Ulang' => 'Dijadwalkan ulang'])->required(),
            DateTimePicker::make('tanggal_followup_berikutnya')->label('Follow-up berikutnya')->seconds(false)->after('tanggal_followup'),
            Textarea::make('catatan')->label('Catatan hasil')->rows(4)->maxLength(1000)->columnSpanFull(),
            Hidden::make('user_id')->default(auth()->id()),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tanggal_followup')
            ->defaultSort('tanggal_followup', 'desc')
            ->columns([
                TextColumn::make('tanggal_followup')->label('Dilakukan')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('metode_followup')->label('Metode')->badge()->color('info'),
                TextColumn::make('status_kontak')->label('Status kontak')->badge()->color(fn (string $state) => $state === 'Terhubung' ? 'success' : ($state === 'Tidak Terhubung' ? 'danger' : 'warning')),
                TextColumn::make('user.name')->label('Petugas'),
                TextColumn::make('tanggal_followup_berikutnya')->label('Jadwal berikutnya')->dateTime('d M Y, H:i')->placeholder('-'),
                TextColumn::make('catatan')->label('Catatan')->limit(45)->placeholder('-')->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()->label('Catat follow-up')->visible(fn () => auth()->user()->canManageOperationalData())->mutateDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();

                    return $data;
                }),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => auth()->user()->canManageOperationalData()),
                DeleteAction::make()->visible(fn () => auth()->user()->hasRole(UserRole::Superadmin)),
            ])
            ->emptyStateHeading('Belum ada riwayat follow-up')
            ->emptyStateDescription('Catat komunikasi pertama untuk lead ini.');
    }
}
