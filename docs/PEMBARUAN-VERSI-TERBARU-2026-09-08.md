# Pembaruan SIVMI CRM Versi 2.3

**Tanggal baseline:** 8 September 2026
**Cakupan:** role Keuangan, pemisahan kewenangan transaksi, REST API data siswa, dan sinkronisasi dokumentasi serta diagram.

Dokumen ini menggantikan addendum versi 2.1 sebagai rujukan perubahan terbaru. Dokumen bernomor versi lama tetap dipertahankan sebagai arsip historis.

## 1. Perubahan Hak Akses

Sistem mempunyai lima role: Superadmin, Admin Cabang, Staff, Keuangan, dan Direksi.

| Peran | Pendaftaran | Pembayaran | Scope |
|---|---|---|---|
| Superadmin | Baca | Baca | Semua cabang |
| Admin Cabang | Baca | Baca | Cabang akun |
| Staff | Buat dan ubah data miliknya | Catat status Menunggu; ubah selama masih Menunggu | Cabang dan data relevan |
| Keuangan | Baca | Catat, verifikasi, tolak, dan koreksi; tanpa hapus | Cabang akun |
| Direksi | Baca | Baca | Semua cabang |

Role Keuangan wajib mempunyai cabang dan hanya dapat ditetapkan oleh Superadmin. Keuangan tidak dapat mengakses Leads, Follow-up, Inbox WhatsApp, Koneksi WhatsApp, Cabang, Sekolah, Program Bimbel, Target Kinerja, Pengguna, Pengaturan, Backup dan Restore, atau Audit Aktivitas. Dashboard dan Laporan tetap tersedia sesuai scope cabang.

## 2. REST API Data Siswa

Endpoint `GET /api/v1/students` menyediakan data siswa yang telah memiliki pendaftaran. Endpoint memerlukan Bearer token dengan ability `students:read`, mengikuti scope cabang API client, mendukung filter `status`, `search`, `cabang_id`, `page`, dan `per_page`, serta mengembalikan pagination.

Data respons menggabungkan Pendaftaran, Lead, Sekolah, Program Bimbel, Cabang, dan total pembayaran berstatus `Lunas` atau `Terverifikasi`. API saat ini menggunakan pola pull. Rancangan push otomatis tetap merupakan pengembangan berikutnya.

## 3. Dampak Arsitektur dan Database

- Aplikasi tetap berupa modular monolith Laravel.
- Penambahan role tidak memerlukan migration karena `users.role` disimpan sebagai string.
- Endpoint siswa tidak menambah tabel; data dibentuk dari relasi yang sudah tersedia.
- Scope dan otorisasi diterapkan pada query, navigasi, aksi, dan URL langsung.

## 4. Deployment

```bash
cd ~/domains/crm.sivmi.id/public_html
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Untuk Docker, bangun ulang service aplikasi dan jalankan migration serta pembersihan cache. Tidak ada migration khusus role Keuangan, tetapi `migrate --force` tetap dijalankan sebagai prosedur rilis standar.

## 5. UAT Tambahan

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| RBAC-KEU-01 | Login sebagai Keuangan | Dashboard, Laporan, Pendaftaran, dan Pembayaran tampil sesuai scope cabang |
| RBAC-KEU-02 | Keuangan membuka Leads atau master data melalui URL langsung | Akses ditolak |
| RBAC-KEU-03 | Keuangan memverifikasi pembayaran cabangnya | Status berhasil berubah dan audit tercatat |
| RBAC-KEU-04 | Keuangan mencoba menghapus pembayaran | Aksi tidak tersedia atau ditolak |
| RBAC-ADM-01 | Admin/Superadmin membuka form tambah atau ubah Pendaftaran/Pembayaran | Aksi tidak tersedia atau ditolak |
| API-STU-01 | Client dengan `students:read` meminta daftar siswa | Respons 200, terpaginasikan, dan sesuai scope |
| API-STU-02 | Client tanpa `students:read` meminta daftar siswa | Respons 403 |
| API-STU-03 | Client cabang mengirim `cabang_id` lain | Data tetap dibatasi pada cabang token |

## 6. Status Pengujian

Pengujian terfokus untuk Pendaftaran, Pembayaran, role Keuangan, master data, dan scope cabang lulus sebanyak **10 test dengan 146 assertion**. Suite penuh terakhir menghasilkan **115 dari 117 test lulus**. Dua masalah fixture lama pada Settings dan Report harus diperbaiki sebelum rilis produksi dan tidak boleh dicatat sebagai quality gate penuh yang lulus.

## 7. Dokumen dan Diagram Aktif

- `docs/DOKUMENTASI-PROJECT-SIVMI-CRM-v2.3.docx`
- `docs/PANDUAN-PENGGUNA-LENGKAP-CRM-VILLA-MERAH-v1.2.docx`
- `docs/BAB XII - SOP Operasional CRM Villa Merah v1.1.docx`
- `docs/DOKUMEN-TESTING-DAN-DEBUGGING-DAN-SECURITY-CRM-VILLA-MERAH-v2.3.docx`
- `docs/CRM_DIAGRAM_PROJECT_VILLA_MERAH_v1.1.drawio`
- `docs/ERD_CRM_VILLA_MERAH_TERBARU_2026-09-08.drawio`
