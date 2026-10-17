<x-filament-panels::page>
    <div class="followup-calendar-layout">
        <aside class="space-y-5">
            <section class="dashboard-card p-4">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-bold capitalize text-slate-800">{{ $monthLabel }}</h2>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="previousMonth" aria-label="Bulan sebelumnya" class="rounded-lg p-2 text-slate-500 transition hover:bg-sky-50 hover:text-[#1476B8]">
                            <x-heroicon-o-chevron-left class="h-4 w-4" />
                        </button>
                        <button type="button" wire:click="nextMonth" aria-label="Bulan berikutnya" class="rounded-lg p-2 text-slate-500 transition hover:bg-sky-50 hover:text-[#1476B8]">
                            <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="followup-month-grid">
                    @foreach(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
                        <span class="py-1 text-[10px] font-bold uppercase text-slate-400">{{ $weekday }}</span>
                    @endforeach
                    @foreach($calendarWeeks as $week)
                        @foreach($week as $day)
                            @php
                                $date = $day['date'];
                                $isToday = $date->isToday();
                                $isSelected = $date->toDateString() === $selectedDate;
                                $events = $day['events'];
                            @endphp
                            <button
                                type="button"
                                wire:click="selectDate('{{ $date->toDateString() }}')"
                                aria-label="{{ $date->translatedFormat('l, d F Y') }}{{ $events->isNotEmpty() ? ', '.$events->count().' jadwal' : ', tidak ada jadwal' }}"
                                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                @class([
                                    'relative mx-auto flex h-8 w-8 items-center justify-center rounded-full text-xs transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1476B8]',
                                    'font-bold text-slate-800' => $day['isCurrentMonth'] && ! $isSelected && ! $isToday,
                                    'text-slate-300' => ! $day['isCurrentMonth'] && ! $isSelected && ! $isToday,
                                    'bg-[#1476B8] font-bold text-white' => $isToday,
                                    'bg-sky-100 font-bold text-[#075985] ring-1 ring-inset ring-sky-300' => $isSelected && ! $isToday,
                                    'hover:bg-sky-50' => ! $isSelected && ! $isToday,
                                ])
                            >
                                {{ $date->day }}
                                @if($events->isNotEmpty())
                                    <span @class([
                                        'absolute bottom-0.5 h-1 w-1 rounded-full',
                                        'bg-white' => $isToday,
                                        'bg-rose-500' => ! $isToday && $events->contains(fn ($event) => $event->tanggal_followup_berikutnya->isPast()),
                                        'bg-[#1476B8]' => ! $isToday && ! $events->contains(fn ($event) => $event->tanggal_followup_berikutnya->isPast()),
                                    ])></span>
                                @endif
                            </button>
                        @endforeach
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-slate-100 pt-3 text-[10px] text-slate-500">
                    <span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-[#1476B8]"></i> Jadwal aktif</span>
                    <span class="inline-flex items-center gap-1.5"><i class="h-2 w-2 rounded-full bg-rose-500"></i> Terlambat</span>
                </div>
            </section>

            <section class="dashboard-card space-y-4 p-4">
                <div>
                    <h2 class="text-sm font-bold text-slate-800">Filter jadwal</h2>
                    <p class="mt-1 text-xs text-slate-500">Saring jadwal yang ditampilkan.</p>
                </div>
                <label class="block text-xs font-semibold text-slate-500">
                    Cari lead
                    <input wire:model.live.debounce.350ms="search" type="search" placeholder="Nama atau nomor WhatsApp" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                </label>
                <label class="block text-xs font-semibold text-slate-500">
                    Metode follow-up
                    <select wire:model.live="method" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">
                        <option value="">Semua metode</option>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Telepon">Telepon</option>
                        <option value="Tatap Muka">Tatap muka</option>
                        <option value="Email">Email</option>
                    </select>
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                    <input wire:model.live="overdueOnly" type="checkbox" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    Hanya jadwal terlambat
                </label>
            </section>

            <section class="dashboard-card overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50/70 px-4 py-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Agenda tanggal terpilih</p>
                    <h2 class="mt-1 text-sm font-bold capitalize text-slate-900">{{ $selectedDayLabel }}</h2>
                    <p class="mt-1 text-xs text-slate-500">{{ $selectedDayEvents->count() }} jadwal follow-up</p>
                </div>
                <div class="max-h-[26rem] divide-y divide-slate-100 overflow-y-auto">
                    @forelse($selectedDayEvents as $event)
                        @php
                            $eventTime = $event->tanggal_followup_berikutnya;
                            $isOverdue = $eventTime->isPast();
                        @endphp
                        <article class="p-3 transition hover:bg-slate-50/70">
                            <div class="flex gap-2.5">
                                <div @class([
                                    'flex w-12 shrink-0 flex-col items-center rounded-lg px-1 py-1.5',
                                    'bg-rose-50 text-rose-700' => $isOverdue,
                                    'bg-sky-50 text-[#1476B8]' => ! $isOverdue,
                                ])>
                                    <span class="text-xs font-bold">{{ $eventTime->format('H:i') }}</span>
                                    <span class="mt-0.5 text-[9px] font-bold uppercase">{{ $isOverdue ? 'Lewat' : 'Jadwal' }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-xs font-bold text-slate-800">{{ $event->lead?->nama ?? 'Lead tidak tersedia' }}</h3>
                                    <p class="mt-1 text-[10px] text-slate-500">{{ $event->metode_followup }} · {{ $event->status_kontak }}</p>
                                    @if(auth()->user()->canManageOperationalData())
                                        <a href="{{ \App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource::getUrl('edit', ['record' => $event]) }}" class="mt-1 inline-flex items-center gap-1 text-[10px] font-bold text-[#1476B8] hover:text-[#075985]">
                                            Ubah follow-up <x-heroicon-o-arrow-up-right class="h-3 w-3" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="flex min-h-28 flex-col items-center justify-center px-4 py-6 text-center">
                            <x-heroicon-o-calendar-days class="h-6 w-6 text-slate-300" />
                            <p class="mt-2 text-xs font-semibold text-slate-600">Belum ada jadwal</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>

        <main class="dashboard-card min-w-0 overflow-hidden">
            <div class="flex flex-col gap-4 border-b border-slate-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between md:px-5">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Jadwal tindak lanjut</p>
                    <h1 class="mt-1 text-xl font-bold capitalize text-slate-900">{{ $weekLabel }}</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="previousWeek" aria-label="Minggu sebelumnya" class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-[#1476B8]">
                        <x-heroicon-o-chevron-left class="h-4 w-4" />
                    </button>
                    <button type="button" wire:click="goToCurrentMonth" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-[#1476B8]">Hari ini</button>
                    <button type="button" wire:click="nextWeek" aria-label="Minggu berikutnya" class="rounded-xl border border-slate-200 bg-white p-2.5 text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-[#1476B8]">
                        <x-heroicon-o-chevron-right class="h-4 w-4" />
                    </button>
                    <span class="rounded-xl bg-sky-50 px-3 py-2 text-xs font-bold text-[#1476B8]">{{ number_format($scheduledCount, 0, ',', '.') }} jadwal</span>
                    @if($overdueCount > 0)
                        <span class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-bold text-rose-700">{{ number_format($overdueCount, 0, ',', '.') }} terlambat</span>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="followup-week-content">
                    <div class="followup-week-head border-b border-slate-200 bg-slate-50/80">
                        <div class="border-r border-slate-200"></div>
                        @foreach($weekDays as $day)
                            @php
                                $date = $day['date'];
                                $isToday = $date->isToday();
                                $isSelected = $date->toDateString() === $selectedDate;
                            @endphp
                            <button
                                type="button"
                                wire:click="selectDate('{{ $date->toDateString() }}')"
                                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                                @class([
                                    'border-r border-slate-200 px-2 py-3 text-center transition last:border-r-0 hover:bg-sky-50',
                                    'bg-sky-50' => $isSelected,
                                ])
                            >
                                <span @class([
                                    'block text-[10px] font-bold uppercase tracking-wide',
                                    'text-[#1476B8]' => $isToday || $isSelected,
                                    'text-slate-500' => ! $isToday && ! $isSelected,
                                ])>{{ $date->translatedFormat('D') }}</span>
                                <span @class([
                                    'mx-auto mt-1 flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold',
                                    'bg-[#1476B8] text-white' => $isToday,
                                    'bg-sky-100 text-[#075985]' => $isSelected && ! $isToday,
                                    'text-slate-800' => ! $isToday && ! $isSelected,
                                ])>{{ $date->day }}</span>
                                <span class="mt-1 block text-[9px] text-slate-400">{{ $day['events']->count() }} jadwal</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="max-h-[min(68vh,760px)] overflow-y-auto">
                        @foreach($timeSlots as $hour)
                            <div class="followup-week-row border-b border-slate-100 last:border-b-0">
                                <div class="border-r border-slate-200 bg-slate-50/60 px-2 pt-2 text-right text-[10px] font-semibold text-slate-400">{{ sprintf('%02d:00', $hour) }}</div>
                                @foreach($weekDays as $day)
                                    @php($events = $day['eventsByHour']->get($hour, collect()))
                                    <div class="min-w-0 border-r border-slate-100 p-1.5 last:border-r-0">
                                        @foreach($events as $event)
                                            @php($eventIsOverdue = $event->tanggal_followup_berikutnya->isPast())
                                            <button
                                                type="button"
                                                wire:click="selectDate('{{ $day['date']->toDateString() }}')"
                                                aria-label="{{ $event->tanggal_followup_berikutnya->format('H:i') }} {{ $event->lead?->nama ?? 'Lead tidak tersedia' }}"
                                                @class([
                                                    'mb-1 block w-full rounded-md border-l-[3px] px-1.5 py-1 text-left text-[10px] leading-4 last:mb-0',
                                                    'border-rose-500 bg-rose-50 text-rose-800 hover:bg-rose-100' => $eventIsOverdue,
                                                    'border-[#1476B8] bg-sky-50 text-sky-900 hover:bg-sky-100' => ! $eventIsOverdue,
                                                ])
                                            >
                                                <span class="block font-bold">{{ $event->tanggal_followup_berikutnya->format('H:i') }}</span>
                                                <span class="block truncate font-semibold">{{ $event->lead?->nama ?? 'Lead tidak tersedia' }}</span>
                                                <span class="block truncate text-[9px] opacity-75">{{ $event->metode_followup }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </main>
    </div>
</x-filament-panels::page>
