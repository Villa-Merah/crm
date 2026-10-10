<?php

namespace App\Filament\Resources\TargetKinerjaCabangs;

use App\Enums\UserRole;
use App\Filament\Resources\TargetKinerjaCabangs\Pages\CreateTargetKinerjaCabang;
use App\Filament\Resources\TargetKinerjaCabangs\Pages\EditTargetKinerjaCabang;
use App\Filament\Resources\TargetKinerjaCabangs\Pages\ListTargetKinerjaCabangs;
use App\Filament\Resources\TargetKinerjaCabangs\RelationManagers\TargetsUserRelationManager;
use App\Models\Cabang;
use App\Models\TargetKinerjaCabang;
use BackedEnum;
use Filament\Actions\EditAction;
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

class TargetKinerjaCabangResource extends Resource
{
    protected static ?string $model = TargetKinerjaCabang::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|\UnitEnum|null $navigationGroup = 'SIVMI CRM';

    protected static ?string $navigationLabel = 'Target Kinerja';

    protected static ?string $pluralModelLabel = 'Target Kinerja';

    protected static ?string $modelLabel = 'target kinerja';

    protected static ?string $slug = 'target-kinerja';

    protected static ?int $navigationSort = 7;

    public static function months(): array
    {
        return [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
    }

    public static function form(Schema $schema): Schema
    {
        $viewer = auth()->user();

        return $schema->components([
            Section::make('Periode dan cakupan')->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('cabang_id')->label('Cabang')->options(Cabang::query()->where('aktif', true)->when(! $viewer->hasGlobalBranchAccess(), fn (Builder $query) => $query->whereKey($viewer->cabang_id))->orderBy('nama')->pluck('nama', 'cabang_id'))->default($viewer->cabang_id)->searchable()->required()->disabled(fn (string $operation) => $operation === 'edit' || ! $viewer->hasGlobalBranchAccess())->dehydrated(),
                Select::make('status')->label('Status')->options(['Draft' => 'Draft', 'Aktif' => 'Aktif', 'Selesai' => 'Selesai'])->default('Draft')->required(),
                TextInput::make('tahun')->label('Tahun')->numeric()->integer()->minValue(2020)->maxValue(2100)->default(now()->year)->required(),
                Select::make('bulan')->label('Bulan')->options(static::months())->default(now()->month)->required(),
            ]),
            Section::make('Target cabang')->columns(['default' => 1, 'md' => 2])->schema([
                TextInput::make('target_omzet')->label('Target omzet')->prefix('Rp')->numeric()->minValue(0)->required(),
                TextInput::make('target_siswa')->label('Target siswa')->numeric()->integer()->minValue(0)->suffix('siswa')->required(),
                Textarea::make('catatan')->label('Catatan')->rows(4)->maxLength(2000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('tahun', 'desc')->columns([
            TextColumn::make('cabang.nama')->label('Cabang')->weight('semibold')->sortable()->visible(fn () => auth()->user()->hasGlobalBranchAccess()),
            TextColumn::make('bulan')->label('Periode')->formatStateUsing(fn (int $state, TargetKinerjaCabang $record) => static::months()[$state].' '.$record->tahun)->sortable(),
            TextColumn::make('status')->label('Status')->badge()->color(fn (string $state) => match ($state) {
                'Aktif' => 'success', 'Selesai' => 'info', default => 'gray'
            }),
            TextColumn::make('target_omzet')->label('Target omzet cabang')->money('IDR', locale: 'id')->sortable(),
            TextColumn::make('target_siswa')->label('Target siswa cabang')->suffix(' siswa')->sortable(),
            TextColumn::make('personal_omzet')->label('Target omzet saya')->money('IDR', locale: 'id')->placeholder('Rp 0')->visible(fn () => auth()->user()->hasRole(UserRole::Staff)),
            TextColumn::make('personal_siswa')->label('Target siswa saya')->suffix(' siswa')->placeholder('0')->visible(fn () => auth()->user()->hasRole(UserRole::Staff)),
            TextColumn::make('allocated_omzet')->label('Omzet dialokasikan')->money('IDR', locale: 'id')->placeholder('Rp 0')->visible(fn () => ! auth()->user()->hasRole(UserRole::Staff)),
            TextColumn::make('allocated_siswa')->label('Siswa dialokasikan')->suffix(' siswa')->placeholder('0')->visible(fn () => ! auth()->user()->hasRole(UserRole::Staff)),
            TextColumn::make('allocation_status')->label('Status alokasi')->state(function (TargetKinerjaCabang $record): string {
                $allocatedOmzet = (float) ($record->allocated_omzet ?? 0);
                $allocatedSiswa = (int) ($record->allocated_siswa ?? 0);
                if ($allocatedOmzet === 0.0 && $allocatedSiswa === 0) {
                    return 'Belum dialokasikan';
                }

                return $allocatedOmzet === (float) $record->target_omzet && $allocatedSiswa === (int) $record->target_siswa
                    ? 'Sesuai target'
                    : 'Belum penuh';
            })->badge()->color(fn (string $state): string => match ($state) {
                'Sesuai target' => 'success', 'Belum penuh' => 'warning', default => 'gray'
            })->visible(fn () => ! auth()->user()->hasRole(UserRole::Staff)),
        ])->filters([
            SelectFilter::make('tahun')->label('Tahun')->options(fn () => TargetKinerjaCabang::query()->visibleTo(auth()->user())->distinct()->orderByDesc('tahun')->pluck('tahun', 'tahun')),
            SelectFilter::make('status')->label('Status')->options(['Draft' => 'Draft', 'Aktif' => 'Aktif', 'Selesai' => 'Selesai']),
        ])->recordActions([
            EditAction::make()->visible(fn (TargetKinerjaCabang $record) => static::canEdit($record)),
        ])->emptyStateHeading('Belum ada target kinerja')->emptyStateDescription('Tetapkan target omzet dan siswa untuk periode cabang.');
    }

    public static function getEloquentQuery(): Builder
    {
        $viewer = auth()->user();

        return parent::getEloquentQuery()->visibleTo($viewer)->with(['cabang', 'createdBy'])
            ->withSum('targetsUser as allocated_omzet', 'target_omzet')
            ->withSum('targetsUser as allocated_siswa', 'target_siswa')
            ->when($viewer->hasRole(UserRole::Staff), fn (Builder $query) => $query
                ->withSum(['targetsUser as personal_omzet' => fn (Builder $builder) => $builder->where('user_id', $viewer->id)], 'target_omzet')
                ->withSum(['targetsUser as personal_siswa' => fn (Builder $builder) => $builder->where('user_id', $viewer->id)], 'target_siswa'));
    }

    public static function canManage(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) || auth()->user()?->hasRole(UserRole::AdminCabang);
    }

    public static function canViewAny(): bool
    {
        return auth()->check() && ! auth()->user()->hasRole(UserRole::Keuangan);
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        $viewer = auth()->user();

        return static::canManage()
            && $record->status !== 'Selesai'
            && ($viewer->hasGlobalBranchAccess() || $record->cabang_id === $viewer->cabang_id);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [TargetsUserRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListTargetKinerjaCabangs::route('/'), 'create' => CreateTargetKinerjaCabang::route('/create'), 'edit' => EditTargetKinerjaCabang::route('/{record}/edit')];
    }
}
