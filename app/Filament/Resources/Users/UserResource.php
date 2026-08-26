<?php

namespace App\Filament\Resources\Users;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Cabang;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $slug = 'pengguna';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $viewer = auth()->user();
        $roleOptions = collect(UserRole::cases())
            ->when($viewer->hasRole(UserRole::AdminCabang), fn ($roles) => $roles->filter(fn (UserRole $role) => $role === UserRole::Staff))
            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()]);

        return $schema->components([
            Section::make('Profil pengguna')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('name')->label('Nama lengkap')->required()->maxLength(150),
                TextInput::make('email')->label('Email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                Select::make('role')->label('Peran')->options($roleOptions)->default(UserRole::Staff->value)->required()->disabled($viewer->hasRole(UserRole::AdminCabang))->dehydrated(),
                Select::make('cabang_id')->label('Cabang')->options(Cabang::query()->where('aktif', true)->when($viewer->hasRole(UserRole::AdminCabang), fn (Builder $query) => $query->whereKey($viewer->cabang_id))->orderBy('nama')->pluck('nama', 'cabang_id'))->default($viewer->cabang_id)->disabled($viewer->hasRole(UserRole::AdminCabang))->dehydrated()->placeholder('Akses seluruh cabang'),
                TextInput::make('password')->label('Kata sandi')->password()->revealable()->required(fn (string $operation): bool => $operation === 'create')->minLength(8)->afterStateHydrated(fn (TextInput $component) => $component->state(''))->dehydrated(fn (?string $state): bool => filled($state))->helperText('Kosongkan saat mengedit jika kata sandi tidak berubah.'),
                Toggle::make('aktif')->label('Pengguna aktif')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            TextColumn::make('name')->label('Pengguna')->description(fn (User $record) => $record->email)->weight('semibold')->searchable(['name', 'email'])->sortable(),
            TextColumn::make('role')->label('Peran')->badge()->formatStateUsing(fn (UserRole $state) => $state->label())->color(fn (UserRole $state) => match ($state) {
                UserRole::Superadmin => 'danger', UserRole::Direksi => 'purple', UserRole::AdminCabang => 'info', UserRole::Staff => 'success'
            }),
            TextColumn::make('cabang.nama')->label('Cabang')->placeholder('Semua cabang')->sortable(),
            TextColumn::make('leads_ditugaskan_count')->counts('leadsDitugaskan')->label('Leads')->badge()->color('primary'),
            TextColumn::make('whatsappAccount.phone_e164')->label('WhatsApp')->formatStateUsing(fn (?string $state) => $state ? '+'.$state : '-')->placeholder('Belum terhubung'),
            TextColumn::make('whatsappAccount.status')->label('Status WA')->badge()->placeholder('-'),
            IconColumn::make('aktif')->label('Aktif')->boolean(),
            TextColumn::make('updated_at')->label('Diperbarui')->since()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('role')->label('Peran')->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])),
            SelectFilter::make('cabang_id')->label('Cabang')->options(Cabang::where('aktif', true)->orderBy('nama')->pluck('nama', 'cabang_id'))->visible(fn () => auth()->user()->hasRole(UserRole::Superadmin)),
            TernaryFilter::make('aktif')->label('Status aktif'),
        ])->recordActions([
            EditAction::make()->visible(fn (User $record) => static::canEdit($record)),
        ])->emptyStateHeading('Belum ada pengguna')->emptyStateDescription('Tambahkan staf pertama untuk cabang ini.');
    }

    public static function getEloquentQuery(): Builder
    {
        $viewer = auth()->user();

        return parent::getEloquentQuery()
            ->with('whatsappAccount')
            ->when($viewer->hasRole(UserRole::AdminCabang), fn (Builder $query) => $query->where('cabang_id', $viewer->cabang_id));
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) || auth()->user()?->hasRole(UserRole::AdminCabang);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        $viewer = auth()->user();
        if ($viewer?->hasRole(UserRole::Superadmin)) {
            return true;
        }

        return $viewer?->hasRole(UserRole::AdminCabang)
            && $record->cabang_id === $viewer->cabang_id
            && $record->hasRole(UserRole::Staff);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
