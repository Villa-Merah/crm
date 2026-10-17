<?php

namespace App\Filament\Resources\Pendaftarans;

use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Filament\Resources\Pendaftarans\Pages\CreatePendaftaran;
use App\Filament\Resources\Pendaftarans\Pages\EditPendaftaran;
use App\Filament\Resources\Pendaftarans\Pages\ListPendaftarans;
use App\Models\Lead;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PendaftaranResource extends Resource
{
    protected static ?string $model = Pendaftaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Pendaftaran';

    protected static ?string $pluralModelLabel = 'Pendaftaran';

    protected static ?string $modelLabel = 'pendaftaran';

    protected static ?string $slug = 'pendaftaran';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        $viewer = auth()->user();

        return $schema->components([
            Section::make('Data pendaftaran')->description('Hubungkan lead yang sudah siap mendaftar dengan program bimbel.')->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('lead_id')->label('Calon siswa')->searchable()->getSearchResultsUsing(fn (string $search) => Lead::query()->visibleTo($viewer)->whereDoesntHave('pendaftaran')->where('nama', 'like', "%{$search}%")->orderBy('nama')->limit(50)->pluck('nama', 'lead_id'))->getOptionLabelUsing(fn ($value) => Lead::query()->visibleTo($viewer)->whereKey($value)->value('nama'))->required()->unique(ignoreRecord: true)->disabled(fn (string $operation): bool => $operation === 'edit')->dehydrated(),
                Select::make('program_bimbel_id')->label('Program bimbel')->options(ProgramBimbel::query()->where('aktif', true)->orderBy('nama_program')->pluck('nama_program', 'program_bimbel_id'))->searchable()->preload()->required(),
                DatePicker::make('tanggal_daftar')->label('Tanggal daftar')->default(today())->required()->maxDate(today()),
                TextInput::make('kelas_angkatan')->label('Kelas/angkatan program')->maxLength(100)->placeholder('Contoh: Angkatan 2026'),
                Select::make('status_pendaftaran')->label('Status')->options(['Menunggu' => 'Menunggu', 'Aktif' => 'Aktif', 'Selesai' => 'Selesai', 'Dibatalkan' => 'Dibatalkan'])->default('Aktif')->required(),
                Hidden::make('cabang_id')->default($viewer->cabang_id),
                Textarea::make('catatan')->label('Catatan')->rows(4)->maxLength(1500)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDoesntHave(
                'payments',
                fn (Builder $paymentQuery): Builder => $paymentQuery->whereIn('status_pembayaran', ['Terverifikasi', 'Lunas']),
            ))
            ->defaultSort('tanggal_daftar', 'desc')->columns([
                TextColumn::make('lead.nama')->label('Siswa')->description(fn (Pendaftaran $record) => $record->lead?->no_wa)->weight('semibold')->searchable(),
                TextColumn::make('programBimbel.nama_program')->label('Program')->wrap()->searchable(),
                TextColumn::make('kelas_angkatan')->label('Kelas/angkatan program')->placeholder('-')->toggleable(),
                TextColumn::make('status_pendaftaran')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                    'Aktif' => 'success', 'Menunggu' => 'warning', 'Dibatalkan' => 'danger', default => 'gray'
                }),
                TextColumn::make('pembayaran_terverifikasi_sum')->label('Terbayar')->money('IDR', locale: 'id')->placeholder('Rp 0')->sortable(),
                TextColumn::make('cabang.nama')->label('Cabang')->visible(fn () => auth()->user()->hasGlobalBranchAccess()),
                TextColumn::make('tanggal_daftar')->label('Tanggal daftar')->date('d M Y')->sortable(),
            ])->filters([
                SelectFilter::make('status_pendaftaran')->label('Status')->options(['Menunggu' => 'Menunggu', 'Aktif' => 'Aktif', 'Selesai' => 'Selesai', 'Dibatalkan' => 'Dibatalkan']),
                SelectFilter::make('program_bimbel_id')->label('Program')->options(ProgramBimbel::where('aktif', true)->orderBy('nama_program')->pluck('nama_program', 'program_bimbel_id'))->searchable(),
            ])->recordActions([
                Action::make('pembayaran')->label('Catat pembayaran')->icon('heroicon-o-banknotes')->color('success')->url(fn (Pendaftaran $record) => PembayaranResource::getUrl('create', ['pendaftaran' => $record->pendaftaran_id]))->visible(fn () => auth()->user()->canRecordPayments()),
                EditAction::make()->visible(fn () => auth()->user()->canManageRegistrations()),
            ])->emptyStateHeading('Tidak ada pendaftaran yang perlu diproses')->emptyStateDescription('Pendaftaran dengan pembayaran terverifikasi tidak ditampilkan pada daftar ini.')->emptyStateIcon('heroicon-o-document-check');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())
            ->with(['lead', 'programBimbel', 'cabang'])
            ->withSum(['payments as pembayaran_terverifikasi_sum' => fn (Builder $query) => $query->whereIn('status_pembayaran', ['Lunas', 'Terverifikasi'])], 'nominal');
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageRegistrations() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canManageRegistrations() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPendaftarans::route('/'),
            'create' => CreatePendaftaran::route('/create'),
            'edit' => EditPendaftaran::route('/{record}/edit'),
        ];
    }
}
