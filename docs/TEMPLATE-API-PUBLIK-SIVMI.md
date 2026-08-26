# Template API Publik SIVMI CRM

Template ini menyediakan contoh API nyata untuk mengirim dan menerima data Leads. Endpoint memakai versi `/api/v1`, JSON, token client yang disimpan dalam bentuk hash, ability, scope role/cabang, pagination, validasi, dan idempotensi.

> API dinonaktifkan secara default. Jangan mengaktifkannya sebelum migration, pembuatan API client, HTTPS, dan pengujian keamanan selesai.

## 1. Endpoint

| Method | Endpoint | Ability | Fungsi |
|---|---|---|---|
| `GET` | `/api/v1/leads` | `leads:read` | Menerima daftar lead yang dapat dilihat client |
| `GET` | `/api/v1/leads/{id}` | `leads:read` | Menerima detail satu lead |
| `POST` | `/api/v1/leads` | `leads:write` | Mengirim/membuat data lead baru |

Webhook WAHA tetap berada di `/api/webhooks/waha` dan menggunakan HMAC SHA-512. Webhook tersebut bukan bagian kontrak publik `/api/v1`.

## 2. Aktivasi lokal

Jalankan migration:

```powershell
php artisan migrate
```

Buat API client. Pengguna audit harus sudah tersedia dan aktif:

```powershell
php artisan api-client:create "Integrasi Leads Lokal" `
  --user=admin@sivmi.test `
  --branch=BDG `
  --abilities=leads:read,leads:write
```

Token mentah hanya ditampilkan satu kali. Simpan di password manager atau secret manager. Database hanya menyimpan hash SHA-256 dan prefix token.

Aktifkan API pada `.env` atau `docker/.env`:

```dotenv
PUBLIC_API_ENABLED=true
PUBLIC_API_RATE_LIMIT=60
PUBLIC_API_MAX_PER_PAGE=100
PUBLIC_API_IDEMPOTENCY_TTL_HOURS=24
```

Setelah mengubah environment:

```powershell
php artisan config:clear
```

## 3. Header standar

```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer sivmi_TOKEN_YANG_DIBERIKAN
```

`X-API-Key` juga diterima sebagai alternatif `Authorization`, tetapi Bearer token direkomendasikan. Operasi `POST` wajib menyertakan:

```http
Idempotency-Key: lead-import-20260825-0001
```

Gunakan nilai baru untuk operasi berbeda. Mengulang key dan payload yang sama akan mengembalikan respons tersimpan dengan header `X-Idempotent-Replay: true`. Key yang sama dengan payload berbeda menghasilkan `409 Conflict`.

## 4. Mengirim data lead

PowerShell:

```powershell
$headers = @{
    Authorization = "Bearer sivmi_TOKEN_YANG_DIBERIKAN"
    Accept = "application/json"
    "Idempotency-Key" = "lead-demo-20260825-0001"
}

$body = @{
    nama = "Calon Siswa Contoh"
    no_wa = "081234567890"
    status_lead = "Cold"
    sumber_lead = "Instagram"
    tanggal_masuk = "2026-08-25"
} | ConvertTo-Json

Invoke-RestMethod `
    -Method Post `
    -Uri "http://localhost:8000/api/v1/leads" `
    -Headers $headers `
    -ContentType "application/json" `
    -Body $body
```

cURL:

```bash
curl -X POST "http://localhost:8000/api/v1/leads" \
  -H "Authorization: Bearer sivmi_TOKEN_YANG_DIBERIKAN" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: lead-demo-20260825-0001" \
  -d '{"nama":"Calon Siswa Contoh","no_wa":"081234567890","status_lead":"Cold","sumber_lead":"Instagram","tanggal_masuk":"2026-08-25"}'
```

Client dengan scope cabang tidak perlu mengirim `cabang_id`; server selalu memakai cabang client. Client global wajib mengirim `cabang_id`. Staff otomatis menjadi PIC lead yang dibuat melalui client miliknya.

Contoh respons `201 Created`:

```json
{
  "data": {
    "id": 141,
    "nama": "Calon Siswa Contoh",
    "no_wa": "081234567890",
    "status": "Cold",
    "sumber": "Instagram",
    "tanggal_masuk": "2026-08-25",
    "cabang": { "id": 1, "kode": "BDG", "nama": "Bandung" }
  },
  "meta": { "request_id": "UUID" }
}
```

## 5. Menerima daftar data

```powershell
$headers = @{
    Authorization = "Bearer sivmi_TOKEN_YANG_DIBERIKAN"
    Accept = "application/json"
}

Invoke-RestMethod `
    -Method Get `
    -Uri "http://localhost:8000/api/v1/leads?status=Cold&sumber=Instagram&per_page=25" `
    -Headers $headers
```

Filter yang tersedia:

- `status`: `Cold`, `Warm`, `Hot`, `Daftar`, atau `Tidak Berminat`;
- `sumber`: `Iklan`, `Instagram`, `Kunjungan`, `Saudara`, `Facebook`, atau `Tiktok`;
- `search`: nama atau nomor WhatsApp;
- `cabang_id`: hanya efektif untuk client global;
- `per_page`: 1 sampai batas `PUBLIC_API_MAX_PER_PAGE`.

Respons list memiliki `data`, `meta`, dan `links`. Query selalu dibatasi oleh role pengguna audit, kepemilikan Staff, cabang client, pagination, dan filter allowlist.

## 6. Menerima detail data

```http
GET /api/v1/leads/141
Authorization: Bearer sivmi_TOKEN_YANG_DIBERIKAN
Accept: application/json
```

Lead di luar scope dikembalikan sebagai `404`, sehingga keberadaan data cabang lain tidak bocor.

## 7. Format error

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Data yang diberikan tidak valid.",
    "fields": {
      "no_wa": ["The no wa field is required."]
    },
    "request_id": "UUID"
  }
}
```

Status utama:

- `400`: Idempotency-Key tidak ada/tidak valid;
- `401`: token tidak valid atau client nonaktif;
- `403`: ability, role, PIC, atau cabang ditolak;
- `404`: resource tidak ditemukan dalam scope;
- `409`: konflik idempotensi/request masih berjalan;
- `422`: validasi payload gagal;
- `429`: rate limit terlampaui;
- `503`: API publik belum diaktifkan.

Semua respons API menyertakan `X-Request-ID` untuk korelasi log.

## 8. Menonaktifkan dan merotasi token

Menonaktifkan client:

```powershell
php artisan tinker
```

```php
App\Models\ApiClient::where('key_prefix', 'sivmi_xxxxxxxx')->update(['active' => false]);
```

Untuk rotasi, nonaktifkan client lama lalu buat client baru. Jangan menyimpan token mentah di repository, log, screenshot, atau dokumentasi.

## 9. Berkas implementasi

- Kontrak OpenAPI: `docs/api/openapi-public-v1.yaml`
- Request siap pakai untuk REST Client/VS Code: `docs/api/SIVMI-Public-API-v1.http`
- Routes: `routes/api.php`
- Controller: `app/Http/Controllers/Api/V1/LeadController.php`
- Request: `app/Http/Requests/Api/V1`
- Resource: `app/Http/Resources/Api/V1/LeadResource.php`
- Middleware: `AuthenticatePublicApi` dan `EnsureApiIdempotency`
- Command token: `app/Console/Commands/CreateApiClient.php`
- Migration: `2026_08_25_000001_create_public_api_tables.php`

Resource Leads ini merupakan template. Modul Pendaftaran, Pembayaran, dan Laporan sebaiknya mengikuti format autentikasi, error, policy, pagination, idempotensi, dan versioning yang sama.
