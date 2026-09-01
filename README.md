# SIVMI CRM

CRM bimbingan belajar berbasis Laravel 12, Filament 5, Livewire, dan Tailwind CSS. Sistem mencakup dashboard kinerja, leads dan follow-up, pendaftaran, pembayaran, program bimbel, master data, RBAC per cabang, notifikasi, audit aktivitas, serta inbox WhatsApp melalui WAHA dengan engine GOWS.

Baseline versi 2.1 (1 September 2026) menambahkan pencarian dan persistensi sekolah dari API Sekolah Indonesia, peta persebaran leads berbasis Leaflet/OpenStreetMap, serta pilih banyak dan aksi massal pada daftar Leads. Rincian perubahan dan UAT tambahan tersedia di [docs/PEMBARUAN-VERSI-TERBARU-2026-09-01.md](docs/PEMBARUAN-VERSI-TERBARU-2026-09-01.md).

## Kebutuhan

- PHP 8.3 atau lebih baru beserta ekstensi Laravel yang diperlukan
- Composer 2
- Node.js 22 dan npm
- MySQL 8/MariaDB atau SQLite untuk pengembangan
- Docker Desktop jika menggunakan container

## Pengembangan lokal full Docker

Mode pengembangan utama tidak memerlukan Laragon, PHP, Composer, Node.js, atau MySQL pada host. Prasyaratnya hanya Docker Desktop dengan Linux containers.

```powershell
cd C:\laragon\www\sivmi.crm
Set-ExecutionPolicy -Scope Process Bypass
.\docker\start-full.ps1
```

Aplikasi tersedia di `http://localhost:8000/admin/login`, sedangkan dashboard WAHA tersedia di `http://localhost:3000/dashboard`.

Seeder hanya ditujukan untuk database Docker lokal yang masih kosong:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan db:seed
```

## Quality gate

```powershell
composer validate
npm run build
.\docker\test-full.ps1
```

Perintah test full Docker memasang `APP_ENV=testing` sebelum Laravel dibootstrap dan menggunakan SQLite in-memory, sehingga database development tidak berubah.

Snapshot pengujian fitur terbaru: **20 test dan 155 assertion lulus** untuk integrasi API Sekolah, peta dashboard, resolver kota, dan aksi massal Leads. Quality gate penuh tetap harus dijalankan sebelum rilis produksi.

## Integrasi WhatsApp WAHA

Pada mode full Docker, Laravel berkomunikasi dengan WAHA melalui hostname service `http://waha:3000`. Setiap admin/staff mempunyai session sendiri. Pesan masuk diterima melalui webhook HMAC, disimpan sebagai event, lalu diproses queue menjadi percakapan, pesan, dan pencocokan lead.

```powershell
.\docker\start-full.ps1
```

Queue worker wajib aktif. Tanpa worker, webhook tetap tercatat tetapi Inbox WhatsApp dan lead tidak diperbarui sampai antrean diproses.

Health endpoint tersedia di `/up`. Panduan instalasi full Docker (dengan referensi Laragon legacy) berada di [docs/PANDUAN-INSTALASI-PERTAMA-LARAGON-DOCKER.md](docs/PANDUAN-INSTALASI-PERTAMA-LARAGON-DOCKER.md), dokumentasi Docker development berada di [docker/README.md](docker/README.md), konfigurasi WAHA berada di [docs/PANDUAN-KONFIGURASI-WAHA.md](docs/PANDUAN-KONFIGURASI-WAHA.md), dan prosedur produksi berada di [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Dokumen sistem terbaru adalah [Dokumentasi SIVMI CRM v1.6](docs/Dokumentasi%20dan%20Pengembangan%20Sistem%20CRM%20SIVMI%20v1.6%20-%20Sementara.docx), sedangkan seluruh diagram terbaru tersedia dalam [Diagram Gabungan SIVMI CRM v1.4](docs/Diagram%20Gabungan%20SIVMI%20CRM%20v1.4.drawio).

Jangan commit `.env`, kredensial, dump database, atau data siswa ke repository.
