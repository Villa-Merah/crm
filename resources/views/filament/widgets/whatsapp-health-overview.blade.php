<x-filament-widgets::widget>
    @php
        $tones = [
            'success' => ['bg-emerald-50 text-emerald-600', 'bg-emerald-500', 'text-emerald-700'],
            'danger' => ['bg-rose-50 text-rose-600', 'bg-rose-500', 'text-rose-700'],
            'warning' => ['bg-amber-50 text-amber-600', 'bg-amber-500', 'text-amber-700'],
            'info' => ['bg-sky-50 text-[#1476B8]', 'bg-[#1476B8]', 'text-sky-700'],
            'neutral' => ['bg-slate-100 text-slate-500', 'bg-slate-400', 'text-slate-600'],
        ];
    @endphp

    <div class="space-y-4" wire:poll.30s aria-live="polite">
        <section class="dashboard-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between md:px-6">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-sky-50 text-xs font-bold text-[#1476B8]">1</span>
                        <h2 class="font-bold text-slate-900">Ringkasan kesehatan WhatsApp</h2>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">Status service WAHA, session pengguna, webhook, dan sinkronisasi CRM.</p>
                </div>
                <button type="button" wire:click="$refresh" class="inline-flex w-fit items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 transition hover:border-sky-200 hover:text-[#1476B8]">
                    <x-heroicon-o-arrow-path class="h-4 w-4" /> Perbarui status
                </button>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4 md:p-6">
                @foreach($healthItems as $item)
                    @php($tone = $tones[$item['tone']] ?? $tones['neutral'])
                    <article class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $tone[0] }}">
                                <x-dynamic-component :component="$item['icon']" class="h-5 w-5" />
                            </div>
                            <span class="h-2.5 w-2.5 rounded-full {{ $tone[1] }} ring-4 ring-slate-50"></span>
                        </div>
                        <p class="mt-4 text-xs font-semibold text-slate-500">{{ $item['label'] }}</p>
                        <p class="mt-1 text-lg font-bold {{ $tone[2] }}">{{ $item['value'] }}</p>
                        <p class="mt-2 text-[11px] leading-5 text-slate-400">{{ $item['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="flex flex-col gap-2 rounded-2xl border border-sky-100 bg-sky-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-xs font-bold text-[#1476B8] shadow-sm">2</span>
                <div><p class="text-sm font-bold text-slate-800">Daftar akun dan session</p><p class="text-[11px] text-slate-500">Nomor, pemilik, cabang, status, dan aktivitas sinkronisasi.</p></div>
            </div>
            <div class="flex items-center gap-2 text-[11px] font-semibold text-slate-500"><span class="flex h-6 w-6 items-center justify-center rounded-lg bg-white text-[#1476B8]">3</span> Tindakan operasional tersedia pada setiap baris</div>
        </div>
    </div>
</x-filament-widgets::widget>
