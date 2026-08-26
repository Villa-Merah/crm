<?php

namespace App\Filament\Resources\ProgramBimbels;

use App\Enums\UserRole;
use App\Filament\Resources\ProgramBimbels\Pages\CreateProgramBimbel;
use App\Filament\Resources\ProgramBimbels\Pages\EditProgramBimbel;
use App\Filament\Resources\ProgramBimbels\Pages\ListProgramBimbels;
use App\Models\CrmSetting;
use App\Models\ProgramBimbel;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProgramBimbelResource extends Resource
{
    protected static ?string $model = ProgramBimbel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Program Bimbel';

    protected static ?string $pluralModelLabel = 'Program Bimbel';

    protected static ?string $modelLabel = 'program bimbel';

    protected static ?string $slug = 'program-bimbel';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Klasifikasi program')->description('Nama program dibuat otomatis dari Kategori + Kelas program + Metode belajar. Contoh: SR GOLD ONLINE.')->columns(['default' => 1, 'md' => 2, 'xl' => 3])->schema([
                Select::make('kategori_program')->label('Kategori')->options(static::settingOptions('program_categories'))->required(),
                Select::make('program_kelas')->label('Kelas program')->options(static::settingOptions('program_classes'))->required(),
                Select::make('tipe_program')->label('Tipe program')->options(static::settingOptions('program_types'))->required(),
                Select::make('metode_belajar')->label('Metode belajar')->options(static::settingOptions('program_learning_methods'))->required(),
                Select::make('jenjang_program')->label('Jenjang')->options(static::settingOptions('program_levels'))->required(),
                Select::make('tingkat_kelas')->label('Tingkat kelas')->options(static::settingOptions('program_class_levels'))->nullable(),
            ]),
            Section::make('Biaya dan layanan')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('biaya_pendaftaran')->label('Biaya pendaftaran')->prefix('Rp')->numeric()->minValue(0)->required(),
                TextInput::make('biaya_pendidikan')->label('Biaya pendidikan')->prefix('Rp')->numeric()->minValue(0)->required(),
                TextInput::make('diskon_pendidikan')->label('Diskon pendidikan')->suffix('%')->numeric()->minValue(0)->maxValue(100)->default(0)->required(),
                TextInput::make('jumlah_pertemuan')->label('Jumlah pertemuan')->numeric()->integer()->minValue(0)->default(0)->required(),
                Textarea::make('fasilitas_program')->label('Fasilitas program')->rows(4)->maxLength(3000)->columnSpanFull(),
                Toggle::make('aktif')->label('Program aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('nama_program')->columns([
            TextColumn::make('nama_program')->label('Program')->weight('semibold')->searchable()->wrap(),
            TextColumn::make('jenjang_program')->label('Jenjang')->badge()->color('info')->sortable(),
            TextColumn::make('metode_belajar')->label('Metode')->badge()->color(fn (string $state) => match ($state) {
                'Online' => 'purple', 'Hybrid' => 'warning', default => 'primary'
            }),
            TextColumn::make('biaya_setelah_diskon')->label('Biaya setelah diskon')->money('IDR', locale: 'id'),
            TextColumn::make('diskon_pendidikan')->label('Diskon')->suffix('%')->toggleable(),
            TextColumn::make('pendaftaran_count')->label('Pendaftaran')->badge()->color('gray'),
            IconColumn::make('aktif')->label('Aktif')->boolean(),
        ])->filters([
            SelectFilter::make('jenjang_program')->label('Jenjang')->options(static::settingOptions('program_levels')),
            SelectFilter::make('metode_belajar')->label('Metode')->options(static::settingOptions('program_learning_methods')),
            TernaryFilter::make('aktif')->label('Status aktif'),
        ])->recordActions([
            EditAction::make()->visible(fn () => auth()->user()->hasRole(UserRole::Superadmin)),
        ])->emptyStateHeading('Belum ada program bimbel')->emptyStateDescription('Tambahkan katalog program SIVMI untuk digunakan saat pendaftaran.');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('pendaftaran');
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListProgramBimbels::route('/'), 'create' => CreateProgramBimbel::route('/create'), 'edit' => EditProgramBimbel::route('/{record}/edit')];
    }

    private static function settingOptions(string $key): array
    {
        $values = CrmSetting::getArray($key);

        return array_combine($values, $values) ?: [];
    }
}
