<x-filament-panels::page>
    <div class="space-y-5">
        @if($auditError)<div role="alert" class="dashboard-card flex items-center justify-between border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"><span><b>Terjadi kendala.</b> {{ $auditError }}</span><button wire:click="$refresh" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white">Coba lagi</button></div>@endif

        <section class="relative overflow-hidden rounded-[18px] bg-gradient-to-br from-[#1476B8] via-[#0d6da9] to-[#075985] p-6 text-white shadow-xl shadow-sky-900/10 md:p-8">
            <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-white/10"></div><div class="relative"><span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">Jejak sistem</span><h2 class="mt-4 text-2xl font-bold md:text-3xl">Audit aktivitas CRM</h2><p class="mt-2 max-w-2xl text-sm text-sky-100">Tinjau perubahan data dan aktivitas akses sesuai cakupan role serta cabang Anda.</p></div>
        </section>

        <section class="dashboard-card p-4">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                <label class="text-xs font-semibold text-slate-500">Pencarian<input wire:model.live.debounce.400ms="pencarian" type="search" placeholder="Cari aktivitas..." class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-semibold text-slate-500">Cabang<select wire:model.live="cabang" @disabled(! auth()->user()->hasGlobalBranchAccess()) class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Semua cabang</option>@foreach($branches as $branch)<option value="{{ $branch->cabang_id }}">{{ $branch->nama }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-slate-500">Pengguna<select wire:model.live="pengguna" @disabled(auth()->user()->hasRole(\App\Enums\UserRole::Staff)) class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Semua pengguna</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-slate-500">Aktivitas<select wire:model.live="event" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="">Semua aktivitas</option>@foreach($events as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                <label class="text-xs font-semibold text-slate-500">Dari tanggal<input wire:model.live="tanggalMulai" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <label class="text-xs font-semibold text-slate-500">Sampai tanggal<input wire:model.live="tanggalAkhir" type="date" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
            </div><div wire:loading class="mt-3 w-full"><div class="skeleton h-1.5 w-full"></div></div>
        </section>

        <section wire:loading.class.remove="hidden" wire:loading.class="flex" class="dashboard-card hidden flex-col gap-3 p-5" aria-label="Memuat audit aktivitas">@foreach(range(1, 5) as $item)<div class="flex gap-4 rounded-xl border border-slate-100 p-4"><div class="skeleton h-10 w-10 shrink-0"></div><div class="w-full"><div class="skeleton h-4 w-44"></div><div class="skeleton mt-3 h-3 w-2/3"></div></div></div>@endforeach</section>

        <section wire:loading.remove class="dashboard-card overflow-hidden">
            <div class="divide-y divide-slate-100">
                @forelse($activities as $activity)
                    @php($style = match($activity->event) { 'created' => ['bg-emerald-50 text-emerald-600','heroicon-o-plus'], 'updated' => ['bg-sky-50 text-[#1476B8]','heroicon-o-pencil-square'], 'deleted' => ['bg-rose-50 text-rose-600','heroicon-o-trash'], 'login' => ['bg-violet-50 text-violet-600','heroicon-o-arrow-right-end-on-rectangle'], 'logout' => ['bg-slate-100 text-slate-500','heroicon-o-arrow-left-start-on-rectangle'], default => ['bg-slate-100 text-slate-500','heroicon-o-clock'] })
                    <article class="flex gap-4 p-4 transition hover:bg-slate-50 md:p-5"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $style[0] }}"><x-dynamic-component :component="$style[1]" class="h-5 w-5" /></span><div class="min-w-0 flex-1"><div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"><h3 class="font-semibold text-slate-800">{{ $activity->description }}</h3><time class="whitespace-nowrap text-xs text-slate-400">{{ $activity->created_at->translatedFormat('d M Y, H:i') }}</time></div><p class="mt-1 text-xs text-slate-500">{{ $activity->user?->name ?? 'Sistem' }} · {{ $activity->cabang?->nama ?? 'Global' }}@if($activity->subject_type) · {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}@endif</p>@if(filled($activity->properties['fields'] ?? []))<div class="mt-2 flex flex-wrap gap-1.5">@foreach($activity->properties['fields'] as $field)<span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500">{{ str($field)->replace('_', ' ')->title() }}</span>@endforeach</div>@endif</div></article>
                @empty
                    <div class="flex min-h-72 flex-col items-center justify-center p-8 text-center"><span class="rounded-2xl bg-slate-100 p-4 text-slate-400"><x-heroicon-o-clipboard-document-list class="h-9 w-9" /></span><h3 class="mt-4 font-bold text-slate-700">Belum ada aktivitas</h3><p class="mt-1 text-sm text-slate-400">Tidak ada jejak aktivitas yang cocok dengan filter.</p></div>
                @endforelse
            </div>
            @if($activities->hasPages())<div class="border-t border-slate-100 p-4">{{ $activities->links() }}</div>@endif
        </section>
    </div>
</x-filament-panels::page>
