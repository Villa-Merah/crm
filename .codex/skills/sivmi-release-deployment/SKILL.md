---
name: sivmi-release-deployment
description: Rencanakan, jalankan, atau periksa release dan deployment SIVMI CRM pada Docker/VPS atau shared hosting. Gunakan untuk preflight Git, backup, build, migrasi, cache, worker, smoke test, bukti rilis, dan rollback.
---

# Release dan Deployment SIVMI CRM

Gunakan `docs/DEPLOYMENT.md` dan `docs/RELEASE-CHECKLIST-DAN-VERIFIKASI-SIVMI-CRM.md` sebagai baseline. Untuk shared hosting, baca juga panduan deployment yang sesuai di `docs/`.

## Sebelum mutasi server

1. Konfirmasi target environment, direktori Laravel yang berisi `artisan`, branch/tag/commit, dan mekanisme hosting.
2. Periksa status Git serta file tracked, modified, deleted, dan untracked. Jangan membuang `.htaccess` atau perubahan server tanpa memahami fungsinya.
3. Pastikan quality gate hijau. Kegagalan test yang belum diselesaikan adalah blocker rilis, bukan catatan kosmetik.
4. Pastikan backup database dan storage tersedia serta dapat dipulihkan.
5. Pastikan `.env` produksi, credential, data siswa, dump, dan artefak sementara tidak masuk Git.

## Urutan rilis

Sesuaikan dengan platform, tetapi jaga urutan aman: ambil kode/artefak immutable, pasang dependency produksi, build aset, aktifkan maintenance bila perlu, jalankan migrasi eksplisit, bersihkan dan bangun ulang cache, restart worker, lalu nonaktifkan maintenance.

Pada Docker produksi gunakan `docker/compose.production.yml`; jangan memakai Compose development. Pada shared hosting jalankan perintah hanya dari root Laravel yang benar.

## Verifikasi wajib

- `/up` menghasilkan respons sehat melalui HTTPS.
- Login dan dashboard dapat dibuka tanpa `APP_DEBUG`.
- Aset tidak menunjuk Vite development server.
- RBAC dan scope cabang untuk jalur kritis tetap benar.
- Queue, scheduler, storage link/upload, email, dan log berjalan.
- Bila WAHA aktif: health, session `WORKING`, webhook HMAC, pesan masuk/keluar, serta queue diverifikasi.
- Tidak ada migration pending atau failed job yang tidak dijelaskan.

## Rollback dan penghentian

- Gunakan tag/image/commit sebelumnya dan backup pra-rilis.
- Jangan menjalankan rollback migrasi tanpa menilai kompatibilitas dan dampak data.
- Jangan memakai `git reset --hard`, menghapus file server, atau `docker compose down -v` tanpa permintaan dan persetujuan eksplisit.
- Hentikan deployment bila backup gagal, target commit ambigu, quality gate merah, migrasi berisiko tanpa rencana, atau smoke test kritis gagal.

## Bukti serah terima

Catat versi/commit, waktu rilis, migrasi, health check, smoke test, status worker/WAHA, anomali, dan keputusan rollback atau lanjut. Jangan masukkan secret ke bukti rilis.
