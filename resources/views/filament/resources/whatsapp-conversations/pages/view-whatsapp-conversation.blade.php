<x-filament-panels::page>
    @php
        $conversation = $this->getRecord();
        $account = $conversation->account;
        $messages = $this->conversationMessages();
        $contactName = $conversation->contact_name ?: 'Kontak WhatsApp';
        $contactPhone = $conversation->contact_phone_e164 ? '+'.$conversation->contact_phone_e164 : $conversation->chat_id;
    @endphp

    @include('filament.resources.whatsapp-conversations.pages.partials.chat-styles')

    <div
        wire:poll.5s="refreshConversation"
        x-data="{ scrollBottom() { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight } }"
        x-init="$nextTick(() => scrollBottom())"
        x-on:whatsapp-message-sent.window="$nextTick(() => scrollBottom())"
        class="sivmi-chat-shell"
    >
        <header class="sivmi-chat-header">
            <div class="sivmi-chat-contact">
                <div class="sivmi-chat-contact-main">
                    <div class="sivmi-chat-avatar">{{ strtoupper(mb_substr($contactName, 0, 2)) }}</div>
                    <div class="sivmi-chat-contact-copy">
                        <div class="sivmi-chat-contact-name-row">
                            <h2>{{ $contactName }}</h2>
                            <span @class(['sivmi-chat-presence', 'bg-emerald-500' => $this->canSendMessage(), 'bg-rose-500' => ! $this->canSendMessage()])></span>
                        </div>
                        <div class="sivmi-chat-contact-meta">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 3.5A1.5 1.5 0 0 1 3.5 2h2.2a1.5 1.5 0 0 1 1.45 1.12l.57 2.17a1.5 1.5 0 0 1-.44 1.48l-1.1 1.02a11.04 11.04 0 0 0 6.03 6.03l1.02-1.1a1.5 1.5 0 0 1 1.48-.44l2.17.57A1.5 1.5 0 0 1 18 14.3v2.2a1.5 1.5 0 0 1-1.5 1.5h-.75C8.16 18 2 11.84 2 4.25V3.5Z" /></svg>
                            <span>{{ $contactPhone }}</span><span aria-hidden="true">•</span><span>{{ $messages->count() }} pesan</span>
                        </div>
                    </div>
                </div>

                <div class="sivmi-chat-badges">
                    <span @class(['sivmi-chat-badge', 'sivmi-chat-badge-success' => $this->canSendMessage(), 'sivmi-chat-badge-danger' => ! $this->canSendMessage()])>
                        <span @class(['sivmi-chat-badge-dot', 'bg-emerald-500' => $this->canSendMessage(), 'bg-rose-500' => ! $this->canSendMessage()])></span>
                        {{ $this->canSendMessage() ? 'WhatsApp terhubung' : 'WhatsApp tidak terhubung' }}
                    </span>
                    <span class="sivmi-chat-badge sivmi-chat-badge-info">
                        <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm-6 7a6 6 0 1 1 12 0H4Z" /></svg>
                        {{ $account?->user?->name ?: 'Akun tidak diketahui' }}
                    </span>
                    @if ($conversation->lead)
                        <a href="{{ \App\Filament\Resources\Leads\LeadResource::getUrl('view', ['record' => $conversation->lead_id]) }}" class="sivmi-chat-badge sivmi-chat-badge-lead">
                            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a5 5 0 1 0 0 10 5 5 0 0 0 0-10ZM3 18a7 7 0 0 1 14 0H3Z" /></svg>
                            Lead: {{ $conversation->lead->nama }}
                        </a>
                    @endif
                </div>
            </div>
        </header>

        <div x-ref="messages" class="sivmi-chat-messages">
            <div class="sivmi-chat-message-list">
                @php
                    $previousDate = null;
                @endphp
                @forelse ($messages as $message)
                    @php
                        $outbound = $message->direction === \App\Enums\WhatsAppMessageDirection::Outbound;
                        $status = $message->processing_status;
                        $messageDate = $message->sent_at?->format('Y-m-d') ?: 'tanpa-tanggal';
                        $mediaKind = str_starts_with((string) $message->message_type, 'image') ? 'image' : (str_starts_with((string) $message->message_type, 'video') ? 'video' : null);
                        $mediaUrl = $message->media_path ? route('waha.messages.media', $message) : null;
                    @endphp

                    @if ($messageDate !== $previousDate)
                        <div class="sivmi-chat-date-divider" wire:key="whatsapp-date-{{ $messageDate }}">
                            <span>
                                @if ($message->sent_at?->isToday())
                                    Hari ini
                                @elseif ($message->sent_at?->isYesterday())
                                    Kemarin
                                @else
                                    {{ $message->sent_at?->translatedFormat('d F Y') ?: 'Tanpa tanggal' }}
                                @endif
                            </span>
                        </div>
                        @php($previousDate = $messageDate)
                    @endif

                    <div @class(['sivmi-chat-message-row', 'sivmi-chat-message-row-outbound' => $outbound, 'sivmi-chat-message-row-inbound' => ! $outbound]) wire:key="whatsapp-message-{{ $message->getKey() }}">
                        @if (! $outbound)
                            <div class="sivmi-chat-message-avatar">{{ strtoupper(mb_substr($contactName, 0, 2)) }}</div>
                        @endif
                        <div @class(['sivmi-chat-bubble', 'sivmi-chat-bubble-outbound' => $outbound, 'sivmi-chat-bubble-inbound' => ! $outbound])>
                            @if ($mediaKind === 'image' && $mediaUrl)
                                <a href="{{ $mediaUrl }}" target="_blank" rel="noopener" class="sivmi-chat-media-link" aria-label="Buka gambar ukuran penuh">
                                    <img src="{{ $mediaUrl }}" alt="{{ $message->body ?: 'Gambar WhatsApp' }}" loading="lazy" class="sivmi-chat-media-image">
                                </a>
                            @elseif ($mediaKind === 'video' && $mediaUrl)
                                <video controls preload="metadata" class="sivmi-chat-media-video">
                                    <source src="{{ $mediaUrl }}" type="{{ data_get($message->raw_payload, 'media.mimetype', 'video/mp4') }}">
                                    Browser tidak mendukung pemutar video.
                                </video>
                            @elseif ($message->has_media)
                                <div class="sivmi-chat-media-unavailable">Media belum tersedia untuk ditampilkan.</div>
                            @endif
                            @if ($message->body)
                                <p @class(['sivmi-chat-message-body', 'sivmi-chat-message-caption' => $message->has_media])>{{ $message->body }}</p>
                            @elseif (! $message->has_media)
                                <p class="sivmi-chat-message-body">[Pesan tanpa teks]</p>
                            @endif
                            <div @class(['sivmi-chat-message-time', 'sivmi-chat-message-time-outbound' => $outbound, 'sivmi-chat-message-time-inbound' => ! $outbound])>
                                <time datetime="{{ $message->sent_at?->toIso8601String() }}">{{ $message->sent_at?->format('H:i') }}</time>
                                @if ($outbound)
                                    <span aria-label="{{ $status->label() }}" title="{{ $status->label() }}">
                                        @if ($status === \App\Enums\WhatsAppMessageStatus::Failed)
                                            · Gagal
                                        @elseif (in_array($status, [\App\Enums\WhatsAppMessageStatus::Read, \App\Enums\WhatsAppMessageStatus::Played], true))
                                            · Dibaca
                                        @elseif ($status === \App\Enums\WhatsAppMessageStatus::Delivered)
                                            · Diterima
                                        @else
                                            · Terkirim
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="sivmi-chat-empty">
                        <div class="sivmi-chat-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm3.75 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM21 12c0 4.142-4.03 7.5-9 7.5a10.1 10.1 0 0 1-3.494-.615L3 20.25l1.46-4.38A6.48 6.48 0 0 1 3 12c0-4.142 4.03-7.5 9-7.5s9 3.358 9 7.5Z" /></svg></div>
                        <p>Belum ada pesan</p>
                        <span>Percakapan akan ditampilkan setelah pesan diterima atau dikirim.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <form wire:submit="sendMessage" class="sivmi-chat-composer" x-data="{ uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true" x-on:livewire-upload-finish="uploading = false" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress">
            <div class="sivmi-chat-composer-inner">
                @if ($attachment)
                    <div class="sivmi-chat-attachment-preview">
                        <div class="sivmi-chat-attachment-icon">
                            @if (str_starts_with((string) $attachment->getMimeType(), 'image/'))
                                <img src="{{ $attachment->temporaryUrl() }}" alt="Preview lampiran">
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-2.36A.75.75 0 0 1 21.5 8.8v6.4a.75.75 0 0 1-1.03.7l-4.72-2.36M5.25 6.75h8.25A2.25 2.25 0 0 1 15.75 9v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V9a2.25 2.25 0 0 1 2.25-2.25Z" /></svg>
                            @endif
                        </div>
                        <div class="sivmi-chat-attachment-copy"><strong>{{ $attachment->getClientOriginalName() }}</strong><span>{{ number_format($attachment->getSize() / 1048576, 2) }} MB</span></div>
                        <button type="button" wire:click="removeAttachment" class="sivmi-chat-attachment-remove" aria-label="Hapus lampiran">&times;</button>
                    </div>
                @endif
                <div x-show="uploading" x-cloak class="sivmi-chat-upload-progress"><span :style="`width: ${progress}%`"></span></div>
                <div class="sivmi-chat-composer-row">
                    <label @class(['sivmi-chat-attach', 'sivmi-chat-attach-disabled' => ! $this->canSendMessage()]) title="Lampirkan gambar atau video">
                        <input id="chatAttachment" type="file" wire:model="attachment" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime" @disabled(! $this->canSendMessage())>
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15.5 9.5-5.8 5.8a4 4 0 0 1-5.66-5.66l6.15-6.14a2.75 2.75 0 0 1 3.89 3.89l-6.16 6.15a1.5 1.5 0 0 1-2.12-2.12l5.45-5.45" /></svg>
                    </label>
                    <div class="sivmi-chat-input-wrap">
                        <label for="messageBody" class="sr-only">Tulis pesan WhatsApp</label>
                        <textarea id="messageBody" wire:model="messageBody" rows="1" maxlength="4000" placeholder="Tulis pesan WhatsApp..." x-on:keydown.ctrl.enter.prevent="$el.form.requestSubmit()" x-on:keydown.meta.enter.prevent="$el.form.requestSubmit()" @disabled(! $this->canSendMessage()) class="sivmi-chat-input"></textarea>
                    </div>
                    <button type="submit" wire:loading.attr="disabled" wire:target="sendMessage,attachment" @disabled(! $this->canSendMessage()) class="sivmi-chat-send">
                        <svg wire:loading.remove wire:target="sendMessage" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3.105 2.289a.75.75 0 0 0-.826.95l1.414 4.949a.75.75 0 0 0 .634.538l6.87.858-6.87.858a.75.75 0 0 0-.634.538l-1.414 4.95a.75.75 0 0 0 .95.926l14.46-6.25a.75.75 0 0 0 0-1.377L3.105 2.289Z" /></svg>
                        <span wire:loading.remove wire:target="sendMessage">Kirim</span><span wire:loading wire:target="sendMessage">Mengirim...</span>
                    </button>
                </div>

                @error('messageBody')
                    <p class="sivmi-chat-composer-error">{{ $message }}</p>
                @enderror
                @error('attachment')
                    <p class="sivmi-chat-composer-error">{{ $message }}</p>
                @enderror
                @if (! $this->canSendMessage())
                    <p class="sivmi-chat-composer-error">Aktifkan session WAHA hingga berstatus Working sebelum mengirim pesan.</p>
                @else
                    <div class="sivmi-chat-composer-help"><span>Pesan dikirim melalui {{ $account?->session_name ?: 'session WhatsApp aktif' }}</span><span class="sivmi-chat-shortcut">Ctrl + Enter untuk mengirim</span></div>
                @endif
            </div>
        </form>
    </div>
</x-filament-panels::page>
