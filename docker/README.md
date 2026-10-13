# Docker SIVMI CRM

Konfigurasi ini ditujukan untuk pengembangan lokal dengan PHP 8.4 FPM,
Nginx, MySQL 8.4, Redis, dan Node.js 22. Queue worker serta scheduler tersedia
melalui profile opsional `workers`.

Untuk deployment produksi, gunakan `docker/compose.production.yml` dan ikuti
runbook `docs/DEPLOYMENT.md`. Jangan memakai Compose development ini di server
produksi karena source code dan dependency di-mount dari host serta Vite berjalan
dalam mode development.

Panduan konfigurasi session, API key, webhook, QR, dan troubleshooting WAHA
tersedia di `docs/PANDUAN-KONFIGURASI-WAHA.md`.

Baseline versi 2.3 juga memerlukan akses keluar HTTPS menuju API Sekolah Indonesia dan tile OpenStreetMap. Library Leaflet disajikan dari `public/vendor/leaflet`, sehingga tidak bergantung pada CDN. Konfigurasi `SCHOOL_API_*` harus tersedia pada `docker/.env`; ringkasan terbaru, termasuk role Keuangan dan API data siswa, berada di `docs/PEMBARUAN-VERSI-TERBARU-2026-09-08.md`.

Untuk skenario Laravel sudah berada di shared hosting tetapi WAHA/GOWS tetap
berjalan pada Docker lokal melalui Cloudflare Tunnel, gunakan runbook
`docs/PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS.md`. Panduan
deployment shared hosting yang lebih luas berada di
`docs/PANDUAN-WAHA-SETELAH-DEPLOY-SHARED-HOSTING.md`.

## Menjalankan aplikasi

Cara yang direkomendasikan pada Windows PowerShell 5.1 maupun PowerShell 7:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\docker\start-full.ps1
```

Skrip tersebut membuat key lokal bila masih placeholder, menjalankan seluruh service beserta queue dan scheduler, lalu menunggu health check. Laragon tidak perlu dijalankan.

Konfigurasi manual berikut tetap tersedia bila diperlukan:

Jalankan perintah berikut dari root proyek:

```powershell
Copy-Item docker/.env.example docker/.env
$dockerAppKeyBytes = New-Object byte[] 32
$dockerRandomGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$dockerRandomGenerator.GetBytes($dockerAppKeyBytes)
$dockerRandomGenerator.Dispose()
$dockerAppKey = 'base64:' + [Convert]::ToBase64String($dockerAppKeyBytes)
$dockerEnvPath = (Resolve-Path 'docker/.env').Path
$dockerEnvContent = (Get-Content -LiteralPath $dockerEnvPath -Raw) -replace '(?m)^APP_KEY=.*$', "APP_KEY=$dockerAppKey"
$utf8WithoutBom = [System.Text.UTF8Encoding]::new($false)
[System.IO.File]::WriteAllText($dockerEnvPath, $dockerEnvContent, $utf8WithoutBom)
docker compose --env-file docker/.env -f docker/compose.yml up -d --build
```

Penulisan file menggunakan API .NET agar kompatibel dengan Windows PowerShell
5.1 maupun PowerShell 7 dan tidak menambahkan BOM ke file environment.

Aplikasi tersedia di `http://localhost:8000` dan Vite di port `5173`.
MySQL diteruskan ke host melalui port `3307` agar tidak bentrok dengan MySQL
Laragon.

`VITE_DEV_SERVER_URL` harus menggunakan alamat yang dapat diakses browser
(secara default `http://localhost:5173`). Jangan menggantinya dengan
`http://0.0.0.0:5173`; alamat tersebut hanya digunakan untuk binding server.

Untuk membuat data demo lokal:

```powershell
docker compose --env-file docker/.env -f docker/compose.yml exec app php artisan db:seed
```

Akun demo setelah seeding:

- Email: `admin@sivmi.test`
- Password: `admin123`

## Queue dan scheduler

```powershell
docker compose --env-file docker/.env -f docker/compose.yml --profile workers up -d
```

## Test dan build produksi

```powershell
.\docker\test-full.ps1
docker compose --env-file docker/.env -f docker/compose.yml run --rm node npm run build
```

Gunakan `test-full.ps1` alih-alih menjalankan `php artisan test` langsung pada
container development. Container utama mempunyai `APP_ENV=local`; skrip test
memasang environment `testing` sebelum Laravel dibootstrap dan memaksa koneksi
SQLite in-memory agar database development tidak tersentuh.

## Optimasi dan pemantauan produksi

Image PHP produksi memuat konfigurasi OPcache khusus dengan
`opcache.validate_timestamps=0`. Container `app` juga menjalankan
`php artisan optimize` saat startup melalui `OPTIMIZE_ON_START=true`. Karena
perubahan kode tidak diperiksa ulang oleh OPcache, setiap rilis wajib membuat
ulang image dan me-recreate container aplikasi:

```bash
docker compose --env-file docker/.env.production -f docker/compose.production.yml build app web
docker compose --env-file docker/.env.production -f docker/compose.production.yml up -d --force-recreate app web queue scheduler
```

Nginx mengaktifkan gzip, cache immutable satu tahun untuk aset Vite yang
memiliki nama ber-hash, dan access log JSON tanpa query string. Waktu respons
p50/p95 selama 15 menit terakhir dapat diperiksa dengan:

```bash
docker compose --env-file docker/.env.production -f docker/compose.production.yml logs --no-color --since 15m web \
  | docker compose --env-file docker/.env.production -f docker/compose.production.yml exec -T app \
      php docker/response_percentiles.php
```

Jalankan perintah tersebut setelah deployment dan secara berkala dari cron atau
sistem monitoring. Pantau kenaikan p95 walaupun nilai rata-rata masih terlihat
normal.

## Menghentikan layanan

```powershell
docker compose --env-file docker/.env -f docker/compose.yml down
```

Gunakan `down -v` hanya jika volume database, Redis, vendor, dan node_modules
memang ingin dihapus. Ganti `APP_KEY` serta seluruh kata sandi sebelum
menggunakan konfigurasi ini di lingkungan selain lokal.
