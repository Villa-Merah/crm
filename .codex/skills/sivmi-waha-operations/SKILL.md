---
name: sivmi-waha-operations
description: Diagnosis, konfigurasi, dan verifikasi operasional WhatsApp SIVMI CRM melalui WAHA engine GOWS, termasuk Docker lokal, Cloudflare Named Tunnel, webhook HMAC, queue, session, QR, dan pengujian end-to-end.
---

# Operasional WAHA GOWS SIVMI CRM

Gunakan runbook project sebagai sumber utama:

- `docs/PANDUAN-KONFIGURASI-WAHA.md`
- `docs/PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS.md`
- `docs/PANDUAN-WAHA-SETELAH-DEPLOY-SHARED-HOSTING.md`

Baca hanya runbook yang sesuai dengan topologi pengguna.

## Model koneksi

- CRM menuju WAHA: `WAHA_BASE_URL` dan header `X-Api-Key`.
- WAHA menuju CRM: `WAHA_WEBHOOK_URL` dengan verifikasi HMAC.
- Pemrosesan pesan masuk bergantung pada queue worker.
- Session dan media bergantung pada volume persisten; jangan menghapus volume saat troubleshooting.

## Diagnosis berurutan

1. Identifikasi topologi: full Docker, shared hosting + WAHA lokal, atau VPS.
2. Periksa health WAHA dari mesin WAHA, resolusi DNS/tunnel, lalu health dari server CRM.
3. Bedakan kegagalan DNS, tunnel `502`, API `401`, timeout, session non-`WORKING`, webhook ditolak, dan queue tertunda.
4. Bandingkan keberadaan konfigurasi tanpa mencetak nilai secret.
5. Periksa session, webhook event, failed jobs, queue, scheduler, dan log yang sudah disamarkan.
6. Setelah perbaikan, uji pesan masuk, pesan keluar, media kecil, ACK, dan pencocokan/pembuatan lead.

## Batas keselamatan

- Jangan menampilkan atau commit API key, HMAC secret, token tunnel, QR, isi pesan, atau nomor penuh.
- Jangan memakai Quick Tunnel sebagai konfigurasi produksi.
- Jangan membuka dashboard/Swagger WAHA ke internet bila tidak diperlukan.
- Jangan menjalankan `docker compose down -v` atau menghapus volume session.
- Perubahan pada server/Cloudflare atau rotasi secret memerlukan otorisasi pengguna pada saat tindakan dilakukan.

## Hasil akhir

Nyatakan status untuk empat jalur: service WAHA, session, webhook CRM, dan sinkronisasi/queue. Sertakan langkah verifikasi dan risiko ketersediaan bila WAHA tetap bergantung pada komputer lokal.
