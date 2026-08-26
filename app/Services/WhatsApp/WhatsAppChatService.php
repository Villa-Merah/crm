<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppAccountStatus;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Support\CrmNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class WhatsAppChatService
{
    public function __construct(
        private readonly WahaClient $client,
        private readonly CrmNotifier $notifier,
    ) {}

    public function sendText(WhatsAppConversation $conversation, User $actor, string $body): WhatsAppMessage
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 4000) {
            throw new RuntimeException('Pesan harus berisi 1 sampai 4.000 karakter.');
        }

        $visible = WhatsAppConversation::query()->visibleTo($actor)->whereKey($conversation)->exists();
        if (! $visible) {
            throw new AuthorizationException('Anda tidak berhak mengirim pesan dari percakapan ini.');
        }

        $conversation->loadMissing('account');
        $account = $conversation->account;
        if (! $account || ! $account->aktif || $account->status !== WhatsAppAccountStatus::Working) {
            throw new RuntimeException('Koneksi WhatsApp belum aktif. Pastikan session WAHA berstatus Working.');
        }

        try {
            $response = $this->client->sendText($account->session_name, $conversation->chat_id, $body);
            $externalId = $this->externalId($response);
            $sentAt = $this->sentAt($response);

            return DB::transaction(function () use ($conversation, $account, $actor, $body, $response, $externalId, $sentAt): WhatsAppMessage {
                $message = WhatsAppMessage::query()->firstOrCreate(
                    ['whatsapp_account_id' => $account->getKey(), 'external_message_id' => $externalId],
                    [
                        'conversation_id' => $conversation->getKey(),
                        'lead_id' => $conversation->lead_id,
                        'direction' => WhatsAppMessageDirection::Outbound,
                        'message_type' => 'text',
                        'body' => $body,
                        'has_media' => false,
                        'sent_at' => $sentAt,
                        'from_me' => true,
                        'processing_status' => WhatsAppMessageStatus::Sent,
                        'raw_payload' => ['source' => 'crm', 'response' => $response],
                    ],
                );

                $conversation->update([
                    'first_message_at' => $conversation->first_message_at ?? $sentAt,
                    'last_message_at' => $sentAt,
                ]);

                ActivityLog::create([
                    'user_id' => $actor->getKey(),
                    'cabang_id' => $account->cabang_id,
                    'subject_type' => $conversation->getMorphClass(),
                    'subject_id' => $conversation->getKey(),
                    'event' => 'whatsapp_message_sent',
                    'description' => 'Pesan WhatsApp dikirim melalui CRM',
                    'properties' => ['message_id' => $message->getKey(), 'status' => WhatsAppMessageStatus::Sent->value],
                    'created_at' => now(),
                ]);

                return $message;
            });
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure($conversation, $actor, $body, $exception);
            throw new RuntimeException('Pesan gagal dikirim melalui WAHA. Silakan periksa koneksi dan coba lagi.', previous: $exception);
        }
    }

    public function sendMedia(WhatsAppConversation $conversation, User $actor, UploadedFile $file, ?string $caption = null): WhatsAppMessage
    {
        $caption = filled($caption) ? trim((string) $caption) : null;
        if ($caption !== null && mb_strlen($caption) > 4000) {
            throw new RuntimeException('Caption maksimal 4.000 karakter.');
        }

        $mimeType = (string) ($file->getMimeType() ?: $file->getClientMimeType());
        $kind = str_starts_with($mimeType, 'image/') ? 'image' : (str_starts_with($mimeType, 'video/') ? 'video' : null);
        if (! $kind || ! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'], true)) {
            throw new RuntimeException('Lampiran harus berupa JPG, PNG, WebP, MP4, atau MOV.');
        }

        $this->assertCanSend($conversation, $actor);
        $account = $conversation->account;
        $originalName = Str::limit($file->getClientOriginalName(), 180, '');
        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            throw new RuntimeException('Lampiran tidak dapat dibaca.');
        }

        $path = null;

        try {
            $response = $kind === 'image'
                ? $this->client->sendImage($account->session_name, $conversation->chat_id, $mimeType, $originalName, $contents, $caption)
                : $this->client->sendVideo($account->session_name, $conversation->chat_id, $mimeType, $originalName, $contents, $caption);

            $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: ($kind === 'image' ? 'jpg' : 'mp4'));
            $path = 'whatsapp-media/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
            Storage::disk('local')->put($path, $contents);
            $externalId = $this->externalId($response);
            $sentAt = $this->sentAt($response);

            return DB::transaction(function () use ($conversation, $account, $actor, $caption, $response, $externalId, $sentAt, $kind, $mimeType, $originalName, $path, $file): WhatsAppMessage {
                $message = WhatsAppMessage::query()->firstOrCreate(
                    ['whatsapp_account_id' => $account->getKey(), 'external_message_id' => $externalId],
                    [
                        'conversation_id' => $conversation->getKey(),
                        'lead_id' => $conversation->lead_id,
                        'direction' => WhatsAppMessageDirection::Outbound,
                        'message_type' => $kind,
                        'body' => $caption,
                        'has_media' => true,
                        'media_path' => $path,
                        'sent_at' => $sentAt,
                        'from_me' => true,
                        'processing_status' => WhatsAppMessageStatus::Sent,
                        'raw_payload' => [
                            'source' => 'crm',
                            'response' => $response,
                            'media' => ['mimetype' => $mimeType, 'filename' => $originalName, 'size' => $file->getSize()],
                        ],
                    ],
                );

                $conversation->update([
                    'first_message_at' => $conversation->first_message_at ?? $sentAt,
                    'last_message_at' => $sentAt,
                ]);

                ActivityLog::create([
                    'user_id' => $actor->getKey(),
                    'cabang_id' => $account->cabang_id,
                    'subject_type' => $conversation->getMorphClass(),
                    'subject_id' => $conversation->getKey(),
                    'event' => 'whatsapp_media_sent',
                    'description' => ucfirst($kind).' WhatsApp dikirim melalui CRM',
                    'properties' => ['message_id' => $message->getKey(), 'media_type' => $kind, 'status' => WhatsAppMessageStatus::Sent->value],
                    'created_at' => now(),
                ]);

                return $message;
            });
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            $this->recordFailure($conversation, $actor, $caption ?: '['.ucfirst($kind).']', $exception, $kind);
            throw new RuntimeException('Media gagal dikirim melalui WAHA. Silakan periksa koneksi dan coba lagi.', previous: $exception);
        }
    }

    /** @param array<string, mixed> $response */
    private function externalId(array $response): string
    {
        $value = data_get($response, 'id')
            ?? data_get($response, 'key.id')
            ?? data_get($response, '_data.Info.ID')
            ?? data_get($response, 'message.id');

        if (is_array($value)) {
            $value = data_get($value, '_serialized') ?? data_get($value, 'id');
        }

        return filled($value) ? (string) $value : 'crm-'.Str::uuid();
    }

    /** @param array<string, mixed> $response */
    private function sentAt(array $response): CarbonImmutable
    {
        $timestamp = data_get($response, 'timestamp') ?? data_get($response, 'message.timestamp');

        return is_numeric($timestamp)
            ? CarbonImmutable::createFromTimestampUTC((int) $timestamp)->setTimezone(config('app.timezone'))
            : CarbonImmutable::now(config('app.timezone'));
    }

    private function assertCanSend(WhatsAppConversation $conversation, User $actor): void
    {
        $visible = WhatsAppConversation::query()->visibleTo($actor)->whereKey($conversation)->exists();
        if (! $visible) {
            throw new AuthorizationException('Anda tidak berhak mengirim pesan dari percakapan ini.');
        }

        $conversation->loadMissing('account');
        if (! $conversation->account || ! $conversation->account->aktif || $conversation->account->status !== WhatsAppAccountStatus::Working) {
            throw new RuntimeException('Koneksi WhatsApp belum aktif. Pastikan session WAHA berstatus Working.');
        }
    }

    private function recordFailure(WhatsAppConversation $conversation, User $actor, string $body, Throwable $exception, string $messageType = 'text'): void
    {
        $account = $conversation->account;
        if (! $account) {
            return;
        }

        $message = WhatsAppMessage::create([
            'conversation_id' => $conversation->getKey(),
            'whatsapp_account_id' => $account->getKey(),
            'lead_id' => $conversation->lead_id,
            'external_message_id' => 'failed-'.Str::uuid(),
            'direction' => WhatsAppMessageDirection::Outbound,
            'message_type' => $messageType,
            'body' => $body,
            'has_media' => false,
            'sent_at' => now(),
            'from_me' => true,
            'processing_status' => WhatsAppMessageStatus::Failed,
            'raw_payload' => ['source' => 'crm', 'error' => Str::limit($exception->getMessage(), 1000)],
        ]);

        $this->notifier->whatsAppSendFailed($actor, $conversation, $message);
    }
}
