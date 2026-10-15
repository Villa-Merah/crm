<x-filament-panels::page>
    <div class="mx-auto max-w-7xl space-y-6" x-data="{ search: '' }">
        <section class="relative overflow-hidden rounded-[20px] bg-gradient-to-br from-[#1476B8] via-[#0d6da9] to-[#075985] p-6 text-white shadow-xl shadow-sky-900/10 md:p-8">
            <div class="absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-28 right-36 h-52 w-52 rounded-full border border-white/10"></div>
            <div class="relative grid gap-6 lg:grid-cols-[1fr_360px] lg:items-end">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                        <x-heroicon-o-lifebuoy class="h-4 w-4" /> Pusat bantuan SIVMI CRM
                    </span>
                    <h2 class="mt-4 text-2xl font-bold md:text-3xl">Apa yang ingin Anda pelajari?</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-sky-100">Temukan panduan penggunaan, alur proses kerja, aturan akses, solusi masalah umum, dan informasi dukungan.</p>
                </div>
                <label class="relative block">
                    <span class="sr-only">Cari bantuan</span>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                    <input x-model.debounce.150ms="search" type="search" placeholder="Cari topik, misalnya: lead atau pembayaran" class="w-full rounded-2xl border-0 bg-white py-3.5 pl-12 pr-4 text-sm text-slate-900 shadow-lg outline-none ring-0 placeholder:text-slate-400 focus:ring-4 focus:ring-white/20">
                </label>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-[1fr_2fr]">
            <div class="dashboard-card p-5 md:p-6">
                <div class="flex items-start gap-3">
                    <span @class(['rounded-xl p-2.5', $this->roleGuide['icon_classes']])><x-heroicon-o-identification class="h-5 w-5" /></span>
                    <div><p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Akses Anda</p><h3 class="mt-1 text-lg font-bold text-slate-900">{{ $this->roleGuide['label'] }}</h3></div>
                </div>
                <p class="mt-4 text-sm leading-6 text-slate-600">{{ $this->roleGuide['summary'] }}</p>
                <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-500">Menu dan tindakan yang tersedia mengikuti role, cabang, serta kepemilikan data akun Anda.</p>
            </div>

            <div class="dashboard-card p-5 md:p-6">
                <div class="flex items-center justify-between gap-3"><div><h3 class="font-bold text-slate-900">Pintasan kerja</h3><p class="mt-1 text-xs text-slate-500">Menu yang paling relevan untuk akun Anda.</p></div><x-heroicon-o-bolt class="h-5 w-5 text-amber-500" /></div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($this->quickLinks as $link)
                        <a href="{{ $link['url'] }}" class="group flex items-center gap-3 rounded-xl border border-slate-200 p-3 transition hover:-translate-y-0.5 hover:border-sky-200 hover:bg-sky-50">
                            <span class="rounded-lg bg-sky-50 p-2 text-[#1476B8] group-hover:bg-white"><x-dynamic-component :component="$link['icon']" class="h-5 w-5" /></span>
                            <span><span class="block text-sm font-bold text-slate-800">{{ $link['label'] }}</span><span class="block text-xs text-slate-500">{{ $link['description'] }}</span></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section data-help-item data-search="mulai cepat login dashboard filter periode cabang pengguna" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" class="dashboard-card p-5 md:p-6">
            <div class="flex items-start gap-3"><span class="rounded-xl bg-emerald-50 p-2.5 text-emerald-600"><x-heroicon-o-rocket-launch class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Mulai cepat</h3><p class="mt-1 text-xs text-slate-500">Empat langkah untuk memulai pekerjaan dengan data yang tepat.</p></div></div>
            <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['Masuk dengan akun sendiri', 'Jangan berbagi kata sandi. Pastikan nama dan role pada profil sudah benar.'],
                    ['Periksa filter dashboard', 'Pilih cabang, tahun, bulan, dan pengguna sesuai cakupan analisis.'],
                    ['Kerjakan dari menu utama', 'Buka Leads, Follow-up, WhatsApp, Pendaftaran, atau Pembayaran sesuai tugas.'],
                    ['Pastikan status tersimpan', 'Periksa notifikasi sukses dan riwayat sebelum menutup halaman.'],
                ] as $index => $step)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"><span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#1476B8] text-xs font-bold text-white">{{ $index + 1 }}</span><h4 class="mt-3 text-sm font-bold text-slate-800">{{ $step[0] }}</h4><p class="mt-2 text-xs leading-5 text-slate-500">{{ $step[1] }}</p></div>
                @endforeach
            </div>
        </section>

        <section data-help-item data-search="alur proses workflow lead follow-up whatsapp pendaftaran pembayaran laporan siswa" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" class="dashboard-card overflow-hidden">
            <div class="border-b border-slate-100 p-5 md:p-6"><div class="flex items-start gap-3"><span class="rounded-xl bg-violet-50 p-2.5 text-violet-600"><x-heroicon-o-arrows-right-left class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Alur proses utama</h3><p class="mt-1 text-xs text-slate-500">Perjalanan data dari calon siswa hingga menjadi siswa dan masuk ke laporan.</p></div></div></div>
            <div class="grid gap-0 md:grid-cols-2 xl:grid-cols-6">
                @foreach([
                    ['1', 'Lead masuk', 'Input manual, impor CSV, API, atau WhatsApp.', 'user-plus'],
                    ['2', 'Validasi', 'Periksa nomor, duplikasi, sumber, cabang, dan penanggung jawab.', 'shield-check'],
                    ['3', 'Follow-up', 'Catat komunikasi, jadwal berikutnya, dan perubahan status.', 'chat-bubble-left-right'],
                    ['4', 'Pendaftaran', 'Lead yang setuju diproses menjadi data pendaftaran.', 'document-check'],
                    ['5', 'Pembayaran', 'Transaksi dicatat lalu diverifikasi oleh petugas berwenang.', 'banknotes'],
                    ['6', 'Analisis', 'Dashboard dan laporan merangkum konversi, siswa, omzet, dan target.', 'chart-bar-square'],
                ] as $flow)
                    <div class="relative border-b border-slate-100 p-5 last:border-0 md:border-r xl:border-b-0">
                        <div class="flex items-center justify-between"><span class="text-xs font-bold text-[#1476B8]">TAHAP {{ $flow[0] }}</span><x-dynamic-component :component="'heroicon-o-'.$flow[3]" class="h-5 w-5 text-slate-400" /></div>
                        <h4 class="mt-3 text-sm font-bold text-slate-800">{{ $flow[1] }}</h4><p class="mt-2 text-xs leading-5 text-slate-500">{{ $flow[2] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @foreach([
                ['Leads dan follow-up', 'lead calon siswa duplikat penugasan status followup', 'user-group', 'bg-sky-50 text-sky-600', [
                    'Gunakan nomor WhatsApp aktif dan data sekolah yang benar.',
                    'Staff hanya mengubah lead miliknya; minta Admin Cabang bila penugasan perlu dipindahkan.',
                    'Catat hasil komunikasi dan tanggal tindak lanjut berikutnya setelah setiap interaksi.',
                ]],
                ['Inbox WhatsApp', 'whatsapp pesan percakapan jadikan lead hubungkan bukan lead', 'chat-bubble-oval-left-ellipsis', 'bg-emerald-50 text-emerald-600', [
                    'Pesan dari nomor baru masuk sebagai percakapan yang perlu ditinjau, bukan otomatis menjadi lead.',
                    'Pilih Jadikan lead, Hubungkan lead, atau Bukan lead sesuai identitas pengirim.',
                    'Pastikan session berstatus Working dan laporkan bila pesan tidak masuk atau gagal terkirim.',
                ]],
                ['Pendaftaran dan pembayaran', 'pendaftaran pembayaran siswa keuangan verifikasi lunas', 'banknotes', 'bg-amber-50 text-amber-600', [
                    'Pendaftaran dibuat dari lead yang telah memenuhi persyaratan.',
                    'Staff mencatat transaksi; role Keuangan melakukan pemeriksaan dan verifikasi pembayaran.',
                    'Admin dan Superadmin memantau data tanpa mengubah transaksi operasional.',
                ]],
                ['Dashboard, target, dan laporan', 'dashboard target kinerja laporan filter ekspor csv omzet konversi', 'presentation-chart-line', 'bg-violet-50 text-violet-600', [
                    'Periksa filter periode, cabang, dan pengguna sebelum membaca angka.',
                    'Target cabang dialokasikan kepada pengguna dan dibandingkan dengan realisasi periode yang sama.',
                    'Gunakan Semua bulan untuk laporan tahunan dan ekspor CSV bila role Anda mengizinkan.',
                ]],
            ] as $guide)
                <article data-help-item data-search="{{ $guide[1] }}" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" class="dashboard-card p-5 md:p-6">
                    <div class="flex items-center gap-3"><span @class(['rounded-xl p-2.5', $guide[3]])><x-dynamic-component :component="'heroicon-o-'.$guide[2]" class="h-5 w-5" /></span><h3 class="font-bold text-slate-900">{{ $guide[0] }}</h3></div>
                    <ul class="mt-4 space-y-3">
                        @foreach($guide[4] as $instruction)
                            <li class="flex gap-2.5 text-sm leading-6 text-slate-600"><x-heroicon-o-check-circle class="mt-1 h-4 w-4 shrink-0 text-emerald-500" /><span>{{ $instruction }}</span></li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </section>

        <section data-help-item data-search="integrasi waha cloudflare sekolah indonesia peta openstreetmap api sinkronisasi queue" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" class="dashboard-card p-5 md:p-6">
            <div class="flex items-start gap-3"><span class="rounded-xl bg-cyan-50 p-2.5 text-cyan-600"><x-heroicon-o-puzzle-piece class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Integrasi sistem</h3><p class="mt-1 text-xs text-slate-500">Layanan eksternal yang mendukung proses CRM.</p></div></div>
            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    ['WAHA GOWS', 'Mengelola session serta pesan WhatsApp masuk dan keluar.'],
                    ['Cloudflare Tunnel', 'Menghubungkan WAHA lokal dengan server CRM melalui HTTPS.'],
                    ['API Sekolah Indonesia', 'Membantu pencarian dan penyimpanan referensi sekolah.'],
                    ['OpenStreetMap', 'Menampilkan persebaran wilayah lead pada peta dashboard.'],
                ] as $integration)
                    <div class="rounded-xl border border-slate-200 p-4"><h4 class="text-sm font-bold text-slate-800">{{ $integration[0] }}</h4><p class="mt-2 text-xs leading-5 text-slate-500">{{ $integration[1] }}</p></div>
                @endforeach
            </div>
        </section>

        <section data-help-item data-search="faq masalah error akses data kosong whatsapp gagal lupa sandi dukungan" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" class="dashboard-card p-5 md:p-6">
            <div class="flex items-start gap-3"><span class="rounded-xl bg-rose-50 p-2.5 text-rose-600"><x-heroicon-o-question-mark-circle class="h-5 w-5" /></span><div><h3 class="font-bold text-slate-900">Pertanyaan umum</h3><p class="mt-1 text-xs text-slate-500">Pemeriksaan awal sebelum menghubungi dukungan.</p></div></div>
            <div class="mt-5 divide-y divide-slate-100">
                @foreach([
                    ['Mengapa data dashboard kosong?', 'Periksa tahun, bulan, cabang, dan pengguna. Pastikan data transaksi berada pada periode yang dipilih.'],
                    ['Mengapa saya tidak dapat mengubah data?', 'Hak ubah ditentukan oleh role, cabang, status proses, dan kepemilikan data. Hubungi administrator bila penugasan tidak sesuai.'],
                    ['Mengapa pesan WhatsApp tidak muncul?', 'Periksa status session pada Koneksi WhatsApp. Queue server, webhook, WAHA, dan tunnel juga harus aktif.'],
                    ['Apa yang dilakukan jika menemukan data ganda?', 'Jangan membuat data baru. Catat identitas yang sama dan minta Admin Cabang atau Superadmin melakukan pemeriksaan.'],
                    ['Apa yang perlu disertakan saat melapor?', 'Sertakan waktu kejadian, menu, langkah yang dilakukan, pesan kesalahan, dan tangkapan layar tanpa menampilkan kata sandi atau secret.'],
                ] as $faq)
                    <details class="group py-4 first:pt-0 last:pb-0"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-bold text-slate-800"><span>{{ $faq[0] }}</span><x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-180" /></summary><p class="mt-3 pr-8 text-sm leading-6 text-slate-600">{{ $faq[1] }}</p></details>
                @endforeach
            </div>
        </section>

        <section data-help-item data-search="kontak dukungan bantuan email telepon admin" x-show="!search || $el.dataset.search.includes(search.toLowerCase())" class="rounded-[18px] border border-sky-100 bg-sky-50 p-5 md:p-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div><h3 class="font-bold text-sky-950">Masih membutuhkan bantuan?</h3><p class="mt-1 text-sm text-sky-800">Hubungi dukungan internal dan sertakan informasi kejadian secara lengkap.</p></div>
                <div class="flex flex-wrap gap-2">
                    @if($this->support['email'])<a href="mailto:{{ $this->support['email'] }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#075985] shadow-sm"><x-heroicon-o-envelope class="h-4 w-4" />{{ $this->support['email'] }}</a>@endif
                    @if($this->support['phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $this->support['phone']) }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#075985] shadow-sm"><x-heroicon-o-phone class="h-4 w-4" />{{ $this->support['phone'] }}</a>@endif
                    @unless($this->support['email'] || $this->support['phone'])<span class="rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-sky-800 shadow-sm">Hubungi Superadmin SIVMI CRM</span>@endunless
                </div>
            </div>
        </section>

        <div x-show="search && !Array.from(document.querySelectorAll('[data-help-item]')).some(item => item.dataset.search.includes(search.toLowerCase()))" x-cloak class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
            <x-heroicon-o-document-magnifying-glass class="mx-auto h-8 w-8 text-slate-400" /><p class="mt-3 font-semibold text-slate-700">Topik belum ditemukan</p><p class="mt-1 text-sm text-slate-500">Coba kata kunci lain atau hubungi dukungan internal.</p>
        </div>
    </div>
</x-filament-panels::page>
