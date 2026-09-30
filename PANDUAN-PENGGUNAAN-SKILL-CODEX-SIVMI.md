# Panduan Penggunaan Skill Codex SIVMI CRM

Dokumen ini menjelaskan cara menggunakan skill Codex khusus project SIVMI CRM. Skill membantu Codex mengikuti aturan teknis dan operasional project secara konsisten, tetapi tidak menggantikan persetujuan pengguna untuk tindakan seperti deployment, perubahan server, commit, push, rotasi secret, atau penghapusan data.

## 1. Lokasi skill

Skill tersimpan di dalam repository:

```text
.codex/
└── skills/
    ├── sivmi-quality-gate/
    │   └── SKILL.md
    ├── sivmi-rbac-security-review/
    │   └── SKILL.md
    ├── sivmi-waha-operations/
    │   └── SKILL.md
    └── sivmi-release-deployment/
        └── SKILL.md
```

Jalankan Codex dari root project agar skill tingkat repository dapat ditemukan dengan konteks yang benar:

```powershell
cd C:\laragon\www\sivmi.crm
```

Jika skill baru belum muncul dalam sesi yang sudah terbuka, mulai sesi Codex baru dari root project.

## 2. Cara memanggil skill

### 2.1 Pemanggilan otomatis

Codex dapat memilih skill secara otomatis ketika permintaan sesuai dengan deskripsinya.

Contoh:

```text
Periksa seluruh quality gate sebelum perubahan ini di-commit.
```

Permintaan tersebut seharusnya mengaktifkan `sivmi-quality-gate`.

### 2.2 Pemanggilan eksplisit

Gunakan awalan `$` diikuti nama skill jika ingin memastikan skill tertentu dipakai:

```text
$sivmi-quality-gate periksa perubahan saat ini sebelum rilis.
```

Beberapa skill dapat disebut bersamaan apabila pekerjaan memang melibatkan beberapa area:

```text
Gunakan $sivmi-rbac-security-review dan $sivmi-quality-gate untuk memeriksa perubahan role Keuangan ini.
```

Pemanggilan eksplisit direkomendasikan untuk pemeriksaan pra-rilis, audit akses, diagnosis WAHA, dan pekerjaan berisiko tinggi.

## 3. Daftar skill dan contoh penggunaan

| Skill | Digunakan untuk | Contoh prompt |
|---|---|---|
| `sivmi-quality-gate` | Pemeriksaan sintaks, format, Composer, build frontend, test, route, Docker, dan kesiapan rilis | `$sivmi-quality-gate jalankan pemeriksaan yang relevan untuk perubahan pada modul Leads.` |
| `sivmi-rbac-security-review` | Pemeriksaan role, policy, scope cabang, URL langsung, bulk action, export, dan API ability | `$sivmi-rbac-security-review audit akses Pendaftaran untuk Superadmin, Admin, Staff, dan Keuangan.` |
| `sivmi-waha-operations` | Konfigurasi dan diagnosis WAHA GOWS, session, tunnel DNS, API key, webhook HMAC, queue, dan pesan | `$sivmi-waha-operations diagnosis mengapa service WAHA tidak terhubung dari server CRM.` |
| `sivmi-release-deployment` | Preflight Git, backup, build, migrasi, cache, worker, smoke test, bukti rilis, dan rollback | `$sivmi-release-deployment buat dan jalankan checklist deployment shared hosting untuk versi terbaru.` |

## 4. Penggunaan `sivmi-quality-gate`

Gunakan setelah implementasi selesai, sebelum commit, sebelum membuat pull request, dan sebelum deployment.

Contoh pemeriksaan perubahan PHP:

```text
$sivmi-quality-gate periksa perubahan PHP dan test terkait. Jangan commit atau push.
```

Contoh pemeriksaan pra-rilis lengkap:

```text
$sivmi-quality-gate jalankan quality gate penuh untuk kandidat rilis. Gunakan test Docker yang terisolasi dan laporkan semua kegagalan.
```

Hasil yang diharapkan:

- daftar area terdampak;
- perintah yang benar-benar dijalankan;
- status sintaks, format, test, dan build;
- jumlah test dan assertion bila tersedia;
- kegagalan atau risiko yang belum diverifikasi;
- keputusan apakah perubahan layak dilanjutkan ke rilis.

Skill tidak boleh menyatakan quality gate lulus apabila masih ada test gagal.

## 5. Penggunaan `sivmi-rbac-security-review`

Gunakan setiap kali menambah role, mengubah menu, resource Filament, policy, controller API, bulk action, export, atau query yang mempunyai scope cabang.

Contoh audit tanpa perubahan kode:

```text
$sivmi-rbac-security-review periksa apakah Log Aktivitas benar-benar hanya dapat diakses Superadmin. Jangan implementasikan perubahan; laporkan temuan saja.
```

Contoh implementasi dan test:

```text
$sivmi-rbac-security-review perbaiki akses Pembayaran agar Admin dan Superadmin hanya dapat melihat, sedangkan Keuangan dapat memverifikasi dalam scope cabangnya. Tambahkan test positif dan negatif.
```

Hasil yang diharapkan:

- matriks role dan aksi yang diperiksa;
- pemeriksaan menu dan akses URL langsung;
- pemeriksaan record lintas cabang;
- pemeriksaan bulk action, export, dan endpoint API;
- test akses sah dan akses yang harus ditolak;
- konfirmasi bahwa respons terlarang tidak membocorkan detail sistem.

## 6. Penggunaan `sivmi-waha-operations`

Gunakan untuk topologi full Docker, WAHA pada VPS, atau CRM di shared hosting dengan WAHA lokal melalui Cloudflare Named Tunnel.

Contoh diagnosis:

```text
$sivmi-waha-operations diagnosis status "Tidak terhubung" pada halaman Koneksi WhatsApp. Mulai dari pemeriksaan lokal dan jangan mengubah server sebelum saya menyetujui.
```

Contoh aktivasi tunnel:

```text
$sivmi-waha-operations pandu saya mengaktifkan WAHA GOWS lokal melalui Named Tunnel waha.sivmi.id sampai health check berhasil.
```

Contoh UAT:

```text
$sivmi-waha-operations buat checklist UAT pesan masuk, pesan keluar, media, ACK, webhook HMAC, queue, dan pencocokan lead.
```

Skill akan membedakan empat lapisan status:

1. service WAHA;
2. session WhatsApp;
3. webhook CRM;
4. sinkronisasi dan queue.

Jangan menempelkan nilai API key, HMAC secret, token tunnel, QR, isi pesan pribadi, atau nomor WhatsApp lengkap ke prompt maupun dokumentasi hasil.

## 7. Penggunaan `sivmi-release-deployment`

Gunakan ketika akan memperbarui shared hosting, VPS, atau deployment Docker produksi.

Contoh membuat rencana tanpa menjalankan perubahan:

```text
$sivmi-release-deployment buat rencana deployment versi terbaru ke shared hosting. Jangan jalankan perintah server.
```

Contoh pendampingan deployment:

```text
$sivmi-release-deployment dampingi deployment ke server produksi. Periksa status Git, backup, migration, cache, queue, scheduler, dan smoke test pada setiap tahap.
```

Contoh pemeriksaan setelah deployment:

```text
$sivmi-release-deployment periksa deployment yang baru selesai dan buat bukti serah terima berdasarkan commit, health check, migration, worker, WAHA, dan smoke test.
```

Skill harus menghentikan proses dan melaporkan blocker apabila:

- backup gagal atau belum tersedia;
- target branch, tag, atau commit tidak jelas;
- quality gate masih merah;
- migrasi berisiko tidak memiliki rencana pemulihan;
- smoke test kritis gagal.

## 8. Contoh alur kerja gabungan

### 8.1 Perubahan role atau hak akses

```text
Gunakan $sivmi-rbac-security-review untuk meninjau perubahan akses terlebih dahulu. Setelah implementasi dan test terfokus selesai, gunakan $sivmi-quality-gate. Jangan commit.
```

Urutan yang diharapkan:

1. petakan role, aksi, dan scope cabang;
2. periksa implementasi UI, policy, query, dan API;
3. tambahkan test positif dan negatif;
4. jalankan quality gate;
5. laporkan kelayakan commit.

### 8.2 Perbaikan integrasi WhatsApp lalu rilis

```text
Gunakan $sivmi-waha-operations untuk memperbaiki integrasi WhatsApp pada environment staging. Setelah UAT lulus, gunakan $sivmi-quality-gate dan siapkan rencana melalui $sivmi-release-deployment.
```

Urutan yang diharapkan:

1. diagnosis service, session, webhook, dan queue;
2. perbaiki hanya komponen yang terbukti bermasalah;
3. lakukan UAT end-to-end;
4. jalankan quality gate;
5. siapkan backup, deployment, smoke test, dan rollback.

## 9. Perbedaan instruksi dan kewenangan

Skill memberi prosedur dan batas keselamatan, tetapi tidak otomatis memberi kewenangan untuk:

- mengubah server produksi;
- mengubah DNS atau Cloudflare;
- merotasi API key atau secret;
- menjalankan migration produksi;
- menghapus file, data, container, atau volume;
- melakukan commit, push, merge, atau membuat release.

Tindakan tersebut hanya dijalankan apabila diminta atau disetujui pengguna sesuai ruang lingkup pekerjaan.

## 10. Memasukkan skill ke Git

Folder `.codex` saat ini diabaikan oleh `.gitignore`. Untuk menambahkan hanya skill yang sudah diperiksa tanpa memasukkan konfigurasi Codex lain, gunakan:

```powershell
git status --short
git add -f .codex/skills
git add PANDUAN-PENGGUNAAN-SKILL-CODEX-SIVMI.md
git diff --cached --stat
git diff --cached -- .codex/skills PANDUAN-PENGGUNAAN-SKILL-CODEX-SIVMI.md
```

Periksa staged diff sebelum commit dan pastikan tidak ada `.env`, credential, token, dump database, data siswa, isi pesan, atau dokumen rahasia.

Contoh commit setelah pengguna menyetujui:

```powershell
git commit -m "docs: tambah skill operasional Codex SIVMI"
git push origin main
```

Jangan menjalankan commit dan push hanya karena skill selesai dibuat; tunggu permintaan pengguna.

## 11. Pemeliharaan skill

Perbarui skill jika terdapat perubahan nyata pada:

- matriks role dan kewenangan;
- arsitektur deployment;
- perintah quality gate;
- topologi WAHA, tunnel, webhook, atau queue;
- standar keamanan dan perlindungan data;
- kegagalan berulang yang menunjukkan instruksi kurang tepat.

Jangan menambah aturan hanya berdasarkan kemungkinan teoritis. Perubahan skill sebaiknya didasarkan pada kebutuhan project, insiden, hasil review, atau kegagalan yang dapat direproduksi.

Setelah mengubah skill, periksa minimal:

- folder dan nilai `name` sama;
- nama menggunakan huruf kecil dan tanda hubung;
- `name` dan `description` tersedia pada YAML frontmatter;
- tidak ada placeholder `[TODO: ...]`;
- tidak ada secret atau data pribadi;
- contoh perintah masih sesuai dengan struktur project terbaru.

## 12. Ringkasan cepat

```text
Quality gate:
$sivmi-quality-gate periksa perubahan ini sebelum commit.

RBAC dan keamanan:
$sivmi-rbac-security-review audit akses dan scope cabang perubahan ini.

WAHA GOWS:
$sivmi-waha-operations diagnosis koneksi WAHA sampai ditemukan penyebabnya.

Deployment:
$sivmi-release-deployment siapkan checklist dan smoke test rilis terbaru.
```
