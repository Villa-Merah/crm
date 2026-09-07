from __future__ import annotations

from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
DOC_TOOLS = ROOT / "storage" / "app" / "doc-tools"
sys.path.insert(0, str(DOC_TOOLS))
sys.path.insert(0, str(ROOT / "docs" / "tools"))

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor

import build_bab_iv_arsitektur_bisnis as b


OUTPUT = ROOT / "docs" / "REKOMENDASI-DAN-SIMULASI-SERVER-CRM-VILLA-MERAH-5-20-ADMIN-v1.0.docx"
BLUE = "1476B8"
NAVY = "0B2545"
MUTED = "64748B"


def set_run(run, size, bold=False, color=NAVY, italic=False):
    b.set_run_font(run, size, bold, color)
    run.italic = italic


def page_break(doc):
    doc.add_page_break()


def table(doc, caption, headers, rows, widths):
    b.add_table(doc, headers, rows, widths, caption)


def callout(doc, title, body, fill=b.LIGHT_BLUE):
    b.add_callout(doc, title, body, fill)


def add_static_toc(doc):
    items = [
        "1. Ringkasan dan keputusan sizing",
        "2. Arsitektur serta asumsi simulasi",
        "3. Rekomendasi spesifikasi 5, 10, dan 20 admin",
        "4. Simulasi penggunaan CPU dan RAM",
        "5. Simulasi penyimpanan tiga tahun",
        "6. Simulasi beban dan kriteria penerimaan",
        "7. WAHA/GOWS dan jumlah session WhatsApp",
        "8. Backup, keamanan, dan ketersediaan",
        "9. Monitoring dan indikator upgrade",
        "10. Rencana implementasi serta acuan",
    ]
    b.add_bullets(doc, items)


def configure_document(doc):
    sec = doc.sections[0]
    sec.page_width = Inches(8.5)
    sec.page_height = Inches(11)
    sec.top_margin = sec.bottom_margin = Inches(1)
    sec.left_margin = sec.right_margin = Inches(1)
    sec.header_distance = sec.footer_distance = Inches(0.492)
    b.configure_styles(doc)

    # Resolve standard_business_brief tokens explicitly.
    normal = doc.styles["Normal"]
    normal.font.name = "Calibri"
    normal.font.size = Pt(11)
    normal.paragraph_format.space_before = Pt(0)
    normal.paragraph_format.space_after = Pt(6)
    normal.paragraph_format.line_spacing = 1.10
    for name, size, before, after, color in [
        ("Heading 1", 16, 16, 8, BLUE),
        ("Heading 2", 13, 12, 6, BLUE),
        ("Heading 3", 12, 8, 4, "1F4D78"),
    ]:
        style = doc.styles[name]
        style.font.name = "Calibri"
        style.font.size = Pt(size)
        style.font.bold = True
        style.font.color.rgb = RGBColor.from_string(color)
        style.paragraph_format.space_before = Pt(before)
        style.paragraph_format.space_after = Pt(after)

    header = sec.header.paragraphs[0]
    header.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    header.paragraph_format.space_after = Pt(0)
    set_run(header.add_run("CRM_VILLA MERAH  |  CAPACITY PLANNING  |  5-20 ADMIN"), 8.5, True, MUTED)
    footer = sec.footer.paragraphs[0]
    b.add_page_number(footer)


def add_masthead(doc):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(12)
    p.paragraph_format.space_after = Pt(3)
    set_run(p.add_run("REKOMENDASI TEKNIS"), 10, True, BLUE)

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(5)
    set_run(p.add_run("SPESIFIKASI DAN SIMULASI\nKEBUTUHAN SERVER"), 24, True, NAVY)

    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(18)
    set_run(p.add_run("CRM_VILLA MERAH untuk 5 sampai 20 administrator"), 14, True, BLUE)

    metadata = [
        ("Versi", "1.0"),
        ("Tanggal baseline", "2 September 2026"),
        ("Target", "Produksi skala kecil-menengah dengan 5, 10, atau 20 admin"),
        ("Stack", "Laravel 12, Filament 5, Nginx/PHP-FPM, MySQL 8.4, Redis, queue, scheduler, dan WAHA/GOWS"),
        ("Status", "Sizing awal; wajib divalidasi dengan monitoring dan load test"),
    ]
    for label, value in metadata:
        p = doc.add_paragraph()
        p.paragraph_format.space_after = Pt(2)
        set_run(p.add_run(f"{label}: "), 10.5, True, NAVY)
        set_run(p.add_run(value), 10.5, False, NAVY)

    rule = doc.add_paragraph()
    rule.paragraph_format.space_before = Pt(10)
    rule.paragraph_format.space_after = Pt(14)
    ppr = rule._p.get_or_add_pPr()
    pbdr = OxmlElement("w:pBdr")
    bottom = OxmlElement("w:bottom")
    bottom.set(qn("w:val"), "single")
    bottom.set(qn("w:sz"), "18")
    bottom.set(qn("w:space"), "1")
    bottom.set(qn("w:color"), "1476B8")
    pbdr.append(bottom)
    ppr.append(pbdr)


def build():
    doc = Document()
    configure_document(doc)
    add_masthead(doc)

    callout(
        doc,
        "REKOMENDASI UTAMA",
        "Untuk produksi dengan WAHA/GOWS pada server yang sama, gunakan 4 vCPU/8 GB/120 GB NVMe untuk 5 admin, 6 vCPU/12 GB/160 GB NVMe untuk 10 admin, dan 8 vCPU/16 GB/250 GB NVMe untuk 20 admin. Jika session WhatsApp, sinkronisasi media, atau trafik meningkat, pisahkan WAHA dari server CRM.",
        b.LIGHT_GREEN,
    )
    b.add_heading(doc, "DAFTAR ISI", 1)
    add_static_toc(doc)

    page_break(doc)
    b.add_heading(doc, "1. RINGKASAN DAN KEPUTUSAN SIZING", 1)
    b.add_body(doc, "CRM Villa Merah bukan hanya aplikasi CRUD. Server menangani halaman Filament/Livewire, query dashboard dan laporan, impor-ekspor, database transaksional, cache Redis, queue, scheduler, webhook, serta koneksi WhatsApp melalui WAHA/GOWS. Oleh karena itu sizing ditentukan oleh admin yang aktif bersamaan, jumlah session WhatsApp, volume pesan/media, dan periode retensi; bukan hanya jumlah akun terdaftar.")
    table(doc, "Tabel 1-1. Ringkasan paket produksi all-in-one", ["Skala", "Beban serentak", "Spesifikasi disarankan", "Kesesuaian"], [
        ["5 admin", "2-3 admin; 1-5 session WA", "4 vCPU, 8 GB RAM, 120 GB NVMe", "Pilot produksi dan satu-dua cabang"],
        ["10 admin", "4-6 admin; 1-10 session WA", "6 vCPU, 12 GB RAM, 160 GB NVMe", "Operasional reguler beberapa cabang"],
        ["20 admin", "8-12 admin; 1-20 session WA", "8 vCPU, 16 GB RAM, 250 GB NVMe", "Produksi utama; WAHA siap dipisah"],
    ], [0.9, 1.55, 2.15, 1.9])
    callout(doc, "Batas rekomendasi", "Angka di atas adalah kapasitas perencanaan dengan headroom sekitar 25-35%, bukan hasil benchmark final. Kapasitas aktual sangat dipengaruhi jumlah query dashboard, ekspor, history sync WAHA, file media, kualitas kode, dan performa storage.", b.LIGHT_AMBER)
    b.add_heading(doc, "1.1 Minimum versus produksi", 2)
    table(doc, "Tabel 1-2. Batas minimum dan produksi", ["Skala", "Minimum uji/pilot", "Produksi disarankan"], [
        ["5 admin", "2 vCPU, 4 GB, 80 GB; WAHA terbatas", "4 vCPU, 8 GB, 120 GB"],
        ["10 admin", "4 vCPU, 8 GB, 120 GB", "6 vCPU, 12 GB, 160 GB"],
        ["20 admin", "6 vCPU, 12 GB, 200 GB", "8 vCPU, 16 GB, 250 GB"],
    ], [1.05, 2.65, 2.8])
    b.add_body(doc, "Konfigurasi minimum tidak direkomendasikan bila backup, antivirus/EDR, banyak ekspor, atau lebih dari beberapa session WhatsApp berjalan bersamaan. Provider yang tidak menawarkan RAM 12 GB dapat memakai tier 16 GB untuk skala 10 admin.")

    b.add_heading(doc, "2. ARSITEKTUR DAN ASUMSI SIMULASI", 1)
    b.add_body(doc, "Simulasi mengikuti komponen proyek saat ini: Nginx, PHP-FPM Laravel, MySQL, Redis, queue worker, scheduler, dan WAHA/GOWS. Node/Vite hanya untuk development dan tidak perlu terus berjalan pada server produksi setelah asset dibangun.")
    table(doc, "Tabel 2-1. Asumsi beban", ["Parameter", "Nilai simulasi", "Catatan"], [
        ["Admin serentak", "60% dari jumlah admin", "Skenario puncak konservatif"],
        ["Lead baru", "300/admin/bulan", "1.500; 3.000; 6.000 lead per bulan"],
        ["Pesan WA", "80/admin/hari", "Incoming dan outgoing metadata"],
        ["Media WA", "10 file/admin/hari @ 0,5 MB", "Rata-rata; video besar mengubah hasil"],
        ["Hari operasi", "365 hari untuk pesan; 12 bulan untuk lead", "Agar proyeksi tahunan mudah dibandingkan"],
        ["Retensi lokal", "Database aktif 3 tahun; media 12-24 bulan", "Media lama lebih baik ke object storage"],
        ["Headroom", "25-35%", "Untuk lonjakan, backup, deploy, dan filesystem cache"],
    ], [1.45, 2.15, 2.9])
    b.add_heading(doc, "2.1 Pola deployment", 2)
    b.add_bullets(doc, [
        "5 admin: satu VPS all-in-one masih efisien, selama backup disimpan di lokasi lain.",
        "10 admin: all-in-one tetap memungkinkan; pisahkan WAHA jika ada banyak media atau reconnect/history sync.",
        "20 admin: gunakan VPS kuat atau dua server: CRM+database dan WAHA; pemisahan database menjadi langkah berikutnya bila laporan berat.",
        "MySQL, Redis, dan WAHA API tidak boleh dipublikasikan langsung ke internet; hanya HTTPS aplikasi/webhook yang dibuka.",
    ])

    b.add_heading(doc, "3. REKOMENDASI SPESIFIKASI PER SKALA", 1)
    table(doc, "Tabel 3-1. Alokasi sumber daya all-in-one", ["Komponen", "5 admin", "10 admin", "20 admin"], [
        ["CPU host", "4 vCPU", "6 vCPU", "8 vCPU"],
        ["RAM host", "8 GB", "12 GB", "16 GB"],
        ["NVMe utama", "120 GB", "160 GB", "250 GB"],
        ["PHP-FPM + Nginx", "1,0-1,5 GB", "1,5-2,0 GB", "2,0-3,0 GB"],
        ["MySQL", "1,5-2,0 GB", "2,5-3,5 GB", "3,5-5,0 GB"],
        ["Redis", "0,25-0,5 GB", "0,5-0,75 GB", "0,75-1,0 GB"],
        ["WAHA/GOWS", "1,0 GB budget", "1,0-1,5 GB", "1,5-3,0 GB"],
        ["Queue/scheduler", "1 worker", "1-2 worker", "2-4 worker"],
        ["OS+agent+cache", "1,0-1,5 GB", "1,5-2,0 GB", "2,0-3,0 GB"],
    ], [1.65, 1.55, 1.55, 1.75])
    callout(doc, "Konfigurasi produksi", "Gunakan Linux LTS 64-bit, PHP 8.3+, Nginx, MySQL 8.4, Redis 7.4, process manager/container restart policy, HTTPS, NTP, firewall, dan backup terenkripsi. APP_ENV=production dan APP_DEBUG=false.")
    b.add_heading(doc, "3.1 Opsi dua server", 2)
    table(doc, "Tabel 3-2. Pemisahan yang disarankan", ["Skala", "Server CRM", "Server WAHA/GOWS", "Kapan dipilih"], [
        ["5 admin", "2-4 vCPU / 4-8 GB", "2 vCPU / 4 GB", "WAHA harus independen atau CRM di shared hosting"],
        ["10 admin", "4 vCPU / 8 GB", "2-4 vCPU / 4-8 GB", "5-10 session aktif, media cukup tinggi"],
        ["20 admin", "4-6 vCPU / 8-12 GB", "4 vCPU / 8 GB", "10-20 session, burst webhook/history sync"],
    ], [0.85, 1.65, 1.65, 2.35])

    b.add_heading(doc, "4. SIMULASI CPU DAN RAM", 1)
    table(doc, "Tabel 4-1. Simulasi RAM pada jam puncak", ["Skala", "Beban kerja", "RAM kerja", "Headroom", "RAM host"], [
        ["5 admin", "3 concurrent; dashboard+CRUD+WAHA", "4,5-5,5 GB", "2,5-3,5 GB", "8 GB"],
        ["10 admin", "6 concurrent; import kecil+WAHA", "7,0-8,5 GB", "3,5-5,0 GB", "12 GB"],
        ["20 admin", "12 concurrent; laporan+burst webhook", "10,0-12,0 GB", "4,0-6,0 GB", "16 GB"],
    ], [0.85, 2.25, 1.25, 1.15, 1.0])
    table(doc, "Tabel 4-2. Simulasi kebutuhan CPU", ["Skala", "CRM+PHP", "MySQL", "WAHA+worker", "Cadangan", "Total"], [
        ["5 admin", "1,2", "0,7", "0,6", "1,5", "4 vCPU"],
        ["10 admin", "2,0", "1,2", "1,0", "1,8", "6 vCPU"],
        ["20 admin", "3,0", "1,8", "1,4", "1,8", "8 vCPU"],
    ], [0.9, 1.2, 1.1, 1.35, 1.2, 0.75])
    b.add_body(doc, "CPU jarang dipakai penuh secara merata. Cadangan diperlukan untuk lonjakan dashboard, kompilasi cache saat deployment, kompresi backup, impor CSV, serta sinkronisasi WAHA. Jika CPU lebih dari 70% selama 15 menit berulang kali, optimasi query dan tambah kapasitas sebelum pengguna merasakan perlambatan.")

    b.add_heading(doc, "5. SIMULASI PENYIMPANAN TIGA TAHUN", 1)
    table(doc, "Tabel 5-1. Pertumbuhan data per tahun", ["Skala", "Lead/tahun", "DB+index", "Media", "Log/ekspor", "Total/tahun"], [
        ["5 admin", "18.000", "2-4 GB", "9 GB", "4-6 GB", "15-19 GB"],
        ["10 admin", "36.000", "4-7 GB", "18 GB", "6-10 GB", "28-35 GB"],
        ["20 admin", "72.000", "8-14 GB", "37 GB", "10-18 GB", "55-69 GB"],
    ], [0.8, 1.15, 1.15, 1.05, 1.2, 1.15])
    table(doc, "Tabel 5-2. Proyeksi kapasitas tiga tahun", ["Skala", "Data aplikasi 3 tahun", "OS+runtime", "Ruang bebas minimum", "Rekomendasi"], [
        ["5 admin", "45-57 GB", "20-25 GB", "30 GB", "120 GB NVMe"],
        ["10 admin", "84-105 GB", "20-25 GB", "35 GB", "160 GB NVMe; media arsip eksternal"],
        ["20 admin", "165-207 GB", "20-25 GB", "50 GB", "250 GB NVMe + object storage"],
    ], [0.8, 1.5, 1.1, 1.2, 1.9])
    callout(doc, "Penting", "Backup off-site tidak boleh dihitung sebagai ruang kosong server produksi. Alarm disk: peringatan pada 70%, tindakan pada 80%, dan kritis pada 90%. Media video atau dokumen besar harus diarahkan ke object storage dengan lifecycle policy.", b.LIGHT_AMBER)
    b.add_heading(doc, "5.1 Rumus yang dapat disesuaikan", 2)
    b.add_bullets(doc, [
        "Lead/tahun = jumlah admin x 300 lead x 12 bulan.",
        "Media/tahun = jumlah admin x 10 file x 0,5 MB x 365 hari.",
        "Kapasitas 3 tahun = pertumbuhan tahunan x 3 + OS/runtime + minimum 25% ruang bebas.",
        "Ganti angka 300 lead, 10 file, dan 0,5 MB dengan hasil monitoring nyata setelah 30 hari produksi.",
    ])

    b.add_heading(doc, "6. SIMULASI BEBAN DAN KRITERIA PENERIMAAN", 1)
    table(doc, "Tabel 6-1. Profil load test", ["Skala", "Virtual user", "Durasi", "Webhook burst", "Target"], [
        ["5 admin", "3 normal + 2 spike", "30 menit", "10 event/menit", "p95 <2 detik; error <1%"],
        ["10 admin", "6 normal + 4 spike", "45 menit", "25 event/menit", "p95 <2 detik; queue <30 detik"],
        ["20 admin", "12 normal + 8 spike", "60 menit", "50 event/menit", "p95 <2 detik; error <1%"],
    ], [0.9, 1.45, 1.1, 1.35, 1.7])
    table(doc, "Tabel 6-2. Campuran aktivitas", ["Aktivitas", "Proporsi", "Yang diuji"], [
        ["Baca/cari/filter", "45%", "Leads, sekolah, follow-up, pagination"],
        ["Dashboard/laporan", "20%", "Agregasi, filter cabang/periode, peta"],
        ["Tambah/ubah data", "20%", "Validasi, nomor WA unik, audit, transaksi"],
        ["WhatsApp", "10%", "Kirim, webhook, persistensi, queue"],
        ["Import/export", "5%", "Memory, job background, keamanan CSV"],
    ], [1.65, 1.05, 3.8])
    b.add_bullets(doc, [
        "Kriteria minimum: HTTP 5xx di bawah 1%, tidak ada data hilang/duplikat, dan halaman utama p95 di bawah 2 detik.",
        "CPU harus kembali di bawah 50% setelah spike; RAM tidak boleh swap terus-menerus; queue backlog harus habis dalam 5 menit.",
        "Jalankan uji pada MySQL produksi/staging, bukan hanya SQLite test, karena dashboard dan GROUP BY sensitif terhadap perbedaan engine.",
    ])

    b.add_heading(doc, "7. WAHA/GOWS DAN SESSION WHATSAPP", 1)
    b.add_body(doc, "Satu session WAHA mewakili satu akun/nomor WhatsApp, sehingga jumlah session tidak selalu sama dengan jumlah admin. Dokumentasi resmi WAHA menyebut minimum host 2 CPU dan 4 GB RAM bahkan untuk satu session. Benchmark resminya mencatat GOWS sekitar 0,1 CPU/200 MB untuk 1 session, 0,5 CPU/1 GB untuk 10 session, dan 1,5 CPU/3 GB untuk 50 session. Angka host rekomendasi tetap lebih tinggi untuk OS, API, media, reconnect, dan history sync.")
    table(doc, "Tabel 7-1. Kapasitas WAHA untuk organisasi 5-20 admin", ["Session aktif", "Benchmark GOWS", "Host yang disarankan", "Penempatan"], [
        ["1-5", "Di antara titik 1 dan 10 session", "2 vCPU / 4 GB", "Boleh satu host dengan CRM 8 GB"],
        ["6-10", "Mendekati 0,5 CPU / 1 GB", "2-4 vCPU / 4-8 GB", "Pisahkan jika media/history sync tinggi"],
        ["11-20", "Interpolasi, bukan angka resmi", "4 vCPU / 8 GB", "Disarankan terpisah untuk isolasi"],
    ], [1.05, 1.75, 1.65, 2.05])
    callout(doc, "Keputusan session", "Jika 20 admin memakai hanya 3 nomor operasional, sizing WAHA mengikuti 3 session, bukan 20 admin. Jika setiap admin mempunyai nomor sendiri, rencanakan 20 session dan pisahkan WAHA agar gangguan sinkronisasi tidak menghabiskan resource CRM.")
    b.add_heading(doc, "7.1 Pengaturan operasional", 2)
    b.add_bullets(doc, [
        "Gunakan image GOWS yang dipin versinya dan volume session persisten.",
        "Batasi history sync dan penyimpanan lokal WAHA sesuai kebutuhan fitur serta retensi.",
        "Queue worker harus aktif permanen dan direstart secara graceful setelah deployment.",
        "Pantau status session, reconnect, webhook gagal, failed_jobs, disk media, dan latency WAHA API.",
    ])

    b.add_heading(doc, "8. BACKUP, KEAMANAN, DAN KETERSEDIAAN", 1)
    table(doc, "Tabel 8-1. Target layanan", ["Skala", "RPO", "RTO", "Backup"], [
        ["5 admin", "24 jam", "8 jam", "DB harian; file harian/mingguan; off-site"],
        ["10 admin", "8 jam", "4 jam", "DB 3x sehari; file harian; uji restore triwulan"],
        ["20 admin", "4 jam", "2-4 jam", "DB 6x sehari/PITR; object versioning; restore berkala"],
    ], [1.0, 1.0, 1.1, 3.4])
    b.add_bullets(doc, [
        "Simpan backup terenkripsi di akun/lokasi berbeda; pertahankan minimal 7 harian, 4 mingguan, dan 6 bulanan.",
        "Buka hanya port 80/443; MySQL, Redis, dan WAHA API berada di jaringan privat atau dilindungi tunnel/access policy.",
        "Gunakan akun individual, least privilege, APP_DEBUG=false, HTTPS, secret di luar Git, dan rotasi API key/webhook secret.",
        "Pasang UPS bila on-premise dan gunakan provider dengan snapshot serta SLA yang sesuai bila memakai VPS/cloud.",
    ])

    b.add_heading(doc, "9. MONITORING DAN INDIKATOR UPGRADE", 1)
    table(doc, "Tabel 9-1. Ambang operasional", ["Metrik", "Peringatan", "Tindakan upgrade/optimasi"], [
        ["CPU", ">70% selama 15 menit", "Profil query/job; tambah vCPU atau pisahkan service"],
        ["RAM", ">80% atau swap aktif", "Tambah RAM; batasi worker; tuning MySQL"],
        ["Disk", ">70%", "Arsip media/log; tambah volume sebelum 80%"],
        ["HTTP p95", ">1,5 detik", "Optimasi indeks/cache; target tetap <2 detik"],
        ["Queue", ">30 detik", "Tambah worker; pisahkan queue prioritas"],
        ["MySQL", "Slow query/lock meningkat", "EXPLAIN, indeks, buffer pool, atau server DB terpisah"],
        ["WAHA", "Reconnect/history sync berulang", "Batasi sync, cek jaringan, pindah ke host terpisah"],
        ["Backup", "Gagal satu jadwal", "Perbaiki segera; eskalasi bila gagal dua kali"],
    ], [1.25, 2.0, 3.25])
    callout(doc, "Trigger pemisahan server", "Pisahkan WAHA bila konsumsi RAM-nya melewati 25% host, session lebih dari 10 dengan media tinggi, atau reconnect memengaruhi CRM. Pisahkan database bila query laporan membuat CPU MySQL tinggi, p95 tetap lambat setelah optimasi, atau kebutuhan RPO/RTO meningkat.", b.LIGHT_AMBER)

    b.add_heading(doc, "10. RENCANA IMPLEMENTASI", 1)
    table(doc, "Tabel 10-1. Tahap penerapan", ["Tahap", "Tindakan", "Bukti selesai"], [
        ["1. Pilih tier", "Pilih 5/10/20 admin dan tentukan jumlah session WA", "Keputusan kapasitas dan anggaran"],
        ["2. Provision", "Siapkan Linux, NVMe, firewall, TLS, backup, monitoring", "Server baseline dan hardening"],
        ["3. Deploy", "Jalankan Nginx, PHP-FPM, MySQL, Redis, queue, scheduler, WAHA", "Health check seluruh service"],
        ["4. Migrasi", "Backup, migrate --force, optimize, restart worker", "Log deployment dan rollback"],
        ["5. Simulasi", "Jalankan load test sesuai Tabel 6-1 dan webhook burst", "Laporan p95/error/resource"],
        ["6. Observasi", "Kumpulkan metrik 30 hari pertama", "Puncak CPU/RAM/disk/queue/WAHA"],
        ["7. Koreksi", "Ubah tier berdasarkan data nyata, bukan hanya jumlah akun", "Capacity review bulanan"],
    ], [1.2, 3.35, 1.95])
    b.add_heading(doc, "10.1 Keputusan akhir", 2)
    callout(doc, "PILIHAN PALING SEIMBANG", "Jika jumlah admin saat ini belum pasti antara 5 dan 20, mulai dengan 6 vCPU, 12-16 GB RAM, dan 160 GB NVMe. Konfigurasi ini cukup lentur untuk 10 admin dan dapat melayani menuju 20 admin selama session WAHA serta pertumbuhan media dipantau. Pada 20 admin aktif, naikkan ke 8 vCPU/16 GB/250 GB atau pisahkan WAHA.", b.LIGHT_GREEN)

    b.add_heading(doc, "10.2 Acuan", 2)
    table(doc, "Tabel 10-2. Dasar rekomendasi", ["Sumber", "Pemakaian"], [
        ["WAHA FAQ - https://waha.devlike.pro/docs/overview/faq/", "Minimum host dan benchmark CPU/RAM GOWS per jumlah session"],
        ["WAHA GOWS - https://waha.devlike.pro/docs/engines/gows/", "Engine tanpa Chromium dan pengaturan history sync"],
        ["Laravel 12 Queues - https://laravel.com/framework/docs/12.x/queues", "Worker permanen, process monitor, timeout, dan restart deployment"],
        ["MySQL 8.4 InnoDB Buffer Pool - https://dev.mysql.com/doc/refman/8.4/en/innodb-performance-buffer-pool.html", "Dasar alokasi cache data/index dan risiko kekurangan RAM"],
        ["Docker resource constraints - https://docs.docker.com/engine/containers/resource_constraints/", "Batas CPU/RAM container dan risiko OOM"],
        ["Repository CRM Villa Merah: README.md dan docker/compose.yml", "Komponen runtime aktual proyek"],
    ], [3.35, 3.15])
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc.save(OUTPUT)
    print(OUTPUT)


if __name__ == "__main__":
    build()
