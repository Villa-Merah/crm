<?php

namespace App\Filament\Resources\Sekolahs;

use App\Enums\UserRole;
use App\Enums\SchoolGrade;
use App\Filament\Resources\Sekolahs\Pages\CreateSekolah;
use App\Filament\Resources\Sekolahs\Pages\EditSekolah;
use App\Filament\Resources\Sekolahs\Pages\ListSekolahs;
use App\Models\Sekolah;
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
            Section::make('Identitas sekolah')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('npsn')->label('NPSN')->helperText('Opsional. Jika diisi, gunakan 8 digit NPSN.')->length(8)->regex('/^\d{8}$/')->unique(ignoreRecord: true),
                TextInput::make('nama_sekolah')->label('Nama sekolah')->required()->maxLength(200),
                Select::make('status_sekolah')->label('Status sekolah')->options(['Negeri' => 'Negeri', 'Swasta' => 'Swasta', 'Lainnya' => 'Lainnya'])->default('Lainnya'),
                Select::make('kluster_sekolah')
                    ->label('Kluster sekolah')
                    ->options(SchoolGrade::options())
                    ->helperText(fn (?string $state): string => SchoolGrade::tryFrom($state)?->description() ?? 'Pilih Grade A sampai Grade E.')
                    ->live()
                    ->placeholder('Belum ditentukan'),
                Toggle::make('aktif')->label('Sekolah aktif')->default(true),
            ]),
            Section::make('Lokasi')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('kecamatan')->label('Kecamatan')->maxLength(100),
                TextInput::make('kota')->label('Kota/Kabupaten')->maxLength(100),
                TextInput::make('provinsi')->label('Provinsi')->maxLength(100),
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
            TextColumn::make('kota')->label('Kota/Kabupaten')->searchable()->sortable(),
            TextColumn::make('kluster_sekolah')->label('Kluster')->badge()->color(fn (?string $state): string => SchoolGrade::tryFrom($state)?->color() ?? 'gray')->placeholder('-'),
            TextColumn::make('leads_count')->counts('leads')->label('Leads')->badge()->color('primary'),
            IconColumn::make('aktif')->label('Aktif')->boolean(),
        ])->filters([
            SelectFilter::make('status_sekolah')->label('Status')->options(['Negeri' => 'Negeri', 'Swasta' => 'Swasta', 'Lainnya' => 'Lainnya']),
            SelectFilter::make('kluster_sekolah')->label('Kluster')->options(SchoolGrade::options()),
            TernaryFilter::make('aktif')->label('Status aktif'),
        ])->recordActions([
            EditAction::make()->visible(fn () => auth()->user()->hasRole(UserRole::Superadmin) || auth()->user()->hasRole(UserRole::AdminCabang)),
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
        return false;
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
