# To Do - Urutan Perbaikan yang Disarankan

## Prioritas utama

1. [x] Perbaiki relasi lead pada pemrosesan webhook WhatsApp.
2. [ ] Ganti branding statis agar membaca `CrmSetting`.
3. [ ] Buat nomor WhatsApp unik pada fixture `ReportTest`.
4. [ ] Jalankan Laravel Pint dan tinjau hasilnya.
5. [ ] Hidupkan Docker Desktop atau MySQL lokal.
6. [ ] Jalankan ulang migration status, queue, scheduler, dan seluruh test.
7. [ ] Lakukan smoke test CRM, API, WAHA, pembayaran, dan RBAC.
8. [ ] Pastikan produksi memakai `APP_DEBUG=false`.

## Catatan operasional

- Fokus pada perbaikan yang memengaruhi integrasi dan validasi data sebelum menjalankan regresi penuh.
- Setelah perbaikan backend dan konfigurasi selesai, jalankan test secara bertahap dari unit/feature yang paling relevan.
- Pastikan environment lokal/ Docker siap sebelum menjalankan migration dan queue worker.
- Untuk produksi, verifikasi nilai debug dan konfigurasi keamanan sebelum deploy.
