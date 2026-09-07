<?php

namespace App\Filament\Resources\Cabangs;

use App\Enums\UserRole;
use App\Filament\Resources\Cabangs\Pages\CreateCabang;
use App\Filament\Resources\Cabangs\Pages\EditCabang;
use App\Filament\Resources\Cabangs\Pages\ListCabangs;
use App\Models\Cabang;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CabangResource extends Resource
{
    protected static ?string $model = Cabang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Cabang';

    protected static ?string $pluralModelLabel = 'Cabang';

    protected static ?string $modelLabel = 'cabang';

    protected static ?string $slug = 'cabang';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi cabang')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('kode_cabang')->label('Kode cabang')->required()->maxLength(20)->unique(ignoreRecord: true),
                TextInput::make('nama')->label('Nama cabang')->required()->maxLength(150),
                TextInput::make('kota')->label('Kota')->maxLength(100),
                TextInput::make('no_telepon')->label('Nomor telepon')->tel()->maxLength(20),
                TextInput::make('email')->label('Email cabang')->email()->maxLength(255),
                Toggle::make('aktif')->label('Cabang aktif')->default(true),
                Textarea::make('alamat')->label('Alamat')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('nama')->columns([
            TextColumn::make('kode_cabang')->label('Kode')->badge()->color('info')->searchable()->sortable(),
            TextColumn::make('nama')->label('Cabang')->weight('semibold')->searchable()->sortable(),
            TextColumn::make('kota')->label('Kota')->searchable()->placeholder('-'),
            TextColumn::make('no_telepon')->label('Telepon')->placeholder('-')->toggleable(),
            TextColumn::make('users_count')->counts('users')->label('Pengguna')->badge()->color('gray'),
            TextColumn::make('leads_count')->counts('leads')->label('Leads')->badge()->color('primary'),
            IconColumn::make('aktif')->label('Aktif')->boolean(),
        ])->filters([
            TernaryFilter::make('aktif')->label('Status aktif'),
        ])->recordActions([
            EditAction::make()->visible(fn () => auth()->user()->hasRole(UserRole::Superadmin)),
        ])->emptyStateHeading('Belum ada cabang')->emptyStateDescription('Tambahkan cabang operasional pertama SIVMI.');
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->when(! $user->hasGlobalBranchAccess(), fn (Builder $query) => $query->whereKey($user->cabang_id));
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && ! auth()->user()->hasRole(UserRole::Keuangan);
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCabangs::route('/'),
            'create' => CreateCabang::route('/create'),
            'edit' => EditCabang::route('/{record}/edit'),
        ];
    }
}
