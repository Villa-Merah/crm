<x-filament-panels::page>
    @php
        $rupiah = fn ($value) => $value >= 1000000000 ? 'Rp '.number_format($value / 1000000000, 1, ',', '.').' M' : ($value >= 1000000 ? 'Rp '.number_format($value / 1000000, 1, ',', '.').' jt' : 'Rp '.number_format($value, 0, ',', '.'));
        $omzetPct = $target->omzet > 0 ? min(100, round($stats['revenue'] / $target->omzet * 100)) : 0;
        $siswaPct = $target->siswa > 0 ? min(100, round($stats['students'] / $target->siswa * 100)) : 0;
        $maxTrend = max(1, $trendLeads->max(), $trendRegs->max());
        $comparisonLabel = $bulan ? 'bulan sebelumnya' : 'tahun sebelumnya';
        $comparisonBadge = function ($change) use ($comparisonLabel) {
            if ($change === null) return ['—', 'Belum ada pembanding', 'bg-slate-100 text-slate-500'];
            if ($change > 0) return ['↑', number_format(abs($change), 1, ',', '.').'% dari '.$comparisonLabel, 'bg-emerald-50 text-emerald-700'];
            if ($change < 0) return ['↓', number_format(abs($change), 1, ',', '.').'% dari '.$comparisonLabel, 'bg-rose-50 text-rose-600'];
            return ['→', 'Tetap dari '.$comparisonLabel, 'bg-slate-100 text-slate-500'];
        };
    @endphp
    <div class="space-y-5" wire:loading.class="opacity-60" wire:target="cabang,tahun,bulan,pengguna" aria-live="polite">
        <div wire:offline class="dashboard-card border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            <b>Koneksi terputus.</b> Data tidak dapat diperbarui. Periksa jaringan Anda lalu coba lagi.
        </div>
        @if($dashboardError)
            <div role="alert" class="dashboard-card flex flex-col gap-3 border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 sm:flex-row sm:items-center sm:justify-between">
                <div><b>Terjadi kendala.</b> {{ $dashboardError }}</div>
                <button type="button" wire:click="$refresh" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-rose-700">Coba lagi</button>
            </div>
        @endif
        <section class="relative overflow-hidden rounded-[18px] bg-gradient-to-br from-[#1476B8] via-[#0d6da9] to-[#075985] p-6 text-white shadow-xl shadow-sky-900/10 md:p-8">
            <div class="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-white/10"></div><div class="absolute -bottom-24 right-48 h-52 w-52 rounded-full bg-cyan-300/10"></div>
            <div class="relative flex flex-col justify-between gap-7 lg:flex-row lg:items-start">
                <div><span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">{{ now()->translatedFormat('l, d F Y') }}</span><h2 class="mt-4 text-2xl font-bold md:text-3xl">Selamat datang, {{ str(auth()->user()->name)->before(' ') }}!</h2><p class="mt-2 max-w-xl text-sm text-sky-100">{{ $dashboardTagline }}</p></div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:min-w-[520px]">
                    @foreach([['Leads hari ini',$stats['leads_today']],['Follow-up hari ini',$stats['followups_today']],['Siswa baru',$stats['new_students']],['Omzet hari ini',$rupiah($stats['revenue_today'])]] as $item)
                        <div class="rounded-xl border border-white/15 bg-white/10 p-3 backdrop-blur"><p class="text-[11px] text-sky-100">{{ $item[0] }}</p><p class="mt-2 text-xl font-bold">{{ $item[1] }}</p></div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="dashboard-card p-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <label class="text-xs font-semibold text-slate-500">Cabang<select wire:model.live="cabang" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->cabang_id }}">{{ $branch->nama }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-slate-500">Tahun<select wire:model.live="tahun" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">@foreach(range(now()->year, now()->year - 2) as $year)<option>{{ $year }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-slate-500">Bulan<select wire:model.live="bulan" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Semua bulan</option>@foreach(range(1,12) as $month)<option value="{{ $month }}">{{ now()->setMonth($month)->translatedFormat('F') }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-slate-500">Pengguna<select wire:model.live="pengguna" @disabled(auth()->user()->hasRole(\App\Enums\UserRole::Staff)) class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Semua pengguna</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label>
            </div>
            @if(auth()->user()->hasRole(\App\Enums\UserRole::AdminCabang) || auth()->user()->hasRole(\App\Enums\UserRole::Staff))
                <p class="mt-3 text-xs text-sky-700">Dashboard menampilkan agregat seluruh cabang secara bawaan. Gunakan filter Cabang untuk melihat ringkasan cabang tertentu.</p>
            @endif
            <div wire:loading class="mt-3 w-full"><div class="skeleton h-1.5 w-full"></div></div>
        </section>

        <section wire:loading.class.remove="hidden" wire:loading.class="flex" wire:target="cabang,tahun,bulan,pengguna" class="dashboard-card hidden flex-col gap-5 p-5" aria-label="Memuat data dashboard">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach(range(1, 4) as $item)
                    <div class="rounded-2xl border border-slate-100 p-4"><div class="skeleton h-10 w-10"></div><div class="skeleton mt-5 h-8 w-24"></div><div class="skeleton mt-3 h-3 w-32"></div></div>
                @endforeach
            </div>
            <div class="grid gap-4 xl:grid-cols-[1.7fr_1fr]"><div class="skeleton h-56 w-full"></div><div class="skeleton h-56 w-full"></div></div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="KPI utama">
            @foreach([['Total leads',$stats['headline_leads'],'bg-sky-50 text-[#1476B8]','heroicon-o-user-group','headline_leads',number_format($stats['headline_leads'], 0, ',', '.').' calon siswa'],['Konversi lead',$stats['conversion'].'%','bg-emerald-50 text-emerald-600','heroicon-o-arrow-trending-up','conversion',number_format($stats['registrations'], 0, ',', '.').' pendaftaran dari '.number_format($stats['leads'], 0, ',', '.').' leads'],['Siswa aktif',$stats['students'],'bg-violet-50 text-violet-600','heroicon-o-academic-cap','students',number_format($stats['registrations'], 0, ',', '.').' pendaftaran periode ini'],['Omzet tervalidasi',$rupiah($stats['headline_revenue']),'bg-amber-50 text-amber-600','heroicon-o-banknotes','headline_revenue','Pembayaran lunas atau terverifikasi']] as $card)
                @php
                    $badge = $comparisonBadge($comparisons[$card[4]]);
                @endphp
                <article class="dashboard-card p-5 transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="flex items-center justify-between gap-3"><div class="rounded-xl {{ $card[2] }} p-2.5"><x-dynamic-component :component="$card[3]" class="h-5 w-5" /></div><span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $badge[2] }}" title="Dibandingkan dengan {{ $comparisonLabel }}">{{ $badge[0] }} {{ $badge[1] }}</span></div>
                    <p class="mt-5 text-3xl font-bold text-slate-900">{{ $card[1] }}</p><p class="mt-1 text-sm font-semibold text-slate-600">{{ $card[0] }}</p><p class="mt-2 text-[11px] text-slate-400">{{ $card[5] }}</p>
                </article>
            @endforeach
        </section>

        @php
            $funnelTotal = max(1, $leadFunnel->sum('total'));
            $funnelStyles = [
                'Cold' => ['bg-sky-500', 'bg-sky-50 text-sky-700', 'border-sky-100'],
                'Warm' => ['bg-amber-400', 'bg-amber-50 text-amber-700', 'border-amber-100'],
                'Hot' => ['bg-rose-500', 'bg-rose-50 text-rose-700', 'border-rose-100'],
                'Daftar' => ['bg-emerald-500', 'bg-emerald-50 text-emerald-700', 'border-emerald-100'],
                'Tidak Berminat' => ['bg-slate-400', 'bg-slate-100 text-slate-600', 'border-slate-200'],
            ];
            $pipelineStages = $leadFunnel->where('label', '!=', 'Tidak Berminat')->values();
            $lostStage = $leadFunnel->firstWhere('label', 'Tidak Berminat');
            $sourceMax = max(1, $leadSources->max('total') ?? 0);
        @endphp
        <section class="grid gap-5 xl:grid-cols-2">
            <div class="dashboard-card p-5 md:p-6">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-bold text-slate-900">Pipeline leads</h3><p class="mt-1 text-xs text-slate-500">Perjalanan leads dari Cold hingga menjadi siswa</p></div><span class="w-fit rounded-full bg-sky-50 px-3 py-1 text-xs font-bold text-[#1476B8]">{{ number_format($stats['leads'], 0, ',', '.') }} leads</span></div>
                @if($stats['leads'] > 0)
                    <div class="mt-6 overflow-x-auto pb-2">
                        <div class="flex min-w-[650px] items-stretch gap-2">
                        @foreach($pipelineStages as $stage)
                            @php
                                $stageStyle = $funnelStyles[$stage->label] ?? ['bg-slate-400', 'bg-slate-100 text-slate-600', 'border-slate-200'];
                                $stagePct = round($stage->total / $funnelTotal * 100, 1);
                            @endphp
                            <article class="relative min-w-0 flex-1 overflow-hidden rounded-2xl border {{ $stageStyle[2] }} bg-white p-4 shadow-sm">
                                <div class="absolute inset-x-0 top-0 h-1.5 {{ $stageStyle[0] }}"></div>
                                <div class="flex items-center justify-between gap-2 pt-1"><span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tahap {{ $loop->iteration }}</span><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $stageStyle[1] }}">{{ $stagePct }}%</span></div>
                                <div class="mt-4 flex items-end justify-between gap-2"><div><p class="text-sm font-bold text-slate-700">{{ $stage->label }}</p><p class="mt-1 text-[10px] text-slate-400">dari total leads</p></div><p class="text-3xl font-bold text-slate-900">{{ number_format($stage->total, 0, ',', '.') }}</p></div>
                            </article>
                            @unless($loop->last)
                                <div class="flex w-5 shrink-0 items-center justify-center text-slate-300" aria-hidden="true"><x-heroicon-o-chevron-right class="h-5 w-5" /></div>
                            @endunless
                        @endforeach
                        </div>
                    </div>
                    @php
                        $lostPct = $lostStage ? round($lostStage->total / $funnelTotal * 100, 1) : 0;
                    @endphp
                    <div class="mt-3 flex flex-col gap-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-200 text-slate-500"><x-heroicon-o-arrow-uturn-down class="h-4 w-4" /></div>
                        <div class="min-w-0 flex-1"><p class="text-xs font-bold text-slate-700">Jalur keluar · Tidak Berminat</p><p class="mt-0.5 text-[10px] text-slate-400">Lead yang tidak melanjutkan ke tahap pendaftaran</p></div>
                        <div class="flex items-center gap-2"><b class="text-lg text-slate-800">{{ number_format($lostStage?->total ?? 0, 0, ',', '.') }}</b><span class="rounded-full bg-white px-2 py-1 text-[10px] font-bold text-slate-500">{{ $lostPct }}%</span></div>
                    </div>
                @else
                    <div class="mt-6 flex min-h-36 flex-col items-center justify-center rounded-2xl bg-slate-50 text-center"><x-heroicon-o-arrow-trending-up class="h-9 w-9 text-slate-300"/><p class="mt-3 font-semibold text-slate-600">Pipeline belum memiliki data</p><p class="mt-1 text-xs text-slate-400">Status lead akan dirangkum setelah data tersedia.</p></div>
                @endif
            </div>

            <div class="dashboard-card p-5 md:p-6">
                <div><h3 class="font-bold text-slate-900">Performa sumber leads</h3><p class="mt-1 text-xs text-slate-500">3 sumber teratas; sumber lainnya digabung sebagai Lainnya</p></div>
                <div class="mt-5 space-y-3">
                    @forelse($leadSources as $source)
                        <div class="rounded-xl border border-slate-100 p-3.5" data-lead-source="{{ $source->sumber }}">
                            <div class="flex items-center gap-3"><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-700">{{ $source->sumber }}</p><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#1476B8]" style="width: {{ max(2, $source->total / $sourceMax * 100) }}%"></div></div></div><div class="text-right"><b class="text-sm text-slate-900">{{ $source->total }} leads</b><p class="mt-0.5 text-[10px] text-emerald-600">{{ $source->registrations }} daftar · {{ $source->conversion }}%</p></div></div>
                        </div>
                    @empty
                        <div class="flex min-h-36 flex-col items-center justify-center rounded-2xl bg-slate-50 text-center"><x-heroicon-o-megaphone class="h-9 w-9 text-slate-300"/><p class="mt-3 font-semibold text-slate-600">Belum ada sumber leads</p><p class="mt-1 text-xs text-slate-400">Isi sumber lead untuk mengukur kanal akuisisi.</p></div>
                    @endforelse
                </div>
            </div>
        </section>

        @php
            $programKpiTotal = $programLeadKpis->sum('total');
            $unassignedProgramLeads = (int) optional($programLeadKpis->firstWhere('program', 'Belum memilih program'))->total;
            $programCoverage = $programKpiTotal > 0 ? round(($programKpiTotal - $unassignedProgramLeads) / $programKpiTotal * 100) : 0;
            $topProgram = $programLeadKpis->first(fn ($item) => ! in_array($item->program, ['Belum memilih program', 'Program lainnya'], true));
            $programColors = ['bg-[#1476B8]', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500', 'bg-rose-500', 'bg-cyan-500', 'bg-slate-400'];
            $programPieColors = ['#1476B8', '#10b981', '#8b5cf6', '#f59e0b', '#f43f5e', '#06b6d4', '#94a3b8'];
        @endphp
        <section class="dashboard-card overflow-hidden" aria-labelledby="program-leads-title">
            <div class="flex flex-col gap-2 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between md:px-6">
                <div>
                    <h3 id="program-leads-title" class="font-bold text-slate-900">KPI leads berdasarkan program</h3>
                    <p class="mt-1 text-xs text-slate-500">Distribusi minat program pada periode dan filter yang dipilih</p>
                </div>
                <span class="w-fit rounded-full bg-sky-50 px-3 py-1.5 text-xs font-bold text-[#1476B8]">{{ number_format($programKpiTotal, 0, ',', '.') }} leads</span>
            </div>

            @if($programKpiTotal > 0)
                <div class="grid gap-6 p-5 md:p-6 lg:grid-cols-[260px_1fr]">
                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-1">
                        <div class="rounded-2xl bg-gradient-to-br from-[#1476B8] to-[#075985] p-5 text-white">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15"><x-heroicon-o-academic-cap class="h-5 w-5" /></div>
                            <p class="mt-5 text-xs text-sky-100">Program teratas</p>
                            <p class="mt-1 line-clamp-2 text-lg font-bold">{{ $topProgram?->program ?? 'Belum tersedia' }}</p>
                            <p class="mt-2 text-sm font-semibold text-white/90">{{ number_format($topProgram?->total ?? 0, 0, ',', '.') }} leads</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50 p-5">
                            <div class="flex items-center justify-between"><span class="text-xs font-semibold text-slate-500">Cakupan pilihan program</span><span class="text-lg font-bold text-emerald-600">{{ $programCoverage }}%</span></div>
                            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-emerald-500 transition-all duration-500" style="width: {{ $programCoverage }}%"></div></div>
                            <p class="mt-3 text-xs leading-5 text-slate-500">{{ number_format($programKpiTotal - $unassignedProgramLeads, 0, ',', '.') }} dari {{ number_format($programKpiTotal, 0, ',', '.') }} leads sudah memilih program.</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @foreach($programLeadKpis as $programKpi)
                            @php
                                $programPct = $programKpiTotal > 0 ? round($programKpi->total / $programKpiTotal * 100) : 0;
                            @endphp
                            <div>
                                <div class="mb-2 flex items-center gap-3 text-sm">
                                    <span class="min-w-0 flex-1 truncate font-semibold text-slate-700" title="{{ $programKpi->program }}">{{ $programKpi->program }}</span>
                                    <span class="hidden whitespace-nowrap text-xs text-slate-400 sm:inline">{{ $programKpi->registrations }} daftar · {{ $programKpi->conversion }}% konversi</span>
                                    <span class="w-20 whitespace-nowrap text-right font-bold text-slate-800">{{ number_format($programKpi->total, 0, ',', '.') }} leads</span>
                                </div>
                                <div class="h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $programColors[$loop->index % count($programColors)] }} transition-all duration-500" style="width: {{ max(2, $programPct) }}%"></div></div>
                                <div class="mt-1.5 flex justify-between text-[10px] text-slate-400"><span>{{ $programPct }}% dari seluruh leads</span><span>{{ $rupiah($programKpi->revenue) }} omzet</span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="flex min-h-56 flex-col items-center justify-center p-8 text-center">
                    <div class="rounded-2xl bg-slate-50 p-4"><x-heroicon-o-academic-cap class="h-9 w-9 text-slate-300" /></div>
                    <p class="mt-4 font-semibold text-slate-600">Belum ada data minat program</p>
                    <p class="mt-1 max-w-sm text-xs leading-5 text-slate-400">Data akan muncul setelah lead pada periode terpilih memiliki program yang diminati.</p>
                </div>
            @endif

            <div class="border-t border-slate-100 bg-slate-50/60 p-5 md:p-6">
                <div class="mb-4">
                    <h4 class="font-bold text-slate-800">Breakdown klasifikasi program</h4>
                    <p class="mt-1 text-xs text-slate-500">Perbandingan leads berdasarkan atribut program yang diminati</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    @foreach($programLeadDimensions as $dimension)
                        @php
                            $dimensionTotal = (int) $dimension['items']->sum('total');
                            $pieCursor = 0;
                            $pieLabel = $dimension['items']->map(function ($item) use ($dimensionTotal) {
                                $percentage = $dimensionTotal > 0 ? round($item->total / $dimensionTotal * 100, 1) : 0;

                                return $item->label.': '.number_format($item->total, 0, ',', '.').' leads ('.number_format($percentage, 1, ',', '.').'%)';
                            })->implode(', ');
                        @endphp
                        <article class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between gap-2">
                                <h5 class="text-sm font-bold text-slate-800">{{ $dimension['label'] }}</h5>
                                <span class="rounded-lg bg-sky-50 px-2 py-1 text-[10px] font-bold text-[#1476B8]">{{ $dimension['items']->count() }} kelompok</span>
                            </div>
                            @if($dimensionTotal > 0)
                                <div class="mt-4 flex justify-center">
                                    <svg
                                        role="img"
                                        aria-label="Distribusi {{ $dimension['label'] }}. {{ $pieLabel }}"
                                        viewBox="0 0 260 230"
                                        style="display: block; width: 100%; max-width: 260px; height: auto;"
                                        data-program-pie="{{ $dimension['label'] }}"
                                    >
                                        <title>{{ $pieLabel }}</title>
                                        @foreach($dimension['items'] as $item)
                                            @php
                                                $slicePercentage = $item->total / $dimensionTotal * 100;
                                                $startAngle = $pieCursor / $dimensionTotal * 360 - 90;
                                                $pieCursor += $item->total;
                                                $endAngle = $pieCursor / $dimensionTotal * 360 - 90;
                                                $middleAngle = ($startAngle + $endAngle) / 2;
                                                $startRadians = deg2rad($startAngle);
                                                $endRadians = deg2rad($endAngle);
                                                $middleRadians = deg2rad($middleAngle);
                                                $centerX = 130;
                                                $centerY = 112;
                                                $radius = 70;
                                                $startX = $centerX + $radius * cos($startRadians);
                                                $startY = $centerY + $radius * sin($startRadians);
                                                $endX = $centerX + $radius * cos($endRadians);
                                                $endY = $centerY + $radius * sin($endRadians);
                                                $largeArc = $slicePercentage > 50 ? 1 : 0;
                                                $slicePath = "M {$centerX} {$centerY} L {$startX} {$startY} A {$radius} {$radius} 0 {$largeArc} 1 {$endX} {$endY} Z";
                                                $valueX = $centerX + ($radius * 0.56) * cos($middleRadians);
                                                $valueY = $centerY + ($radius * 0.56) * sin($middleRadians);
                                                $labelX = $centerX + ($radius + 22) * cos($middleRadians);
                                                $labelY = $centerY + ($radius + 22) * sin($middleRadians);
                                                $sliceColor = $programPieColors[$loop->index % count($programPieColors)];
                                                $shortLabel = \Illuminate\Support\Str::limit((string) $item->label, 17);
                                            @endphp
                                            @if($dimension['items']->count() === 1)
                                                <circle cx="{{ $centerX }}" cy="{{ $centerY }}" r="{{ $radius }}" fill="{{ $sliceColor }}">
                                                    <title>{{ $item->label }}: {{ number_format($slicePercentage, 1, ',', '.') }}% ({{ number_format($item->total, 0, ',', '.') }})</title>
                                                </circle>
                                            @else
                                                <path d="{{ $slicePath }}" fill="{{ $sliceColor }}" stroke="#ffffff" stroke-width="1.5">
                                                    <title>{{ $item->label }}: {{ number_format($slicePercentage, 1, ',', '.') }}% ({{ number_format($item->total, 0, ',', '.') }})</title>
                                                </path>
                                            @endif
                                            @if($slicePercentage >= 8)
                                                <text x="{{ $valueX }}" y="{{ $valueY }}" text-anchor="middle" dominant-baseline="middle" fill="#0f172a" font-size="10" font-weight="700">{{ number_format($slicePercentage, 1, ',', '.') }}% ({{ number_format($item->total, 0, ',', '.') }})</text>
                                            @endif
                                            <text x="{{ $labelX }}" y="{{ $labelY }}" text-anchor="middle" dominant-baseline="middle" fill="#475569" font-size="9" font-weight="600">{{ $shortLabel }}</text>
                                        @endforeach
                                    </svg>
                                </div>
                                <div class="mt-5 space-y-2.5" aria-label="Label data {{ $dimension['label'] }}">
                                    @foreach($dimension['items'] as $item)
                                        @php
                                            $dimensionPct = round($item->total / $dimensionTotal * 100, 1);
                                            $legendColor = $programPieColors[$loop->index % count($programPieColors)];
                                        @endphp
                                        <div class="flex items-start gap-2 text-xs">
                                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $legendColor }}" aria-hidden="true"></span>
                                            <span class="min-w-0 flex-1 break-words font-semibold leading-4 text-slate-600" title="{{ $item->label }}">{{ $item->label }}</span>
                                            <span class="whitespace-nowrap text-slate-400">{{ number_format($dimensionPct, 1, ',', '.') }}%</span>
                                            <b class="w-8 whitespace-nowrap text-right text-slate-800">{{ number_format($item->total, 0, ',', '.') }}</b>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-4">
                                    <div class="flex min-h-24 flex-col items-center justify-center rounded-xl bg-slate-50 px-3 text-center">
                                        <x-heroicon-o-chart-bar class="h-6 w-6 text-slate-300" />
                                        <p class="mt-2 text-[11px] text-slate-400">Belum ada data</p>
                                    </div>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="dashboard-card p-5 md:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div><h3 class="font-bold text-slate-900">Top 5 sekolah</h3><p class="mt-1 text-xs text-slate-500">Sekolah penyumbang leads terbanyak pada periode terpilih</p></div>
                    <div class="rounded-xl bg-sky-50 p-2.5 text-[#1476B8]"><x-heroicon-o-building-library class="h-5 w-5" /></div>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse($topSchools as $school)
                        @php
                            $schoolPct = $stats['leads'] > 0 ? round($school->total / $stats['leads'] * 100) : 0;
                        @endphp
                        <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3.5">
                            <div class="flex items-center gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $loop->first ? 'bg-[#1476B8] text-white' : 'bg-white text-slate-500' }} text-xs font-bold shadow-sm">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-700" title="{{ $school->nama_sekolah }}">{{ $school->nama_sekolah }}</p><p class="mt-0.5 truncate text-[11px] text-slate-400">{{ $school->kota ?: 'Wilayah belum diisi' }}</p></div>
                                <div class="text-right"><b class="text-sm text-slate-800">{{ number_format($school->total, 0, ',', '.') }}</b><p class="text-[10px] text-slate-400">{{ $schoolPct }}%</p></div>
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-[#1476B8] transition-all duration-500" style="width: {{ max(2, $schoolPct) }}%"></div></div>
                        </div>
                    @empty
                        <div class="flex min-h-56 flex-col items-center justify-center rounded-xl bg-slate-50 text-center"><x-heroicon-o-building-library class="h-9 w-9 text-slate-300"/><p class="mt-3 font-semibold text-slate-600">Belum ada data sekolah</p><p class="mt-1 text-xs text-slate-400">Lead pada periode ini belum terhubung ke sekolah.</p></div>
                    @endforelse
                </div>
            </div>

            <div
                class="dashboard-card overflow-hidden"
                wire:key="lead-location-map-{{ md5($leadMapPoints->toJson()) }}"
                data-points="{{ $leadMapPoints->toJson() }}"
                x-data="{
                    map: null,
                    points: [],
                    loadFailed: false,
                    async init() {
                        this.points = JSON.parse(this.$el.dataset.points || '[]')
                        for (let attempt = 0; attempt < 80 && !window.L; attempt++) {
                            await new Promise((resolve) => setTimeout(resolve, 100))
                        }
                        if (!window.L) {
                            this.loadFailed = true
                            return
                        }
                        if (!this.$refs.map || this.map || this.points.length === 0) return

                        this.map = L.map(this.$refs.map, {
                            scrollWheelZoom: false,
                            zoomControl: true,
                            attributionControl: true,
                        }).setView([-2.5, 118], 5)

                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 18,
                            attribution: '&copy; OpenStreetMap contributors',
                        }).addTo(this.map)

                        const bounds = []
                        this.points.forEach((point) => {
                            const radius = Math.min(28, 7 + Math.sqrt(point.total) * 3)
                            const marker = L.circleMarker([point.latitude, point.longitude], {
                                radius,
                                color: '#047857',
                                weight: 2,
                                fillColor: '#10b981',
                                fillOpacity: 0.72,
                            }).addTo(this.map)

                            const popup = document.createElement('div')
                            const city = document.createElement('strong')
                            const metric = document.createElement('p')
                            city.textContent = point.city
                            metric.textContent = new Intl.NumberFormat('id-ID').format(point.total) + ' leads'
                            metric.style.margin = '4px 0 0'
                            popup.append(city, metric)
                            marker.bindPopup(popup)

                            const tooltip = document.createElement('span')
                            tooltip.textContent = point.city + ': ' + new Intl.NumberFormat('id-ID').format(point.total)
                            marker.bindTooltip(tooltip)
                            bounds.push([point.latitude, point.longitude])
                        })

                        if (bounds.length === 1) this.map.setView(bounds[0], 9)
                        if (bounds.length > 1) this.map.fitBounds(bounds, { padding: [28, 28], maxZoom: 9 })
                        setTimeout(() => this.map?.invalidateSize(), 150)
                    },
                    destroy() {
                        this.map?.remove()
                        this.map = null
                    },
                }"
            >
                <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-start sm:justify-between md:px-6">
                    <div>
                        <h3 class="font-bold text-slate-900">Peta persebaran leads</h3>
                        <p class="mt-1 text-xs text-slate-500">Kota asal lead; kota sekolah digunakan jika kota asal belum diisi</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ number_format($mappedLeadCount, 0, ',', '.') }} terpetakan</span>
                        <div class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600"><x-heroicon-o-map-pin class="h-5 w-5" /></div>
                    </div>
                </div>

                @if($leadMapPoints->isNotEmpty())
                    <div class="relative">
                        <div wire:ignore x-ref="map" class="w-full bg-slate-100" style="height: 360px; min-height: 360px; isolation: isolate" role="img" aria-label="Peta jumlah leads berdasarkan kota"></div>
                        <div x-cloak x-show="loadFailed" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-slate-50 p-8 text-center">
                            <x-heroicon-o-globe-alt class="h-10 w-10 text-slate-300"/>
                            <p class="mt-3 font-semibold text-slate-600">Layanan peta belum dapat dimuat</p>
                            <p class="mt-1 max-w-sm text-xs leading-5 text-slate-400">Data lokasi tetap tersedia. Periksa akses browser ke CDN Leaflet dan tile OpenStreetMap.</p>
                        </div>
                    </div>
                    <div class="border-t border-slate-100 bg-slate-50/70 px-5 py-3 text-[11px] text-slate-500 md:px-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span>Ukuran lingkaran menunjukkan jumlah leads pada filter aktif.</span>
                            <span>Cakupan seluruh leads: <b class="text-slate-700">{{ $mapCoverage }}%</b></span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2" aria-label="Top 5 wilayah atau kota">
                            <span class="font-bold text-slate-600">Top 5 wilayah/kota:</span>
                            @foreach($topCities as $city)
                                <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-600">{{ $city->kota }} · {{ number_format($city->total, 0, ',', '.') }}</span>
                            @endforeach
                        </div>
                        @if($unlocatedLeadCount > 0)
                            <p class="mt-2 text-slate-500">{{ number_format($unlocatedLeadCount, 0, ',', '.') }} leads belum memiliki kota asal maupun kota sekolah.</p>
                        @endif
                        @if($unmappedCities->isNotEmpty())
                            <p class="mt-2 text-amber-700">Belum dikenali: {{ $unmappedCities->take(4)->join(', ') }}{{ $unmappedCities->count() > 4 ? ' dan '.($unmappedCities->count() - 4).' wilayah lainnya' : '' }}.</p>
                        @endif
                    </div>
                @else
                    <div class="flex min-h-[360px] flex-col items-center justify-center bg-slate-50 p-8 text-center">
                        <x-heroicon-o-map-pin class="h-10 w-10 text-slate-300"/>
                        <p class="mt-3 font-semibold text-slate-600">Belum ada lokasi yang dapat dipetakan</p>
                        <p class="mt-1 max-w-sm text-xs leading-5 text-slate-400">Isi kota asal lead atau kota sekolah, kemudian pilih periode yang memiliki data.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1.7fr_1fr]">
            <div class="dashboard-card p-5 md:p-6">
                <div class="flex items-start justify-between"><div><h3 class="font-bold text-slate-900">Tren leads & pendaftaran</h3><p class="mt-1 text-xs text-slate-500">Pergerakan {{ $bulan ? 'harian' : 'bulanan' }} pada periode terpilih</p></div><div class="flex gap-3 text-[11px]"><span class="flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-[#1476B8]"></i> Leads</span><span class="flex items-center gap-1"><i class="h-2 w-2 rounded-full bg-emerald-500"></i> Pendaftaran</span></div></div>
                @if($trendLeads->sum() + $trendRegs->sum() > 0)
                    <div class="mt-8 flex h-56 items-end gap-1 overflow-hidden">@foreach($days as $i => $day)@php $trendLabel = $bulan ? 'Tanggal '.$day : now()->setMonth($day)->translatedFormat('F'); @endphp<div class="group flex h-full min-w-0 flex-1 items-end justify-center gap-px" title="{{ $trendLabel }}: {{ $trendLeads[$i] }} leads, {{ $trendRegs[$i] }} pendaftaran"><div class="w-1/2 rounded-t bg-[#1476B8] transition hover:bg-[#075985]" style="height:{{ max(3,$trendLeads[$i]/$maxTrend*100) }}%"></div><div class="w-1/2 rounded-t bg-emerald-400 transition hover:bg-emerald-600" style="height:{{ max(3,$trendRegs[$i]/$maxTrend*100) }}%"></div></div>@endforeach</div>
                    <div class="mt-2 flex gap-1 text-center text-[10px] text-slate-400">@foreach($days as $day)<span class="min-w-0 flex-1">{{ $bulan ? ($loop->first || $loop->last || $day % 5 === 0 ? $day : '') : now()->setMonth($day)->translatedFormat('M') }}</span>@endforeach</div>
                @else <div class="mt-8 flex h-56 flex-col items-center justify-center rounded-xl bg-slate-50 text-center"><x-heroicon-o-chart-bar class="h-10 w-10 text-slate-300"/><p class="mt-3 font-semibold text-slate-600">Belum ada data tren</p><p class="text-xs text-slate-400">Coba pilih periode atau cabang lain.</p></div>@endif
            </div>
            <div class="dashboard-card p-5 md:p-6"><div class="flex items-start justify-between gap-3"><div><h3 class="font-bold text-slate-900">Progress target</h3><p class="mt-1 text-xs text-slate-500">Realisasi dibanding laju waktu periode</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-600">Waktu {{ $targetPace }}%</span></div>
                @foreach([['Target omzet',$rupiah($stats['revenue']).' / '.$rupiah($target->omzet),$omzetPct,'bg-[#1476B8]',$target->omzet],['Target siswa',$stats['students'].' / '.$target->siswa.' siswa',$siswaPct,'bg-emerald-500',$target->siswa]] as $progress)
                    @php
                        $targetStatus = $progress[4] <= 0 ? ['Target belum diatur', 'bg-slate-100 text-slate-500'] : ($progress[2] >= 100 ? ['Tercapai', 'bg-emerald-50 text-emerald-700'] : ($progress[2] + 10 >= $targetPace ? ['Sesuai jalur', 'bg-sky-50 text-[#1476B8]'] : ($progress[2] + 20 >= $targetPace ? ['Berisiko', 'bg-amber-50 text-amber-700'] : ['Tertinggal', 'bg-rose-50 text-rose-600'])));
                    @endphp
                    <div class="mt-7"><div class="mb-2 flex items-center justify-between gap-2 text-sm"><span class="font-semibold text-slate-700">{{ $progress[0] }}</span><div class="flex items-center gap-2"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $targetStatus[1] }}">{{ $targetStatus[0] }}</span><span class="font-bold">{{ $progress[2] }}%</span></div></div><div class="relative h-2.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $progress[3] }} transition-all duration-500" style="width:{{ $progress[2] }}%"></div><span class="absolute inset-y-0 w-0.5 bg-slate-500" style="left: {{ $targetPace }}%" title="Laju waktu {{ $targetPace }}%"></span></div><p class="mt-2 text-xs text-slate-400">{{ $progress[1] }}</p></div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="dashboard-card p-5 md:p-6"><div class="flex items-center justify-between"><div><h3 class="font-bold text-slate-900">Aktivitas terbaru</h3><p class="text-xs text-slate-500">Pembaruan tim paling baru</p></div><span class="rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-bold text-emerald-600">● Langsung</span></div>
                <div class="mt-4 divide-y divide-slate-100">@forelse($activities as $activity)<div class="flex gap-3 py-3"><div class="mt-1 h-8 w-8 shrink-0 rounded-full bg-sky-50 p-2 text-[#1476B8]"><x-heroicon-o-user-plus /></div><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-700">{{ $activity->nama }}</p><p class="text-xs text-slate-500">{{ $activity->status_lead }} · {{ $activity->petugas ?? 'Belum ditugaskan' }}</p></div><time class="ml-auto whitespace-nowrap text-[10px] text-slate-400">{{ \Carbon\Carbon::parse($activity->created_at)->diffForHumans() }}</time></div>@empty<div class="py-12 text-center text-sm text-slate-400">Belum ada aktivitas.</div>@endforelse</div>
            </div>
            <div class="dashboard-card p-5 md:p-6"><h3 class="font-bold text-slate-900">Peringkat kinerja user</h3><p class="text-xs text-slate-500">Mengutamakan hasil daftar dan konversi, bukan volume semata</p><div class="mt-5 space-y-3">@forelse($ranking as $i => $person)<div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3"><div class="flex h-9 w-9 items-center justify-center rounded-full {{ $i === 0 ? 'bg-amber-400 text-white' : 'bg-white text-slate-500' }} font-bold">{{ $i + 1 }}</div><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $person->name }}</p><div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-slate-500"><span>{{ $person->leads }} leads</span><span class="font-semibold text-emerald-600">{{ $person->registrations }} daftar</span><span>{{ $person->conversion }}% konversi</span></div></div><div class="w-16"><div class="h-1.5 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-emerald-500" style="width:{{ $ranking->max('conversion') ? $person->conversion/$ranking->max('conversion')*100 : 0 }}%"></div></div></div></div>@empty<p class="py-12 text-center text-sm text-slate-400">Belum ada data kinerja.</p>@endforelse</div></div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[1fr_1.6fr]">
            <div class="dashboard-card p-5 md:p-6"><h3 class="font-bold text-slate-900">Aksi cepat</h3><p class="text-xs text-slate-500">Pintasan pekerjaan utama</p><div class="mt-5 grid grid-cols-2 gap-3">@php
                $viewer = auth()->user();
                $quickActions = [];
                if ($viewer->canViewSalesData()) {
                    $quickActions[] = [$viewer->canManageOperationalData() ? 'Tambah lead' : 'Lihat leads','heroicon-o-user-plus','from-[#1476B8] to-[#075985]',\App\Filament\Resources\Leads\LeadResource::getUrl($viewer->canManageOperationalData() ? 'create' : 'index')];
                    $quickActions[] = [$viewer->canManageOperationalData() ? 'Catat follow-up' : 'Lihat follow-up','heroicon-o-calendar-days','from-emerald-500 to-emerald-600',\App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource::getUrl($viewer->canManageOperationalData() ? 'create' : 'index')];
                }
                $quickActions[] = [$viewer->canManageRegistrations() ? 'Pendaftaran' : 'Lihat pendaftaran','heroicon-o-document-check','from-amber-400 to-orange-500',\App\Filament\Resources\Pendaftarans\PendaftaranResource::getUrl($viewer->canManageRegistrations() ? 'create' : 'index')];
                $quickActions[] = [$viewer->canRecordPayments() ? 'Catat pembayaran' : 'Lihat pembayaran','heroicon-o-banknotes','from-violet-500 to-purple-600',\App\Filament\Resources\Pembayarans\PembayaranResource::getUrl($viewer->canRecordPayments() ? 'create' : 'index')];
            @endphp @foreach($quickActions as $action)<a href="{{ $action[3] }}" class="rounded-xl bg-gradient-to-br {{ $action[2] }} p-4 text-white transition hover:-translate-y-0.5"><x-dynamic-component :component="$action[1]" class="h-5 w-5"/><p class="mt-5 text-xs font-semibold">{{ $action[0] }}</p></a>@endforeach</div></div>
            @php
                $pendingItems = [['Pembayaran diperiksa',$pending['payments'],'bg-sky-50 text-[#1476B8]',\App\Filament\Resources\Pembayarans\PembayaranResource::getUrl()]];
                if ($viewer->canViewSalesData()) {
                    array_unshift($pendingItems, ['Follow-up lewat jadwal',$pending['followup'],'bg-amber-50 text-amber-600',\App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource::getUrl()]);
                    $pendingItems[] = ['Lead belum diproses',$pending['new_leads'],'bg-rose-50 text-rose-500',\App\Filament\Resources\Leads\LeadResource::getUrl()];
                }
            @endphp
            <div class="dashboard-card p-5 md:p-6"><div class="flex items-center justify-between"><div><h3 class="font-bold text-slate-900">Tindakan tertunda</h3><p class="text-xs text-slate-500">Item yang memerlukan perhatian</p></div><span class="rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-500">{{ collect($pendingItems)->sum(fn ($item) => $item[1]) }} tertunda</span></div><div class="mt-5 grid gap-3 {{ count($pendingItems) > 1 ? 'md:grid-cols-3' : '' }}">@foreach($pendingItems as $pendingItem)<div class="rounded-xl border border-slate-100 p-4"><div class="inline-flex h-9 w-9 items-center justify-center rounded-xl {{ $pendingItem[2] }} font-bold">{{ $pendingItem[1] }}</div><p class="mt-4 text-sm font-semibold text-slate-700">{{ $pendingItem[0] }}</p><a href="{{ $pendingItem[3] }}" class="mt-3 inline-block text-xs font-bold text-[#1476B8]">Tinjau sekarang →</a></div>@endforeach</div></div>
        </section>
    </div>
</x-filament-panels::page>

@assets
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}?v=1.9.4">
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}?v=1.9.4" data-cfasync="false"></script>
@endassets
