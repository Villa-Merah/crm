# SIVMI CRM

CRM bimbingan belajar berbasis Laravel 12, Filament 5, Livewire, dan Tailwind CSS. Sistem mencakup dashboard kinerja, leads dan follow-up, pendaftaran, pembayaran, program bimbel, master data, RBAC per cabang, notifikasi, audit aktivitas, serta inbox WhatsApp melalui WAHA dengan engine GOWS.

RBAC mencakup Superadmin, Admin Cabang, Staff, Keuangan, dan Direksi. Staff membuat pendaftaran dan mencatat pembayaran awal, Keuangan mengelola verifikasi pembayaran dalam scope cabang, sedangkan Admin Cabang dan Superadmin memiliki akses baca pada modul Pendaftaran dan Pembayaran.

Baseline versi 2.3 (8 September 2026) mencakup pencarian dan persistensi sekolah dari API Sekolah Indonesia, peta persebaran leads berbasis Leaflet/OpenStreetMap, aksi massal Leads, REST API data siswa, serta pemisahan tugas transaksi melalui role Keuangan. Rincian perubahan dan UAT tambahan tersedia di [docs/PEMBARUAN-VERSI-TERBARU-2026-09-08.md](docs/PEMBARUAN-VERSI-TERBARU-2026-09-08.md).

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

Snapshot pengujian role terbaru: **10 test dan 146 assertion lulus** untuk aturan Pendaftaran, Pembayaran, master data, scope cabang, dan role Keuangan. Pada quality gate penuh terakhir, **115 dari 117 test lulus**; dua kegagalan fixture lama pada Settings dan Report masih harus diperbaiki sebelum rilis produksi.

## Integrasi WhatsApp WAHA

Pada mode full Docker, Laravel berkomunikasi dengan WAHA melalui hostname service `http://waha:3000`. Setiap admin/staff mempunyai session sendiri. Pesan masuk diterima melalui webhook HMAC, disimpan sebagai event, lalu diproses queue menjadi percakapan, pesan, dan pencocokan lead.

```powershell
.\docker\start-full.ps1
```

Queue worker wajib aktif. Tanpa worker, webhook tetap tercatat tetapi Inbox WhatsApp dan lead tidak diperbarui sampai antrean diproses.

Health endpoint tersedia di `/up`. Panduan instalasi full Docker (dengan referensi Laragon legacy) berada di [docs/PANDUAN-INSTALASI-PERTAMA-LARAGON-DOCKER.md](docs/PANDUAN-INSTALASI-PERTAMA-LARAGON-DOCKER.md), dokumentasi Docker development berada di [docker/README.md](docker/README.md), konfigurasi WAHA berada di [docs/PANDUAN-KONFIGURASI-WAHA.md](docs/PANDUAN-KONFIGURASI-WAHA.md), aktivasi WAHA GOWS lokal melalui tunnel DNS berada di [docs/PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS.md](docs/PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS.md), dan prosedur produksi berada di [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). Dokumentasi project terbaru tersedia dalam [Dokumentasi Project SIVMI CRM edisi 3.0](docs/DOKUMENTASI-PROJECT-SIVMI-CRM-TERBARU-2026-09-16.docx), dilengkapi [Diagram dan Tabel Project SIVMI CRM](docs/DIAGRAM-DAN-TABEL-PROJECT-SIVMI-CRM-TERBARU-2026-09-16.drawio) yang dapat diedit. Diagram historis tetap tersedia dalam [Diagram Project CRM Villa Merah v1.1](docs/CRM_DIAGRAM_PROJECT_VILLA_MERAH_v1.1.drawio) dan [ERD CRM Villa Merah 8 September 2026](docs/ERD_CRM_VILLA_MERAH_TERBARU_2026-09-08.drawio).

Integrasi data siswa ke sistem lain menggunakan endpoint baca `GET /api/v1/students` dengan API client ber-ability `students:read`. Konfigurasi server, token, scope cabang, request/response, keamanan, pengujian, dan rancangan push otomatis dijelaskan dalam [Panduan Integrasi API Data Siswa](docs/PANDUAN-INTEGRASI-API-DATA-SISWA.md).

Jangan commit `.env`, kredensial, dump database, atau data siswa ke repository.
