# Todo dan Issue SIVMI CRM

Dokumen ini menampung pekerjaan operasional jangka pendek, kendala, bug, risiko, dan hasil verifikasi. Daftar pekerjaan tingkat proyek berada pada `tasklist.md`.

**Pembaruan terakhir:** 12 Oktober 2026

## Rencana portal dan modul marketing

Rincian scope, data, branch, risiko, dan acceptance criteria ada di `RENCANA-MODUL-MARKETING.md`. Semua item berikut belum diimplementasikan.

- [ ] Setujui lima modul terpisah: Leads, Konten, DAM, Medsos, Ads; kartu lain pada gambar hanya referensi UI.
- [ ] Putuskan role `marketing`, approver konten/anggaran, serta hak tiap role dan cabang.
- [ ] Pilah dan amankan perubahan lokal sebelum membuat branch; jangan sertakan `.env`.
- [ ] `feature/module-portal`: landing pasca-login, kartu per role, redirect, navigasi balik, tes URL langsung.
- [ ] `feature/module-leads-entry`: kartu Leads ke modul existing; regresi Leads/RBAC/WAHA.
- [ ] `feature/module-dam`: skema dan private storage; upload, metadata, versi, pencarian, review, unduh aman.
- [ ] `feature/module-konten`: ide, brief, naskah, revisi, approval editorial, tautan ke aset DAM; tanpa penyimpanan file langsung.
- [ ] `feature/module-medsos`: akun kanal, kalender, draft, approval, publikasi manual, relasi Konten dan DAM.
- [ ] `feature/module-ads`: kampanye, anggaran, approval, materi Konten/kreatif DAM, hasil manual, tes izin.
- [ ] `feature/marketing-reports`: sepakati UTM dan atribusi sebelum laporan lintas modul.
- [ ] Tiap branch: tes, format/build, review diff, PR, staging/UAT, dokumentasi, deploy, smoke test.

## Todo aktif

### Laporan seluruh bulan dan tahun berjalan

Tujuan: menambahkan mode laporan tahunan melalui pilihan **Semua bulan** sehingga data Januari–Desember pada tahun terpilih dapat dianalisis tanpa mengubah ketepatan laporan bulanan yang sudah ada.

- [x] Pertahankan bulan berjalan sebagai filter awal dan sediakan opsi **Semua bulan** agar kebiasaan pengguna tidak berubah.
- [x] Ubah properti `Laporan::$bulan` menjadi nullable dan pastikan query string kosong (`bulan=`) diperlakukan sebagai mode tahunan.
- [x] Tambahkan pilihan **Semua bulan** pada filter Bulan di `resources/views/filament/pages/laporan.blade.php`.
- [x] Refactor `periodRange()` agar mode bulanan memakai awal–akhir bulan, sedangkan mode tahunan memakai 1 Januari sampai 1 Januari tahun berikutnya.
- [x] Ubah label periode menjadi nama bulan dan tahun untuk mode bulanan, serta `Tahun YYYY` untuk mode tahunan.
- [x] Pertahankan agregasi kartu ringkasan: total leads, pendaftaran, konversi, omzet terverifikasi, dan rata-rata transaksi pada seluruh periode terpilih.
- [x] Ubah grafik tren menjadi harian saat satu bulan dipilih dan bulanan Januari–Desember saat **Semua bulan** dipilih.
- [x] Jumlahkan target omzet dan target siswa dari seluruh target berstatus `Aktif` atau `Selesai` pada tahun terpilih; tetap hormati filter cabang dan pengguna.
- [x] Pastikan tabel kinerja cabang, funnel leads, metode pembayaran, dan performa program menggunakan rentang tahunan yang sama.
- [x] Sesuaikan ekspor CSV: nama bulanan `laporan-sivmi-YYYY-MM.csv`, nama tahunan `laporan-sivmi-YYYY-tahunan.csv`, serta label periode yang konsisten di isi file.
- [x] Pastikan Superadmin/Direksi dapat melihat lintas cabang, Admin Cabang hanya cabangnya, dan Staff hanya data serta alokasi miliknya sesuai kebijakan laporan saat ini.
- [x] Tambahkan state kosong dan penanganan error yang tetap benar pada mode tahunan.
- [x] Tambahkan pengujian regresi laporan bulanan agar hasil lama tidak berubah.
- [x] Tambahkan pengujian tahunan untuk data Januari–Desember, pengecualian data tahun lain, tren 12 bulan, penjumlahan target, scope cabang/pengguna, dan CSV tahunan.
- [x] Jalankan `vendor\\bin\\pint --test`, test `ReportTest`, test RBAC terkait, lalu `docker\\test-full.ps1` sebelum commit.

**Selesai 12 Oktober 2026:** implementasi tersedia pada halaman Laporan. Verifikasi terakhir: test terfokus laporan 5 test/33 assertion, test laporan dan pengaturan 12 test/76 assertion, serta full suite 124 test/786 assertion seluruhnya lulus.

Kriteria penerimaan:

1. Filter Bulan menyediakan opsi **Semua bulan**.
2. Mode tahunan hanya menghitung data pada tahun yang dipilih dan tidak memasukkan tahun lain.
3. Grafik menampilkan tepat 12 titik/batang dari Januari sampai Desember.
4. Target tahunan merupakan jumlah target bulanan berstatus `Aktif` dan `Selesai` sesuai scope pengguna.
5. Tampilan, CSV, RBAC, dan hasil perhitungan menggunakan periode yang sama.
6. Seluruh test terkait laporan dan full quality gate lulus.

- [ ] Terapkan Cloudflare Named Tunnel untuk `waha.sivmi.id` pada komputer WAHA.
- [ ] Pastikan service `cloudflared` otomatis berjalan setelah Windows restart.
- [ ] Samakan `WAHA_API_KEY` pada container lokal dan server CRM.
- [ ] Samakan `WAHA_WEBHOOK_HMAC_SECRET` pada konfigurasi yang berkaitan.
- [ ] Pastikan server memakai `WAHA_BASE_URL=https://waha.sivmi.id`.
- [ ] Pastikan webhook memakai `https://crm.sivmi.id/api/webhooks/waha`.
- [ ] Jalankan queue worker dan scheduler pada server.
- [ ] Lakukan UAT pesan masuk, pesan keluar, media, ACK, dan pembuatan/pencocokan lead.
- [ ] Periksa `failed_jobs`, event webhook gagal, dan log Laravel setelah UAT.
- [ ] Tentukan apakah dokumen penting pada folder `docs/` akan dilacak oleh Git.
- [ ] Jalankan full test suite dan selesaikan seluruh kegagalan sebelum rilis.

## Issue aktif

### ISSUE-001 — Folder `docs/` diabaikan oleh Git

| Atribut | Nilai |
|---|---|
| Status | `OPEN` |
| Prioritas | Tinggi |
| Dampak | Panduan, DOCX, dan Draw.io baru tidak otomatis masuk commit |
| Penyebab | Aturan `/docs/` pada `.gitignore` |
| Tindakan | Tentukan allowlist dokumen atau gunakan `git add -f` hanya untuk artefak yang disetujui |
| Verifikasi | `git ls-files` menampilkan setiap dokumen yang wajib ikut repository |

### ISSUE-003 — Ketersediaan WAHA bergantung pada komputer lokal

| Atribut | Nilai |
|---|---|
| Status | `OPEN` |
| Prioritas | Tinggi |
| Dampak | Integrasi WhatsApp berhenti saat komputer, Docker, WAHA, internet, atau tunnel tidak aktif |
| Mitigasi | Auto-start Docker, restart policy container, Windows service `cloudflared`, UPS, dan monitoring health |
| Rekomendasi jangka lanjut | Pindahkan WAHA ke VPS yang selalu aktif bila penggunaan sudah kritis |
| Verifikasi | Simulasi restart dan pastikan session kembali `Working` tanpa kehilangan volume |

### ISSUE-004 — Endpoint WAHA dipublikasikan melalui internet

| Atribut | Nilai |
|---|---|
| Status | `OPEN` |
| Prioritas | Tinggi |
| Risiko | Percobaan akses API, kebocoran session, atau penyalahgunaan apabila API key lemah |
| Mitigasi | API key panjang, dashboard/Swagger nonaktif, HMAC webhook, TLS tunnel, rotasi secret, dan monitoring |
| Verifikasi | `/api/sessions` tanpa key menghasilkan `401` dan dashboard tidak tersedia dari internet |

### ISSUE-005 — Queue shared hosting tidak permanen

| Atribut | Nilai |
|---|---|
| Status | `OPEN` |
| Prioritas | Sedang |
| Dampak | Webhook tercatat tetapi Inbox dan Leads terlambat diperbarui |
| Mitigasi sementara | Cron `queue:work --stop-when-empty` dengan `flock` setiap menit |
| Rekomendasi | Gunakan Supervisor/systemd atau worker Docker pada VPS |
| Verifikasi | Event webhook berpindah dari `Pending` dan tidak ada failed job yang tertinggal |

## Issue selesai

### ISSUE-002 — Full test suite belum seluruhnya hijau

| Atribut | Nilai |
|---|---|
| Status | `CLOSED` |
| Prioritas | Tinggi |
| Dampak awal | Quality gate produksi belum dapat dinyatakan lulus penuh |
| Kondisi awal | 115 dari 117 test lulus pada snapshot baseline dokumentasi |
| Area | Fixture lama Settings dan Report |
| Tindakan | Memperbarui implementasi dan fixture terkait, lalu menjalankan suite pada Docker dengan SQLite in-memory |
| Verifikasi | 124 test dan 786 assertion lulus melalui `docker\\test-full.ps1` |
| Ditutup | 12 Oktober 2026 |

## Format issue baru

Salin format berikut:

```markdown
### ISSUE-XXX — Judul singkat

| Atribut | Nilai |
|---|---|
| Status | `OPEN` |
| Prioritas | Tinggi/Sedang/Rendah |
| Ditemukan | YYYY-MM-DD |
| Area | Modul atau komponen |
| Gejala | Uraian yang dapat diamati |
| Dampak | Dampak bisnis atau teknis |
| Cara reproduksi | Langkah minimum untuk mengulang masalah |
| Penyebab | Isi setelah diagnosis |
| Tindakan | Perbaikan atau mitigasi |
| Verifikasi | Test atau bukti penerimaan |
| Ditutup | Isi setelah selesai |
```

## Aturan pengelolaan

1. Jangan menuliskan password, API key, HMAC secret, token tunnel, data siswa, atau isi pesan WhatsApp.
2. Gunakan ID issue yang tidak digunakan ulang.
3. Satu issue harus menjelaskan satu masalah utama.
4. Sertakan gejala dan bukti tanpa menyalin data sensitif dari log.
5. Hubungkan issue dengan task pada `tasklist.md` jika memerlukan pekerjaan terencana.
6. Ubah status menjadi `CLOSED` hanya setelah verifikasi berhasil.
