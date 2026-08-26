# Panduan Konfigurasi WAHA untuk SIVMI CRM

Panduan ini menjelaskan konfigurasi WAHA sebagai penghubung WhatsApp untuk setiap admin dan staff SIVMI CRM. Session, QR, webhook, pencocokan lead, dan inbox dikelola dari SIVMI CRM. Baseline lokal utama adalah **full Docker**; mode Laragon/hybrid hanya dipertahankan sebagai referensi kompatibilitas. Dashboard WAHA boleh diaktifkan khusus development, tetapi pada production harus dibatasi atau dinonaktifkan agar API key dan session tidak terekspos.

## 1. Arsitektur singkat

Alur pesan masuk:

1. Nomor admin/staff terhubung ke satu session WAHA.
2. WAHA menerima pesan WhatsApp.
3. WAHA mengirim webhook bertanda tangan HMAC ke Laravel.
4. Laravel menyimpan event lalu memprosesnya melalui queue.
5. Nomor pengirim dinormalisasi ke format `62xxxxxxxx`.
6. Lead dicari berdasarkan nomor yang sudah dinormalisasi.
7. Nomor baru membuat lead berstatus `Cold` dengan kanal `WhatsApp`.
8. Payload GOWS berbasis LID dinormalisasi melalui `_data.Info.SenderAlt`; nama kontak dibaca dari `_data.Info.PushName`.
8. Satu nomor yang cocok pada cabang yang sama ditautkan otomatis.
9. Nomor ganda atau nomor yang sudah ada pada cabang lain diberi status konflik untuk pemeriksaan manual.

## 2. Persyaratan

- Docker Desktop dalam status **Engine running** dan menggunakan Linux containers.
- Docker Desktop aktif dalam mode Linux containers untuk baseline full Docker.
- Laragon Apache/Nginx dan MySQL hanya diperlukan jika sengaja memakai mode hybrid/legacy.
- Migration aplikasi sudah dijalankan.
- Queue worker Laravel aktif.
- Nomor WhatsApp operasional tersedia untuk setiap admin/staff.
- Superadmin atau Admin Cabang tersedia untuk membuat koneksi.

Verifikasi Docker:

```powershell
docker version
docker info
```

## 3. Menyiapkan konfigurasi lokal full Docker

Salin `docker/.env.example` menjadi `docker/.env`, lalu pastikan konfigurasi jaringan internal menggunakan:

```dotenv
WAHA_IMAGE=devlikeapro/waha:gows
WAHA_BASE_URL=http://waha:3000
WAHA_WEBHOOK_URL=http://web/api/webhooks/waha
WAHA_PORT=3000
```

Gunakan nilai kuat dan berbeda untuk `WAHA_API_KEY`, `WAHA_WEBHOOK_HMAC_SECRET`, serta kredensial dashboard development. Laravel dan WAHA membaca nilai yang sama dari `docker/.env`; API key digunakan untuk panggilan API, sedangkan secret HMAC digunakan untuk memverifikasi webhook.

### Referensi hybrid/legacy

Salin contoh konfigurasi Docker. Jangan mengubah file contoh secara langsung untuk pemakaian harian.

```powershell
cd C:\laragon\www\sivmi.crm
Copy-Item docker\.env.example docker\.env
```

Ubah nilai berikut di `docker/.env`:

```dotenv
WAHA_IMAGE=devlikeapro/waha:gows
WAHA_PORT=3000
WAHA_API_KEY=ganti-dengan-api-key-lokal-yang-kuat
WHATSAPP_DEFAULT_ENGINE=GOWS
WAHA_GODEBUG=netdns=go
WAHA_DASHBOARD_ENABLED=true
WAHA_DASHBOARD_USERNAME=admin
WAHA_DASHBOARD_PASSWORD=ganti-dengan-password-dashboard-yang-kuat
WAHA_WEBHOOK_HMAC_SECRET=ganti-dengan-hmac-secret-yang-kuat
```

Gunakan string acak yang berbeda untuk API key dan HMAC secret. Jangan menyimpan credential production di Git.

Pada `.env` Laravel untuk mode Laragon, gunakan nilai yang sama:

```dotenv
WAHA_BASE_URL=http://127.0.0.1:3000
WAHA_API_KEY=ganti-dengan-api-key-lokal-yang-kuat
WAHA_WEBHOOK_URL=http://sivmi.crm.test/api/webhooks/waha
WAHA_WEBHOOK_HMAC_SECRET=ganti-dengan-hmac-secret-yang-kuat
WAHA_TIMEOUT=15
QUEUE_CONNECTION=database
```

Catatan penting:

- `WAHA_API_KEY` Laravel harus sama dengan API key container WAHA.
- `WAHA_WEBHOOK_HMAC_SECRET` juga harus sama pada kedua sisi.
- `WHATSAPP_DEFAULT_ENGINE=GOWS` menggunakan engine tanpa Chromium yang lebih ringan dan mendukung alur passkey terbaru.
- `127.0.0.1:3000` digunakan Laravel/Laragon untuk memanggil container WAHA.
- `sivmi.crm.test` dikenali container melalui `host-gateway` untuk mengirim webhook kembali ke Laragon.

Muat ulang konfigurasi Laravel:

```powershell
php artisan config:clear
php artisan migrate
```

## 4. Menjalankan WAHA

Untuk baseline full Docker, jalankan seluruh stack dari root proyek:

```powershell
.\docker\start-full.ps1
.\docker\status-full.ps1
```

Aplikasi tersedia di `http://localhost:8000/admin/login` dan dashboard WAHA development di `http://localhost:3000/dashboard`.

### Menjalankan WAHA saja pada mode hybrid/legacy

Jalankan hanya service WAHA ketika aplikasi masih menggunakan Laragon:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml up -d waha
```

Periksa status:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml ps waha
docker logs --tail 100 sivmi-crm-waha
```

Status awal dapat menunjukkan `Health: starting`. Tunggu hingga menjadi `healthy`, lalu periksa endpoint:

```powershell
Invoke-RestMethod http://127.0.0.1:3000/health
```

Periksa akses API dengan key yang telah dipasang:

```powershell
$wahaHeaders = @{ 'X-Api-Key' = 'ganti-dengan-api-key-lokal-yang-kuat' }
Invoke-RestMethod -Headers $wahaHeaders http://127.0.0.1:3000/api/sessions
```

Respons `401` berarti key pada perintah tidak sama dengan key container.

### Membuka dashboard WAHA lokal

Endpoint root `http://localhost:3000/` bukan halaman WAHA dan dapat menampilkan `Cannot GET /`. Dashboard development tersedia di:

```text
http://localhost:3000/dashboard
```

Masuk menggunakan `WAHA_DASHBOARD_USERNAME` dan `WAHA_DASHBOARD_PASSWORD` dari `docker/.env`. Saat dashboard meminta API key, masukkan nilai `WAHA_API_KEY` dari file yang sama.

Gunakan dashboard untuk memeriksa status session dan event. Pembuatan session SIVMI tetap dilakukan melalui menu **Koneksi WhatsApp** agar session terhubung dengan pengguna, cabang, RBAC, dan audit CRM.

## 5. Menjalankan queue dan scheduler

Pesan webhook disimpan lebih dahulu lalu diproses oleh queue. Tanpa worker, webhook diterima tetapi inbox dan lead tidak segera diperbarui.

Buka terminal terpisah:

```powershell
cd C:\laragon\www\sivmi.crm
php artisan queue:work --tries=5 --timeout=120
```

Untuk sinkronisasi status session otomatis, jalankan scheduler pada terminal lain:

```powershell
php artisan schedule:work
```

Pada server production, gunakan Supervisor, systemd, atau service worker Docker agar kedua proses otomatis hidup kembali.

## 6. Membuat koneksi WhatsApp pengguna

1. Masuk ke SIVMI CRM sebagai Superadmin atau Admin Cabang.
2. Buka **MASTER DATA → Koneksi WhatsApp**.
3. Klik **Buat koneksi WhatsApp**.
4. Pilih admin/staff pemilik nomor.
5. Masukkan nomor dengan format `08...`, `8...`, atau `62...`; sistem akan menyimpannya menjadi `62...`.
6. Aktifkan **Integrasi aktif**, kemudian simpan.
7. Pada baris akun, pilih **Siapkan session** dan konfirmasi.
8. Pilih **Mulai**.
9. Pilih **Lihat QR** untuk membuka QR melalui endpoint CRM yang terlindungi autentikasi.
10. Pada ponsel, buka **WhatsApp → Perangkat tertaut → Tautkan perangkat**, lalu scan QR.
11. Kembali ke daftar koneksi dan pilih **Sinkronkan**.
12. Setelah status menjadi **Working**, pilih **Aktifkan chat** agar event pesan masuk, pesan keluar, dan status dibaca dikirim ke CRM.

Session siap digunakan jika badge status berubah menjadi **Working**. Jika session berstatus **Failed** atau **Stopped**, tindakan **Mulai** dan halaman QR akan mencoba restart session secara otomatis. QR hanya diminta setelah WAHA kembali ke status `SCAN_QR_CODE`; kegagalan sementara ditampilkan sebagai halaman pemulihan, bukan error 500.

Satu pengguna hanya boleh mempunyai satu koneksi aktif. Jangan menggunakan satu session untuk beberapa pengguna karena kepemilikan chat, cabang, dan audit akan menjadi tidak akurat.

## 7. Menguji pesan masuk

1. Kirim pesan dari nomor lain ke nomor yang baru ditautkan.
2. Pastikan terminal queue menampilkan job tanpa error.
3. Buka **SIVMI CRM → Inbox WhatsApp**.
4. Periksa percakapan, isi pesan, pemilik akun, cabang, dan status pencocokan.
5. Buka halaman Leads dan periksa badge/kanal WhatsApp.

Hasil yang diharapkan:

| Kondisi nomor pengirim | Hasil |
|---|---|
| Belum ada di Leads | Lead `Cold` baru dibuat dan ditugaskan kepada pemilik session |
| Tepat satu lead pada cabang yang sama | Percakapan ditautkan otomatis |
| Lebih dari satu lead | Status konflik, tidak membuat duplikat baru |
| Nomor ada pada cabang lain | Status konflik untuk mencegah akses silang cabang |
| Group, newsletter, atau status broadcast | Diabaikan |

Untuk engine GOWS, chat personal dapat menggunakan ID berakhiran `@lid`. Sistem mempertahankan LID sebagai identitas chat, mengambil nomor telepon dari `SenderAlt`, menghapus suffix perangkat seperti `:5`, lalu menormalisasikannya ke format E.164 tanpa tanda `+`.

## 8. Status session

| Status | Arti | Tindakan |
|---|---|---|
| Disconnected | Session belum dimulai atau perangkat terputus | Klik **Mulai**, lalu tampilkan QR |
| Connecting | Menunggu inisialisasi atau scan QR | Tunggu dan scan QR |
| Working | WhatsApp terhubung dan dapat menerima pesan | Tidak ada tindakan |
| Failed | WAHA/API/session mengalami kesalahan | Periksa log, key, dan jaringan lalu sinkronkan |

## 9. Menjalankan seluruh aplikasi melalui Docker

Untuk full-Docker, Laravel memanggil WAHA melalui nama service internal. Konfigurasi `docker/.env` harus menggunakan:

```dotenv
WAHA_BASE_URL=http://waha:3000
WAHA_WEBHOOK_URL=http://web/api/webhooks/waha
```

Jalankan aplikasi dan worker:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml up -d --build
docker compose --env-file docker\.env -f docker\compose.yml --profile workers up -d
```

## 10. Konfigurasi production

- Gunakan `docker/compose.production.yml` dan `docker/.env.production`.
- Dashboard dan Swagger production tetap dinonaktifkan kecuali tersedia kebutuhan operasional serta kontrol jaringan yang memadai.
- Gunakan credential berbeda dari development.
- Gunakan `WAHA_API_KEY_HASH` pada service WAHA sesuai format hash yang didukung image WAHA yang digunakan.
- Laravel tetap memerlukan API key asli melalui `WAHA_API_KEY` untuk memanggil API.
- Gunakan URL webhook HTTPS publik.
- Jangan mengekspos port WAHA ke internet jika Laravel dan WAHA berada pada jaringan Docker yang sama.
- Batasi firewall sehingga hanya reverse proxy atau jaringan aplikasi yang dapat mengakses service.
- Simpan volume `waha_sessions` pada storage persisten dan sertakan dalam strategi backup terenkripsi.
- Jalankan queue worker dan scheduler sebagai service yang dimonitor.

## 11. Troubleshooting

### Docker API atau pipe tidak ditemukan

Pesan seperti `dockerDesktopLinuxEngine: The system cannot find the file specified` berarti Docker Engine belum berjalan. Buka Docker Desktop, tunggu **Engine running**, lalu ulangi perintah.

### Container tetap `Health: starting`

```powershell
docker logs --tail 200 sivmi-crm-waha
docker inspect sivmi-crm-waha --format '{{json .State.Health}}'
```

Jika container terus gagal, restart tanpa menghapus volume session:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml restart waha
```

### CRM menampilkan “WAHA tidak dapat dihubungi”

Periksa:

1. Container berstatus `healthy`.
2. `WAHA_BASE_URL` adalah `http://127.0.0.1:3000` untuk Laragon atau `http://waha:3000` untuk full-Docker.
3. API key pada Laravel dan container sama.
4. Jalankan `php artisan config:clear` setelah mengubah `.env`.

### Pesan tidak masuk ke Inbox WhatsApp

Periksa:

```powershell
php artisan queue:failed
php artisan queue:work --tries=5 --timeout=120
docker logs --tail 200 sivmi-crm-waha
```

Pastikan session berstatus `Working`, webhook URL benar, HMAC secret sama, dan Laragon dapat menerima `POST /api/webhooks/waha`.

Gunakan query diagnostik berikut untuk membedakan webhook yang tidak diterima dari queue yang tidak berjalan:

```powershell
php artisan tinker --execute="dump(App\Models\WahaWebhookEvent::count(), App\Models\WhatsAppConversation::count(), App\Models\WhatsAppMessage::count());"
```

- Event bertambah tetapi conversation/message tetap nol: jalankan queue worker.
- Event tidak bertambah: periksa URL webhook, resolusi `host-gateway`, firewall, serta HMAC secret.
- Event berstatus `Failed`: periksa `last_error`, `storage/logs/laravel.log`, dan `php artisan queue:failed`.
- Pada Laragon, worker terminal harus tetap hidup. Pada Docker/production, jalankan worker sebagai service yang dimonitor dan otomatis restart.

### QR tidak tampil

1. Pastikan session sudah disiapkan dan dimulai.
2. Klik **Sinkronkan**.
3. Pastikan status belum `Working`; QR hanya diperlukan ketika belum terhubung.
4. Periksa API key dan log Laravel pada `storage/logs/laravel.log`.
5. SIVMI akan me-restart session `FAILED/STOPPED`, menunggu status QR, dan menampilkan state pemulihan jika WAHA belum siap.

### Menghentikan WAHA

```powershell
docker compose --env-file docker\.env -f docker\compose.yml stop waha
```

Menghapus container dengan `down` tidak menghapus session selama volume tidak dihapus. Jangan menggunakan `down -v` kecuali seluruh session WhatsApp memang ingin dihapus dan semua nomor siap ditautkan ulang.

## 12. Checklist penerimaan

- [ ] WAHA berstatus `healthy`.
- [ ] API sessions dapat diakses dengan `X-Api-Key`.
- [ ] Queue worker dan scheduler berjalan.
- [ ] Setiap admin/staff memiliki nomor dan session sendiri.
- [ ] QR berhasil dipindai dan session berstatus `Working`.
- [ ] Pesan masuk muncul di Inbox WhatsApp.
- [ ] Lead baru dibuat dengan status `Cold` dan kanal `WhatsApp`.
- [ ] Nomor duplikat ditandai konflik tanpa membuat lead tambahan.
- [ ] Staff tidak dapat melihat inbox pengguna/cabang lain.
- [ ] API key dan HMAC secret production tidak menggunakan nilai contoh.

## 13. Snapshot verifikasi as-built

Pada 24 Agustus 2026, baseline full Docker diverifikasi dengan Nginx, Laravel/PHP-FPM, MySQL, Redis, queue, scheduler, dan WAHA `2026.8.1 CORE` menggunakan engine GOWS pada satu jaringan Compose. Session pengguna mencapai status `WORKING`; webhook HMAC, normalisasi GOWS/LID/`SenderAlt`, pencocokan lead, chat dua arah, status ACK, unread badge, dan notifikasi sistem tersedia. Suite aplikasi lulus **72 test dengan 448 assertion** menggunakan environment testing dan SQLite in-memory. Konfigurasi hybrid tetap didokumentasikan sebagai opsi legacy, bukan jalur utama.
