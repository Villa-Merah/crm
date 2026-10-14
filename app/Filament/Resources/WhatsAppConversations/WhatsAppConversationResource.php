<?php

namespace App\Filament\Resources\WhatsAppConversations;

use App\Enums\UserRole;
use App\Enums\WhatsAppMatchStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\WhatsAppConversations\Pages\ListWhatsAppConversations;
use App\Filament\Resources\WhatsAppConversations\Pages\ViewWhatsAppConversation;
use App\Models\Lead;
use App\Models\WhatsAppConversation;
use App\Services\WhatsApp\WhatsAppLeadQualificationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConversationResource extends Resource
{
    protected static ?string $model = WhatsAppConversation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'SIVMI CRM';

    protected static ?string $navigationLabel = 'Inbox WhatsApp';

    protected static ?string $modelLabel = 'percakapan WhatsApp';

    protected static ?string $pluralModelLabel = 'Inbox WhatsApp';

    protected static ?string $slug = 'inbox-whatsapp';

    protected static ?int $navigationSort = 4;

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas percakapan')->columns(['default' => 1, 'md' => 3])->schema([
                TextEntry::make('contact_name')->label('Kontak')->placeholder('Tidak diketahui')->weight('bold'),
                TextEntry::make('contact_phone_e164')->label('Nomor')->formatStateUsing(fn (?string $state) => $state ? '+'.$state : '-')->copyable(),
                TextEntry::make('match_status')->label('Pencocokan')->badge()->color(fn (WhatsAppMatchStatus $state) => static::matchColor($state)),
                TextEntry::make('account.user.name')->label('Nomor diterima oleh'),
                TextEntry::make('account.cabang.nama')->label('Cabang'),
                TextEntry::make('lead.nama')->label('Lead terkait')->placeholder('Belum terhubung'),
            ]),
            Section::make('Riwayat pesan')->schema([
                RepeatableEntry::make('messages')->label('')->schema([
                    TextEntry::make('direction')->label('Arah')->badge()->color(fn ($state) => $state->value === 'Inbound' ? 'primary' : 'success'),
                    TextEntry::make('sent_at')->label('Waktu')->dateTime('d M Y, H:i'),
                    TextEntry::make('body')->label('Pesan')->placeholder('[Media/tanpa teks]')->columnSpanFull(),
                ])->columns(['default' => 1, 'md' => 2]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('last_message_at', 'desc')->columns([
            TextColumn::make('contact_name')->label('Kontak')->description(fn (WhatsAppConversation $record) => $record->contact_phone_e164 ? '+'.$record->contact_phone_e164 : $record->chat_id)->searchable()->weight('semibold'),
            TextColumn::make('latestMessage.body')->label('Pesan terakhir')->limit(55)->placeholder('[Media/tanpa teks]')->wrap(),
            TextColumn::make('match_status')->label('Status')->badge()->color(fn (WhatsAppMatchStatus $state) => static::matchColor($state)),
            TextColumn::make('lead.nama')->label('Lead')->placeholder('Belum terhubung')->url(fn (WhatsAppConversation $record) => $record->lead_id ? LeadResource::getUrl('view', ['record' => $record->lead_id]) : null),
            TextColumn::make('account.user.name')->label('Admin / Staff'),
            TextColumn::make('account.cabang.nama')->label('Cabang')->visible(fn () => auth()->user()->hasGlobalBranchAccess()),
            TextColumn::make('unread_count')->label('Belum dibaca')->badge()->color('danger')->sortable(),
            TextColumn::make('last_message_at')->label('Pesan terakhir')->since()->sortable(),
        ])->filters([
            SelectFilter::make('match_status')->label('Status pencocokan')->options(collect(WhatsAppMatchStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->value])),
        ])->recordActions([
            ViewAction::make(),
            Action::make('createLead')->label('Jadikan lead')->icon('heroicon-o-user-plus')->color('primary')
                ->visible(fn (WhatsAppConversation $record) => static::canResolve() && ! $record->lead_id && $record->match_status !== WhatsAppMatchStatus::Conflict)
                ->requiresConfirmation()
                ->modalHeading('Jadikan kontak sebagai lead?')
                ->modalDescription('Data awal lead akan dibuat dari identitas kontak, cabang, dan session WhatsApp penerima.')
                ->action(function (WhatsAppConversation $record): void {
                    app(WhatsAppLeadQualificationService::class)->createLead($record, auth()->user());
                    Notification::make()->success()->title('Kontak berhasil dijadikan lead')->send();
                }),
            Action::make('linkLead')->label('Hubungkan lead')->icon('heroicon-o-link')->color('warning')
                ->visible(fn (WhatsAppConversation $record) => static::canResolve() && ! $record->lead_id)
                ->schema(fn (WhatsAppConversation $record) => [
                    Select::make('lead_id')->label('Lead')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => Lead::query()
                            ->visibleTo(auth()->user())
                            ->where('cabang_id', $record->account->cabang_id)
                            ->where(fn (Builder $query) => $query
                                ->where('nama', 'like', "%{$search}%")
                                ->orWhere('no_wa', 'like', "%{$search}%")
                                ->orWhere('no_wa_normalized', 'like', "%{$search}%"))
                            ->orderBy('nama')
                            ->limit(50)
                            ->pluck('nama', 'lead_id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => Lead::query()
                            ->visibleTo(auth()->user())
                            ->where('cabang_id', $record->account->cabang_id)
                            ->whereKey($value)
                            ->value('nama'))
                        ->required(),
                ])->action(function (WhatsAppConversation $record, array $data): void {
                    $lead = Lead::query()->findOrFail($data['lead_id']);
                    app(WhatsAppLeadQualificationService::class)->linkLead($record, $lead, auth()->user());
                    Notification::make()->success()->title('Percakapan berhasil dihubungkan ke lead')->send();
                }),
            Action::make('ignore')->label('Bukan lead')->icon('heroicon-o-no-symbol')->color('gray')
                ->visible(fn (WhatsAppConversation $record) => static::canResolve() && ! $record->lead_id && $record->match_status !== WhatsAppMatchStatus::Ignored)
                ->schema([
                    Select::make('reason')->label('Alasan')->options([
                        'Kontak pribadi' => 'Kontak pribadi',
                        'Vendor atau mitra' => 'Vendor atau mitra',
                        'Kontak internal' => 'Kontak internal',
                        'Spam' => 'Spam',
                        'Lainnya' => 'Lainnya',
                    ])->required(),
                    Textarea::make('note')->label('Catatan')->maxLength(300),
                ])
                ->action(function (WhatsAppConversation $record, array $data): void {
                    $reason = $data['reason'].(filled($data['note'] ?? null) ? ': '.str($data['note'])->limit(300) : '');
                    app(WhatsAppLeadQualificationService::class)->ignore($record, auth()->user(), $reason);
                    Notification::make()->success()->title('Percakapan ditandai bukan lead')->send();
                }),
            Action::make('reopen')->label('Tinjau kembali')->icon('heroicon-o-arrow-path')->color('warning')
                ->visible(fn (WhatsAppConversation $record) => static::canResolve() && ! $record->lead_id && $record->match_status === WhatsAppMatchStatus::Ignored)
                ->requiresConfirmation()
                ->action(function (WhatsAppConversation $record): void {
                    app(WhatsAppLeadQualificationService::class)->reopen($record, auth()->user());
                    Notification::make()->success()->title('Percakapan dikembalikan untuk ditinjau')->send();
                }),
        ])->emptyStateHeading('Belum ada percakapan')->emptyStateDescription('Pesan yang diterima melalui session WAHA akan muncul di sini.');
    }

    public static function matchColor(WhatsAppMatchStatus $status): string
    {
        return match ($status) {
            WhatsAppMatchStatus::Matched => 'success', WhatsAppMatchStatus::NewLead => 'primary',
            WhatsAppMatchStatus::Conflict => 'danger', WhatsAppMatchStatus::Unresolved => 'warning', default => 'gray',
        };
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['account.user', 'account.cabang', 'lead', 'latestMessage']);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageOperationalData() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = (int) static::getEloquentQuery()->sum('unread_count');

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function canView(Model $record): bool
    {
        return static::getEloquentQuery()->whereKey($record)->exists();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canResolve(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin)
            || auth()->user()?->hasRole(UserRole::AdminCabang)
            || auth()->user()?->hasRole(UserRole::Staff);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppConversations::route('/'),
            'view' => ViewWhatsAppConversation::route('/{record}'),
        ];
    }
}
