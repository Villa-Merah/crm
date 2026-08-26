<x-filament-widgets::widget>
    @php
        $statTones = [
            'sky' => 'bg-sky-50 text-[#1476B8]',
            'emerald' => 'bg-emerald-50 text-emerald-600',
            'violet' => 'bg-violet-50 text-violet-600',
            'amber' => 'bg-amber-50 text-amber-600',
        ];
        $issueTones = [
            'danger' => 'border-rose-100 bg-rose-50 text-rose-600',
            'warning' => 'border-amber-100 bg-amber-50 text-amber-600',
            'info' => 'border-sky-100 bg-sky-50 text-[#1476B8]',
        ];
    @endphp

    <div class="space-y-5" wire:poll.30s aria-live="polite">
        <section class="dashboard-card p-5 md:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-xs font-bold text-[#1476B8]">4</span><h2 class="font-bold text-slate-900">Statistik pesan dan percakapan</h2></div>
                    <p class="mt-2 text-xs text-slate-500">Ringkasan aktivitas pada akun yang boleh diakses sesuai role dan cabang.</p>
                </div>
                <a href="{{ $inboxUrl }}" class="inline-flex w-fit items-center gap-2 rounded-xl bg-[#1476B8] px-3.5 py-2 text-xs font-bold text-white transition hover:bg-[#075985]"><x-heroicon-o-inbox class="h-4 w-4" /> Buka Inbox WhatsApp</a>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <article class="rounded-2xl border border-slate-100 p-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $statTones[$stat['tone']] }}"><x-dynamic-component :component="$stat['icon']" class="h-5 w-5" /></div>
                        <p class="mt-4 text-2xl font-bold text-slate-900">{{ number_format($stat['value'], 0, ',', '.') }}</p>
                        <p class="mt-1 text-sm font-semibold text-slate-700">{{ $stat['label'] }}</p>
                        <p class="mt-1 text-[11px] text-slate-400">{{ $stat['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="dashboard-card p-5 md:p-6">
            <div class="flex items-start justify-between gap-3">
                <div><div class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-rose-50 text-xs font-bold text-rose-600">5</span><h2 class="font-bold text-slate-900">Masalah yang perlu ditangani</h2></div><p class="mt-2 text-xs text-slate-500">Prioritas operasional berdasarkan session, pencocokan lead, pesan, dan webhook.</p></div>
                <span class="rounded-full {{ $issues->isEmpty() ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }} px-3 py-1 text-xs font-bold">{{ $issues->sum('count') }} masalah</span>
            </div>
            @if($issues->isEmpty())
                <div class="mt-5 flex min-h-32 flex-col items-center justify-center rounded-2xl border border-emerald-100 bg-emerald-50/60 p-6 text-center"><x-heroicon-o-check-circle class="h-9 w-9 text-emerald-500" /><p class="mt-3 text-sm font-bold text-emerald-700">Tidak ada masalah aktif</p><p class="mt-1 text-xs text-emerald-600/80">Session, pencocokan, pesan, dan webhook berada dalam kondisi normal.</p></div>
            @else
                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($issues as $issue)
                        <article class="rounded-2xl border p-4 {{ $issueTones[$issue['tone']] ?? $issueTones['info'] }}">
                            <div class="flex items-center justify-between gap-3"><p class="text-sm font-bold">{{ $issue['label'] }}</p><span class="flex h-8 min-w-8 items-center justify-center rounded-lg bg-white px-2 text-sm font-bold shadow-sm">{{ $issue['count'] }}</span></div>
                            <p class="mt-3 text-[11px] leading-5 opacity-80">{{ $issue['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="dashboard-card overflow-hidden">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between md:px-6">
                <div><div class="flex items-center gap-2"><span class="flex h-7 w-7 items-center justify-center rounded-lg bg-violet-50 text-xs font-bold text-violet-600">6</span><h2 class="font-bold text-slate-900">Riwayat sinkronisasi dan audit</h2></div><p class="mt-2 text-xs text-slate-500">Jejak pembaruan akun, event webhook, dan perubahan konfigurasi terbaru.</p></div>
                <a href="{{ $auditUrl }}" class="text-xs font-bold text-[#1476B8] hover:text-[#075985]">Lihat seluruh audit →</a>
            </div>
            <div class="grid divide-y divide-slate-100 lg:grid-cols-3 lg:divide-x lg:divide-y-0">
                <div class="p-5 md:p-6">
                    <h3 class="text-sm font-bold text-slate-800">Sinkronisasi akun</h3>
                    <div class="mt-4 space-y-3">
                        @forelse($accounts as $account)
                            @php
                                $statusTone = match($account->status) {
                                    \App\Enums\WhatsAppAccountStatus::Working => 'bg-emerald-500',
                                    \App\Enums\WhatsAppAccountStatus::Connecting => 'bg-amber-400',
                                    \App\Enums\WhatsAppAccountStatus::Failed => 'bg-rose-500',
                                    default => 'bg-slate-400',
                                };
                            @endphp
                            <div class="flex items-start gap-3"><span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $statusTone }} ring-4 ring-slate-50"></span><div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-slate-700">{{ $account->user?->name ?? $account->session_name }}</p><p class="mt-1 truncate text-[10px] text-slate-400">{{ $account->session_name }} · {{ $account->last_seen_at?->diffForHumans() ?? 'belum disinkronkan' }}</p></div></div>
                        @empty
                            <p class="py-8 text-center text-xs text-slate-400">Belum ada akun WhatsApp.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-5 md:p-6">
                    <h3 class="text-sm font-bold text-slate-800">Event webhook</h3>
                    <div class="mt-4 space-y-3">
                        @forelse($recentWebhooks as $event)
                            <div class="flex items-start gap-3"><div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $event->status === 'Failed' ? 'bg-rose-50 text-rose-600' : ($event->status === 'Pending' ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600') }}"><x-heroicon-o-bolt class="h-4 w-4" /></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-slate-700">{{ $event->event_name }}</p><p class="mt-1 truncate text-[10px] text-slate-400">{{ $event->session_name }} · {{ $event->status }} · {{ $event->created_at->diffForHumans() }}</p></div></div>
                        @empty
                            <p class="py-8 text-center text-xs text-slate-400">Belum ada event webhook.</p>
                        @endforelse
                    </div>
                </div>

                <div class="p-5 md:p-6">
                    <h3 class="text-sm font-bold text-slate-800">Audit konfigurasi</h3>
                    <div class="mt-4 space-y-3">
                        @forelse($recentActivities as $activity)
                            <div class="flex items-start gap-3"><div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600"><x-heroicon-o-clipboard-document-list class="h-4 w-4" /></div><div class="min-w-0 flex-1"><p class="truncate text-xs font-bold text-slate-700">{{ $activity->description }}</p><p class="mt-1 truncate text-[10px] text-slate-400">{{ $activity->user?->name ?? 'Sistem' }} · {{ $activity->created_at->diffForHumans() }}</p></div></div>
                        @empty
                            <p class="py-8 text-center text-xs text-slate-400">Belum ada aktivitas konfigurasi.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
