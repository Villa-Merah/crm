<?php

namespace App\Filament\Resources\FollowupRiwayats;

use App\Enums\UserRole;
use App\Filament\Resources\FollowupRiwayats\Pages\CreateFollowupRiwayat;
use App\Filament\Resources\FollowupRiwayats\Pages\EditFollowupRiwayat;
use App\Filament\Resources\FollowupRiwayats\Pages\ListFollowupRiwayats;
use App\Filament\Resources\FollowupRiwayats\Schemas\FollowupRiwayatForm;
use App\Filament\Resources\FollowupRiwayats\Tables\FollowupRiwayatsTable;
use App\Models\FollowupRiwayat;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FollowupRiwayatResource extends Resource
{
    protected static ?string $model = FollowupRiwayat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Follow-up';

    protected static string|\UnitEnum|null $navigationGroup = 'SIVMI CRM';

    protected static ?string $modelLabel = 'follow-up';

    protected static ?string $pluralModelLabel = 'follow-up';

    protected static ?string $slug = 'follow-up';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return FollowupRiwayatForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FollowupRiwayatsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('lead', fn (Builder $query) => $query->visibleTo(auth()->user()))
            ->with(['lead', 'user', 'lead.cabang']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canViewSalesData() ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageOperationalData() ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canManageOperationalData() ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowupRiwayats::route('/'),
            'create' => CreateFollowupRiwayat::route('/create'),
            'edit' => EditFollowupRiwayat::route('/{record}/edit'),
        ];
    }
}
