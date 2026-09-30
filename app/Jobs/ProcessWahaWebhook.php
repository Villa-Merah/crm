<?php

namespace App\Jobs;

use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppMatchStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\UserWhatsAppAccount;
use App\Models\WahaWebhookEvent;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\LeadMatcher;
use App\Services\WhatsApp\WahaClient;
use App\Support\CrmNotifier;
use App\Support\PhoneNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProcessWahaWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60, 180];

    public function __construct(public readonly int $eventId) {}

    public function handle(PhoneNormalizer $normalizer, LeadMatcher $matcher, CrmNotifier $notifier, WahaClient $client): void
    {
        $event = WahaWebhookEvent::query()->findOrFail($this->eventId);
        if ($event->status === 'Processed' || $event->status === 'Ignored') {
            return;
        }

        $event->update(['status' => 'Processing', 'attempts' => $event->attempts + 1, 'last_error' => null]);

        try {
            $account = UserWhatsAppAccount::query()->where('session_name', $event->session_name)->where('aktif', true)->first();
            if (! $account) {
                $event->update(['status' => 'Ignored', 'last_error' => 'Session tidak terdaftar atau tidak aktif.', 'processed_at' => now()]);

                return;
            }

            if ($event->event_name === 'session.status') {
                $this->processSessionStatus($event, $account);

                return;
            }

            if ($event->event_name === 'message.ack') {
                $this->processMessageAck($event, $account, $notifier);

                return;
            }

            if (! in_array($event->event_name, ['message', 'message.any'], true)) {
                $event->update(['status' => 'Ignored', 'processed_at' => now()]);

                return;
            }

            $this->processMessage($event, $account, $normalizer, $matcher, $notifier, $client);
        } catch (Throwable $exception) {
            $event->update(['status' => 'Failed', 'last_error' => str($exception->getMessage())->limit(2000)->toString()]);
            throw $exception;
        }
    }

    private function processMessageAck(WahaWebhookEvent $event, UserWhatsAppAccount $account, CrmNotifier $notifier): void
    {
        $externalId = (string) data_get($event->payload, 'payload.id');
        $ackName = strtoupper((string) data_get($event->payload, 'payload.ackName'));

        if (filled($externalId)) {
            $message = WhatsAppMessage::query()
                ->where('whatsapp_account_id', $account->getKey())
                ->where('external_message_id', $externalId)
                ->first();

            if ($message) {
                $message->update(['processing_status' => match ($ackName) {
                    'ERROR' => WhatsAppMessageStatus::Failed->value,
                    'PENDING' => WhatsAppMessageStatus::Pending->value,
                    'SERVER' => WhatsAppMessageStatus::Sent->value,
                    'DEVICE' => WhatsAppMessageStatus::Delivered->value,
                    'READ' => WhatsAppMessageStatus::Read->value,
                    'PLAYED' => WhatsAppMessageStatus::Played->value,
                    default => WhatsAppMessageStatus::Sent->value,
                }]);

                if ($ackName === 'ERROR') {
                    $recipient = $account->user()->where('aktif', true)->first();
                    $conversation = $message->conversation;
                    if ($recipient && $conversation) {
                        $notifier->whatsAppSendFailed($recipient, $conversation, $message);
                    }
                }
            }
        }

        $event->update(['status' => 'Processed', 'processed_at' => now()]);
    }

    private function processSessionStatus(WahaWebhookEvent $event, UserWhatsAppAccount $account): void
    {
        $status = strtoupper((string) data_get($event->payload, 'payload.status'));
        $account->update([
            'status' => match ($status) {
                'WORKING' => WhatsAppAccountStatus::Working,
                'STARTING', 'SCAN_QR_CODE' => WhatsAppAccountStatus::Connecting,
                'FAILED' => WhatsAppAccountStatus::Failed,
                default => WhatsAppAccountStatus::Disconnected,
            },
            'waha_me_id' => data_get($event->payload, 'me.id') ?? data_get($event->payload, 'payload.me.id'),
            'connected_at' => $status === 'WORKING' ? ($account->connected_at ?? now()) : $account->connected_at,
            'last_seen_at' => now(),
            'last_error' => $status === 'FAILED' ? 'WAHA melaporkan session gagal.' : null,
        ]);
        $event->update(['status' => 'Processed', 'processed_at' => now()]);
    }

    private function processMessage(
        WahaWebhookEvent $event,
        UserWhatsAppAccount $account,
        PhoneNormalizer $normalizer,
        LeadMatcher $matcher,
        CrmNotifier $notifier,
        WahaClient $client,
    ): void {
        $payload = (array) data_get($event->payload, 'payload', []);
        $fromMe = (bool) data_get($payload, 'fromMe', false);
        $chatId = (string) (data_get($payload, 'chatId') ?: ($fromMe ? data_get($payload, 'to') : data_get($payload, 'from')));

        if (blank($chatId) || str_contains($chatId, '@g.us') || str_contains($chatId, '@newsletter') || str_contains($chatId, 'status@broadcast')) {
            $event->update(['status' => 'Ignored', 'processed_at' => now()]);

            return;
        }

        $externalId = (string) data_get($payload, 'id');
        if (blank($externalId)) {
            throw new \UnexpectedValueException('Payload pesan tidak memiliki ID.');
        }

        $sentAt = CarbonImmutable::createFromTimestampUTC((int) data_get($payload, 'timestamp', now()->timestamp))->setTimezone(config('app.timezone'));
        $phoneSource = $chatId;
        if (str_contains($chatId, '@lid')) {
            $phoneSource = (string) ($fromMe
                ? data_get($payload, '_data.Info.RecipientAlt')
                : data_get($payload, '_data.Info.SenderAlt'));
        }

        $phone = $normalizer->fromChatId($phoneSource);
        $contactName = data_get($payload, 'pushName')
            ?: data_get($payload, '_data.Info.PushName')
            ?: data_get($payload, '_data.notifyName');

        $media = $this->downloadIncomingMedia($payload, $client);

        DB::transaction(function () use ($event, $account, $matcher, $notifier, $payload, $fromMe, $chatId, $externalId, $sentAt, $phone, $contactName, $media): void {
            $conversation = WhatsAppConversation::query()->firstOrCreate(
                ['whatsapp_account_id' => $account->getKey(), 'chat_id' => $chatId],
                [
                    'contact_phone_e164' => $phone,
                    'contact_name' => $contactName,
                    'match_status' => WhatsAppMatchStatus::Unresolved,
                    'first_message_at' => $sentAt,
                    'last_message_at' => $sentAt,
                ],
            );

            // Heal orphaned references that can exist in imported legacy data
            // when foreign-key checks were disabled during the import.
            if ($conversation->lead_id && ! $conversation->lead()->exists()) {
                $conversation->lead_id = null;
                $conversation->match_status = WhatsAppMatchStatus::Unresolved;
            }

            if (! $conversation->lead_id && $phone) {
                $result = $matcher->matchOrCreate($account, $phone, $contactName, $sentAt, ! $fromMe);
                $conversation->lead_id = $result['lead']?->getKey();
                $conversation->match_status = $result['status'];

                if ($conversation->lead_id) {
                    $conversation->messages()->update(['lead_id' => $conversation->lead_id]);
                }
            }

            $conversation->contact_phone_e164 ??= $phone;
            $conversation->contact_name ??= $contactName;
            if ($conversation->isDirty()) {
                $conversation->save();
            }

            $message = WhatsAppMessage::query()->firstOrCreate(
                ['whatsapp_account_id' => $account->getKey(), 'external_message_id' => $externalId],
                [
                    'conversation_id' => $conversation->getKey(),
                    'lead_id' => $conversation->lead_id,
                    'direction' => $fromMe ? WhatsAppMessageDirection::Outbound : WhatsAppMessageDirection::Inbound,
                    'message_type' => $media['kind'] ?? ((bool) data_get($payload, 'hasMedia', false) ? 'media' : 'text'),
                    'body' => filled(data_get($payload, 'body')) ? str((string) data_get($payload, 'body'))->limit(10000)->toString() : null,
                    'has_media' => (bool) data_get($payload, 'hasMedia', false),
                    'media_path' => $media['path'] ?? null,
                    'sent_at' => $sentAt,
                    'from_me' => $fromMe,
                    'processing_status' => $fromMe ? WhatsAppMessageStatus::Sent : WhatsAppMessageStatus::Processed,
                    'raw_payload' => [
                        'id' => $externalId,
                        'timestamp' => data_get($payload, 'timestamp'),
                        'from' => data_get($payload, 'from'),
                        'to' => data_get($payload, 'to'),
                        'fromMe' => $fromMe,
                        'hasMedia' => (bool) data_get($payload, 'hasMedia', false),
                        'media' => $media ? [
                            'mimetype' => $media['mimetype'],
                            'filename' => $media['filename'],
                            'size' => $media['size'],
                        ] : null,
                    ],
                ],
            );

            if ($message->wasRecentlyCreated) {
                $conversation->first_message_at ??= $sentAt;
                $conversation->last_message_at = $sentAt;
                $conversation->contact_phone_e164 ??= $phone;
                $conversation->contact_name ??= $contactName;
                if (! $fromMe) {
                    $conversation->unread_count++;
                }
                $conversation->save();

                if (! $fromMe) {
                    $notifier->incomingWhatsAppMessage($conversation, $message);
                }

                if ($conversation->lead_id) {
                    $conversation->lead()->update([
                        'whatsapp_first_seen_at' => DB::raw('COALESCE(whatsapp_first_seen_at, CURRENT_TIMESTAMP)'),
                        'whatsapp_last_seen_at' => $sentAt,
                    ]);
                }
            }

            $event->update(['status' => 'Processed', 'processed_at' => now()]);
        });
    }

    /** @param array<string, mixed> $payload @return array{kind: string, path: string, mimetype: string, filename: string, size: int}|null */
    private function downloadIncomingMedia(array $payload, WahaClient $client): ?array
    {
        if (! (bool) data_get($payload, 'hasMedia', false)) {
            return null;
        }

        $url = (string) data_get($payload, 'media.url', '');
        if ($url === '') {
            return null;
        }

        $download = $client->downloadMedia($url);
        $contents = $download['body'];
        $mimeType = strtolower(trim(explode(';', (string) (data_get($payload, 'media.mimetype') ?: $download['content_type']))[0]));
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'], true)) {
            return null;
        }

        $kind = str_starts_with($mimeType, 'image/') ? 'image' : (str_starts_with($mimeType, 'video/') ? 'video' : null);
        if (! $kind) {
            return null;
        }

        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/quicktime' => 'mov',
            default => $kind === 'image' ? 'jpg' : 'mp4',
        };
        $filename = basename((string) (data_get($payload, 'media.filename') ?: 'whatsapp-'.$kind.'.'.$extension));
        $path = 'whatsapp-media/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
        Storage::disk('local')->put($path, $contents);

        return [
            'kind' => $kind,
            'path' => $path,
            'mimetype' => $mimeType,
            'filename' => Str::limit($filename, 180, ''),
            'size' => strlen($contents),
        ];
    }
}
