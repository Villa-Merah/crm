# Task List SIVMI CRM

Dokumen ini menjadi daftar pekerjaan utama proyek SIVMI CRM. Detail kendala, bug, dan tindakan singkat dicatat pada `todo.md`.

**Pembaruan terakhir:** 14 September 2026  
**Baseline:** SIVMI CRM versi 2.3

## Aturan status

| Status | Arti |
|---|---|
| `BACKLOG` | Belum dijadwalkan |
| `READY` | Siap dikerjakan dan kebutuhan sudah jelas |
| `IN PROGRESS` | Sedang dikerjakan |
| `BLOCKED` | Tidak dapat dilanjutkan sampai hambatan diselesaikan |
| `REVIEW` | Implementasi selesai dan menunggu pemeriksaan |
| `DONE` | Selesai, diuji, dan terdokumentasi |

## Prioritas pekerjaan

| ID | Prioritas | Status | Pekerjaan | Hasil yang diharapkan |
|---|---|---|---|---|
| TASK-001 | Tinggi | `DONE` | Dokumentasikan aktivasi WAHA GOWS lokal melalui Cloudflare Named Tunnel | Panduan lokal, DNS, server, keamanan, pengujian, dan troubleshooting tersedia |
| TASK-002 | Tinggi | `READY` | Terapkan Named Tunnel `waha.sivmi.id` pada komputer operasional | Connector `cloudflared` sehat dan `/health` dapat diakses melalui HTTPS |
| TASK-003 | Tinggi | `READY` | Terapkan konfigurasi WAHA pada server CRM | Server memakai URL tunnel, API key sesuai, dan webhook HMAC aktif |
| TASK-004 | Tinggi | `READY` | Jalankan UAT WhatsApp end-to-end | QR, pesan masuk, pesan keluar, media, ACK, lead matching, queue, dan audit lulus |
| TASK-005 | Tinggi | `READY` | Selesaikan dua kegagalan pada full test suite | Seluruh test lulus sebelum rilis produksi |
| TASK-006 | Sedang | `DONE` | Tambahkan role Keuangan dan pisahkan kewenangan transaksi | Staff mencatat transaksi awal; Keuangan memverifikasi; Admin/Superadmin baca-saja |
| TASK-007 | Sedang | `DONE` | Sediakan REST API baca data siswa | Endpoint `/api/v1/students`, ability, filter, pagination, dan scope cabang tersedia |
| TASK-008 | Sedang | `REVIEW` | Sinkronkan seluruh dokumentasi dan diagram baseline 2.3 | Markdown, DOCX, Draw.io, OpenAPI, serta struktur proyek konsisten |
| TASK-009 | Sedang | `READY` | Tentukan kebijakan pelacakan folder `docs/` pada Git | Dokumen penting ikut repository tanpa memasukkan file rahasia atau QA sementara |
| TASK-010 | Rendah | `BACKLOG` | Otomatiskan pemeriksaan dokumentasi pada quality gate | Link, domain, struktur Markdown, OpenAPI, dan artefak diperiksa otomatis |

## Tahapan aktivasi WAHA dengan tunnel DNS

- [ ] Pastikan Docker Desktop dan container WAHA berjalan.
- [ ] Pastikan image `devlikeapro/waha:gows` dan engine `GOWS` digunakan.
- [ ] Buat API key dan HMAC secret yang kuat serta berbeda.
- [ ] Buat Cloudflare Named Tunnel.
- [ ] Hubungkan public hostname `waha.sivmi.id` ke `http://127.0.0.1:3000`.
- [ ] Pasang `cloudflared` sebagai Windows service.
- [ ] Uji `/health` melalui hostname publik.
- [ ] Pastikan `/api/sessions` tanpa API key menghasilkan `401`.
- [ ] Konfigurasikan `.env` server CRM.
- [ ] Aktifkan queue dan scheduler server.
- [ ] Buat session dari menu Koneksi WhatsApp.
- [ ] Pindai QR dan pastikan status `Working`.
- [ ] Klik **Aktifkan chat** untuk memasang webhook HMAC.
- [ ] Uji pesan masuk, keluar, media, ACK, dan pencocokan lead.
- [ ] Catat hasil pengujian dan bukti pada UAT.

## Definition of Done

Sebuah task dapat diberi status `DONE` apabila:

1. Implementasi atau dokumen sudah tersedia pada lokasi yang benar.
2. Tidak ada credential, data siswa, token, atau secret yang masuk repository.
3. Pemeriksaan sintaks, test terkait, dan pemeriksaan akses telah lulus.
4. Perubahan telah diuji sesuai role dan scope cabang.
5. Dokumentasi operasional dan deployment sudah diperbarui.
6. Perubahan sudah direview dan siap di-commit.

## Referensi

- `todo.md`
- `../README.md`
- `../docs/DEPLOYMENT.md`
- `../docs/PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS.md`
- `../docs/PANDUAN-KONFIGURASI-WAHA.md`
- `../docs/PEMBARUAN-VERSI-TERBARU-2026-09-08.md`

