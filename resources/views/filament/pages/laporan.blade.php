<x-filament-panels::page>
    @php
        $rupiah = fn ($value) => $value >= 1000000000 ? 'Rp '.number_format($value / 1000000000, 1, ',', '.').' M' : ($value >= 1000000 ? 'Rp '.number_format($value / 1000000, 1, ',', '.').' jt' : 'Rp '.number_format($value, 0, ',', '.'));
        $maxTrend = max(1, $trendLeads->max(), $trendRegistrations->max());
        $funnelMax = max(1, (int) $funnel->max('total'));
        $paymentMax = max(1, (float) $paymentMethods->max('total'));
    @endphp

    <div class="space-y-5" aria-live="polite">
        <div wire:offline class="dashboard-card border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            <b>Koneksi terputus.</b> Laporan tidak dapat diperbarui sampai koneksi kembali tersedia.
        </div>

        @if($reportError)
            <div role="alert" class="dashboard-card flex flex-col gap-3 border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 sm:flex-row sm:items-center sm:justify-between">
                <div><b>Terjadi kendala.</b> {{ $reportError }}</div>
                <button type="button" wire:click="$refresh" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white">Coba lagi</button>
            </div>
        @endif

        <section class="relative overflow-hidden rounded-[18px] bg-gradient-to-br from-[#1476B8] via-[#0d6da9] to-[#075985] p-6 text-white shadow-xl shadow-sky-900/10 md:p-8">
            <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">{{ $periodLabel }}</span>
                    <h2 class="mt-4 text-2xl font-bold md:text-3xl">Laporan kinerja SIVMI</h2>
                    <p class="mt-2 max-w-2xl text-sm text-sky-100">Analisis funnel calon siswa, pendaftaran, omzet terverifikasi, dan capaian target berdasarkan filter yang dipilih.</p>
                </div>
                @if($this->canExport())<button type="button" wire:click="exportCsv" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/15 px-4 py-3 text-sm font-bold text-white transition hover:bg-white/25 disabled:opacity-60">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" />
                    <span wire:loading.remove wire:target="exportCsv">Ekspor CSV</span>
                    <span wire:loading wire:target="exportCsv">Menyiapkan...</span>
                </button>@endif
            </div>
        </section>

        <section class="dashboard-card p-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <label class="text-xs font-semibold text-slate-500">Cabang
                    <select wire:model.live="cabang" @disabled(! auth()->user()->hasGlobalBranchAccess()) class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                        <option value="">Semua cabang</option>
                        @foreach($branches as $branch)<option value="{{ $branch->cabang_id }}">{{ $branch->nama }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">Tahun
                    <select wire:model.live="tahun" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                        @foreach($years as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">Bulan
                    <select wire:model.live="bulan" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                        @foreach(range(1, 12) as $month)<option value="{{ $month }}">{{ now()->setMonth($month)->translatedFormat('F') }}</option>@endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-slate-500">Pengguna
                    <select wire:model.live="pengguna" @disabled(auth()->user()->hasRole(\App\Enums\UserRole::Staff)) class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                        <option value="">Semua pengguna</option>
                        @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                    </select>
                </label>
            </div>
            <div wire:loading class="mt-3 w-full" wire:target="cabang,tahun,bulan,pengguna"><div class="skeleton h-1.5 w-full"></div></div>
        </section>

        <section wire:loading.class.remove="hidden" wire:loading.class="flex" wire:target="cabang,tahun,bulan,pengguna" class="dashboard-card hidden flex-col gap-5 p-5" aria-label="Memuat laporan">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach(range(1, 4) as $item)<div class="rounded-2xl border border-slate-100 p-4"><div class="skeleton h-5 w-24"></div><div class="skeleton mt-5 h-9 w-32"></div></div>@endforeach</div>
            <div class="skeleton h-64 w-full"></div>
        </section>

        <div wire:loading.remove wire:target="cabang,tahun,bulan,pengguna" class="space-y-5">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['Total leads', $stats['leads'], 'heroicon-o-user-group', 'bg-sky-50 text-[#1476B8]'],
                    ['Pendaftaran', $stats['registrations'], 'heroicon-o-document-check', 'bg-emerald-50 text-emerald-600'],
                    ['Rasio konversi', number_format($stats['conversion'], 1, ',', '.').'%', 'heroicon-o-arrow-trending-up', 'bg-violet-50 text-violet-600'],
                    ['Omzet terverifikasi', $rupiah($stats['revenue']), 'heroicon-o-banknotes', 'bg-amber-50 text-amber-600'],
                ] as $card)
                    <div class="dashboard-card p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                        <div class="flex items-center justify-between"><span class="rounded-xl {{ $card[3] }} p-2.5"><x-dynamic-component :component="$card[2]" class="h-5 w-5" /></span><span class="text-xs text-slate-400">{{ $periodLabel }}</span></div>
                        <p class="mt-5 text-3xl font-bold text-slate-900">{{ $card[1] }}</p><p class="mt-1 text-sm text-slate-500">{{ $card[0] }}</p>
                    </div>
                @endforeach
            </section>

            @if(! $hasData)
                <section class="dashboard-card flex min-h-72 flex-col items-center justify-center p-8 text-center">
                    <div class="rounded-2xl bg-slate-100 p-4 text-slate-400"><x-heroicon-o-document-magnifying-glass class="h-9 w-9" /></div>
                    <h3 class="mt-4 font-bold text-slate-700">Belum ada data laporan</h3>
                    <p class="mt-1 max-w-md text-sm text-slate-400">Tidak ditemukan aktivitas pada cabang, periode, dan pengguna yang dipilih.</p>
                </section>
            @else
                <section class="dashboard-card p-5 md:p-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-bold text-slate-900">Tren leads dan pendaftaran</h3><p class="mt-1 text-xs text-slate-500">Pergerakan harian selama {{ $periodLabel }}</p></div><div class="flex gap-4 text-xs"><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-[#1476B8]"></i>Leads</span><span class="flex items-center gap-1.5"><i class="h-2.5 w-2.5 rounded-full bg-emerald-500"></i>Pendaftaran</span></div></div>
                    <div class="mt-8 flex h-64 items-end gap-1 overflow-hidden">
                        @foreach($days as $index => $day)
                            <div class="group flex min-w-0 flex-1 items-end justify-center gap-px" title="Tanggal {{ $day }}: {{ $trendLeads[$index] }} leads, {{ $trendRegistrations[$index] }} pendaftaran">
                                <div class="w-1/2 rounded-t bg-[#1476B8] transition hover:bg-[#075985]" style="height:{{ max(2, $trendLeads[$index] / $maxTrend * 100) }}%"></div>
                                <div class="w-1/2 rounded-t bg-emerald-400 transition hover:bg-emerald-600" style="height:{{ max(2, $trendRegistrations[$index] / $maxTrend * 100) }}%"></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="grid gap-5 xl:grid-cols-2">
                    <div class="dashboard-card p-5 md:p-6"><h3 class="font-bold text-slate-900">Funnel leads</h3><p class="mt-1 text-xs text-slate-500">Distribusi status lead pada periode terpilih</p>
                        <div class="mt-6 space-y-4">@forelse($funnel as $item)<div><div class="mb-1.5 flex justify-between text-sm"><span class="font-semibold text-slate-600">{{ $item->status_lead }}</span><b>{{ $item->total }}</b></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-[#1476B8] to-sky-400" style="width:{{ $item->total / $funnelMax * 100 }}%"></div></div></div>@empty<p class="py-10 text-center text-sm text-slate-400">Belum ada funnel.</p>@endforelse</div>
                    </div>
                    <div class="dashboard-card p-5 md:p-6"><h3 class="font-bold text-slate-900">Metode pembayaran</h3><p class="mt-1 text-xs text-slate-500">Omzet terverifikasi berdasarkan kanal pembayaran</p>
                        <div class="mt-6 space-y-4">@forelse($paymentMethods as $item)<div><div class="mb-1.5 flex justify-between gap-3 text-sm"><span class="font-semibold text-slate-600">{{ $item->metode_pembayaran ?? 'Tidak dicatat' }} <small class="font-normal text-slate-400">({{ $item->transactions }} transaksi)</small></span><b>{{ $rupiah($item->total) }}</b></div><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-violet-500 to-purple-400" style="width:{{ $item->total / $paymentMax * 100 }}%"></div></div></div>@empty<p class="py-10 text-center text-sm text-slate-400">Belum ada pembayaran terverifikasi.</p>@endforelse</div>
                    </div>
                </section>

                <section class="dashboard-card overflow-hidden">
                    <div class="p-5 md:p-6"><h3 class="font-bold text-slate-900">Kinerja cabang</h3><p class="mt-1 text-xs text-slate-500">Realisasi periode terhadap target aktif</p></div>
                    <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Cabang</th><th class="px-5 py-3">Leads</th><th class="px-5 py-3">Pendaftaran</th><th class="px-5 py-3">Konversi</th><th class="px-5 py-3">Omzet</th><th class="px-5 py-3">Capaian omzet</th><th class="px-5 py-3">Capaian siswa</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @foreach($branchesReport as $row)<tr class="transition hover:bg-slate-50"><td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-800">{{ $row['name'] }}</td><td class="px-5 py-4">{{ $row['leads'] }}</td><td class="px-5 py-4">{{ $row['registrations'] }}</td><td class="px-5 py-4">{{ number_format($row['conversion'], 1, ',', '.') }}%</td><td class="whitespace-nowrap px-5 py-4 font-semibold">{{ $rupiah($row['revenue']) }}</td><td class="min-w-44 px-5 py-4"><div class="flex justify-between text-xs"><span>{{ number_format($row['revenue_progress'], 1, ',', '.') }}%</span><span class="text-slate-400">{{ $rupiah($row['target_revenue']) }}</span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#1476B8]" style="width:{{ min(100, $row['revenue_progress']) }}%"></div></div></td><td class="min-w-40 px-5 py-4"><div class="flex justify-between text-xs"><span>{{ number_format($row['student_progress'], 1, ',', '.') }}%</span><span class="text-slate-400">{{ $row['target_students'] }} siswa</span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-emerald-500" style="width:{{ min(100, $row['student_progress']) }}%"></div></div></td></tr>@endforeach
                    </tbody></table></div>
                </section>

                <section class="dashboard-card overflow-hidden">
                    <div class="p-5 md:p-6"><h3 class="font-bold text-slate-900">Performa program bimbel</h3><p class="mt-1 text-xs text-slate-500">Program berdasarkan jumlah pendaftaran pada periode terpilih</p></div>
                    <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-y border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Program</th><th class="px-5 py-3">Pendaftaran</th><th class="px-5 py-3">Omzet terverifikasi</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($programsReport as $program)<tr class="hover:bg-slate-50"><td class="px-5 py-4 font-semibold text-slate-800">{{ $program['name'] }}</td><td class="px-5 py-4">{{ $program['registrations'] }} siswa</td><td class="px-5 py-4 font-semibold">{{ $rupiah($program['revenue']) }}</td></tr>@empty<tr><td colspan="3" class="px-5 py-10 text-center text-slate-400">Belum ada pendaftaran program.</td></tr>@endforelse</tbody></table></div>
                </section>
            @endif
        </div>
    </div>
</x-filament-panels::page>
