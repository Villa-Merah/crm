<x-filament-panels::page>
    <div class="mx-auto max-w-6xl space-y-5">
        <section class="relative overflow-hidden rounded-[18px] bg-gradient-to-br from-[#1476B8] via-[#0d6da9] to-[#075985] p-6 text-white shadow-xl shadow-sky-900/10 md:p-8">
            <div class="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div><span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">Pemulihan sistem</span><h2 class="mt-4 text-2xl font-bold md:text-3xl">Backup & Restore Database</h2><p class="mt-2 max-w-2xl text-sm text-sky-100">Lindungi data CRM dengan backup SQL privat dan pemulihan yang diaudit.</p></div>
                <button type="button" wire:click="createBackup" wire:loading.attr="disabled" wire:target="createBackup" @disabled(! $this->diagnostics['ready']) class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-[#075985] shadow-lg transition hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-60">
                    <x-heroicon-o-arrow-down-tray class="h-5 w-5" /><span wire:loading.remove wire:target="createBackup">Buat backup sekarang</span><span wire:loading wire:target="createBackup">Membuat backup...</span>
                </button>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="dashboard-card p-5"><div class="flex items-center gap-3"><span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600"><x-heroicon-o-server-stack class="h-5 w-5" /></span><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Status perangkat</p><p class="mt-1 font-bold {{ $this->diagnostics['ready'] ? 'text-emerald-600' : 'text-rose-600' }}">{{ $this->diagnostics['ready'] ? 'Siap digunakan' : 'Perlu konfigurasi' }}</p></div></div></div>
            <div class="dashboard-card p-5"><div class="flex items-center gap-3"><span class="rounded-xl bg-sky-50 p-2.5 text-[#1476B8]"><x-heroicon-o-archive-box class="h-5 w-5" /></span><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Backup tersedia</p><p class="mt-1 text-xl font-bold text-slate-900">{{ count($this->backups) }}</p></div></div></div>
            <div class="dashboard-card p-5"><div class="flex items-center gap-3"><span class="rounded-xl bg-amber-50 p-2.5 text-amber-600"><x-heroicon-o-clock class="h-5 w-5" /></span><div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Retensi otomatis</p><p class="mt-1 font-bold text-slate-900">{{ config('backup.retention') }} file terbaru</p></div></div></div>
        </section>

        @unless($this->diagnostics['ready'])
            <section class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800"><div class="flex gap-3"><x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" /><div><p class="font-bold">Backup belum siap</p><p class="mt-1">{{ $this->diagnostics['message'] }} Rebuild image Docker agar paket client MySQL terpasang, atau atur lokasi binary melalui environment.</p></div></div></section>
        @endunless

        <section class="dashboard-card overflow-hidden">
            <div class="border-b border-slate-100 p-5 md:p-6"><h3 class="font-bold text-slate-900">Riwayat backup</h3><p class="mt-1 text-xs text-slate-500">File disimpan pada storage privat dan hanya dapat diunduh oleh Superadmin.</p></div>
            <div wire:loading.delay wire:target="createBackup,restoreSelected,restoreUploaded" class="space-y-3 p-5"><div class="h-16 animate-pulse rounded-xl bg-slate-100"></div><div class="h-16 animate-pulse rounded-xl bg-slate-100"></div></div>
            <div wire:loading.remove wire:target="createBackup,restoreSelected,restoreUploaded">
                @forelse($this->backups as $backup)
                    <div class="flex flex-col gap-4 border-b border-slate-100 p-5 last:border-0 md:flex-row md:items-center md:justify-between">
                        <div class="flex min-w-0 items-center gap-3"><span class="rounded-xl bg-sky-50 p-2.5 text-[#1476B8]"><x-heroicon-o-circle-stack class="h-5 w-5" /></span><div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-800">{{ $backup['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $backup['created_at']->format('d M Y, H:i:s') }} · {{ $backup['size_human'] }}</p></div></div>
                        <div class="flex items-center gap-2"><a href="{{ route('backups.download', ['filename' => $backup['name']]) }}" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Unduh</a><button type="button" wire:click="selectRestore(@js($backup['name']))" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 transition hover:bg-amber-100"><x-heroicon-o-arrow-path class="h-4 w-4" />Restore</button></div>
                    </div>
                @empty
                    <div class="p-10 text-center"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400"><x-heroicon-o-archive-box class="h-6 w-6" /></span><p class="mt-4 font-semibold text-slate-700">Belum ada backup</p><p class="mt-1 text-sm text-slate-500">Buat backup pertama sebelum melakukan perubahan besar pada sistem.</p></div>
                @endforelse
            </div>
        </section>

        @if($selectedBackup)
            <section class="rounded-[18px] border border-amber-200 bg-amber-50 p-5 md:p-6">
                <div class="flex gap-3"><x-heroicon-o-exclamation-triangle class="h-6 w-6 shrink-0 text-amber-600" /><div class="w-full"><h3 class="font-bold text-amber-900">Konfirmasi restore backup</h3><p class="mt-1 text-sm text-amber-800">Database aktif akan ditimpa dari <strong>{{ $selectedBackup }}</strong>. Sistem membuat backup pengaman otomatis terlebih dahulu.</p><label class="mt-4 block max-w-md text-xs font-bold text-amber-900">Ketik RESTORE<input wire:model="restoreConfirmation" type="text" autocomplete="off" class="mt-2 w-full rounded-xl border-amber-300 bg-white text-sm" placeholder="RESTORE"></label>@error('restoreConfirmation')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror<div class="mt-4 flex gap-2"><button wire:click="restoreSelected" wire:loading.attr="disabled" wire:target="restoreSelected" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"><span wire:loading.remove wire:target="restoreSelected">Pulihkan database</span><span wire:loading wire:target="restoreSelected">Memulihkan...</span></button><button wire:click="cancelRestore" class="rounded-xl border border-amber-300 px-4 py-2 text-sm font-bold text-amber-800">Batal</button></div></div></div>
            </section>
        @endif

        <section class="dashboard-card p-5 md:p-6">
            <div class="flex items-start gap-3"><span class="rounded-xl bg-violet-50 p-2.5 text-violet-600"><x-heroicon-o-arrow-up-tray class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Restore dari file SQL</h3><p class="mt-1 text-xs text-slate-500">Gunakan hanya backup MySQL/MariaDB tepercaya. Maksimal {{ number_format(config('backup.max_upload_kb') / 1024, 0) }} MB.</p></div></div>
            <form wire:submit="restoreUploaded" class="mt-5 grid gap-4 md:grid-cols-[1fr_260px_auto] md:items-end">
                <label class="text-sm font-semibold text-slate-700">File backup<input wire:model="restoreUpload" type="file" accept=".sql" class="mt-2 block w-full rounded-xl border border-slate-200 bg-slate-50 p-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-sky-100 file:px-3 file:py-2 file:font-semibold file:text-[#075985]"></label>
                <label class="text-sm font-semibold text-slate-700">Konfirmasi<input wire:model="uploadConfirmation" type="text" autocomplete="off" placeholder="Ketik RESTORE" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"></label>
                <button type="submit" wire:loading.attr="disabled" wire:target="restoreUploaded,restoreUpload" @disabled(! $this->diagnostics['ready']) class="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60"><span wire:loading.remove wire:target="restoreUploaded">Restore file</span><span wire:loading wire:target="restoreUploaded">Memproses...</span></button>
            </form>
            @error('restoreUpload')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror @error('uploadConfirmation')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
        </section>

        <section class="rounded-2xl border border-sky-100 bg-sky-50 p-5 text-xs leading-6 text-sky-900"><strong>Catatan operasional:</strong> unduh backup penting ke penyimpanan terpisah. Backup aplikasi ini mencakup database; file media pada storage dan volume sesi WAHA harus dicadangkan melalui mekanisme infrastruktur.</section>
    </div>
</x-filament-panels::page>
