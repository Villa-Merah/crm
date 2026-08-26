<?php

namespace App\Filament\Resources\WhatsAppConversations;

use App\Enums\UserRole;
use App\Enums\WhatsAppMatchStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\WhatsAppConversations\Pages\ListWhatsAppConversations;
use App\Filament\Resources\WhatsAppConversations\Pages\ViewWhatsAppConversation;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\WhatsAppConversation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
            Action::make('linkLead')->label('Hubungkan lead')->icon('heroicon-o-link')->color('warning')
                ->visible(fn (WhatsAppConversation $record) => static::canResolve() && ! $record->lead_id)
                ->schema(fn (WhatsAppConversation $record) => [
                    Select::make('lead_id')->label('Lead')->options(
                        Lead::query()->visibleTo(auth()->user())
                            ->when($record->contact_phone_e164, fn (Builder $query) => $query->where('no_wa_normalized', $record->contact_phone_e164))
                            ->orderBy('nama')->pluck('nama', 'lead_id')
                    )->searchable()->required(),
                ])->action(function (WhatsAppConversation $record, array $data): void {
                    $lead = Lead::query()->visibleTo(auth()->user())->findOrFail($data['lead_id']);
                    DB::transaction(function () use ($record, $lead): void {
                        $record->update(['lead_id' => $lead->getKey(), 'match_status' => WhatsAppMatchStatus::Matched]);
                        $record->messages()->update(['lead_id' => $lead->getKey()]);
                        $lead->update([
                            'whatsapp_first_seen_at' => $lead->whatsapp_first_seen_at ?? $record->first_message_at,
                            'whatsapp_last_seen_at' => $record->last_message_at,
                        ]);
                        ActivityLog::create([
                            'user_id' => auth()->id(), 'cabang_id' => $lead->cabang_id,
                            'subject_type' => $record->getMorphClass(), 'subject_id' => $record->getKey(),
                            'event' => 'whatsapp_linked', 'description' => 'Percakapan WhatsApp dihubungkan ke lead',
                            'properties' => ['lead_id' => $lead->getKey()], 'created_at' => now(),
                        ]);
                    });
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
        return auth()->user()?->hasRole(UserRole::Superadmin) || auth()->user()?->hasRole(UserRole::AdminCabang);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppConversations::route('/'),
            'view' => ViewWhatsAppConversation::route('/{record}'),
        ];
    }
}
