# Profil Sistem dan Infrastruktur SIVMI CRM

**Status baseline:** As-built pengembangan lokal dan rancangan deployment  
**Tanggal baseline:** 25 Agustus 2026  
**Sumber baseline teknis:** konfigurasi aplikasi, dependency lock, Docker Compose, route aplikasi, dan data akun hosting yang diberikan pemilik sistem.

## 1. Identitas dan Karakteristik Sistem

| Atribut | Keterangan terbaru |
|---|---|
| Nama sistem | **SIVMI CRM** |
| Kode/nama proyek | `sivmi.crm` / `sivmi-crm` |
| Jenis sistem | Sistem Informasi Customer Relationship Management berbasis web untuk operasional bimbingan belajar |
| Platform | Aplikasi web responsif untuk mobile, tablet, laptop, dan desktop |
| Bentuk arsitektur | **Modular monolith** berbasis Laravel dengan antarmuka admin web, REST API versi 1, webhook, queue, scheduler, dan integrasi layanan eksternal |
| Framework backend | **Laravel 12.66.0** |
| Admin panel | **Filament 5.7.6** |
| Komponen reaktif | **Livewire 4.4.0** |
| Bahasa backend | **PHP 8.4.13**; kebutuhan proyek pada Composer adalah PHP `^8.3` |
| Basis data | **MySQL 8.4** pada lingkungan Docker; produksi menggunakan MySQL yang disediakan hosting |
| ORM | Eloquent ORM |
| Front-end | Blade, Filament, Livewire, Tailwind CSS **4.3.3**, dan JavaScript ES Module |
| Build tool | Node.js **22.20.0**, npm **10.9.4**, Vite **8.2.1**, Laravel Vite Plugin **3.2.0** |
| Font antarmuka | Instrument Sans, bobot 400, 500, dan 600 |
| Editor pengembangan | Visual Studio Code |
| Version control | Git dengan repository remote GitHub |
| Bahasa antarmuka | Bahasa Indonesia |
| Timezone | `Asia/Jakarta` |
| Locale | `id`, fallback `id`, Faker `id_ID` |
| AI tools pengembangan | Codex / OpenAI untuk asistensi pengembangan, pengujian, debugging, dan dokumentasi; keputusan akhir tetap diverifikasi pengembang |
| URL produksi | [https://lead.sivmi.id/](https://lead.sivmi.id/) |

## 2. Pengguna dan Hak Akses

| Aktor | Cakupan akses |
|---|---|
| Superadmin | Mengelola seluruh cabang, master data, pengguna, konfigurasi, integrasi, audit, dan data operasional |
| Admin Cabang | Mengelola operasional, staff, target, dan koneksi WhatsApp pada cabangnya sendiri |
| Staff | Menangani leads, follow-up, Inbox WhatsApp, pendaftaran, pembayaran, dan data yang ditugaskan kepadanya |
| Direksi | Melihat dashboard, KPI, laporan, dan audit lintas cabang tanpa melakukan mutasi data operasional |
| API Client | Mengakses REST API berdasarkan bearer token, ability `leads:read` dan/atau `leads:write`, serta scope cabang |
| WAHA | Sistem eksternal untuk session WhatsApp, pesan masuk/keluar, status pesan, dan media melalui engine GOWS |

## 3. Fokus Bisnis dan Modul Aktual

| Area | Cakupan implementasi |
|---|---|
| Dashboard dan KPI | Ringkasan leads, konversi, siswa aktif, omzet tervalidasi, funnel status lead, tren, target, distribusi program, kategori, tipe, metode, kelas, jenjang, sekolah, dan wilayah |
| Leads | Input, edit, import/export CSV, sumber lead, status `Cold`, `Warm`, `Hot`, `Daftar`, dan `Tidak Berminat`, program diminati, sekolah, cabang, serta PIC |
| Follow-up | Riwayat kontak, metode, status kontak, jadwal berikutnya, dan catatan hasil |
| Inbox WhatsApp | Percakapan teks dan media, unread count, pencocokan lead, penandaan konflik/duplikasi, serta pengiriman pesan melalui session pengguna |
| Pendaftaran | Konversi lead menjadi siswa/pendaftaran pada program bimbel |
| Pembayaran | Pencatatan dan validasi transaksi pembayaran pendaftaran |
| Program bimbel | Klasifikasi program, kategori, kelas program, metode belajar, tipe, jenjang, tingkat kelas, biaya, diskon, pertemuan, dan fasilitas |
| Master data | Cabang, sekolah, pengguna, program, pendaftaran, dan pembayaran |
| Target kinerja | Target omzet dan siswa per cabang serta alokasi target pengguna |
| Laporan | Pelaporan berbasis role/cabang dan ekspor sesuai izin |
| Kontrol | Pengaturan aplikasi, notifikasi database, audit aktivitas, profil pengguna, dan koneksi WhatsApp |
| Integrasi | REST API leads dan webhook WAHA dengan kontrol keamanan serta idempotensi |

> Modul tugas dan pembelajaran belum tercatat sebagai resource operasional pada baseline kode saat ini. Keduanya harus diperlakukan sebagai rencana pengembangan, bukan fitur as-built.

## 4. Antarmuka REST API dan Webhook

| Atribut | Keterangan |
|---|---|
| Gaya API | REST API berbasis JSON dengan prefix versi `/api/v1` |
| API baca | `GET /api/v1/leads` dan `GET /api/v1/leads/{lead}` |
| API tulis | `POST /api/v1/leads` |
| Autentikasi API | Bearer token milik API Client; token disimpan dalam bentuk hash |
| Otorisasi | Ability `leads:read` dan `leads:write`, serta pembatasan cabang |
| Proteksi | Rate limiting, request ID, idempotency key untuk operasi tulis, dan respons error terstruktur |
| Webhook WAHA | `POST /api/webhooks/waha` |
| Keamanan webhook | HMAC signature, identifikasi session, dan deduplikasi message ID |
| Engine WhatsApp | WAHA menggunakan engine **GOWS** |

REST API pada baseline terbaru bersifat **baca dan tulis untuk data leads**, bukan hanya baca.

## 5. Driver dan Penyimpanan Aplikasi

| Atribut | Konfigurasi baseline |
|---|---|
| Database driver | MySQL |
| Session driver | Database |
| Cache store | Database |
| Queue connection | Database |
| Notification | Database notification dengan polling Filament |
| Penyimpanan file | Laravel filesystem; media publik melalui `/storage` setelah `storage:link` |
| Redis | Redis 7.4 tersedia pada Docker Compose, tetapi belum menjadi driver utama session/cache/queue pada baseline environment |
| Logging | Stack/single log; data sensitif tidak boleh dicatat ke log atau audit properties |

## 6. Lingkungan Pengembangan Lokal Utama

| Atribut | Keterangan terbaru |
|---|---|
| Mode lokal utama | **Full Docker Compose tanpa ketergantungan runtime pada Laragon** |
| Web server | Nginx **1.27 Alpine** |
| PHP runtime | PHP **8.4 FPM Bookworm** |
| Database | MySQL **8.4** |
| Cache service opsional | Redis **7.4 Alpine** |
| Node container | Node.js **22 Alpine** untuk development server/build asset |
| WhatsApp service | `devlikeapro/waha:gows` |
| URL lokal aplikasi | `http://localhost:8000` |
| URL lokal WAHA | `http://localhost:3000` |
| URL Vite development | `http://localhost:5173` |
| Port MySQL host | `3307`, dipetakan ke port `3306` container |
| Service Docker | `app`, `web`, `database`, `redis`, `node`, `queue`, `scheduler`, dan `waha` |
| Laragon | Tetap dapat digunakan sebagai alternatif pengembangan legacy melalui Apache/MySQL lokal, tetapi bukan baseline utama terbaru |

## 7. Lingkungan Produksi Shared Hosting

| Atribut | Keterangan berdasarkan data akun hosting |
|---|---|
| Model hosting | Shared Hosting |
| Provider | Hostinger.com |
| Paket | Business |
| Masa aktif paket | Sampai **15 Januari 2028** |
| URL | [https://lead.sivmi.id/](https://lead.sivmi.id/) |
| Nama server | `server1981` |
| Lokasi server utama | Asia — Indonesia |
| Lokasi backup | Singapura |
| Disk space | 50 GB |
| RAM | 3.072 MB |
| CPU | 2 core |
| Inode | 600.000 |
| Add-on/website | Hingga 50 website |
| Proses maksimal | 120 |
| PHP worker | 60 |
| Bandwidth | Unlimited sesuai ketentuan provider |
| Document root Laravel | Harus diarahkan ke direktori `public` |
| Build asset | `npm run build` dijalankan sebelum deployment; server produksi hanya membutuhkan hasil `public/build` |

## 8. Dukungan Node.js Hosting

| Atribut | Keterangan |
|---|---|
| Versi Node.js yang didukung | 24.x, 22.x, 20.x, dan 18.x |
| Kesesuaian proyek | Sesuai; baseline build proyek memakai Node.js 22.x |
| Package manager | npm sebagai default; yarn dan pnpm tersedia |
| Framework front-end yang didukung | Angular, Astro, Gatsby, Next.js, Nitro, Nuxt, Parcel, React, React Router, Svelte, SvelteKit, Vite, dan Vue.js |
| Framework backend Node.js yang didukung | Astro, Express, Fastify, Hono, NestJS, Next.js, Nitro, Nuxt, React Router, dan SvelteKit |
| Kebutuhan runtime SIVMI CRM | Node.js tidak wajib berjalan terus-menerus di produksi karena backend menggunakan PHP; Node.js hanya diperlukan untuk build asset Vite |

## 9. Catatan Kelayakan Shared Hosting

Laravel dan antarmuka Filament dapat dijalankan pada shared hosting selama PHP, ekstensi, document root, file permission, cron, dan MySQL telah dikonfigurasi dengan benar. Namun, baseline terbaru memiliki komponen yang memerlukan proses jangka panjang.

| Komponen | Shared hosting | Rekomendasi |
|---|---|---|
| Web Laravel/Filament | Dapat dijalankan | Gunakan PHP 8.4 jika tersedia, atau minimal PHP 8.3 sesuai Composer |
| MySQL | Dapat dijalankan | Gunakan database production terpisah dan kredensial rahasia |
| Vite/Tailwind | Dapat dibangun | Build sebelum upload; jangan menjalankan dev server di produksi |
| Scheduler | Dapat dijalankan melalui cron | Jalankan `php artisan schedule:run` setiap menit jika provider mengizinkan |
| Queue worker permanen | Terbatas | Gunakan cron `queue:work --stop-when-empty` sebagai kompromi atau pindahkan worker ke VPS |
| WAHA GOWS | Tidak cocok dijalankan langsung pada shared hosting | Jalankan WAHA pada mesin lokal yang terekspos secara aman, layanan eksternal, atau VPS |
| Webhook WAHA | Dapat diterima oleh domain HTTPS | Endpoint harus dapat dijangkau WAHA dan menggunakan HMAC secret |
| Docker Compose | Umumnya tidak tersedia pada shared hosting | Gunakan deployment PHP konvensional atau VPS untuk menyamakan arsitektur Docker |

## 10. Target Arsitektur Berikutnya

Target operasional yang direkomendasikan adalah VPS berbasis Docker Compose dengan Nginx, PHP-FPM, MySQL/managed database, Redis, queue worker, scheduler, dan WAHA GOWS. Shared hosting dapat dipakai sementara untuk antarmuka web dan database, tetapi tidak merepresentasikan seluruh arsitektur as-built Docker secara utuh.

