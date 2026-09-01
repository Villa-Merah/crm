<?php

namespace App\Filament\Resources\Sekolahs;

use App\Enums\SchoolGrade;
use App\Enums\UserRole;
use App\Filament\Resources\Sekolahs\Pages\CreateSekolah;
use App\Filament\Resources\Sekolahs\Pages\EditSekolah;
use App\Filament\Resources\Sekolahs\Pages\ListSekolahs;
use App\Filament\Support\SchoolApiActions;
use App\Models\Sekolah;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class SekolahResource extends Resource
{
    protected static ?string $model = Sekolah::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Sekolah';

    protected static ?string $pluralModelLabel = 'Sekolah';

    protected static ?string $modelLabel = 'sekolah';

    protected static ?string $slug = 'sekolah';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pencarian data resmi')
                ->description('Cari berdasarkan nama atau NPSN. Data yang dipilih akan mengisi form dan tetap disimpan di database CRM.')
                ->schema([
                    Actions::make([SchoolApiActions::fillSchoolForm()])->fullWidth(),
                ])
                ->visible(fn (): bool => (bool) config('services.school_api.enabled')),
            Section::make('Identitas sekolah')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('npsn')->label('NPSN')->helperText('Opsional. Gunakan 8 karakter huruf/angka sesuai data resmi.')->length(8)->regex('/^[A-Za-z0-9]{8}$/')->unique(ignoreRecord: true),
                TextInput::make('nama_sekolah')->label('Nama sekolah')->required()->maxLength(200),
                Select::make('status_sekolah')->label('Status sekolah')->options(['Negeri' => 'Negeri', 'Swasta' => 'Swasta', 'Lainnya' => 'Lainnya'])->default('Lainnya'),
                TextInput::make('bentuk_pendidikan')->label('Bentuk pendidikan')->maxLength(50)->placeholder('Contoh: SMA, SMP, PKBM'),
                TextInput::make('akreditasi')->label('Akreditasi')->maxLength(20),
                Select::make('kluster_sekolah')
                    ->label('Kluster sekolah')
                    ->options(SchoolGrade::options())
                    ->helperText(fn (?string $state): string => SchoolGrade::tryFrom($state)?->description() ?? 'Pilih Grade A sampai Grade E.')
                    ->live()
                    ->placeholder('Belum ditentukan'),
                Toggle::make('aktif')->label('Sekolah aktif')->default(true),
                Hidden::make('sumber_data')->default('manual'),
                Hidden::make('api_last_synced_at'),
            ]),
            Section::make('Lokasi')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('kecamatan')->label('Kecamatan')->maxLength(100),
                TextInput::make('kota')->label('Kota/Kabupaten')->maxLength(100),
                TextInput::make('provinsi')->label('Provinsi')->maxLength(100),
                TextInput::make('kode_wilayah')->label('Kode wilayah')->maxLength(20),
                Textarea::make('alamat')->label('Alamat')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('nama_sekolah')->columns([
            TextColumn::make('npsn')->label('NPSN')->placeholder('-')->searchable()->toggleable(),
            TextColumn::make('nama_sekolah')->label('Sekolah')->weight('semibold')->searchable()->sortable(),
            TextColumn::make('status_sekolah')->label('Status')->badge()->color(fn (string $state) => $state === 'Negeri' ? 'info' : ($state === 'Swasta' ? 'warning' : 'gray')),
            TextColumn::make('bentuk_pendidikan')->label('Bentuk')->placeholder('-')->toggleable(),
            TextColumn::make('akreditasi')->label('Akreditasi')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('kota')->label('Kota/Kabupaten')->searchable()->sortable(),
            TextColumn::make('kluster_sekolah')->label('Kluster')->badge()->color(fn (?string $state): string => SchoolGrade::tryFrom($state)?->color() ?? 'gray')->placeholder('-'),
            TextColumn::make('leads_count')->counts('leads')->label('Leads')->badge()->color('primary'),
            IconColumn::make('aktif')->label('Aktif')->boolean(),
            TextColumn::make('sumber_data')->label('Sumber')->badge()->formatStateUsing(fn (?string $state): string => $state === 'api-sekolah' ? 'API' : 'Manual')->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status_sekolah')->label('Status')->options(['Negeri' => 'Negeri', 'Swasta' => 'Swasta', 'Lainnya' => 'Lainnya']),
            SelectFilter::make('kluster_sekolah')->label('Kluster')->options(SchoolGrade::options()),
            SelectFilter::make('kota')->label('Kota/Kabupaten')->options(fn (): array => Sekolah::query()->whereNotNull('kota')->where('kota', '!=', '')->distinct()->orderBy('kota')->pluck('kota', 'kota')->all())->searchable(),
            SelectFilter::make('provinsi')->label('Provinsi')->options(fn (): array => Sekolah::query()->whereNotNull('provinsi')->where('provinsi', '!=', '')->distinct()->orderBy('provinsi')->pluck('provinsi', 'provinsi')->all())->searchable(),
            TernaryFilter::make('aktif')->label('Status aktif'),
        ])->recordActions([
            EditAction::make()->visible(fn () => auth()->user()->hasRole(UserRole::Superadmin) || auth()->user()->hasRole(UserRole::AdminCabang)),
            DeleteAction::make()
                ->label('Hapus')
                ->visible(fn (Sekolah $record): bool => static::canDelete($record))
                ->requiresConfirmation(),
        ])->toolbarActions([
            BulkActionGroup::make([
                BulkAction::make('ubahTerpilih')
                    ->label('Ubah data terpilih')
                    ->icon('heroicon-o-pencil-square')
                    ->visible(fn (): bool => static::canCreate())
                    ->form([
                        Select::make('status_sekolah')->label('Ubah status sekolah')->options(['Negeri' => 'Negeri', 'Swasta' => 'Swasta', 'Lainnya' => 'Lainnya'])->placeholder('Jangan ubah'),
                        Select::make('kluster_sekolah')->label('Ubah kluster')->options(SchoolGrade::options())->placeholder('Jangan ubah'),
                        Select::make('aktif')->label('Ubah status aktif')->options(['1' => 'Aktif', '0' => 'Tidak aktif'])->placeholder('Jangan ubah'),
                        TextInput::make('kota')->label('Ubah Kota/Kabupaten')->placeholder('Kosongkan jika tidak diubah')->maxLength(100),
                        TextInput::make('provinsi')->label('Ubah Provinsi')->placeholder('Kosongkan jika tidak diubah')->maxLength(100),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $updates = collect($data)
                            ->filter(fn ($value): bool => filled($value))
                            ->all();

                        if ($updates !== []) {
                            Sekolah::query()->whereKey($records->modelKeys())->update($updates);
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make()
                    ->label('Hapus data terpilih')
                    ->visible(fn (): bool => static::canCreate()),
            ]),
        ])->emptyStateHeading('Belum ada sekolah')->emptyStateDescription('Tambahkan sekolah asal calon siswa.');
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) || auth()->user()?->hasRole(UserRole::AdminCabang);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canCreate() && ! $record->leads()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSekolahs::route('/'),
            'create' => CreateSekolah::route('/create'),
            'edit' => EditSekolah::route('/{record}/edit'),
        ];
    }
}
