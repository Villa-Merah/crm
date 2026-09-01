# Pembaruan SIVMI CRM Versi 2.1

**Tanggal baseline:** 1 September 2026

**Commit baseline:** `31c387e`

**Cakupan:** API Sekolah Indonesia, peta persebaran leads, aksi massal leads, normalisasi sumber lead, dan penyempurnaan kualitas data.

Dokumen ini menjadi addendum resmi untuk dokumen aktif SIVMI CRM. Dokumen berlabel versi lama tetap dipertahankan sebagai arsip historis dan tidak dianggap menggambarkan seluruh kemampuan versi 2.1.

## 1. Ringkasan perubahan

### 1.1 Integrasi API Sekolah Indonesia

- Pencarian sekolah tersedia pada form Lead dan master Sekolah berdasarkan nama atau NPSN.
- Data yang dipilih dari API dinormalisasi, lalu disimpan ke database lokal agar dapat dipakai kembali.
- Sinkronisasi memakai NPSN sebagai identitas utama untuk mencegah duplikasi.
- Metadata yang disimpan mencakup bentuk pendidikan, akreditasi, kode wilayah, sumber data, dan waktu sinkronisasi.
- Jika API tidak tersedia, pengguna tetap dapat memilih sekolah lokal atau memasukkan sekolah secara manual.

Konfigurasi environment:

```dotenv
SCHOOL_API_ENABLED=true
SCHOOL_API_URL=https://sekolah.devapi.id/sekolah
SCHOOL_API_TIMEOUT=5
SCHOOL_API_CACHE_TTL=86400
SCHOOL_API_RESULT_LIMIT=20
```

### 1.2 Peta persebaran leads

- Dashboard menampilkan peta interaktif berbasis Leaflet 1.9.4 yang disajikan dari aset lokal aplikasi dan tile OpenStreetMap.
- Agregasi dilakukan pada tingkat kota, bukan alamat individual, untuk membatasi paparan data pribadi.
- Lokasi mengutamakan `leads.kota_asal`; jika kosong, sistem memakai kota pada master sekolah.
- Ukuran lingkaran menunjukkan jumlah leads dalam filter aktif.
- Filter periode, cabang, pengguna, serta scope role tetap berlaku pada peta.
- Sistem menampilkan cakupan data terpetakan, top lima kota, dan daftar wilayah yang belum dikenali.
- Koordinat kota diselesaikan oleh `app/Support/IndonesiaCityCoordinates.php`; kota baru harus ditambahkan ke resolver bila belum tersedia.

Catatan operasi: berkas Leaflet berada di `public/vendor/leaflet`, sedangkan browser tetap harus dapat mengakses tile OpenStreetMap. Skrip Leaflet diberi `data-cfasync="false"` agar tidak digabungkan oleh Cloudflare Rocket Loader. Jika peta kosong, lakukan hard refresh, periksa Console/Network browser, dan pastikan kebijakan firewall/CSP tidak memblokir tile OpenStreetMap.

### 1.3 Pilih banyak dan aksi massal Leads

- Daftar Leads menyediakan checkbox per baris dan pilihan seluruh hasil filter.
- Bulk edit mendukung status, sumber lead, kota asal, kelas sekolah, sekolah, program, dan PIC.
- Field nullable dapat dikosongkan melalui pilihan yang disediakan.
- Penghapusan per baris dan massal hanya tersedia untuk Superadmin.
- Lead yang sudah memiliki pendaftaran dilindungi dari penghapusan.
- Direksi tetap read-only; Staff dan Admin Cabang tetap dibatasi sesuai scope data.
- Pembaruan berjalan melalui model sehingga observer, riwayat, dan audit aktivitas tetap tercatat.

### 1.4 Kualitas data yang tetap berlaku

- Data minimum Lead: Nama lengkap dan Nomor WhatsApp.
- Nomor WhatsApp dinormalisasi dan diperiksa untuk mencegah duplikasi lintas format `08`, `62`, dan `+62`.
- Field kota, sekolah, kelas sekolah, program, cabang, PIC, sumber, status, dan tanggal masuk boleh kosong sesuai kebijakan operasional.
- Pada master Sekolah, hanya Nama Sekolah yang wajib; detail lain boleh dilengkapi kemudian atau melalui API.
- `sumber_lead_id` menjadi referensi terstruktur ke master sumber lead.

## 2. Dampak arsitektur

- Bentuk aplikasi tetap **modular monolith** Laravel. Penambahan API eksternal dan CDN peta tidak mengubahnya menjadi microservices.
- API Sekolah adalah dependency eksternal pada modul master data; database CRM tetap menjadi sumber operasional.
- Peta tidak menambah tabel baru. Agregasi dibuat dari Lead dan Sekolah, sedangkan koordinat kota memakai resolver statis.
- Aksi massal menggunakan resource Filament dan policy/scope yang sama dengan CRUD Leads.

## 3. Prosedur deployment pembaruan

### Shared hosting

```bash
cd ~/domains/crm.sivmi.id/public_html
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

Pastikan variabel `SCHOOL_API_*` tersedia pada `.env`. Peta dan aksi massal tidak memerlukan migration tambahan di luar migration API Sekolah. Build aset tidak diperlukan untuk perbaikan tinggi peta terakhir karena ukuran kontainer peta ditetapkan langsung pada view, tetapi jalankan build jika rilis berikutnya mengubah CSS/JavaScript terkompilasi.

### Docker lokal/produksi

```powershell
docker compose --env-file docker/.env -f docker/compose.yml up -d --build
docker compose --env-file docker/.env -f docker/compose.yml exec app php artisan migrate --force
docker compose --env-file docker/.env -f docker/compose.yml exec app php artisan optimize:clear
```

## 4. UAT tambahan versi 2.1

| ID | Skenario | Hasil yang diharapkan |
|---|---|---|
| MAP-01 | Buka Dashboard dengan koneksi internet aktif | Peta, tile, kontrol zoom, dan bubble kota tampil. |
| MAP-02 | Ubah periode/cabang/PIC | Bubble, top kota, dan cakupan mengikuti filter serta scope role. |
| MAP-03 | Lead tidak memiliki kota asal tetapi sekolah memiliki kota | Lead dihitung menggunakan kota sekolah. |
| MAP-04 | Kota belum tersedia pada resolver | Kota masuk daftar belum dikenali tanpa merusak Dashboard. |
| BULK-01 | Pilih beberapa Lead dan ubah status | Seluruh Lead terpilih berubah dan audit/riwayat tercatat. |
| BULK-02 | Pilih seluruh hasil filter | Hanya data dalam hasil filter dan scope pengguna yang diproses. |
| BULK-03 | Kosongkan field nullable | Nilai field terpilih menjadi kosong tanpa mengubah field lain. |
| BULK-04 | Direksi mencoba bulk edit | Aksi tidak tersedia atau ditolak. |
| BULK-05 | Superadmin menghapus Lead tanpa pendaftaran | Lead terhapus setelah konfirmasi. |
| BULK-06 | Hapus Lead yang memiliki pendaftaran | Penghapusan ditolak dan alasan ditampilkan. |
| SCH-01 | Cari dan pilih sekolah dari API | Sekolah tersimpan lokal dan terhubung ke Lead. |
| SCH-02 | Pilih kembali NPSN yang sama | Record diperbarui tanpa duplikasi. |
| SCH-03 | API timeout/tidak aktif | Pesan jelas; pilihan lokal dan input manual tetap berfungsi. |

## 5. Dokumen aktif

- Dokumen utama: `DOKUMENTASIPROJECT-SIVMI-CRM-FINAL 0109-VERSI-2.1.docx`.
- Runbook deployment: `docs/DEPLOYMENT.md`.
- Profil as-built: `docs/PROFIL-SISTEM-DAN-INFRASTRUKTUR-TERBARU.md`.
- Panduan Docker: `docker/README.md`.
- Panduan WAHA/GOWS tetap berada pada `docs/PANDUAN-KONFIGURASI-WAHA.md` dan `docs/PANDUAN-WAHA-SETELAH-DEPLOY-SHARED-HOSTING.md`.
- ERD terbaru: `docs/ERD_CRM_VILLA_MERAH_TERBARU_2026-09-01.drawio`. Peta dashboard tidak mengubah ERD karena tidak menambah tabel.
