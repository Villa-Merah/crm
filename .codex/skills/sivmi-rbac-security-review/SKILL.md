---
name: sivmi-rbac-security-review
description: Tinjau atau ubah otorisasi, role, scope cabang, dan keamanan data SIVMI CRM. Gunakan saat pekerjaan menyentuh policy, resource Filament, API token/ability, Pendaftaran, Pembayaran, audit aktivitas, atau akses data lintas cabang.
---

# Review RBAC dan Keamanan SIVMI CRM

Pertahankan matriks akses dan isolasi cabang pada setiap jalur UI, API, query, aksi massal, ekspor, serta relasi.

## Invarian akses

- `Superadmin`: akses lintas cabang, tetapi Pendaftaran dan Pembayaran hanya baca.
- `Admin Cabang`: scope cabangnya sendiri; Pendaftaran dan Pembayaran hanya baca.
- `Staff`: menangani operasi yang ditugaskan dalam scope cabang, termasuk pencatatan transaksi awal sesuai aturan aplikasi.
- `Keuangan`: mengelola verifikasi pembayaran dalam scope cabang.
- `Direksi`: membaca dashboard, KPI, laporan, dan audit sesuai kebijakan tanpa mutasi operasional.
- Log Aktivitas hanya dapat diakses Superadmin.
- API client harus dibatasi oleh bearer token, ability yang tepat, dan scope cabang.

Jika implementasi atau dokumentasi terbaru berbeda, verifikasi sumber kebijakan di kode dan dokumen sebelum mengubah perilaku; jangan menebak perluasan hak.

## Checklist review

1. Temukan policy, query scope, resource/page Filament, controller, request, action, export, dan test yang terdampak.
2. Uji bukan hanya visibilitas menu, tetapi juga URL langsung, route/action langsung, record lintas cabang, bulk action, export, dan API.
3. Pastikan query tidak menerima `cabang_id`, user ID, atau ability dari input klien tanpa validasi terhadap actor.
4. Pastikan respons terlarang memakai halaman **Akses dibatasi** atau respons API yang sesuai, tanpa membocorkan detail internal.
5. Pastikan data siswa, nomor WhatsApp, isi pesan, token, API key, dan secret tidak tercetak di log atau fixture publik.
6. Tambahkan test positif dan negatif untuk setiap role terdampak, termasuk isolasi Bandung, JakSel, dan JakPus bila fixture tersedia.
7. Jalankan test terfokus RBAC lalu quality gate yang proporsional.

## Keluaran

Laporkan matriks role × aksi yang diperiksa, celah yang ditemukan, perubahan yang dibuat, dan test yang membuktikan penolakan maupun akses sah.
