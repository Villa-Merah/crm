# Profil Sistem dan Infrastruktur SIVMI CRM

**Status baseline:** As-built pengembangan lokal dan rancangan deployment
**Tanggal baseline:** 8 September 2026
**Sumber baseline teknis:** konfigurasi aplikasi, dependency lock, Docker Compose, route aplikasi, dan data akun hosting yang diberikan pemilik sistem.

## Ringkasan Eksekutif

| Item utama | Isi yang dibahas |
|---|---|
| **1. Masalah dan Tujuan** | SIVMI CRM dibangun untuk menyatukan pengelolaan lead, komunikasi, follow-up, pendaftaran, dan pembayaran yang sebelumnya berpotensi tersebar pada catatan pribadi, berkas terpisah, atau percakapan WhatsApp. Tujuannya adalah menyediakan riwayat pelanggan yang terstruktur, mengurangi duplikasi data, memperjelas tanggung jawab PIC, dan mendukung keputusan berbasis data. |
| **2. Scope dan Skala** | Sistem mencakup Dashboard dan KPI, Leads, Follow-up, Inbox WhatsApp, Pendaftaran, Pembayaran, Program Bimbel, master data, Target Kinerja, Laporan, audit, REST API, webhook WAHA, serta integrasi API Sekolah Indonesia. Pengguna meliputi Superadmin, Admin Cabang, Staff, Keuangan, Direksi, API Client, dan WAHA dengan hak akses berbasis peran serta cakupan cabang. |
| **3. Arsitektur dan Teknologi** | Arsitektur menggunakan modular monolith berbasis Laravel dengan Filament, Livewire, Blade, Tailwind CSS, MySQL, REST API, queue, scheduler, dan integrasi eksternal. Lingkungan lokal utama menggunakan Docker Compose dengan Nginx, PHP-FPM, MySQL, Redis, queue worker, scheduler, dan WAHA GOWS; produksi shared hosting hanya menjalankan komponen web PHP dan database yang sesuai. |
| **4. Metodologi** | Pengembangan menggunakan pendekatan modular dan bertahap, dengan pemisahan fungsi bisnis berdasarkan modul, validasi data, normalisasi nomor WhatsApp, pembatasan akses, idempotensi, serta pencatatan audit. Setiap perubahan perlu diverifikasi melalui pengujian aplikasi, integrasi, keamanan, dan kesiapan deployment sebelum digunakan operasional. |
| **5. Governance dan Service Management** | Governance diarahkan pada prinsip COBIT melalui pengendalian akses, pemisahan kewenangan, audit aktivitas, perlindungan data, pengelolaan risiko, dan pemantauan KPI. Pengelolaan layanan diarahkan pada praktik ITIL, terutama pengelolaan insiden, perubahan, permintaan layanan, backup, pemulihan, dan pemantauan komponen aplikasi. |
| **6. Risiko dan Mitigasi** | Risiko utama meliputi keterbatasan shared hosting untuk queue worker dan WAHA, ketergantungan pada layanan eksternal, keamanan webhook dan token API, duplikasi data, serta kesinambungan backup. Mitigasinya mencakup HMAC, rate limiting, idempotency key, deduplikasi, normalisasi data, cache dan timeout integrasi, backup, cron scheduler, serta rekomendasi pemindahan komponen jangka panjang ke VPS berbasis Docker. |
| **7. Implementasi dan Kualitas** | Implementasi mencakup penyiapan environment Docker, konfigurasi database dan filesystem, build asset Vite, migrasi data, integrasi API, konfigurasi webhook, pengujian hak akses, verifikasi transaksi, pelatihan pengguna, dokumentasi operasional, dan change management. Deployment produksi harus mengarahkan document root ke direktori `public` dan memastikan cron serta permission berjalan sesuai kebutuhan Laravel. |
| **8. KPI dan Manfaat** | Keberhasilan diukur melalui jumlah dan konversi lead, kecepatan serta kelengkapan follow-up, jumlah siswa terdaftar, omzet tervalidasi, pencapaian target cabang dan pengguna, kualitas data, serta ketersediaan layanan. Manfaat yang diharapkan adalah proses yang lebih terpusat, pelacakan pelanggan yang lebih baik, kontrol operasional yang lebih kuat, dan laporan manajemen yang lebih cepat serta dapat ditelusuri. |

## Governance dan Service Management Terhubung dengan Project

### 1. Tujuan governance pada SIVMI CRM

Governance SIVMI CRM berfungsi memastikan bahwa penggunaan data, fitur, integrasi, dan infrastruktur mendukung proses bisnis bimbingan belajar secara terkendali. Governance tidak hanya mengatur siapa yang dapat membuka menu, tetapi juga siapa yang boleh melihat atau mengubah data, siapa yang bertanggung jawab atas layanan, bagaimana perubahan disetujui, bagaimana insiden ditangani, dan bagaimana bukti operasional disimpan.

Penerapannya dapat dipetakan ke lima keputusan governance TI:

| Keputusan governance | Penerapan pada SIVMI CRM | Bukti atau data terhubung |
|---|---|---|
| Prinsip TI | Data lead, komunikasi, pendaftaran, dan pembayaran menjadi sumber informasi operasional terpusat. | Modul Leads, Follow-up, Inbox WhatsApp, Pendaftaran, Pembayaran, Dashboard, dan Laporan |
| Arsitektur | Modular monolith Laravel dengan service pendukung Nginx, PHP-FPM, MySQL, Redis, queue, scheduler, dan WAHA. | `docker/compose.production.yml`, REST API, webhook WAHA, dan integrasi API Sekolah Indonesia |
| Infrastruktur | Komponen web, database, worker, scheduler, Redis, storage, dan session WhatsApp harus memiliki owner serta prosedur pemulihan. | Service Docker production, volume `mysql_data`, `redis_data`, `storage_data`, dan `waha_sessions` |
| Prioritas bisnis | Akuisisi lead, follow-up, konversi, pembayaran tervalidasi, dan pemantauan cabang menjadi prioritas layanan. | Status lead, riwayat follow-up, status pembayaran, target kinerja, dan KPI dashboard |
| Investasi TI | Shared hosting dapat dipakai untuk web sementara, tetapi operasi penuh memerlukan VPS/Docker karena WAHA dan worker membutuhkan proses jangka panjang. | Catatan kelayakan shared hosting dan target arsitektur VPS pada bagian 10 dan 11 |

### 2. Struktur peran dan pemisahan kewenangan

Project menggunakan lima role aplikasi utama pada `App\\Enums\\UserRole`: Superadmin, Admin Cabang, Staff, Keuangan, dan Direksi. API Client dan WAHA merupakan aktor integrasi, bukan role panel operasional. Role tidak hanya mengatur tampilan navigasi, tetapi dipakai pada policy, resource, query scope, action, dan pemeriksaan akses URL langsung.

| Role | Kewenangan governance | Service responsibility dan batasan |
|---|---|---|
| Superadmin | Akses global, konfigurasi, pengguna, cabang, integrasi, audit, backup, dan restore. | Menjadi pemilik keputusan konfigurasi dan pemulihan; pada Pendaftaran/Pembayaran hanya melihat data. |
| Admin Cabang | Mengelola operasional penjualan dan pengguna/staff dalam cabangnya. | Menjaga kualitas data cabang dan target; tidak memutasi Pendaftaran/Pembayaran. |
| Staff | Membuat dan memperbarui lead, follow-up, pendaftaran, serta pembayaran awal. | Menjadi pelaksana proses operasional; akses dibatasi pada data dan aktivitas yang ditugaskan. |
| Keuangan | Mencatat, memeriksa, memverifikasi, menolak, dan mengoreksi pembayaran sesuai cabang. | Tidak mengakses operasional lead, follow-up, WhatsApp, atau master data; wajib memiliki cabang. |
| Direksi | Melihat dashboard, KPI, laporan, dan audit lintas cabang. | Berfungsi sebagai pemantau dan pengambil keputusan; tidak melakukan mutasi operasional. |
| API Client | Membaca/menulis data melalui bearer token dan ability yang diberikan. | Terbatas pada `leads:read`, `leads:write`, `students:read`, dan scope cabang. |
| WAHA | Mengirim event session, pesan, status pesan, dan media melalui webhook/API. | Tidak memperoleh akses panel; setiap event harus melewati HMAC, deduplikasi, dan pemrosesan queue. |

Kontrol teknis yang sudah terlihat pada kode meliputi `hasGlobalBranchAccess()`, `canManageOperationalData()`, `canManageRegistrations()`, `canRecordPayments()`, dan `canVerifyPayments()`. Dengan demikian, pemisahan tugas untuk pembayaran telah dibuat eksplisit: Staff dapat mencatat pembayaran, sedangkan verifikasi dilakukan oleh Keuangan.

### 3. Data governance dan scope cabang

Scope cabang merupakan kontrol utama karena data SIVMI CRM mencakup beberapa wilayah dan pengguna dengan kewenangan berbeda. Model seperti `ActivityLog`, `Pembayaran`, `Lead`, `Pendaftaran`, percakapan WhatsApp, akun WhatsApp, dan API Client menggunakan hubungan `cabang_id` atau relasi cabang untuk membatasi data.

Contoh penerapan:

- `ActivityLog::visibleTo()` mengembalikan seluruh audit untuk role global, membatasi pengguna non-global ke `cabang_id`, dan membatasi Staff hanya pada aktivitas miliknya.
- Halaman Audit Aktivitas memaksa cabang pengguna non-global dan memaksa Staff melihat audit miliknya sendiri.
- Pendaftaran dan Pembayaran menerapkan pembatasan pada query serta tindakan, bukan hanya menyembunyikan tombol.
- API menggunakan ability token, filter cabang, pagination, dan pembatasan scope client.
- Pencocokan lead WhatsApp memeriksa akun, session, nomor normalisasi, serta cabang untuk mencegah percakapan tertaut ke data cabang yang salah.

#### Data snapshot yang terhubung

Backup database `sivmi-crm-20260827-163813-manual-dtg9ov.sql` menunjukkan kondisi snapshot pada 27 Agustus 2026. Angka ini adalah data historis backup, bukan klaim jumlah live saat ini.

| Entitas | Data snapshot | Makna governance/service |
|---|---:|---|
| Cabang | 3 | Scope wilayah sudah menjadi dimensi kontrol akses dan pelaporan. |
| Pengguna | 11 | Perlu review role, status aktif, cabang, dan prinsip least privilege. |
| Activity log | 17 | Audit sudah menyimpan event, user, cabang, subject, IP, user agent, dan properties. |
| Akun WhatsApp | 1 berstatus `Working` | Snapshot belum merepresentasikan target terbaru 3 session WAHA aktif; konfigurasi produksi harus diuji untuk tiga session. |
| Lead | sekitar 205 | Menjadi sumber utama funnel, assignment PIC, dan scope cabang. |
| Pendaftaran | sekitar 68 | Menghubungkan konversi lead dengan layanan dan cabang. |
| Pembayaran | sekitar 68 | Data finansial memerlukan pemisahan pencatatan dan verifikasi. |
| Event webhook WAHA | sekitar 197 | Menunjukkan bahwa webhook dan queue adalah komponen layanan yang harus dimonitor, bukan sekadar integrasi pasif. |

### 4. Service catalogue dan ownership

Service management perlu melihat SIVMI CRM sebagai kumpulan layanan yang saling bergantung. Kegagalan satu komponen dapat memengaruhi proses bisnis yang berbeda, sehingga owner, dependensi, indikator kesehatan, dan prosedur pemulihan perlu dicatat.

| Layanan | Komponen/data | Owner utama | Indikator layanan |
|---|---|---|---|
| Aplikasi CRM | Nginx, PHP-FPM, Laravel, Filament, Livewire | Technical Lead | HTTP 200/health endpoint, response time, error rate |
| Data operasional | MySQL, migration, Eloquent, backup SQL | Database Owner/Superadmin | Koneksi database, query error, backup berhasil, restore test |
| Queue dan scheduler | `queue:work`, `schedule:work`, database/Redis | Operations Owner | Queue backlog, job failed, scheduler last run |
| WhatsApp | WAHA GOWS, session, webhook, media, inbox | Integration Owner | Session `Working`, last seen, webhook pending/failed, pesan gagal |
| Akses dan keamanan | Auth, role, policy, bearer token, HMAC | Security Owner | Failed login, token/secret rotation, unauthorized request, audit event |
| Pelaporan dan KPI | Dashboard, target, laporan, export | Product Owner/Direksi | Aktual vs target, freshness data, keberhasilan export |
| Dukungan pengguna | SOP, training, incident/request handling | Service Desk/Branch Owner | Waktu respons, waktu penyelesaian, user adoption, issue berulang |

### 5. Praktik ITIL yang disesuaikan

#### Incident management

Insiden adalah gangguan yang menurunkan atau menghentikan layanan, misalnya session WhatsApp terputus, webhook gagal, queue menumpuk, dashboard tidak dapat dimuat, database tidak tersedia, atau pengguna salah melihat scope cabang. Setiap insiden perlu memiliki nomor, waktu mulai, layanan terdampak, cabang terdampak, severity, owner, tindakan sementara, akar masalah, dan waktu pemulihan.

Prioritas awal yang disarankan:

| Severity | Contoh pada project | Target respons | Target pemulihan awal |
|---|---|---:|---:|
| P1 kritis | Aplikasi atau database tidak tersedia; data lintas cabang terbuka; seluruh WhatsApp gagal. | 15 menit | 4 jam |
| P2 tinggi | Tiga session WAHA gagal, queue terus bertambah, pembayaran tidak dapat diverifikasi. | 30 menit | 8 jam |
| P3 sedang | Satu cabang atau fitur laporan terganggu, tetapi transaksi utama masih berjalan. | 4 jam kerja | 1 hari kerja |
| P4 rendah | Kesalahan label, export tertentu, atau permintaan bantuan pengguna. | 1 hari kerja | 3 hari kerja |

Widget `WhatsAppHealthOverview` dan `WhatsAppOperationsOverview` sudah menyediakan data untuk triage: jumlah session terhubung/gagal, service health, webhook pending/failed, percakapan unread, konflik pencocokan, lead unresolved, pesan gagal, dan aktivitas akun WhatsApp.

#### Change management

Perubahan pada migration, role, policy, pembayaran, webhook, session WhatsApp, konfigurasi environment, dan deployment harus melewati analisis dampak. Change request minimal mencatat alasan, owner, risiko, layanan terdampak, rencana test, rencana rollback, waktu pelaksanaan, dan hasil verifikasi.

Runbook deployment sudah mendukung sebagian kontrol ini melalui build image production, penggunaan `IMAGE_TAG`, migrasi eksplisit, backup sebelum migrasi, smoke test, health check, dan rollback ke image sebelumnya. Gap yang harus dijaga adalah bukti persetujuan perubahan, catatan keputusan arsitektur, dan verifikasi restore database jika migrasi tidak kompatibel ke belakang.

#### Request management

Permintaan layanan harus dibedakan dari insiden, misalnya pembuatan pengguna/cabang, penggantian role, reset koneksi WhatsApp, permintaan laporan, impor CSV, atau permintaan restore. Permintaan yang mengubah hak akses atau data finansial wajib memiliki pemohon, approver, bukti pelaksanaan, dan audit log.

#### Problem management

Insiden berulang seperti webhook gagal, lead ganda, queue backlog, query dashboard lambat, atau session sering disconnected harus dikumpulkan sebagai problem. Penyelesaiannya tidak cukup dengan restart; perlu analisis akar masalah, tindakan permanen, owner, dan verifikasi bahwa frekuensi insiden menurun.

#### Backup, restore, dan continuity

Backup database dapat dibuat melalui `DatabaseBackupService` menggunakan `mysqldump` dengan `--single-transaction`, `--quick`, dan `--skip-lock-tables`. Restore hanya dapat dilakukan Superadmin, memerlukan konfirmasi `RESTORE`, memvalidasi file SQL, dan membuat safety backup sebelum pemulihan. Retensi backup dikendalikan oleh konfigurasi `backup.retention` dengan default hingga 20 file.

Kontrol tersebut melindungi database, tetapi service continuity masih harus mencakup `storage_data`, media WhatsApp, volume `waha_sessions`, konfigurasi environment, serta backup offsite. Backup yang berhasil dibuat harus dilengkapi uji restore berkala; keberadaan file SQL saja belum membuktikan kemampuan pemulihan.

### 6. Kontrol keamanan dan integrasi

| Kontrol | Implementasi project | Risiko yang dikurangi |
|---|---|---|
| HMAC webhook | `VerifyWahaWebhookHmac` memeriksa algoritma SHA-512, signature, dan secret sebelum request diproses. | Event palsu atau manipulasi webhook. |
| Idempotency API | `EnsureApiIdempotency` menyimpan hash request, response, TTL, dan menolak penggunaan key untuk payload berbeda. | Duplikasi lead atau transaksi akibat retry. |
| Queue webhook | `ProcessWahaWebhook` memiliki retry 5 kali dengan backoff 10, 30, 60, dan 180 detik. | Kehilangan event saat gangguan sementara. |
| Deduplikasi event | Event diproses berdasarkan status `Pending`, `Processing`, `Processed`, `Ignored`, atau `Failed`. | Pemrosesan event WhatsApp berulang. |
| Pembatasan secret | Production mewajibkan API key, HMAC secret, password database/Redis, dan `APP_KEY` berbeda dari development. | Kebocoran kredensial dan akses tidak sah. |
| Health monitoring | Widget dan health check Docker memeriksa service WAHA, session, webhook, database, dan service aplikasi. | Gangguan tidak terdeteksi terlalu lama. |

### 7. KPI governance dan service management

| Dimensi | KPI terhubung | Target awal yang disarankan |
|---|---|---:|
| Akses | Persentase uji RBAC lulus, unauthorized request, review role selesai | 100% skenario kritis lulus; review bulanan |
| Audit | Aktivitas penting memiliki user, cabang, waktu, event, dan properties | 100% perubahan kritis tercatat |
| WhatsApp | Session `Working`, webhook success rate, pesan gagal, event pending, konflik lead | 3 session aktif; webhook sukses >=99%; tidak ada backlog menetap |
| Queue | Job gagal, usia queue tertua, waktu tunggu, retry exhaustion | Tidak ada job kritis tertunda >30 menit |
| Availability | Uptime aplikasi, database, WAHA, dan scheduler | >=99% bulanan untuk baseline awal |
| Backup | Job backup berhasil, usia backup terakhir, restore test | Backup harian 100%; restore test minimal bulanan |
| Perubahan | Change sukses tanpa rollback, incident pascarilis, migration failure | >=95% change sukses; tidak ada change tanpa approval |
| Dukungan | Waktu respons dan penyelesaian P1-P4, issue berulang | Mengikuti target severity dan ditinjau bulanan |
| Adopsi | Pengguna aktif per role, kelengkapan follow-up, proses di luar sistem | >=80% pengguna target aktif; tren proses manual menurun |

### 8. Gap dan rencana penguatan

Kontrol aplikasi sudah cukup kuat untuk menjadi fondasi, tetapi beberapa hal masih perlu diformalisasi agar governance dan service management benar-benar operasional:

1. Tetapkan RACI resmi untuk Product Owner, Technical Lead, Database Owner, Integration Owner, Security Owner, Service Desk, dan Branch Owner.
2. Buat register layanan, register risiko, katalog incident/request/problem, serta SLA yang disetujui pemilik sistem.
3. Pisahkan data metrik service management dari log teknis biasa agar tren queue, webhook, backup, response time, dan availability dapat dilaporkan.
4. Lakukan review role dan scope cabang setiap bulan, terutama karena target operasional sekarang mencakup tiga session WhatsApp.
5. Uji restore database, storage, media, dan session WAHA; dokumentasikan RPO/RTO aktual, bukan hanya target.
6. Tambahkan approval dan bukti change untuk migration, policy, role, secret, deployment, serta perubahan integrasi.
7. Gunakan hasil monitoring WhatsApp sebagai input incident/problem management, bukan hanya widget informasi di dashboard.

## 1. Gambaran Umum Sistem Informasi

SIVMI CRM, yang pada antarmuka operasional menggunakan identitas **CRM_VILLA MERAH**, merupakan sistem informasi Customer Relationship Management berbasis web yang dirancang untuk mendukung proses pemasaran, penerimaan calon siswa, pelayanan komunikasi, pendaftaran, pembayaran, serta pemantauan kinerja pada lembaga bimbingan belajar. Sistem memusatkan data calon siswa dan aktivitas pelayanan dalam satu aplikasi sehingga informasi tidak lagi tersebar pada catatan pribadi, berkas terpisah, atau percakapan WhatsApp tanpa riwayat yang terstruktur.

Tujuan utama sistem adalah membantu organisasi mengelola perjalanan calon siswa sejak pertama kali menjadi lead hingga melakukan pendaftaran dan pembayaran. Setiap lead dapat dicatat berdasarkan nama, nomor WhatsApp yang dinormalisasi, kota asal, sekolah, kelas sekolah, program yang diminati, sumber informasi, status, cabang, dan penanggung jawab atau PIC. Nomor WhatsApp digunakan sebagai salah satu identitas penting untuk mencegah duplikasi data, sedangkan perubahan status dan aktivitas follow-up disimpan sebagai riwayat yang dapat ditelusuri.

Alur operasional utama sistem meliputi:

1. Lead diperoleh melalui input manual, impor CSV, komunikasi WhatsApp, atau proses lain yang diizinkan organisasi.
2. Data utama divalidasi dan nomor WhatsApp dinormalisasi untuk menjaga konsistensi serta mengurangi duplikasi.
3. Lead ditempatkan pada cabang dan, apabila diperlukan, ditugaskan kepada PIC sesuai kewenangan pengguna.
4. PIC melakukan follow-up, mencatat hasil komunikasi, menentukan jadwal tindak lanjut, dan memperbarui status lead.
5. Lead yang berhasil dikonversi diproses menjadi data pendaftaran siswa pada program bimbel yang dipilih.
6. Transaksi pembayaran dicatat dan divalidasi sebagai bagian dari administrasi pendaftaran.
7. Data operasional diolah menjadi dashboard, indikator kinerja, peta persebaran, target, dan laporan untuk mendukung evaluasi manajemen.

Sistem digunakan oleh **Superadmin, Admin Cabang, Staff, Keuangan, dan Direksi** dengan hak akses yang berbeda. Superadmin memiliki cakupan lintas cabang dan konfigurasi sistem; Admin Cabang mengelola operasional penjualan pada cabangnya; Staff menangani leads, follow-up, komunikasi, pendaftaran, dan pencatatan awal pembayaran; Keuangan memeriksa serta mengelola transaksi dalam scope cabangnya; sedangkan Direksi memperoleh akses pemantauan dan pelaporan. Pada modul Pendaftaran dan Pembayaran, Admin Cabang dan Superadmin hanya dapat melihat data. Pembatasan diterapkan pada navigasi, query, tindakan, dan akses URL langsung.

Modul utama mencakup Dashboard, Leads, Follow-up, Inbox WhatsApp, Pendaftaran, Pembayaran, Program Bimbel, Cabang, Sekolah, Pengguna, Target Kinerja, Laporan, Pengaturan, Notifikasi, Audit Aktivitas, Backup dan Restore, serta Koneksi WhatsApp. Pencarian sekolah dapat menggunakan API Sekolah Indonesia; sekolah yang dipilih kemudian disimpan ke database lokal agar tetap dapat digunakan oleh proses internal. Dashboard juga menyediakan visualisasi persebaran lead menggunakan Leaflet dan OpenStreetMap.

Integrasi WhatsApp dijalankan melalui **WAHA dengan engine GOWS**. Aplikasi berkomunikasi dengan WAHA melalui API, sedangkan pesan atau status dari WhatsApp diterima melalui webhook yang dilindungi signature HMAC dan mekanisme deduplikasi. Pemrosesan yang tidak harus diselesaikan langsung dilakukan melalui queue, sementara scheduler menjalankan pekerjaan berkala seperti pemrosesan terjadwal dan pemeliharaan operasional.

Dari sisi arsitektur, SIVMI CRM termasuk **modular monolith**, yaitu fungsi bisnis berada dalam satu aplikasi Laravel dan satu basis kode utama, tetapi dipisahkan secara logis berdasarkan modul. MySQL digunakan untuk penyimpanan data utama, sedangkan Nginx, PHP-FPM, queue worker, scheduler, Redis, dan WAHA dijalankan sebagai komponen pendukung. Bentuk ini sesuai untuk pengembangan dan operasional saat ini karena lebih sederhana untuk dikelola daripada microservices, tetapi tetap memungkinkan pemisahan layanan tertentu pada masa mendatang apabila beban, organisasi tim, atau kebutuhan skalabilitas meningkat.

Secara umum, sistem ini sudah dapat dikategorikan sebagai **aplikasi atau Sistem Informasi CRM vertikal untuk bimbingan belajar** karena menjalankan fungsi inti pengelolaan hubungan dengan calon pelanggan: akuisisi lead, pencatatan interaksi, penugasan PIC, pengelolaan pipeline, konversi menjadi siswa, pencatatan transaksi, serta analisis kinerja. Ruang lingkupnya tidak ditujukan sebagai CRM generik untuk semua industri, melainkan disesuaikan dengan proses bisnis pendidikan nonformal dan operasional Villa Merah.

## 2. Identitas dan Karakteristik Sistem

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
| URL produksi | [https://crm.sivmi.id/](https://crm.sivmi.id/) |

## 3. Pengguna dan Hak Akses

| Aktor | Cakupan akses |
|---|---|
| Superadmin | Mengelola seluruh cabang, master data, pengguna, konfigurasi, integrasi, audit, dan operasional; hanya melihat Pendaftaran dan Pembayaran |
| Admin Cabang | Mengelola operasional penjualan, staff, target, dan koneksi WhatsApp pada cabangnya sendiri; hanya melihat Pendaftaran dan Pembayaran |
| Staff | Menangani leads, follow-up, Inbox WhatsApp, pendaftaran, pembayaran, dan data yang ditugaskan kepadanya |
| Keuangan | Mengelola dan memverifikasi pembayaran pada cabangnya, melihat pendaftaran, dashboard, dan laporan; tidak mengakses operasional leads, follow-up, WhatsApp, atau master data |
| Direksi | Melihat dashboard, KPI, laporan, dan audit lintas cabang tanpa melakukan mutasi data operasional |
| API Client | Mengakses REST API berdasarkan bearer token, ability `leads:read`, `leads:write`, dan/atau `students:read`, serta scope cabang |
| WAHA | Sistem eksternal untuk session WhatsApp, pesan masuk/keluar, status pesan, dan media melalui engine GOWS |

## 4. Fokus Bisnis dan Modul Aktual

| Area | Cakupan implementasi |
|---|---|
| Dashboard dan KPI | Ringkasan leads, konversi, siswa aktif, omzet tervalidasi, funnel status lead, tren, target, distribusi program, kategori, tipe, metode, kelas, jenjang, sekolah, wilayah, serta peta persebaran kota berbasis Leaflet/OpenStreetMap |
| Leads | Input, edit, import/export CSV, pilih banyak, bulk edit, penghapusan terkontrol, sumber lead terstruktur, status `Cold`, `Warm`, `Hot`, `Daftar`, dan `Tidak Berminat`, program diminati, sekolah, cabang, serta PIC |
| Follow-up | Riwayat kontak, metode, status kontak, jadwal berikutnya, dan catatan hasil |
| Inbox WhatsApp | Percakapan teks dan media, unread count, pencocokan lead, penandaan konflik/duplikasi, serta pengiriman pesan melalui session pengguna |
| Pendaftaran | Konversi lead menjadi siswa/pendaftaran pada program bimbel |
| Pembayaran | Staff mencatat pembayaran awal berstatus menunggu; Keuangan cabang mencatat, memverifikasi, menolak, dan mengoreksi transaksi sesuai kewenangan |
| Program bimbel | Klasifikasi program, kategori, kelas program, metode belajar, tipe, jenjang, tingkat kelas, biaya, diskon, pertemuan, dan fasilitas |
| Master data | Cabang, sekolah, pengguna, program, pendaftaran, dan pembayaran; sekolah dapat dicari dari API Sekolah Indonesia lalu dipersistenkan ke database lokal |
| Target kinerja | Target omzet dan siswa per cabang serta alokasi target pengguna |
| Laporan | Pelaporan berbasis role/cabang dan ekspor sesuai izin |
| Kontrol | Pengaturan aplikasi, notifikasi database, audit aktivitas, profil pengguna, dan koneksi WhatsApp |
| Integrasi | REST API leads, webhook WAHA dengan kontrol keamanan/idempotensi, serta API Sekolah Indonesia dengan cache, timeout, normalisasi, dan fallback manual |

> Modul tugas dan pembelajaran belum tercatat sebagai resource operasional pada baseline kode saat ini. Keduanya harus diperlakukan sebagai rencana pengembangan, bukan fitur as-built.

### Matriks akses Pendaftaran dan Pembayaran

| Peran | Pendaftaran | Pembayaran |
|---|---|---|
| Superadmin | Lihat seluruh cabang; tidak membuat atau mengubah | Lihat seluruh cabang; tidak membuat, mengubah, atau menghapus |
| Admin Cabang | Lihat cabang sendiri; tidak membuat atau mengubah | Lihat cabang sendiri; tidak membuat, mengubah, atau menghapus |
| Staff | Membuat dan mengubah data miliknya | Mencatat transaksi berstatus menunggu dan mengubahnya selama masih menunggu |
| Keuangan | Lihat seluruh pendaftaran pada cabangnya | Mencatat dan memverifikasi pembayaran pada cabangnya; tidak menghapus |
| Direksi | Lihat seluruh cabang | Lihat seluruh cabang; tidak melakukan mutasi |

Role Keuangan wajib memiliki cabang. Hanya Superadmin yang dapat menetapkan role Keuangan melalui manajemen pengguna. Pembatasan diterapkan pada navigasi, query data, tombol tindakan, dan otorisasi URL langsung.

## 5. Antarmuka REST API dan Webhook

| Atribut | Keterangan |
|---|---|
| Gaya API | REST API berbasis JSON dengan prefix versi `/api/v1` |
| API baca | `GET /api/v1/leads` dan `GET /api/v1/leads/{lead}` |
| API tulis | `POST /api/v1/leads` |
| API data siswa | `GET /api/v1/students` dengan ability `students:read`, filter, pagination, dan scope cabang |
| Autentikasi API | Bearer token milik API Client; token disimpan dalam bentuk hash |
| Otorisasi | Ability `leads:read`, `leads:write`, dan `students:read`, serta pembatasan cabang |
| Proteksi | Rate limiting, request ID, idempotency key untuk operasi tulis, dan respons error terstruktur |
| Webhook WAHA | `POST /api/webhooks/waha` |
| Keamanan webhook | HMAC signature, identifikasi session, dan deduplikasi message ID |
| Engine WhatsApp | WAHA menggunakan engine **GOWS** |

REST API pada baseline terbaru bersifat **baca dan tulis untuk data leads** serta **baca untuk data siswa terdaftar**. Panduan konfigurasi dan pemakaian endpoint siswa tersedia di `docs/PANDUAN-INTEGRASI-API-DATA-SISWA.md`.

## 6. Driver dan Penyimpanan Aplikasi

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

## 7. Lingkungan Pengembangan Lokal Utama

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

## 8. Lingkungan Produksi Shared Hosting

| Atribut | Keterangan berdasarkan data akun hosting |
|---|---|
| Model hosting | Shared Hosting |
| Provider | Hostinger.com |
| Paket | Business |
| Masa aktif paket | Sampai **15 Januari 2028** |
| URL | [https://crm.sivmi.id/](https://crm.sivmi.id/) |
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

## 9. Dukungan Node.js Hosting

| Atribut | Keterangan |
|---|---|
| Versi Node.js yang didukung | 24.x, 22.x, 20.x, dan 18.x |
| Kesesuaian proyek | Sesuai; baseline build proyek memakai Node.js 22.x |
| Package manager | npm sebagai default; yarn dan pnpm tersedia |
| Framework front-end yang didukung | Angular, Astro, Gatsby, Next.js, Nitro, Nuxt, Parcel, React, React Router, Svelte, SvelteKit, Vite, dan Vue.js |
| Framework backend Node.js yang didukung | Astro, Express, Fastify, Hono, NestJS, Next.js, Nitro, Nuxt, React Router, dan SvelteKit |
| Kebutuhan runtime SIVMI CRM | Node.js tidak wajib berjalan terus-menerus di produksi karena backend menggunakan PHP; Node.js hanya diperlukan untuk build asset Vite |

## 10. Catatan Kelayakan Shared Hosting

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

## 11. Target Arsitektur Berikutnya

Target operasional yang direkomendasikan adalah VPS berbasis Docker Compose dengan Nginx, PHP-FPM, MySQL/managed database, Redis, queue worker, scheduler, dan WAHA GOWS. Shared hosting dapat dipakai sementara untuk antarmuka web dan database, tetapi tidak merepresentasikan seluruh arsitektur as-built Docker secara utuh.
