<?php

namespace App\Filament\Resources\WhatsAppConversations\Pages;

use App\Enums\WhatsAppAccountStatus;
use App\Filament\Resources\WhatsAppConversations\WhatsAppConversationResource;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppChatService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

class ViewWhatsAppConversation extends ViewRecord
{
    use WithFileUploads;

    protected static string $resource = WhatsAppConversationResource::class;

    protected string $view = 'filament.resources.whatsapp-conversations.pages.view-whatsapp-conversation';

    public string $messageBody = '';

    public $attachment = null;

    public function getTitle(): string|HtmlString
    {
        return new HtmlString('Chat dengan '.e($this->getRecord()->contact_name ?: $this->getRecord()->contact_phone_e164 ?: 'kontak WhatsApp'));
    }

    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key)->load(['messages' => fn ($query) => $query->oldest('sent_at')]);
        if ($record->unread_count > 0) {
            $record->update(['unread_count' => 0]);
        }

        return $record;
    }

    /** @return Collection<int, WhatsAppMessage> */
    public function conversationMessages(): Collection
    {
        return $this->getRecord()->messages()->oldest('sent_at')->oldest('id')->get();
    }

    public function refreshConversation(): void
    {
        $record = $this->getRecord();
        $record->refresh()->load(['account.user', 'account.cabang', 'lead']);
        if ($record->unread_count > 0) {
            $record->update(['unread_count' => 0]);
        }
    }

    public function canSendMessage(): bool
    {
        return $this->getRecord()->account?->aktif
            && $this->getRecord()->account?->status === WhatsAppAccountStatus::Working;
    }

    public function sendMessage(WhatsAppChatService $chat): void
    {
        $this->validate([
            'messageBody' => ['nullable', 'required_without:attachment', 'string', 'max:4000'],
            'attachment' => ['nullable', 'required_without:messageBody', 'file', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime', 'max:10240'],
        ], [
            'messageBody.required_without' => 'Tulis pesan atau pilih lampiran terlebih dahulu.',
            'messageBody.max' => 'Pesan maksimal 4.000 karakter.',
            'attachment.required_without' => 'Tulis pesan atau pilih lampiran terlebih dahulu.',
            'attachment.mimetypes' => 'Lampiran harus berupa JPG, PNG, WebP, MP4, atau MOV.',
            'attachment.max' => 'Ukuran lampiran maksimal 10 MB.',
        ]);

        try {
            if ($this->attachment) {
                $chat->sendMedia($this->getRecord(), auth()->user(), $this->attachment, $this->messageBody);
            } else {
                $chat->sendText($this->getRecord(), auth()->user(), $this->messageBody);
            }
            $this->messageBody = '';
            $this->attachment = null;
            $this->resetValidation();
            $this->refreshConversation();
            $this->dispatch('whatsapp-message-sent');
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->title('Pesan tidak terkirim')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeAttachment(): void
    {
        $this->attachment = null;
        $this->resetValidation('attachment');
    }
}
