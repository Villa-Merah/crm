# Checklist Rilis dan Verifikasi SIVMI CRM

**Baseline:** Versi 2.3
**Tanggal:** 8 September 2026
**Tujuan:** panduan ringkas untuk memastikan rilis SIVMI CRM siap digunakan dan dapat ditelusuri.

## 1. Ruang Lingkup Rilis

Rilis baseline ini mencakup:

- role Keuangan dan pembatasan kewenangan transaksi;
- endpoint `GET /api/v1/students` dengan ability `students:read`;
- pencarian sekolah melalui API Sekolah Indonesia dengan fallback manual;
- peta persebaran leads;
- aksi massal pada modul Leads;
- pembaruan dokumentasi dan diagram sistem.

## 2. Pemeriksaan Sebelum Rilis

### Kode dan dependency

- [ ] Branch rilis berada pada commit yang disetujui.
- [ ] Working tree bersih dan tidak ada file rahasia yang ikut dilacak.
- [ ] `composer install --no-dev --optimize-autoloader` berhasil.
- [ ] Build asset dengan `npm run build` berhasil.
- [ ] `php artisan test --filter=PublicStudentApiTest` berhasil.
- [ ] Test role, transaksi, master data, dan scope cabang berhasil.
- [ ] Hasil suite penuh dicatat apa adanya; test yang gagal tidak boleh ditandai lulus.

### Konfigurasi

- [ ] `APP_ENV=production` dan `APP_DEBUG=false`.
- [ ] `APP_URL` menggunakan HTTPS final.
- [ ] `APP_KEY`, kredensial database, Redis, SMTP, WAHA, dan HMAC disimpan sebagai secret.
- [ ] `PUBLIC_API_ENABLED` dan rate limit sesuai keputusan rilis.
- [ ] `SCHOOL_API_ENABLED` hanya aktif apabila URL, timeout, cache, dan fallback telah diuji.
- [ ] Domain mengarah ke document root Laravel `public` atau reverse proxy yang benar.

### Data dan backup

- [ ] Backup database dibuat sebelum migration.
- [ ] Backup storage dan volume session WAHA dibuat bila fitur WhatsApp aktif.
- [ ] Restore backup pernah diuji pada environment terisolasi.
- [ ] Tidak ada akun demo atau token API uji pada database produksi.

## 3. Prosedur Deployment Docker

Dijalankan dari root repository dengan file environment produksi:

```powershell
docker compose --env-file docker/.env.production -f docker/compose.production.yml config --quiet
docker compose --env-file docker/.env.production -f docker/compose.production.yml build --pull
docker compose --env-file docker/.env.production -f docker/compose.production.yml run --rm app php artisan migrate --force
docker compose --env-file docker/.env.production -f docker/compose.production.yml up -d --remove-orphans
```

Setelah container aktif:

```powershell
docker compose --env-file docker/.env.production -f docker/compose.production.yml exec app php artisan optimize:clear
docker compose --env-file docker/.env.production -f docker/compose.production.yml exec app php artisan optimize
docker compose --env-file docker/.env.production -f docker/compose.production.yml ps
```

Jangan menjalankan `docker compose down -v` pada produksi karena volume persisten akan terhapus.

## 4. Smoke Test Pascarilis

Catat waktu, environment, commit, pelaksana, dan hasil setiap pemeriksaan berikut:

- [ ] `GET /up` mengembalikan status sehat.
- [ ] Login berhasil untuk akun uji tiap role yang disetujui.
- [ ] Dashboard terbuka dan scope cabang sesuai role.
- [ ] Keuangan dapat memverifikasi pembayaran cabangnya dan tidak dapat menghapus pembayaran.
- [ ] Admin Cabang dan Superadmin hanya dapat melihat Pendaftaran dan Pembayaran.
- [ ] Staff dapat membuat follow-up, pendaftaran, dan pembayaran sesuai status yang diizinkan.
- [ ] Direksi hanya melakukan pembacaan dashboard, laporan, dan audit.
- [ ] `GET /api/v1/students` menghasilkan `200` untuk token `students:read`.
- [ ] Token tanpa `students:read` menghasilkan `403`.
- [ ] Client yang dibatasi cabang tidak dapat membaca cabang lain.
- [ ] Pagination API diproses sampai halaman terakhir.
- [ ] Sekolah Indonesia dapat dicari; kegagalan layanan masih menyediakan fallback manual.
- [ ] Peta leads tampil tanpa mengambil asset dari Vite development server.
- [ ] Queue, scheduler, notifikasi, audit, dan upload storage berjalan.
- [ ] WAHA berstatus `WORKING` dan webhook tanpa HMAC ditolak, bila WhatsApp aktif.

## 5. Bukti Rilis Minimum

Simpan bukti berikut pada catatan rilis atau tiket deployment:

| Bukti | Isi minimum |
|---|---|
| Identitas rilis | commit SHA, waktu, environment, pelaksana |
| Validasi kode | perintah test, build, dan hasilnya |
| Migrasi | output `migrate --force` dan daftar migration |
| Health check | respons `/up` dan status container |
| RBAC | hasil smoke test tiap role |
| API | status code, request ID, dan hasil uji scope tanpa token rahasia |
| Backup | waktu backup, lokasi, dan hasil verifikasi file |
| Rollback | tag/image sebelumnya dan keputusan rollback |

Jangan menyimpan bearer token, password, HMAC secret, payload data pribadi, atau isi `.env` ke dalam bukti rilis.

## 6. Kriteria Persetujuan

Rilis dapat disetujui apabila:

1. build dan migration berhasil;
2. health check sehat;
3. tidak ada kegagalan kritis pada RBAC, scope cabang, API, queue, atau storage;
4. backup dapat ditemukan dan prosedur restore diketahui;
5. semua kegagalan yang tersisa dicatat sebagai risiko terbuka dengan pemilik dan rencana perbaikan.

Test yang gagal karena fixture lama, environment, atau konfigurasi tidak boleh disamarkan sebagai test lulus. Rilis harus ditahan apabila kegagalan tersebut memengaruhi autentikasi, otorisasi, integritas transaksi, kebocoran data, atau proses backup/restore.

## 7. Rollback

1. Hentikan promosi versi baru dan simpan log error yang relevan.
2. Kembalikan `IMAGE_TAG` atau artefak ke versi terakhir yang disetujui.
3. Jalankan health check dan smoke test minimum.
4. Jika migration tidak kompatibel ke belakang, restore database dari backup sebelum rilis setelah mendapat persetujuan.
5. Verifikasi queue, scheduler, storage, API, dan session WAHA.
6. Catat penyebab, dampak, waktu pemulihan, serta tindak lanjut.

Jangan menjalankan `migrate:rollback` secara otomatis tanpa meninjau dampak terhadap data produksi.

## 8. Referensi

- [Deployment Docker](DEPLOYMENT.md)
- [Profil Sistem dan Infrastruktur](PROFIL-SISTEM-DAN-INFRASTRUKTUR-TERBARU.md)
- [Pembaruan Versi 2.3](PEMBARUAN-VERSI-TERBARU-2026-09-08.md)
- [Panduan Integrasi API Data Siswa](PANDUAN-INTEGRASI-API-DATA-SISWA.md)
- [Konfigurasi WAHA](PANDUAN-KONFIGURASI-WAHA.md)
