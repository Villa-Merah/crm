---
name: sivmi-quality-gate
description: Jalankan dan nilai quality gate SIVMI CRM sebelum review, commit, atau rilis. Gunakan untuk validasi perubahan Laravel, Filament, frontend, Docker, migrasi, dan dokumentasi tanpa menyentuh database development.
---

# Quality Gate SIVMI CRM

Validasi perubahan secara proporsional terhadap risikonya dan laporkan bukti yang benar-benar dijalankan.

## Alur

1. Periksa `git status --short` dan pertahankan perubahan pengguna yang tidak terkait.
2. Tentukan area terdampak dari diff: PHP, frontend, database, Docker, API, RBAC, atau dokumentasi.
3. Jalankan pemeriksaan cepat yang relevan:
   - PHP yang berubah: `php -l <file>`.
   - Format PHP: `vendor/bin/pint --test` bila tersedia.
   - Konfigurasi Composer: `composer validate`.
   - Frontend/aset: `npm run build`.
   - Route/API: `php artisan route:list` bila perubahan menyentuh routing.
4. Jalankan test terfokus lebih dahulu, lalu full suite bila perubahan lintas modul atau akan dirilis.
5. Untuk full suite Docker gunakan `docker/test-full.ps1`. Skrip ini wajib memakai environment testing dan SQLite in-memory.
6. Bila Compose berubah, validasi file Compose dengan environment yang sesuai sebelum menjalankan service.
7. Ringkas hasil sebagai lulus, gagal, atau tidak dijalankan beserta alasannya. Jangan menyatakan quality gate hijau jika masih ada kegagalan.

## Batas keselamatan

- Jangan menjalankan seeder pada produksi.
- Jangan menjalankan test terhadap database development atau produksi.
- Jangan mengubah `.env`, menghapus volume, atau memakai `docker compose down -v` hanya untuk membuat test lulus.
- Jangan menutupi test gagal dengan menghapus atau melemahkan assertion tanpa alasan bisnis yang sah.
- Jangan commit atau push kecuali pengguna memintanya.

## Kriteria selesai

Hasil akhir menyebutkan perintah yang dijalankan, jumlah test/assertion bila tersedia, kegagalan yang tersisa, dan risiko yang belum diverifikasi.
