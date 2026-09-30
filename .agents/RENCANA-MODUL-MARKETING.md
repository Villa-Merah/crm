# Rencana Portal Modul SIVMI Marketing

**Tanggal:** 22 September 2026

**Status:** perencanaan; belum ada implementasi atau branch fitur.

## Tujuan dan ruang lingkup

Setelah login, pengguna melihat halaman pemilih modul seperti referensi: kartu responsif dengan ikon, nama, dan deskripsi. Hanya modul yang diizinkan bagi role pengguna yang muncul. Akses URL langsung tetap diperiksa pada server; menyembunyikan kartu saja tidak cukup.

Lima modul dalam lingkup:

1. **Leads:** CRM yang sudah ada; hanya diberi pintu masuk dari portal dan disesuaikan seperlunya.
2. **Konten:** perencanaan ide, brief, naskah, revisi, persetujuan, dan status materi pemasaran.
3. **DAM (Digital Asset Management):** pustaka file digital, metadata, versi, lisensi, dan pengelolaan akses aset.
4. **Medsos:** kalender, draft, persetujuan, dan pencatatan publikasi media sosial.
5. **Ads (Iklan):** perencanaan kampanye, anggaran, materi, dan pencatatan hasil.

Kartu lain pada gambar (Dashboard, Produk, Data Siswa, Akademik, Keuangan, Event, Buzzer Marketing) adalah referensi tampilan dan tidak otomatis masuk lingkup. Nama merek portal mengikuti pengaturan aplikasi, bukan teks statis gambar.

## Kondisi proyek

- Aplikasi memakai satu panel Filament di `/admin`; `AdminPanelProvider::homeUrl()` saat ini menuju Dashboard.
- Role tersedia: `superadmin`, `admin_cabang`, `staff`, `keuangan`, `direksi`. Belum ada role khusus marketing.
- Leads dan Follow-up sudah ada. Konten, DAM, Medsos, dan Ads belum mempunyai tabel/model/halaman/izin.
- Hak akses CRM memakai role dan scope cabang; aturan Keuangan untuk transaksi tidak boleh berubah tanpa keputusan eksplisit.
- Worktree `main` lokal berisi perubahan lain. Jangan membuat atau berpindah branch fitur sebelum perubahan tersebut dipilah dan diamankan.

## Keputusan sebelum implementasi

| Topik | Usulan awal | Perlu persetujuan |
|---|---|---|
| Role marketing | Role `marketing` terpisah dari `staff` | Ya; jangan otomatis memberi akses data siswa/WhatsApp/pembayaran |
| Admin Cabang | Kelola modul sesuai cabang; approval bila ditunjuk | Ya |
| Direksi | Baca ringkasan dan laporan lintas cabang | Ya |
| Keuangan | Tidak mengakses Leads/Konten/DAM/Medsos; anggaran Ads hanya bila diperlukan | Ya |
| Konten | Alur editorial/approval terpisah dari penyimpanan file DAM | Ya |
| Medsos MVP | Publikasi manual dengan URL/bukti; API platform fase berikutnya | Ya |
| Ads MVP | Pencatatan internal, tanpa belanja iklan otomatis | Ya |
| DAM | Private storage dengan unduh terotorisasi | Ya |

Ini **usulan**, bukan kebijakan yang sudah diterapkan. Finalisasi role, approver, cabang, retensi file, dan kepemilikan akun sebelum membuat migration.

## Arsitektur dan MVP

```text
Login Filament
  -> Portal pemilih modul (role + scope cabang)
      -> Leads existing
      -> Konten
      -> DAM
      -> Medsos
      -> Ads
```

Portal adalah halaman terautentikasi dalam panel yang sama, bukan aplikasi terpisah. Katalog modul terpusat menyimpan label, ikon, rute, urutan, dan aturan akses. Deep link yang sah tetap dapat dipakai. Semua operasi tulis wajib diperiksa lagi lewat policy dan query scope. Audit mutasi penting tetap tersedia hanya untuk Superadmin.

| Modul | MVP | Data/relasi utama | Batas keamanan |
|---|---|---|---|
| Portal | Kartu sesuai role, redirect pasca-login, kembali ke portal | Katalog modul di kode; belum perlu tabel | URL terlarang ditolak HTTP 403 |
| Leads | Tautkan ke list/form existing; RBAC dan filter tetap | `leads`, `followup_riwayat`, `sumber_leads` | Jangan duplikasi tabel/alur WAHA |
| Konten | Ide/brief, naskah, revisi, status editorial, approval | Konten, versi/revisi, penulis, approver; referensi aset DAM opsional | Izin edit/persetujuan terpisah; tidak menyimpan file langsung |
| DAM | Upload, metadata/tag, versi, pencarian, review, unduh | Aset, versi, tag, pemilik, cabang | Validasi MIME/ukuran; private storage, kuota, backup |
| Medsos | Akun kanal, kalender, draft, approval, status, URL hasil | Post, akun, konten disetujui, aset DAM | Token platform tidak plaintext; tanpa auto-publish di MVP |
| Ads | Kampanye, channel, jadwal, anggaran, approval, hasil manual | Kampanye, hasil, konten/creative DAM, opsional Leads | Tanpa spend otomatis; anggaran dibatasi izin |

Nama tabel perlu dirancang dan ditinjau sebelum migration. Konten adalah objek editorial; DAM menyimpan file/aset yang dapat ditautkan ke banyak konten, post, atau kampanye melalui relasi pivot. Definisikan UTM, sumber lead, jendela atribusi, dan deduplikasi sebelum membuat laporan gabungan.

## Urutan branch per modul

`main` adalah branch rilis stabil. Setiap branch dibuat dari `main` yang sinkron dan worktree bersih; satu pull request per unit kerja. Jangan bercabang dari fitur lain sebelum dependensinya tergabung ke `main`.

| Tahap | Branch | Isi | Prasyarat |
|---|---|---|---|
| 0 | `docs/marketing-module-plan` | Rencana dan keputusan RBAC | Tidak ada |
| 1 | `feature/module-portal` | Landing pasca-login, katalog, redirect, tes akses | Keputusan RBAC |
| 2 | `feature/module-leads-entry` | Kartu Leads dan regresi fitur existing | Portal di `main` |
| 3 | `feature/module-dam` | Skema, storage aman, CRUD aset, tes | Portal di `main` |
| 4 | `feature/module-konten` | Ide, brief, naskah, revisi, approval; tautkan aset DAM | DAM di `main` |
| 5 | `feature/module-medsos` | Kalender, draft, approval, publikasi manual | Konten dan DAM di `main` |
| 6 | `feature/module-ads` | Kampanye, anggaran, approval, metrik manual | Konten dan DAM di `main` |
| 7 | `feature/marketing-reports` | KPI/atribusi lintas modul | Leads, Medsos, Ads di `main` |

Integrasi Meta/Instagram/TikTok/Google Ads masing-masing membutuhkan proposal dan branch sendiri setelah MVP internal stabil.

### Langkah Git pada setiap fitur

1. Pilah dan amankan pekerjaan lokal yang sekarang; jangan `git reset --hard`, `git clean`, atau commit `.env`.
2. Saat worktree bersih: `git status`, `git fetch origin`, `git pull --ff-only origin main`.
3. Buat branch sesuai tabel, misalnya `git switch -c feature/module-portal`.
4. Kerjakan berkas yang berkaitan saja; buat migration baru, jangan ubah migration produksi yang sudah berjalan.
5. Jalankan format, tes terkait, full test suite, build aset, dan tes negatif role/lintas cabang.
6. Stage berkas terpilih dan tinjau `git diff --cached`; pastikan secret/data siswa/perubahan lain tidak ikut.
7. Commit terarah, `git push -u origin <branch>`, lalu ajukan pull request ke `main`.
8. Review dan uji staging; merge setelah quality gate lulus. Deploy `main` dengan backup, migration, dan smoke test.

## Kriteria penerimaan lintas modul

- Login menuju portal; logout, sesi kedaluwarsa, dan deep link sah tetap bekerja.
- Kartu dan rute sesuai role; URL terlarang menampilkan akses dibatasi/HTTP 403.
- Data dan mutasi terikat cabang; hak global Superadmin/Direksi mengikuti keputusan RBAC.
- Keuangan tidak memperoleh hak mutasi Leads/marketing hanya karena portal baru.
- Mutasi penting tercatat; file, token, dan PII tidak bocor lewat URL atau log.
- UI desktop/seluler dan keadaan kosong/loading/gagal/ditolak jelas.
- Migration aman untuk data existing, rollback/backup tersedia.
- Dokumentasi, matriks RBAC, diagram, UAT, dan deployment diperbarui pada branch terkait.

## Risiko utama

| Risiko | Mitigasi |
|---|---|
| Hak role marketing belum jelas | Bekukan matriks RBAC sebelum coding |
| File DAM membengkak atau lisensi tidak jelas | Kuota, retensi, backup, pemilik/sumber/masa berlaku aset |
| Integrasi vendor berubah atau token bocor | MVP manual dulu; integrasi terpisah dengan OAuth, rotasi token, rate-limit |
| Hasil Ads tidak cocok dengan Leads | Standarkan UTM, sumber, atribusi, deduplikasi |
| Worktree lokal kotor | Jangan switch/create branch implementasi sampai perubahan existing aman |
