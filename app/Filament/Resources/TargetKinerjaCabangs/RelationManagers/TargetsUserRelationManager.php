<?php

namespace App\Filament\Resources\TargetKinerjaCabangs\RelationManagers;

use App\Enums\UserRole;
use App\Filament\Resources\TargetKinerjaCabangs\TargetKinerjaCabangResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class TargetsUserRelationManager extends RelationManager
{
    protected static string $relationship = 'targetsUser';
    protected static ?string $title = 'Alokasi target user';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Pengguna')->options(fn () => User::query()->where('cabang_id', $this->getOwnerRecord()->cabang_id)->where('aktif', true)->whereIn('role', [UserRole::AdminCabang->value, UserRole::Staff->value])->orderBy('name')->pluck('name', 'id'))->searchable()->required()->disabledOn('edit')->dehydrated(),
            TextInput::make('target_omzet')->label('Target omzet')->prefix('Rp')->numeric()->minValue(0)->required(),
            TextInput::make('target_siswa')->label('Target siswa')->numeric()->integer()->minValue(0)->suffix('siswa')->required(),
            Textarea::make('catatan')->label('Catatan')->rows(3)->maxLength(1000)->columnSpanFull(),
        ])->columns(['default' => 1, 'md' => 2]);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query
            ->when(auth()->user()->hasRole(UserRole::Staff), fn (Builder $builder) => $builder->where('user_id', auth()->id()))
            ->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Pengguna')->weight('semibold'),
                TextColumn::make('user.role')->label('Role')->formatStateUsing(fn (UserRole $state) => $state->label())->badge()->color('gray'),
                TextColumn::make('target_omzet')->label('Target omzet')->money('IDR', locale: 'id'),
                TextColumn::make('target_siswa')->label('Target siswa')->suffix(' siswa'),
                TextColumn::make('catatan')->label('Catatan')->limit(45)->placeholder('-')->toggleable(),
            ])->headerActions([
                CreateAction::make()->label('Tambah alokasi')->visible(fn () => TargetKinerjaCabangResource::canManage())->mutateDataUsing(function (array $data): array {
                    User::query()->whereKey($data['user_id'])->where('cabang_id', $this->getOwnerRecord()->cabang_id)->where('aktif', true)->whereIn('role', [UserRole::AdminCabang->value, UserRole::Staff->value])->firstOrFail();
                    if ($this->getOwnerRecord()->targetsUser()->where('user_id', $data['user_id'])->exists()) {
                        throw ValidationException::withMessages(['data.user_id' => 'Pengguna ini sudah memiliki alokasi pada periode tersebut.']);
                    }
                    return $data;
                }),
            ])->recordActions([
                EditAction::make()->visible(fn () => TargetKinerjaCabangResource::canManage()),
            ])->emptyStateHeading('Belum ada alokasi user')->emptyStateDescription('Bagikan target cabang kepada Admin Cabang dan Staff.');
    }
}
