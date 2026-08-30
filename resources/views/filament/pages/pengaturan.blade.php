<x-filament-panels::page>
    <div class="mx-auto max-w-5xl space-y-5">
        <section class="relative overflow-hidden rounded-[18px] bg-gradient-to-br from-[#1476B8] via-[#0d6da9] to-[#075985] p-6 text-white shadow-xl shadow-sky-900/10 md:p-8">
            <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="relative"><span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">Konfigurasi sistem</span><h2 class="mt-4 text-2xl font-bold md:text-3xl">Pengaturan SIVMI CRM</h2><p class="mt-2 max-w-2xl text-sm text-sky-100">Kelola identitas aplikasi, informasi dukungan, pilihan Program Bimbel, dan kebijakan ekspor laporan dari satu tempat.</p></div>
        </section>

        <form wire:submit="save" class="space-y-5">
            <section class="dashboard-card p-5 md:p-6">
                <div class="flex items-start gap-3"><span class="rounded-xl bg-sky-50 p-2.5 text-[#1476B8]"><x-heroicon-o-building-office-2 class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Identitas aplikasi</h3><p class="mt-1 text-xs text-slate-500">Nama aplikasi akan digunakan pada brand panel dan dokumen ekspor.</p></div></div>
                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">Nama aplikasi<input wire:model="appName" type="text" maxlength="60" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm" required>@error('appName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700">Nama organisasi<input wire:model="organizationName" type="text" maxlength="100" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm" required>@error('organizationName')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700 md:col-span-2">Tagline dashboard<textarea wire:model="dashboardTagline" rows="3" maxlength="180" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm" required></textarea><span class="mt-1 block text-xs font-normal text-slate-400">Ditampilkan pada kartu sambutan dashboard.</span>@error('dashboardTagline')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                </div>
            </section>

            <section class="dashboard-card p-5 md:p-6">
                <div class="flex items-start gap-3"><span class="rounded-xl bg-cyan-50 p-2.5 text-cyan-600"><x-heroicon-o-user-group class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Data calon siswa</h3><p class="mt-1 text-xs text-slate-500">Daftar kelas sekolah ini berdiri sendiri dan tidak mengubah paket atau angkatan Program Bimbel.</p></div></div>
                <label class="mt-6 block text-sm font-semibold text-slate-700">Kelas sekolah
                    <textarea wire:model="schoolClassLevels" rows="6" maxlength="1500" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 font-mono text-sm" required></textarea>
                    <span class="mt-1 block text-xs font-normal text-slate-400">Tulis satu opsi per baris. Contoh: X, XI, XII, Alumni/Gap Year.</span>
                    @error('schoolClassLevels')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                </label>
            </section>

            <section class="dashboard-card p-5 md:p-6">
                <div class="flex items-start gap-3"><span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600"><x-heroicon-o-lifebuoy class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Kontak dukungan</h3><p class="mt-1 text-xs text-slate-500">Informasi kontak internal untuk bantuan penggunaan CRM.</p></div></div>
                <div class="mt-6 grid gap-5 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700">Email dukungan<input wire:model="supportEmail" type="email" maxlength="255" placeholder="dukungan@organisasi.id" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">@error('supportEmail')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700">Nomor dukungan<input wire:model="supportPhone" type="tel" maxlength="30" placeholder="+62 ..." class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">@error('supportPhone')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                </div>
            </section>

            <section class="dashboard-card p-5 md:p-6">
                <div class="flex items-start gap-3"><span class="rounded-xl bg-violet-50 p-2.5 text-violet-600"><x-heroicon-o-shield-check class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Kebijakan laporan</h3><p class="mt-1 text-xs text-slate-500">Superadmin, Admin Cabang, dan Direksi tetap dapat mengekspor laporan sesuai cakupan aksesnya.</p></div></div>
                <label class="mt-6 flex cursor-pointer items-start justify-between gap-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <span><span class="block text-sm font-semibold text-slate-700">Izinkan Staff mengekspor CSV</span><span class="mt-1 block text-xs text-slate-500">Jika aktif, Staff hanya dapat mengekspor agregat cabang dan data miliknya sendiri.</span></span>
                    <input wire:model="staffReportExport" type="checkbox" class="mt-1 rounded border-slate-300 text-[#1476B8] focus:ring-[#1476B8]">
                </label>
            </section>

            <section class="dashboard-card p-5 md:p-6">
                <div class="flex items-start gap-3"><span class="rounded-xl bg-amber-50 p-2.5 text-amber-600"><x-heroicon-o-academic-cap class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Pengaturan Program Bimbel</h3><p class="mt-1 text-xs text-slate-500">Kelola pilihan klasifikasi pada form Program Bimbel. Tulis satu opsi per baris.</p></div></div>
                <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach([
                        ['programCategories', 'Kategori program', 'Contoh: SR, AR'],
                        ['programClasses', 'Paket program', 'Contoh: Platinum, Gold'],
                        ['programTypes', 'Tipe program', 'Contoh: Reguler, Intensif'],
                        ['programLearningMethods', 'Metode belajar', 'Contoh: Tatap Muka, Online'],
                        ['programLevels', 'Jenjang program', 'Contoh: SD, SMP, SMA'],
                        ['programClassLevels', 'Tingkat target program', 'Contoh: I, II, III'],
                    ] as [$model, $label, $hint])
                        <label class="text-sm font-semibold text-slate-700">{{ $label }}
                            <textarea wire:model="{{ $model }}" rows="6" maxlength="1500" class="mt-2 w-full rounded-xl border-slate-200 bg-slate-50 font-mono text-sm" required></textarea>
                            <span class="mt-1 block text-xs font-normal text-slate-400">{{ $hint }}</span>
                            @error($model)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                </div>
            </section>

            <div class="sticky bottom-4 flex items-center justify-between rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-xl shadow-slate-900/10 backdrop-blur">
                <span wire:dirty class="text-xs font-semibold text-amber-600">Ada perubahan yang belum disimpan.</span><span wire:dirty.remove class="text-xs text-slate-400">Pengaturan tersimpan.</span>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex items-center gap-2 rounded-xl bg-[#1476B8] px-5 py-2.5 text-sm font-bold text-white transition hover:bg-[#075985] disabled:opacity-60"><x-heroicon-o-check class="h-5 w-5" /><span wire:loading.remove wire:target="save">Simpan pengaturan</span><span wire:loading wire:target="save">Menyimpan...</span></button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
