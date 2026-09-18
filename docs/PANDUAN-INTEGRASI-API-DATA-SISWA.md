# Panduan Integrasi API Data Siswa SIVMI CRM

**Status dokumen:** Sesuai implementasi saat ini
**Tanggal baseline:** 8 September 2026
**Versi API:** v1

## 1. Tujuan

API Data Siswa digunakan untuk memberikan data siswa yang telah terdaftar di SIVMI CRM kepada sistem lain, misalnya sistem akademik, aplikasi pembelajaran, data warehouse, atau sistem pelaporan manajemen. Integrasi dilakukan melalui HTTPS dan API key sehingga sistem lain tidak perlu dan tidak boleh mengakses database CRM secara langsung.

Pada implementasi saat ini, integrasi menggunakan pola **pull**: sistem tujuan mengambil data dari SIVMI CRM melalui endpoint `GET /api/v1/students`. SIVMI CRM belum mengirim data siswa secara otomatis ke endpoint sistem tujuan. Rancangan pola **push** dijelaskan pada bagian akhir sebagai pengembangan berikutnya.

## 2. Gambaran Alur

```text
SIVMI CRM
  └─ pendaftaran + lead + sekolah + program + cabang + pembayaran
                         │
                         ▼
                GET /api/v1/students
                         │ HTTPS + Bearer token
                         ▼
                 Sistem tujuan
             menyimpan external_id CRM
```

Data siswa API bersumber dari data pendaftaran. Dengan demikian, lead yang belum mempunyai pendaftaran tidak termasuk dalam hasil endpoint siswa.

## 3. Kemampuan yang Tersedia

| Kemampuan | Status |
|---|---|
| Daftar siswa terdaftar | Tersedia |
| Filter status pendaftaran | Tersedia |
| Pencarian nama, nomor WhatsApp, atau kota | Tersedia |
| Filter cabang untuk client global | Tersedia |
| Pembatasan otomatis berdasarkan cabang client | Tersedia |
| Pagination | Tersedia |
| Ringkasan pembayaran terverifikasi | Tersedia |
| Detail satu siswa | Belum tersedia |
| Filter `updated_since` | Belum tersedia |
| Push otomatis ke sistem tujuan | Belum tersedia |
| Webhook perubahan siswa | Belum tersedia |

## 4. Endpoint dan Environment

| Lingkungan | Base URL |
|---|---|
| Produksi | `{APP_URL}/api/v1` |
| Docker lokal | `http://localhost:8000/api/v1` |

Endpoint siswa:

```http
GET /api/v1/students
```

Gunakan domain yang sama dengan nilai `APP_URL` pada server. Contoh apabila aplikasi berjalan di `https://crm.sivmi.id`:

```text
https://crm.sivmi.id/api/v1/students
```

## 5. Konfigurasi SIVMI CRM

### 5.1 Mengaktifkan API

Tambahkan atau perbarui konfigurasi berikut pada `.env` server CRM:

```env
PUBLIC_API_ENABLED=true
PUBLIC_API_RATE_LIMIT=60
PUBLIC_API_MAX_PER_PAGE=100
PUBLIC_API_IDEMPOTENCY_TTL_HOURS=24
```

Keterangan:

| Variabel | Fungsi |
|---|---|
| `PUBLIC_API_ENABLED` | Mengaktifkan atau menonaktifkan seluruh API publik |
| `PUBLIC_API_RATE_LIMIT` | Batas request per menit untuk setiap client/rute |
| `PUBLIC_API_MAX_PER_PAGE` | Batas maksimum record dalam satu halaman |
| `PUBLIC_API_IDEMPOTENCY_TTL_HOURS` | Masa simpan idempotency key operasi tulis; belum digunakan endpoint siswa baca |

Terapkan perubahan konfigurasi:

```bash
php artisan optimize:clear
php artisan optimize
```

Pada Docker:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan optimize:clear
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan optimize
```

### 5.2 Menentukan pengguna audit

API client wajib dikaitkan dengan pengguna aplikasi yang masih aktif. Pengguna tersebut menjadi identitas audit dan menentukan batas akses data. Sebelum membuat client, pastikan:

- akun pengguna aktif;
- role pengguna sesuai;
- pengguna mempunyai cabang apabila bukan pengguna global;
- cabang masih aktif;
- email yang digunakan benar.

### 5.3 Membuat API client siswa

Client lintas cabang hanya boleh menggunakan pengguna yang memiliki akses global:

```bash
php artisan api-client:create "Integrasi Sistem Akademik" \
  --user=superadmin@villamerah.id \
  --abilities=students:read
```

Client yang dibatasi ke satu cabang:

```bash
php artisan api-client:create "Integrasi Akademik Bandung" \
  --user=adminbandung@villamerah.id \
  --branch=BDG \
  --abilities=students:read
```

PowerShell menggunakan backtick sebagai penyambung baris:

```powershell
php artisan api-client:create "Integrasi Akademik Bandung" `
  --user=adminbandung@villamerah.id `
  --branch=BDG `
  --abilities=students:read
```

Perintah menampilkan raw token satu kali. Contoh bentuk token:

```text
sivmi_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Salin token saat itu juga dan simpan pada secret manager atau environment sistem tujuan. Database CRM hanya menyimpan hash token sehingga token asli tidak dapat ditampilkan kembali.

### 5.4 Menonaktifkan token

Jika token bocor, integrasi dihentikan, atau client diganti, nonaktifkan client berdasarkan `key_prefix` yang tercatat:

```bash
php artisan tinker
```

```php
App\Models\ApiClient::where('key_prefix', 'sivmi_xxxxxxxx')
    ->update(['active' => false]);
```

Setelah itu buat token baru. Jangan mengaktifkan kembali token yang sudah diduga bocor.

## 6. Konfigurasi Sistem Tujuan

Simpan alamat dan token pada environment sistem tujuan:

```env
SIVMI_API_BASE_URL=https://crm.sivmi.id/api/v1
SIVMI_API_TOKEN=sivmi_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SIVMI_API_TIMEOUT=20
SIVMI_API_PER_PAGE=100
```

Jangan meletakkan token pada JavaScript browser, aplikasi publik, URL, log, atau repository Git. Request sebaiknya dilakukan dari backend sistem tujuan.

Header yang didukung:

```http
Accept: application/json
Authorization: Bearer sivmi_xxxxx
```

Sebagai alternatif, middleware juga menerima:

```http
X-API-Key: sivmi_xxxxx
```

Bearer token direkomendasikan agar pola autentikasi konsisten.

## 7. Parameter Request

| Parameter | Tipe | Wajib | Ketentuan |
|---|---|---|---|
| `status` | string | Tidak | Hanya `Aktif` atau `Nonaktif` |
| `search` | string | Tidak | Maksimal 100 karakter; mencari nama, nomor WhatsApp, atau kota asal |
| `cabang_id` | integer | Tidak | Harus merupakan ID cabang yang valid; hanya efektif bagi client tanpa scope cabang tetap |
| `page` | integer | Tidak | Minimal 1 |
| `per_page` | integer | Tidak | Minimal 1, maksimal `PUBLIC_API_MAX_PER_PAGE`, default 100 |

Contoh mengambil seluruh siswa aktif:

```bash
curl "https://crm.sivmi.id/api/v1/students?status=Aktif&per_page=100&page=1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer sivmi_xxxxx"
```

Contoh pencarian:

```bash
curl "https://crm.sivmi.id/api/v1/students?search=Bandung&per_page=25" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer sivmi_xxxxx"
```

Contoh PowerShell:

```powershell
$headers = @{
    Accept = 'application/json'
    Authorization = 'Bearer sivmi_xxxxx'
}

Invoke-RestMethod `
    -Uri 'https://crm.sivmi.id/api/v1/students?status=Aktif&per_page=100&page=1' `
    -Headers $headers `
    -Method Get `
    -TimeoutSec 20
```

## 8. Struktur Respons

Contoh respons berhasil:

```json
{
  "data": [
    {
      "id": 125,
      "nama": "Ahmad Fauzan",
      "no_wa": "6281234567890",
      "kota_asal": "Bandung",
      "kelas": "Angkatan 2026",
      "status_pendaftaran": "Aktif",
      "tanggal_daftar": "2026-09-04",
      "sekolah": {
        "id": 31,
        "nama": "SMAN 1 Bandung"
      },
      "program": {
        "id": 8,
        "nama": "SR GOLD",
        "biaya": 24000000
      },
      "cabang": {
        "id": 2,
        "kode": "BDG",
        "nama": "Bandung"
      },
      "pembayaran": {
        "total_terverifikasi": 10000000,
        "sisa_tagihan": 14000000,
        "status": "unpaid"
      },
      "created_at": "2026-09-04T08:00:00.000000Z",
      "updated_at": "2026-09-04T09:00:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 100,
    "total": 250,
    "last_page": 3,
    "request_id": "b5913b83-16aa-4f04-a1c4-ccaa706d54f7"
  },
  "links": {
    "first": "https://crm.sivmi.id/api/v1/students?page=1",
    "last": "https://crm.sivmi.id/api/v1/students?page=3",
    "prev": null,
    "next": "https://crm.sivmi.id/api/v1/students?page=2"
  }
}
```

Catatan pemetaan:

- `id` adalah `pendaftaran_id` dan harus disimpan sebagai external ID oleh sistem tujuan;
- `kelas` menggunakan kelas/angkatan pendaftaran apabila tersedia, lalu memakai kelas sekolah lead sebagai fallback;
- `total_terverifikasi` hanya menjumlahkan pembayaran berstatus `Lunas` atau `Terverifikasi`;
- `sisa_tagihan` adalah biaya program dikurangi pembayaran terverifikasi;
- nilai sekolah, program, atau atribut opsional dapat bernilai `null`;
- nomor WhatsApp merupakan data pribadi dan hanya boleh digunakan sesuai tujuan integrasi.

## 9. Pagination dan Sinkronisasi

Sistem tujuan wajib membaca semua halaman. Jangan berasumsi seluruh siswa selalu berada pada halaman pertama.

Algoritma minimum:

1. Request halaman pertama dengan `per_page=100`.
2. Proses dan lakukan upsert berdasarkan `data[].id`.
3. Baca `links.next`.
4. Ulangi selama `links.next` tidak bernilai `null`.
5. Catat waktu sinkronisasi, jumlah berhasil, jumlah gagal, dan `request_id`.

Karena filter `updated_since` belum tersedia, sinkronisasi saat ini mengambil data secara terpaginasikan lalu melakukan **upsert**, bukan insert tanpa pemeriksaan. Jangan menggunakan nama atau nomor WhatsApp sebagai primary key integrasi; gunakan `id` dari CRM.

## 10. Scope Cabang dan Hak Akses

Ability minimum untuk endpoint ini adalah:

```text
students:read
```

Atur satu token untuk satu sistem dan satu kebutuhan. Apabila client mempunyai `cabang_id`, seluruh respons otomatis dibatasi ke cabang tersebut dan parameter `cabang_id` dari request tidak memperluas akses. Client global dapat menggunakan filter `cabang_id`, tetapi tetap mengikuti cakupan role pengguna audit yang terhubung.

Jangan memberikan ability `*`, `leads:read`, atau `leads:write` apabila sistem hanya membutuhkan data siswa.

## 11. Kode Status dan Penanganan Error

| HTTP | Kode | Penyebab umum | Tindakan |
|---|---|---|---|
| `200` | — | Request berhasil | Proses `data` dan pagination |
| `401` | `unauthenticated` | Token hilang, terlalu pendek, salah, nonaktif, atau pemilik token nonaktif | Periksa header dan lakukan rotasi token |
| `403` | `forbidden` | Client tidak memiliki `students:read` | Buat client dengan ability yang sesuai |
| `422` | `validation_failed` | Parameter query tidak valid | Periksa `error.fields` |
| `429` | — | Rate limit terlampaui | Tunggu dan ulangi dengan exponential backoff |
| `503` | `api_disabled` | `PUBLIC_API_ENABLED=false` atau cache konfigurasi belum diperbarui | Aktifkan API dan bersihkan cache konfigurasi |
| `500` | — | Gangguan aplikasi/server | Catat `X-Request-ID`, periksa log, jangan melakukan retry tanpa batas |

Contoh error:

```json
{
  "error": {
    "code": "forbidden",
    "message": "API client tidak memiliki ability yang diperlukan.",
    "request_id": "72db39e3-c260-4145-8d74-30469e2cbc9e"
  }
}
```

Simpan `X-Request-ID` dari header atau `request_id` dari body untuk penelusuran masalah pada log server.

## 12. Strategi Retry

Sistem tujuan boleh melakukan retry pada timeout, `429`, `502`, `503`, atau `504`. Gunakan jeda bertahap, misalnya 2, 5, 15, 30, dan 60 detik, dengan jitter. Jangan melakukan retry otomatis untuk `401`, `403`, dan `422` sebelum penyebab konfigurasi atau request diperbaiki.

Atur timeout koneksi antara 10–20 detik. Jalankan sinkronisasi melalui background job atau scheduler sistem tujuan, bukan pada request pengguna di browser.

## 13. Keamanan dan Perlindungan Data

- Gunakan HTTPS pada produksi.
- Simpan token hanya pada backend dan secret manager/environment.
- Gunakan token berbeda untuk setiap sistem tujuan.
- Terapkan prinsip least privilege dengan ability `students:read`.
- Batasi client ke cabang tertentu apabila akses global tidak diperlukan.
- Jangan mencatat bearer token dan payload data pribadi ke log biasa.
- Rotasi token secara berkala dan segera setelah insiden.
- Nonaktifkan client yang tidak lagi digunakan.
- Batasi data yang disalin sesuai kebutuhan dan kebijakan organisasi.
- Lindungi database sistem tujuan dengan kontrol akses dan backup terenkripsi.
- Tetapkan masa retensi dan prosedur penghapusan data pada sistem tujuan.
- Bila infrastruktur mendukung, tambahkan IP allowlist pada reverse proxy sebagai lapisan tambahan.

## 14. Pengujian Sebelum Produksi

Checklist integrasi:

- [ ] API publik diaktifkan pada environment uji.
- [ ] API client mempunyai ability `students:read`.
- [ ] Scope cabang sudah sesuai.
- [ ] Request tanpa token menghasilkan `401`.
- [ ] Token tanpa ability menghasilkan `403`.
- [ ] Token benar menghasilkan `200`.
- [ ] Filter `Aktif` dan `Nonaktif` bekerja.
- [ ] Pagination diproses sampai halaman terakhir.
- [ ] Sistem tujuan melakukan upsert berdasarkan `id`.
- [ ] Nilai `null` tidak menyebabkan kegagalan mapping.
- [ ] Data lintas cabang tidak bocor.
- [ ] Token tidak muncul pada log atau source code.
- [ ] Timeout, retry, dan rate limit sudah diuji.
- [ ] Rekonsiliasi jumlah siswa CRM dan sistem tujuan dilakukan.

Jalankan pengujian endpoint pada source SIVMI CRM:

```bash
php artisan test --filter=PublicStudentApiTest
```

Pada Docker:

```powershell
docker compose --env-file docker\.env -f docker\compose.yml exec app php artisan test --filter=PublicStudentApiTest
```

## 15. Troubleshooting

### API menghasilkan 503

Pastikan `.env` berisi `PUBLIC_API_ENABLED=true`, lalu jalankan:

```bash
php artisan optimize:clear
php artisan optimize
```

### API menghasilkan 401

Periksa bahwa header memakai token lengkap, client masih aktif, dan pengguna pemilik API client masih aktif. Token mentah tidak dapat diambil kembali dari database; buat token baru jika token hilang.

### API menghasilkan 403

Token tidak mempunyai ability `students:read`. Jangan mengubah hash token secara manual; buat client baru dengan ability yang tepat.

### Data cabang lain tidak muncul

Ini merupakan perilaku yang benar jika client dibatasi ke satu cabang. Gunakan client global hanya setelah kebutuhan akses lintas cabang disetujui.

### Data siswa kosong

Pastikan lead sudah mempunyai record pendaftaran. Endpoint siswa tidak mengembalikan lead yang belum terdaftar. Periksa juga filter status dan scope cabang client.

### Jumlah pembayaran berbeda

API hanya menghitung transaksi dengan status `Lunas` atau `Terverifikasi`. Pembayaran berstatus menunggu atau gagal tidak masuk `total_terverifikasi`.

## 16. Rancangan Push API Otomatis

Bagian ini adalah **rekomendasi pengembangan dan belum merupakan fitur aktif**. Push digunakan apabila SIVMI CRM harus segera mengirim data ketika pendaftaran dibuat atau diperbarui.

Konfigurasi yang disarankan:

```env
STUDENT_SYNC_ENABLED=false
STUDENT_SYNC_BASE_URL=https://sistem-tujuan.example/api/v1
STUDENT_SYNC_TOKEN=
STUDENT_SYNC_WEBHOOK_SECRET=
STUDENT_SYNC_TIMEOUT=15
STUDENT_SYNC_MAX_ATTEMPTS=5
```

Sistem tujuan menyediakan endpoint idempoten, misalnya:

```http
POST /api/v1/students
PUT /api/v1/students/{external_id}
```

Request harus membawa:

```http
Authorization: Bearer {token-sistem-tujuan}
Idempotency-Key: sivmi-student-{id}-{version}
X-SIVMI-Signature: sha256={hmac}
Content-Type: application/json
```

Implementasi CRM yang direkomendasikan terdiri dari event perubahan pendaftaran, tabel outbox, queue job, API client sistem tujuan, retry bertahap, dead-letter/failed job, audit, serta halaman monitoring dan kirim ulang. Transaksi pendaftaran tidak boleh dibatalkan hanya karena sistem tujuan sedang tidak tersedia; event disimpan lebih dahulu lalu dikirim secara asinkron.

## 17. Rekomendasi Pengembangan API

Prioritas pengembangan berikutnya:

1. Tambahkan `GET /api/v1/students/{student}`.
2. Tambahkan filter `updated_since` dan urutan deterministik berdasarkan `updated_at` serta `id`.
3. Tambahkan cursor pagination untuk volume data besar.
4. Tambahkan audit request API tanpa menyimpan token atau data sensitif berlebihan.
5. Tambahkan manajemen dan rotasi API client melalui antarmuka Superadmin.
6. Tambahkan outbox dan push API bila diperlukan sinkronisasi mendekati real-time.
7. Tambahkan versioning/mapping identifier agar perubahan struktur data tidak memutus sistem tujuan.

## 18. Referensi Internal

- Kontrak OpenAPI: `docs/api/openapi-public-v1.yaml`
- Spesifikasi REST API: `docs/SPESIFIKASI-REST-API-SIVMI-CRM-v1.md`
- Route API: `routes/api.php`
- Resource respons siswa: `app/Http/Resources/Api/V1/StudentResource.php`
- Validasi parameter: `app/Http/Requests/Api/V1/ListStudentRequest.php`
- Autentikasi API: `app/Http/Middleware/AuthenticatePublicApi.php`
- Pengujian: `tests/Feature/PublicStudentApiTest.php`
