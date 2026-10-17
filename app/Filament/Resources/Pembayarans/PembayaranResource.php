<?php

namespace App\Filament\Resources\Pembayarans;

use App\Filament\Resources\Pembayarans\Pages\CreatePembayaran;
use App\Filament\Resources\Pembayarans\Pages\EditPembayaran;
use App\Filament\Resources\Pembayarans\Pages\ListPembayarans;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class PembayaranResource extends Resource
{
    protected static ?string $model = Pembayaran::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?string $pluralModelLabel = 'Pembayaran';

    protected static ?string $modelLabel = 'pembayaran';

    protected static ?string $slug = 'pembayaran';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        $viewer = auth()->user();
        $statusOptions = $viewer->canVerifyPayments()
            ? ['Menunggu' => 'Menunggu verifikasi', 'Terverifikasi' => 'Terverifikasi', 'Ditolak' => 'Ditolak']
            : ['Menunggu' => 'Menunggu verifikasi'];

        return $schema->components([
            Section::make('Data pembayaran')->description('Catat transaksi berdasarkan pendaftaran siswa.')->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('pendaftaran_id')->label('Pendaftaran siswa')->default(fn () => request()->integer('pendaftaran') ?: null)->searchable()->getSearchResultsUsing(fn (string $search) => Pendaftaran::query()->visibleTo($viewer)->with(['lead', 'programBimbel'])->where(fn (Builder $query) => $query->whereHas('lead', fn (Builder $leadQuery) => $leadQuery->where('nama', 'like', "%{$search}%"))->orWhereHas('programBimbel', fn (Builder $programQuery) => $programQuery->where('nama_program', 'like', "%{$search}%")))->latest('tanggal_daftar')->limit(50)->get()->mapWithKeys(fn (Pendaftaran $registration) => [$registration->pendaftaran_id => $registration->lead->nama.' — '.$registration->programBimbel->nama_program]))->getOptionLabelUsing(function ($value) use ($viewer) {
                    $registration = Pendaftaran::query()->visibleTo($viewer)->with(['lead', 'programBimbel'])->find($value);

                    return $registration ? $registration->lead->nama.' — '.$registration->programBimbel->nama_program : null;
                })->required(),
                DateTimePicker::make('tanggal_pembayaran')->label('Tanggal pembayaran')->default(now())->seconds(false)->required()->maxDate(now()),
                TextInput::make('nominal')->label('Nominal')->numeric()->prefix('Rp')->minValue(1)->required(),
                Select::make('metode_pembayaran')->label('Metode')->options(['Transfer Bank' => 'Transfer bank', 'Tunai' => 'Tunai', 'QRIS' => 'QRIS', 'Kartu Debit' => 'Kartu debit', 'Lainnya' => 'Lainnya'])->required(),
                Select::make('status_pembayaran')->label('Status')->options($statusOptions)->default('Menunggu')->required()->disabled(! $viewer->canVerifyPayments())->dehydrated(),
                TextInput::make('nomor_referensi')->label('Nomor referensi')->maxLength(100)->placeholder('Opsional'),
                Textarea::make('catatan')->label('Catatan')->rows(4)->maxLength(1500)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('tanggal_pembayaran', 'desc')->columns([
            TextColumn::make('pendaftaran.lead.nama')->label('Siswa')->description(fn (Pembayaran $record) => $record->pendaftaran?->programBimbel?->nama_program)->weight('semibold')->searchable(),
            TextColumn::make('nominal')->label('Nominal')->money('IDR', locale: 'id')->weight('semibold')->sortable(),
            TextColumn::make('metode_pembayaran')->label('Metode')->badge()->color('gray'),
            TextColumn::make('status_pembayaran')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                'Lunas', 'Terverifikasi' => 'success', 'Menunggu' => 'warning', 'Ditolak' => 'danger', default => 'gray'
            }),
            TextColumn::make('nomor_referensi')->label('Referensi')->placeholder('-')->toggleable(),
            TextColumn::make('pendaftaran.cabang.nama')->label('Cabang')->visible(fn () => auth()->user()->hasGlobalBranchAccess()),
            TextColumn::make('tanggal_pembayaran')->label('Tanggal')->dateTime('d M Y, H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status_pembayaran')->label('Status')->options(['Menunggu' => 'Menunggu verifikasi', 'Terverifikasi' => 'Terverifikasi', 'Ditolak' => 'Ditolak']),
            SelectFilter::make('metode_pembayaran')->label('Metode')->options(['Transfer Bank' => 'Transfer bank', 'Tunai' => 'Tunai', 'QRIS' => 'QRIS', 'Kartu Debit' => 'Kartu debit', 'Lainnya' => 'Lainnya']),
        ])->recordActions([
            EditAction::make()->visible(fn (Pembayaran $record) => static::canEdit($record)),
        ])->emptyStateHeading('Belum ada pembayaran')->emptyStateDescription('Catat pembayaran pertama dari pendaftaran siswa.')->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())
            ->with(['pendaftaran.lead', 'pendaftaran.programBimbel', 'pendaftaran.cabang']);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canRecordPayments() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $viewer = auth()->user();
        if (! $viewer?->canRecordPayments()) {
            return false;
        }

        return $viewer->canVerifyPayments() || $record->status_pembayaran === 'Menunggu';
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPembayarans::route('/'),
            'create' => CreatePembayaran::route('/create'),
            'edit' => EditPembayaran::route('/{record}/edit'),
        ];
    }
}
