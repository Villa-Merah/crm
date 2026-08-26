# Panduan Instalasi Pertama SIVMI CRM

> **Mode aktif yang direkomendasikan: full Docker tanpa Laragon.** Laragon tidak perlu dijalankan. Folder `C:\laragon\www\sivmi.crm` hanya menjadi lokasi source code dan dapat dipindahkan ke folder Windows lain jika diinginkan. Baseline ini telah diverifikasi dengan 72 test dan 448 assertion pada 24 Agustus 2026.

## Mulai cepat full Docker

Prasyarat: Docker Desktop aktif dengan status **Engine running** dan menggunakan Linux containers.

Jalankan dari Windows PowerShell 5.1 atau PowerShell 7:

```powershell
cd C:\laragon\www\sivmi.crm
Set-ExecutionPolicy -Scope Process Bypass
.\docker\start-full.ps1
```

Skrip akan:

1. membuat `docker/.env` bila belum tersedia;
2. membuat `APP_KEY`, API key WAHA, HMAC secret, dan password dashboard lokal jika masih placeholder;
3. membangun image aplikasi;
4. menjalankan Nginx, PHP/Laravel, MySQL, Redis, Node, queue, scheduler, dan WAHA;
5. menjalankan migration otomatis tanpa menghapus data;
6. menunggu health check aplikasi dan WAHA.

Jalankan test full Docker dengan skrip khusus:

```powershell
.\docker\test-full.ps1
```

Skrip memasang `APP_ENV=testing` sebelum Laravel dibootstrap dan menggunakan SQLite in-memory. Jangan menjalankan `php artisan test` langsung pada container `app` karena container development mewarisi `APP_ENV=local`, yang membuat helper pengujian form Filament mengabaikan pengisian state.

Snapshot verifikasi full Docker 24 Agustus 2026: **72 test lulus dengan 448 assertion**.

Akses:

```text
SIVMI CRM : http://localhost:8000/admin/login
WAHA      : http://localhost:3000/dashboard
```

Perintah operasional:

```powershell
.\docker\status-full.ps1
.\docker\logs-full.ps1 app
.\docker\logs-full.ps1 waha
.\docker\stop-full.ps1
```

Seeder tidak dijalankan otomatis. Untuk database Docker baru dan kosong saja:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan db:seed
```

Bagian Laragon/hybrid di bawah dipertahankan sebagai referensi alternatif lama. Untuk pengembangan sekarang, gunakan skrip mulai cepat di atas.

Panduan ini digunakan untuk menjalankan SIVMI CRM pertama kali pada Windows dengan konfigurasi hybrid:

- Laravel, Filament, PHP, dan MySQL berjalan melalui Laragon;
- WAHA dengan engine GOWS berjalan melalui Docker Desktop;
- queue worker dan scheduler Laravel berjalan pada terminal Laragon;
- aplikasi diakses melalui `http://sivmi.crm.test`;
- dashboard WAHA lokal diakses melalui `http://localhost:3000/dashboard`.

Konfigurasi hybrid pada bagian ini dipertahankan sebagai referensi kompatibilitas lama, bukan baseline pengembangan. Jangan menjalankan service `app`, `web`, atau `database` dari Docker bersamaan dengan Laragon jika mengikuti bagian legacy ini.

## 1. Arsitektur pengembangan lokal

```text
Browser
  |
  v
http://sivmi.crm.test
  |
  +-- Laragon Apache/Nginx + PHP
  +-- MySQL Laragon :3306
  +-- Laravel queue worker
  +-- Laravel scheduler
  |
  +-- API ke http://127.0.0.1:3000
          |
          v
      Docker WAHA GOWS
          |
          +-- webhook ke http://sivmi.crm.test/api/webhooks/waha
```

Folder proyek yang digunakan:

```text
C:\laragon\www\sivmi.crm
```

## 2. Perangkat lunak yang diperlukan

Siapkan:

1. Laragon dengan Apache atau Nginx dan MySQL.
2. PHP 8.3 atau lebih baru. PHP 8.4 dapat digunakan.
3. Composer 2.
4. Node.js 22 dan npm.
5. Docker Desktop dengan Linux containers.
6. Git jika proyek diperoleh dari repository.

Buka **Laragon Terminal**, kemudian periksa:

```powershell
php -v
composer --version
node -v
npm -v
docker version
docker compose version
```

Periksa PHP yang digunakan:

```powershell
where.exe php
```

Gunakan terminal Laragon agar PHP CLI sesuai dengan PHP yang digunakan web server Laragon. Pastikan ekstensi minimum seperti `curl`, `fileinfo`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, dan `zip` aktif.

## 3. Menempatkan proyek di Laragon

Jika folder proyek sudah berada di lokasi berikut, lewati tahap penyalinan:

```text
C:\laragon\www\sivmi.crm
```

Pada Laragon:

1. Klik **Start All**.
2. Pastikan web server dan MySQL berstatus aktif.
3. Klik **Menu → Tools → Quick add** hanya jika versi PHP atau Node yang diperlukan belum tersedia.
4. Klik **Menu → Reload** setelah menambahkan proyek baru agar virtual host dibuat ulang.

Laragon seharusnya membuat alamat:

```text
http://sivmi.crm.test
```

Jika domain belum dikenali, gunakan **Menu → Apache/Nginx → Reload** atau restart Laragon sebagai administrator sekali untuk memperbarui file hosts.

## 4. Menginstal dependency aplikasi

Buka Laragon Terminal:

```powershell
cd C:\laragon\www\sivmi.crm
composer install
npm install
```

Jangan menghapus dependency atau file proyek yang sudah ada jika instalasi dilakukan pada workspace pengembangan aktif.

## 5. Membuat database MySQL Laragon

Buka HeidiSQL atau phpMyAdmin dari Laragon, kemudian buat database kosong:

```text
sivmi_crm
```

Gunakan collation:

```text
utf8mb4_unicode_ci
```

Konfigurasi default Laragon umumnya:

```text
Host     : 127.0.0.1
Port     : 3306
Username : root
Password : kosong
```

Jika instalasi Laragon menggunakan password MySQL, gunakan password tersebut pada `.env`.

## 6. Membuat konfigurasi Laravel

Salin contoh environment hanya jika `.env` belum tersedia:

```powershell
Copy-Item .env.example .env
```

Buka `.env`, lalu gunakan konfigurasi dasar berikut:

```dotenv
APP_NAME="SIVMI CRM"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://sivmi.crm.test
APP_TIMEZONE=Asia/Jakarta

APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sivmi_crm
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

WAHA_BASE_URL=http://127.0.0.1:3000
WAHA_API_KEY=GANTI_DENGAN_API_KEY_LOKAL
WAHA_WEBHOOK_URL=http://sivmi.crm.test/api/webhooks/waha
WAHA_WEBHOOK_HMAC_SECRET=GANTI_DENGAN_HMAC_SECRET_LOKAL
WAHA_TIMEOUT=15
```

API key WAHA dan HMAC secret harus berbeda. Buat dua nilai acak menggunakan perintah berikut dan salin masing-masing hasilnya:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Jangan memasukkan `.env`, API key, HMAC secret, password, atau data siswa ke repository.

Jika `APP_KEY` masih kosong, jalankan:

```powershell
php artisan key:generate
php artisan config:clear
```

## 7. Menyiapkan database aplikasi

Jalankan migration:

```powershell
php artisan migrate
php artisan storage:link
```

Periksa status migration:

```powershell
php artisan migrate:status
```

### Data demo lokal

Untuk instalasi lokal pertama yang membutuhkan contoh data, jalankan:

```powershell
php artisan db:seed
```

Peringatan: seeder demo mengosongkan dan mengisi ulang data CRM utama. Jangan menjalankannya pada database yang sudah berisi data kerja dan jangan menjalankannya di production.

Akun demo setelah seeding:

| Role | Email | Kata sandi |
|---|---|---|
| Superadmin | `admin@sivmi.test` | `password` |
| Direksi | `direksi@sivmi.test` | `password` |
| Admin Bandung | `admin.bdg@sivmi.test` | `password` |
| Admin JakSel | `admin.jks@sivmi.test` | `password` |
| Admin JakPus | `admin.jkp@sivmi.test` | `password` |

Kredensial tersebut hanya untuk development lokal.

## 8. Membangun aset frontend

Untuk penggunaan normal tanpa Vite development server:

```powershell
npm run build
```

Jika sedang mengembangkan tampilan dan memerlukan pembaruan otomatis, gunakan terminal terpisah:

```powershell
npm run dev
```

Jangan menjalankan `npm run dev` dan mengharapkan aset production pada server lain. Jalankan kembali `npm run build` sebelum verifikasi rilis.

## 9. Menyiapkan konfigurasi Docker WAHA

Pastikan Docker Desktop sudah dibuka dan statusnya **Engine running**.

Salin environment Docker:

```powershell
Copy-Item docker\.env.example docker\.env
```

Edit `docker/.env`. Untuk mode hybrid, minimal periksa nilai berikut:

```dotenv
WAHA_IMAGE=devlikeapro/waha:gows
WAHA_PORT=3000
WAHA_API_KEY=GANTI_DENGAN_API_KEY_LOKAL
WHATSAPP_DEFAULT_ENGINE=GOWS
WAHA_GODEBUG=netdns=go
WAHA_DASHBOARD_ENABLED=true
WAHA_DASHBOARD_USERNAME=admin
WAHA_DASHBOARD_PASSWORD=GANTI_DENGAN_PASSWORD_DASHBOARD
```

Nilai `WAHA_API_KEY` pada `docker/.env` wajib sama dengan `WAHA_API_KEY` pada `.env` Laravel.

`WAHA_WEBHOOK_HMAC_SECRET` yang digunakan aplikasi berasal dari `.env` Laravel dan dikirim ke konfigurasi session saat tindakan **Siapkan session** atau **Aktifkan chat** dijalankan.

Validasi konfigurasi Compose:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml config --quiet
```

## 10. Menjalankan WAHA melalui Docker

Pada konfigurasi hybrid, jalankan hanya service `waha`:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml up -d waha
```

Periksa status container:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml ps waha
docker logs --tail 100 sivmi-crm-waha
```

Tunggu sampai health menjadi `healthy`. Periksa endpoint:

```powershell
Invoke-RestMethod http://127.0.0.1:3000/health
```

Alamat root `http://localhost:3000/` dapat menampilkan `Cannot GET /`; itu bukan kegagalan. Gunakan:

```text
http://localhost:3000/dashboard
```

Login dashboard memakai `WAHA_DASHBOARD_USERNAME` dan `WAHA_DASHBOARD_PASSWORD` dari `docker/.env`. Ketika diminta API key, gunakan `WAHA_API_KEY` dari file yang sama.

## 11. Menjalankan queue worker dan scheduler

Webhook WhatsApp diproses melalui queue. Buka terminal Laragon kedua:

```powershell
cd C:\laragon\www\sivmi.crm
php artisan queue:work --tries=5 --timeout=120
```

Buka terminal Laragon ketiga:

```powershell
cd C:\laragon\www\sivmi.crm
php artisan schedule:work
```

Kedua terminal harus tetap hidup selama pengembangan. Jika kode job berubah, restart worker:

```powershell
php artisan queue:restart
```

## 12. Membuka aplikasi

Akses:

```text
http://sivmi.crm.test/admin/login
```

Jika menggunakan fallback server Laravel:

```powershell
php artisan serve
```

Kemudian buka:

```text
http://127.0.0.1:8000/admin/login
```

Jika memakai `php artisan serve`, sesuaikan sementara `APP_URL` dan `WAHA_WEBHOOK_URL`. Untuk konfigurasi hybrid standar, tetap gunakan virtual host Laragon `sivmi.crm.test`.

## 13. Menautkan nomor WhatsApp pertama

1. Masuk sebagai Superadmin atau Admin Cabang.
2. Buka **MASTER DATA → Koneksi WhatsApp**.
3. Klik **Buat koneksi WhatsApp**.
4. Pilih admin atau staff pemilik nomor.
5. Masukkan nomor WhatsApp operasional.
6. Simpan koneksi.
7. Klik **Siapkan session**.
8. Klik **Mulai**.
9. Klik **Lihat QR**.
10. Pada ponsel buka **WhatsApp → Perangkat tertaut → Tautkan perangkat**.
11. Scan QR yang ditampilkan CRM.
12. Kembali ke daftar koneksi dan klik **Sinkronkan**.
13. Pastikan status berubah menjadi **Working**.
14. Klik **Aktifkan chat** untuk mengaktifkan event pesan masuk, pesan keluar, dan status dibaca.

Satu pengguna hanya boleh memiliki satu session WhatsApp. Gunakan nomor dan session berbeda untuk setiap admin/staff.

## 14. Menguji chat dan notifikasi

1. Kirim pesan dari nomor lain ke nomor yang ditautkan.
2. Pastikan terminal queue memproses job tanpa error.
3. Buka **SIVMI CRM → Inbox WhatsApp**.
4. Pastikan badge pesan belum dibaca muncul.
5. Buka percakapan.
6. Balas melalui composer chat CRM.
7. Pastikan status berubah dari **Terkirim** menjadi **Diterima** atau **Dibaca** ketika event ACK diterima.
8. Periksa ikon lonceng Filament untuk notifikasi pesan baru.

Jika nomor pengirim belum ada pada data Leads, sistem membuat lead `Cold` melalui kanal WhatsApp. Nomor ganda atau lead lintas cabang tidak ditautkan otomatis dan ditandai untuk pemeriksaan.

## 15. Verifikasi instalasi

Jalankan:

```powershell
php artisan about
php artisan migrate:status
php artisan route:list
php artisan test --compact
npm run build
```

Periksa aplikasi dan WAHA:

```powershell
Invoke-WebRequest -UseBasicParsing http://sivmi.crm.test/up
Invoke-RestMethod http://127.0.0.1:3000/health
docker compose --env-file docker\.env -f docker\compose.yml ps waha
```

Hasil yang diharapkan:

- `/up` memberikan status HTTP 200;
- migration tidak memiliki status pending;
- test berhasil;
- build Vite berhasil;
- container WAHA berstatus `healthy`;
- session pengguna berstatus `Working`;
- chat masuk dan keluar muncul pada Inbox WhatsApp.

## 16. Urutan menjalankan aplikasi setiap hari

1. Buka Laragon dan klik **Start All**.
2. Buka Docker Desktop dan tunggu **Engine running**.
3. Jalankan WAHA:

```powershell
cd C:\laragon\www\sivmi.crm
docker compose --env-file docker\.env -f docker\compose.yml up -d waha
```

4. Jalankan queue worker pada terminal kedua:

```powershell
php artisan queue:work --tries=5 --timeout=120
```

5. Jalankan scheduler pada terminal ketiga:

```powershell
php artisan schedule:work
```

6. Buka `http://sivmi.crm.test/admin`.

## 17. Menghentikan lingkungan lokal

Hentikan queue dan scheduler dengan `Ctrl+C` pada terminal masing-masing.

Hentikan WAHA tanpa menghapus session:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml stop waha
```

Kemudian klik **Stop** pada Laragon jika seluruh service lokal ingin dihentikan.

Jangan menjalankan perintah berikut kecuali seluruh session WhatsApp dan volume development memang ingin dihapus:

```text
docker compose down -v
```

## 18. Troubleshooting

### `SQLSTATE[HY000] [2002] Connection refused`

Penyebab umum:

- MySQL Laragon belum aktif;
- `DB_HOST` atau `DB_PORT` salah;
- perintah dijalankan dengan `.env` Docker;
- port 3306 digunakan service lain.

Solusi:

1. Klik **Start All** pada Laragon.
2. Pastikan `.env` menggunakan `DB_HOST=127.0.0.1` dan `DB_PORT=3306`.
3. Jalankan `php artisan config:clear`.
4. Ulangi `php artisan migrate:status`.

### Login menampilkan kredensial tidak ditemukan

Pastikan migration dan seeder sudah dijalankan:

```powershell
php artisan migrate:status
php artisan db:seed
```

Jangan menjalankan seeder jika database sudah berisi data kerja.

### Tampilan login tanpa CSS

Jalankan:

```powershell
npm install
npm run build
php artisan view:clear
```

Pastikan folder `public/build` dan `public/build/manifest.json` tersedia.

### `ERR_CONNECTION_REFUSED` pada `sivmi.crm.test`

- pastikan web server Laragon aktif;
- reload virtual host Laragon;
- periksa bahwa folder berada pada `C:\laragon\www\sivmi.crm`;
- periksa file hosts Windows;
- jangan menambahkan `:8000` jika memakai virtual host Laragon.

### `ERR_TOO_MANY_REDIRECTS`

Periksa kesesuaian URL:

```dotenv
APP_URL=http://sivmi.crm.test
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
```

Kemudian jalankan:

```powershell
php artisan optimize:clear
```

Hapus cookie khusus domain `sivmi.crm.test` dari browser jika redirect lama masih tersimpan.

### Docker tidak dapat terhubung ke engine

Jika muncul error `dockerDesktopLinuxEngine` atau named pipe tidak ditemukan:

1. Buka Docker Desktop.
2. Tunggu status **Engine running**.
3. Pastikan Linux containers digunakan.
4. Ulangi `docker version` dan perintah Compose.

### Port 3000 sudah digunakan

Periksa:

```powershell
docker ps
Get-NetTCPConnection -LocalPort 3000 -ErrorAction SilentlyContinue
```

Hentikan container WAHA duplikat atau ubah `WAHA_PORT` pada `docker/.env`. Jika port diubah, sesuaikan `WAHA_BASE_URL` Laravel.

### CRM menampilkan “WAHA tidak dapat dihubungi”

Periksa:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml ps waha
docker logs --tail 200 sivmi-crm-waha
Invoke-RestMethod http://127.0.0.1:3000/health
php artisan config:clear
```

Pastikan API key pada `.env` dan `docker/.env` identik.

### Pesan masuk tidak muncul

1. Pastikan session berstatus `Working`.
2. Klik **Aktifkan chat** pada menu Koneksi WhatsApp.
3. Pastikan queue worker berjalan.
4. Periksa:

```powershell
php artisan queue:failed
docker logs --tail 200 sivmi-crm-waha
```

5. Periksa jumlah event dan chat:

```powershell
php artisan tinker --execute="dump(App\Models\WahaWebhookEvent::count(), App\Models\WhatsAppConversation::count(), App\Models\WhatsAppMessage::count());"
```

- event bertambah tetapi conversation tidak bertambah: queue worker tidak aktif atau job gagal;
- event tidak bertambah: periksa webhook URL, HMAC, firewall, dan konfigurasi session;
- pesan keluar gagal: periksa status session, API key, dan log WAHA.

### `502 Bad Gateway` dari Nginx

Periksa status container terlebih dahulu:

```powershell
.\docker\status-full.ps1
.\docker\logs-full.ps1 app
.\docker\logs-full.ps1 web
```

Jika `app` sehat tetapi log Nginx menampilkan `connect() failed (111: Connection refused)` ke alamat FastCGI lama, buat ulang container web:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml up -d --force-recreate web
```

Konfigurasi Nginx proyek menggunakan DNS internal Docker untuk me-resolve ulang service `app`, dan `start-full.ps1` otomatis membuat ulang container web setelah aplikasi dibangun.

### Queue berhenti setelah perubahan kode

Jalankan:

```powershell
php artisan queue:restart
php artisan queue:work --tries=5 --timeout=120
```

## 19. Checklist instalasi pertama

- [ ] Laragon web server dan MySQL aktif (hanya mode hybrid/legacy).
- [ ] PHP 8.3+ dan Composer tersedia.
- [ ] Node.js 22 dan npm tersedia.
- [ ] Docker Desktop berstatus Engine running.
- [ ] Dependency Composer dan npm terpasang.
- [ ] `.env` Laravel menggunakan MySQL Laragon (hanya mode hybrid/legacy).
- [ ] `APP_KEY` sudah dibuat.
- [ ] Database `sivmi_crm` sudah dibuat.
- [ ] Seluruh migration berhasil.
- [ ] Seeder demo hanya dijalankan pada database lokal kosong.
- [ ] `npm run build` berhasil.
- [ ] `http://sivmi.crm.test/admin/login` dapat dibuka.
- [ ] Container WAHA berstatus healthy.
- [ ] Queue worker dan scheduler aktif.
- [ ] Session WhatsApp berstatus Working.
- [ ] Tindakan Aktifkan chat sudah dijalankan.
- [ ] Pesan masuk, balasan, ACK, unread badge, dan notifikasi berhasil diuji.
- [ ] Seluruh test aplikasi berhasil.

## 20. Alternatif: menjalankan seluruh aplikasi melalui Docker

Bagian ini adalah alternatif terhadap mode hybrid, bukan langkah lanjutan setelah Laragon. Hentikan Apache/Nginx dan MySQL Laragon terlebih dahulu jika port lokal bentrok. Pada mode ini Laravel, Nginx, MySQL, Redis, Node, WAHA, queue, dan scheduler dijalankan sebagai container.

Siapkan konfigurasi dan `APP_KEY` dari root proyek:

```powershell
Copy-Item docker\.env.example docker\.env
$sivmiDockerAppKeyBytes = New-Object byte[] 32
$sivmiRandomGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$sivmiRandomGenerator.GetBytes($sivmiDockerAppKeyBytes)
$sivmiRandomGenerator.Dispose()
$sivmiDockerAppKey = 'base64:' + [Convert]::ToBase64String($sivmiDockerAppKeyBytes)
$sivmiDockerEnvPath = (Resolve-Path 'docker\.env').Path
$sivmiDockerEnvContent = (Get-Content -LiteralPath $sivmiDockerEnvPath -Raw) -replace '(?m)^APP_KEY=.*$', "APP_KEY=$sivmiDockerAppKey"
$sivmiUtf8WithoutBom = [System.Text.UTF8Encoding]::new($false)
[System.IO.File]::WriteAllText($sivmiDockerEnvPath, $sivmiDockerEnvContent, $sivmiUtf8WithoutBom)
```

Perintah tersebut tidak memerlukan PHP host dan kompatibel dengan Windows PowerShell 5.1 maupun PowerShell 7.

Ubah seluruh password lokal, `WAHA_API_KEY`, `WAHA_WEBHOOK_HMAC_SECRET`, dan password dashboard pada `docker/.env`, lalu validasi dan jalankan:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml config --quiet
docker compose --env-file docker\.env -f docker\compose.yml up -d --build
docker compose --env-file docker\.env -f docker\compose.yml --profile workers up -d
```

Akses aplikasi melalui:

```text
http://localhost:8000/admin/login
```

Periksa seluruh service:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml ps
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan migrate:status
```

Jika memerlukan data demo pada database Docker yang masih kosong:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan db:seed
```

Peringatan seeder yang sama tetap berlaku. Database Docker disimpan dalam volume `mysql_data` dan berbeda dari database MySQL Laragon. Jangan menganggap data kedua lingkungan otomatis tersinkronisasi.

Verifikasi mode full Docker:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan test --compact
docker compose --env-file docker\.env -f docker\compose.yml run --rm node npm run build
Invoke-WebRequest -UseBasicParsing http://localhost:8000/up
Invoke-RestMethod http://127.0.0.1:3000/health
```

Hentikan container tanpa menghapus data:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml down
```

Jangan menambahkan opsi `-v` kecuali volume database dan session WhatsApp memang hendak dihapus.

## 21. Referensi lanjutan

- Konfigurasi rinci WAHA: `docs/PANDUAN-KONFIGURASI-WAHA.md`
- Deployment VPS/production: `docs/DEPLOYMENT.md`
- Konfigurasi Docker: `docker/README.md`
- Diagram sistem: file `.drawio` dalam folder `docs`
