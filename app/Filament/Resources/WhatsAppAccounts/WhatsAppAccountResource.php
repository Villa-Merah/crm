<?php

namespace App\Filament\Resources\WhatsAppAccounts;

use App\Enums\UserRole;
use App\Enums\WhatsAppAccountStatus;
use App\Filament\Resources\WhatsAppAccounts\Pages\CreateWhatsAppAccount;
use App\Filament\Resources\WhatsAppAccounts\Pages\EditWhatsAppAccount;
use App\Filament\Resources\WhatsAppAccounts\Pages\ListWhatsAppAccounts;
use App\Filament\Resources\WhatsAppConversations\WhatsAppConversationResource;
use App\Models\User;
use App\Models\UserWhatsAppAccount;
use App\Services\WhatsApp\WahaSessionService;
use App\Support\PhoneNormalizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class WhatsAppAccountResource extends Resource
{
    protected static ?string $model = UserWhatsAppAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?string $navigationLabel = 'Koneksi WhatsApp';

    protected static ?string $modelLabel = 'koneksi WhatsApp';

    protected static ?string $pluralModelLabel = 'Koneksi WhatsApp';

    protected static ?string $slug = 'koneksi-whatsapp';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        $viewer = auth()->user();
        $users = User::query()
            ->where('aktif', true)
            ->whereIn('role', [UserRole::AdminCabang->value, UserRole::Staff->value])
            ->when(! $viewer->hasGlobalBranchAccess(), fn (Builder $query) => $query->where('cabang_id', $viewer->cabang_id))
            ->orderBy('name')->pluck('name', 'id');

        return $schema->components([
            Section::make('Akun WhatsApp pengguna')
                ->description('Gunakan nomor operasional yang dikuasai organisasi. API key WAHA tidak disimpan pada data pengguna.')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('user_id')->label('Admin / Staff')->options($users)->searchable()->required()->disabledOn('edit')->dehydrated(),
                    TextInput::make('phone_e164')->label('Nomor WhatsApp')->tel()->required()->maxLength(20)
                        ->helperText('Format disimpan sebagai kode negara, contoh 628123456789.')
                        ->dehydrateStateUsing(fn (?string $state) => app(PhoneNormalizer::class)->normalize($state))
                        ->unique(ignoreRecord: true),
                    Toggle::make('aktif')->label('Integrasi aktif')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('updated_at', 'desc')->columns([
            TextColumn::make('user.name')->label('Pengguna')->description(fn (UserWhatsAppAccount $record) => $record->user?->email)->searchable()->weight('semibold'),
            TextColumn::make('phone_e164')->label('Nomor WhatsApp')->formatStateUsing(fn (string $state) => '+'.$state)->searchable(),
            TextColumn::make('session_name')->label('Session')->searchable()->copyable()->toggleable(),
            TextColumn::make('cabang.nama')->label('Cabang')->visible(fn () => auth()->user()->hasGlobalBranchAccess()),
            TextColumn::make('status')->label('Status')->badge()
                ->formatStateUsing(fn (WhatsAppAccountStatus $state) => match ($state) {
                    WhatsAppAccountStatus::Working => 'Terhubung', WhatsAppAccountStatus::Connecting => 'Menghubungkan',
                    WhatsAppAccountStatus::Failed => 'Gagal', default => 'Terputus',
                })
                ->description(fn (UserWhatsAppAccount $record) => filled($record->last_error) ? str($record->last_error)->limit(80) : null)
                ->color(fn (WhatsAppAccountStatus $state) => match ($state) {
                    WhatsAppAccountStatus::Working => 'success', WhatsAppAccountStatus::Connecting => 'warning',
                    WhatsAppAccountStatus::Failed => 'danger', default => 'gray',
                }),
            TextColumn::make('conversations_count')->counts('conversations')->label('Percakapan')->badge(),
            TextColumn::make('messages_count')->counts('messages')->label('Pesan')->badge()->toggleable(),
            TextColumn::make('last_seen_at')->label('Sinkronisasi')->since()->placeholder('Belum pernah'),
            IconColumn::make('aktif')->label('Aktif')->boolean(),
        ])->recordActions([
                Action::make('provision')->label('Siapkan session')->icon('heroicon-o-cloud-arrow-up')->color('gray')
                    ->visible(fn (UserWhatsAppAccount $record) => static::canManage() && blank($record->metadata['provisioned_at'] ?? null))
                    ->requiresConfirmation()->action(fn (UserWhatsAppAccount $record) => static::runWahaAction($record, 'provision')),
                Action::make('start')->label('Mulai')->icon('heroicon-o-play')->color('success')
                    ->visible(fn (UserWhatsAppAccount $record) => static::canManage() && $record->status !== WhatsAppAccountStatus::Working)
                    ->action(fn (UserWhatsAppAccount $record) => static::runWahaAction(
                        $record,
                        $record->status === WhatsAppAccountStatus::Failed ? 'restart' : 'start',
                    )),
                Action::make('sync')->label('Sinkronkan')->icon('heroicon-o-arrow-path')
                    ->action(fn (UserWhatsAppAccount $record) => static::runWahaAction($record, 'refresh')),
                Action::make('stop')->label('Hentikan')->icon('heroicon-o-stop')->color('danger')
                    ->visible(fn (UserWhatsAppAccount $record) => static::canManage() && $record->status === WhatsAppAccountStatus::Working)
                    ->requiresConfirmation()
                    ->modalDescription('Session dihentikan tanpa menghapus data percakapan maupun konfigurasi akun.')
                    ->action(fn (UserWhatsAppAccount $record) => static::runWahaAction($record, 'stop')),
                Action::make('syncWebhooks')->label('Aktifkan chat')->icon('heroicon-o-chat-bubble-left-right')->color('primary')
                    ->visible(fn () => static::canManage())
                    ->requiresConfirmation()
                    ->modalDescription('Memperbarui webhook session agar pesan masuk, pesan keluar, dan status dibaca diterima oleh CRM.')
                    ->action(fn (UserWhatsAppAccount $record) => static::runWahaAction($record, 'syncWebhooks')),
                Action::make('qr')->label('Lihat QR')->icon('heroicon-o-qr-code')->color('warning')
                    ->url(fn (UserWhatsAppAccount $record) => route('waha.accounts.qr', $record))->openUrlInNewTab()
                    ->visible(fn (UserWhatsAppAccount $record) => static::canManage() && $record->status !== WhatsAppAccountStatus::Working),
                Action::make('inbox')->label('Buka inbox')->icon('heroicon-o-inbox')->color('gray')
                    ->url(fn () => WhatsAppConversationResource::getUrl())
                    ->visible(fn (UserWhatsAppAccount $record) => $record->conversations_count > 0),
                EditAction::make()->visible(fn (UserWhatsAppAccount $record) => static::canEdit($record)),
        ])->emptyStateHeading('Belum ada nomor WhatsApp')->emptyStateDescription('Tambahkan nomor operasional admin atau staff, lalu siapkan session WAHA.');
    }

    public static function runWahaAction(UserWhatsAppAccount $account, string $method): void
    {
        try {
            app(WahaSessionService::class)->{$method}($account);
            Notification::make()->title('Perintah WAHA berhasil dijalankan')->success()->send();
        } catch (Throwable $exception) {
            $account->update(['status' => WhatsAppAccountStatus::Failed, 'last_error' => str($exception->getMessage())->limit(2000)->toString()]);
            report($exception);
            Notification::make()->title('WAHA tidak dapat dihubungi')->body('Periksa service, API key, dan status session.')->danger()->send();
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['user', 'cabang'])->withCount(['conversations', 'messages']);
    }

    public static function canManage(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) || auth()->user()?->hasRole(UserRole::AdminCabang);
    }

    public static function canViewAny(): bool
    {
        return static::canManage();
    }

    public static function canCreate(): bool
    {
        return static::canManage();
    }

    public static function canEdit(Model $record): bool
    {
        $viewer = auth()->user();

        return $viewer?->hasRole(UserRole::Superadmin) || ($viewer?->hasRole(UserRole::AdminCabang) && $record->cabang_id === $viewer->cabang_id);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppAccounts::route('/'),
            'create' => CreateWhatsAppAccount::route('/create'),
            'edit' => EditWhatsAppAccount::route('/{record}/edit'),
        ];
    }
}
