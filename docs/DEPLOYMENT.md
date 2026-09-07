# Deployment SIVMI CRM dengan Docker Compose

Baseline deployment menggunakan full Docker: `web` (Nginx), `app` (PHP-FPM/Laravel), `mysql`, `redis`, `queue`, `scheduler`, dan `waha` (GOWS). Laragon tidak menjadi dependensi deployment. Baseline aplikasi per 8 September 2026 adalah versi 2.3 dengan role Keuangan dan REST API data siswa.

Baseline dokumentasi versi 2.3 tanggal 8 September 2026 mencakup API Sekolah Indonesia, peta persebaran leads, aksi massal Leads, endpoint `GET /api/v1/students`, dan role Keuangan. Pastikan server dapat mengakses `https://sekolah.devapi.id` dan tile OpenStreetMap. Leaflet disajikan dari aset lokal aplikasi. Lihat `docs/PEMBARUAN-VERSI-TERBARU-2026-09-08.md` untuk konfigurasi, UAT, dan fallback.

Gunakan `docs/RELEASE-CHECKLIST-DAN-VERIFIKASI-SIVMI-CRM.md` sebagai checklist persetujuan rilis, smoke test, bukti deployment, dan rollback.

Dokumen ini adalah runbook deployment satu server menggunakan Nginx, PHP-FPM, MySQL, Redis, queue worker, dan scheduler. Untuk arsitektur multi-server, gunakan database/Redis terkelola dan sesuaikan layanan pada Compose.

## 1. Prasyarat

- Docker Engine dan Docker Compose tersedia.
- DNS domain sudah mengarah ke server.
- TLS/HTTPS ditangani reverse proxy atau load balancer di depan port aplikasi.
- Lokasi backup database dan storage sudah tersedia.
- Akses SMTP produksi sudah disiapkan.
- Nomor WhatsApp operasional, kredensial WAHA, dan volume session persisten sudah disiapkan jika integrasi WhatsApp diaktifkan.

## 2. Siapkan environment

Jalankan dari root proyek:

```powershell
Copy-Item docker/.env.production.example docker/.env.production
php artisan key:generate --show
```

Salin hasil perintah kedua ke `APP_KEY` di `docker/.env.production`. Ganti seluruh nilai `GANTI_...`, domain, SMTP, dan port. Persyaratan minimum:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` memakai URL HTTPS final
- `APP_KEY`, password MySQL, password root, dan password Redis unik serta kuat
- `SESSION_SECURE_COOKIE=true`
- `WAHA_BASE_URL`, `WAHA_API_KEY`, `WAHA_WEBHOOK_URL`, dan `WAHA_WEBHOOK_HMAC_SECRET` menggunakan nilai produksi yang berbeda dari development
- `SCHOOL_API_ENABLED=true`, URL API Sekolah menggunakan HTTPS, serta timeout/cache/result limit disesuaikan dengan jaringan server

File `docker/.env.production` diabaikan oleh Git dan Docker build context.

## 3. Validasi dan build

```powershell
docker compose --env-file docker/.env.production -f docker/compose.production.yml config --quiet
docker compose --env-file docker/.env.production -f docker/compose.production.yml build --pull
```

Image produksi membangun dependency Composer tanpa paket development dan membangun aset Vite di dalam multi-stage build. Source code host tidak di-mount ke container.

## 4. Migrasi dan start

Untuk deployment pertama:

```powershell
docker compose --env-file docker/.env.production -f docker/compose.production.yml up -d database redis
docker compose --env-file docker/.env.production -f docker/compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file docker/.env.production -f docker/compose.production.yml up -d
```

Jangan menjalankan `db:seed` pada produksi. Migrasi tidak dijalankan otomatis ketika container restart; keputusan migrasi tetap eksplisit pada proses rilis.

Untuk rilis berikutnya, backup dahulu, build tag baru melalui `IMAGE_TAG`, jalankan migrasi, lalu restart layanan:

```powershell
docker compose --env-file docker/.env.production -f docker/compose.production.yml build
docker compose --env-file docker/.env.production -f docker/compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file docker/.env.production -f docker/compose.production.yml up -d --remove-orphans
```

## 5. Verifikasi

```powershell
docker compose --env-file docker/.env.production -f docker/compose.production.yml ps
docker compose --env-file docker/.env.production -f docker/compose.production.yml logs --tail 100 app web queue scheduler
Invoke-WebRequest -UseBasicParsing https://crm.example.id/up
```

Jika WAHA diaktifkan, verifikasi juga health container, status session, dan jangkauan webhook dari jaringan container ke endpoint Laravel. Endpoint webhook harus menolak request tanpa signature HMAC.

Lakukan smoke test manual:

- login dengan akun produksi non-demo;
- dashboard terbuka dan filter cabang mengikuti role;
- buat dan ubah satu lead uji, lalu hapus/arsipkan sesuai prosedur;
- import CSV kecil dan export data sesuai hak akses;
- notifikasi, audit aktivitas, pengiriman email, queue, dan scheduler berjalan;
- session WAHA berstatus `WORKING`, pesan uji muncul di Inbox WhatsApp, dan nomor GOWS/LID dinormalisasi dari `SenderAlt`;
- file upload dapat dibuka melalui `/storage/...`;
- tampilan desktop dan mobile tidak memuat aset dari Vite development server.

## 6. Backup dan rollback

Sebelum migrasi, backup volume database dan `storage_data`. Simpan image dengan tag immutable, misalnya nomor versi atau commit SHA. Rollback aplikasi dilakukan dengan mengembalikan `IMAGE_TAG` sebelumnya. Jika migrasi tidak kompatibel ke belakang, restore database dari backup yang dibuat sebelum rilis; jangan memakai `migrate:rollback` tanpa meninjau dampak data.

Jangan menjalankan `docker compose down -v` di produksi karena perintah tersebut menghapus volume persisten.

## Checklist persetujuan rilis

- [ ] Semua test dan build lulus.
- [ ] `docker/test-full.ps1` lulus dengan environment testing terisolasi. Jangan rilis sebelum dua fixture lama pada Settings dan Report yang masih gagal pada snapshot 8 September 2026 diperbaiki.
- [ ] Tidak ada migration berstatus pending di artefak yang akan dirilis.
- [ ] `APP_DEBUG=false` dan health endpoint sehat.
- [ ] Kredensial demo tidak ada di database produksi.
- [ ] RBAC Superadmin, Admin Cabang, Staff, Keuangan, dan Direksi diuji, termasuk akses baca-saja Admin/Superadmin pada Pendaftaran dan Pembayaran.
- [ ] Pembatasan data Bandung, JakSel, dan JakPus diuji.
- [ ] Backup dan prosedur restore telah diuji.
- [ ] TLS, DNS, firewall, SMTP, logging, dan monitoring aktif.
- [ ] Queue worker dan scheduler berstatus running.
- [ ] WAHA menggunakan engine GOWS, API key kuat, webhook HTTPS, dan HMAC secret yang berbeda dari API key.
- [ ] Volume session WAHA persisten dan tidak terhapus saat release.
- [ ] Pesan masuk menghasilkan conversation/message serta pencocokan lead tanpa duplikasi lintas cabang.
- [ ] UAT visual mobile, tablet, laptop, dan desktop disetujui.
