from __future__ import annotations

from pathlib import Path
import sys

PROJECT_ROOT = Path(__file__).resolve().parents[2]
DOC_TOOLS = PROJECT_ROOT / "storage" / "app" / "doc-tools"
sys.path.insert(0, str(DOC_TOOLS))

from PIL import Image, ImageDraw
from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches, Pt

import build_bab_iv_arsitektur_bisnis as b
import build_bab_vii_arsitektur_teknologi as v7


OUTPUT = PROJECT_ROOT / "docs" / "BAB X - Panduan Penggunaan SIVMI CRM v1.0.docx"
ASSETS = PROJECT_ROOT / "docs" / "_bab_x_assets"
COVER = ASSETS / "cover-bab-x.png"

PRIMARY = "#1476B8"
PRIMARY_DARK = "#075985"
INK = "#0B2545"
MUTED = "#64748B"
BG = "#F6F8FC"
GREEN = "#10B981"
AMBER = "#F59E0B"
ROSE = "#F43F5E"
PURPLE = "#A855F7"


def font(name: str, size: int):
    return v7.font(name, size)


def canvas(title: str, subtitle: str, size=(1800, 1080)):
    return v7.canvas(title, subtitle, size)


def box(draw, xy, title, lines, fill="#F8FAFC", outline=PRIMARY, title_size=27, body_size=20):
    return v7.box(draw, xy, title, lines, fill, outline, title_size, body_size)


def arrow(draw, start, end, color="#64748B", width=5):
    return v7.arrow(draw, start, end, color, width)


def make_cover() -> None:
    ASSETS.mkdir(parents=True, exist_ok=True)
    image = Image.new("RGB", (1600, 2200), BG)
    draw = ImageDraw.Draw(image)
    for y in range(2200):
        ratio = y / 2200
        start = (20, 118, 184)
        end = (7, 89, 133)
        draw.line((0, y, 1600, y), fill=tuple(round(start[i] * (1 - ratio) + end[i] * ratio) for i in range(3)))
    draw.ellipse((1010, -180, 1770, 580), fill=(27, 139, 199))
    draw.ellipse((1140, 140, 1640, 640), fill=(8, 103, 151))
    draw.rounded_rectangle((120, 250, 1480, 1910), radius=44, fill="white")
    draw.text((190, 350), "SIVMI CRM", font=font("arialbd.ttf", 34), fill=PRIMARY)
    draw.text((190, 610), "BAB X", font=font("arialbd.ttf", 48), fill=PRIMARY_DARK)
    draw.multiline_text((190, 700), "PANDUAN\nPENGGUNAAN", font=font("arialbd.ttf", 88), fill=INK, spacing=18)
    draw.text((190, 1045), "Panduan kerja harian untuk Dashboard, Leads,", font=font("arial.ttf", 31), fill="#475569")
    draw.text((190, 1097), "WhatsApp, transaksi, laporan, dan master data", font=font("arial.ttf", 31), fill="#475569")
    draw.rounded_rectangle((190, 1245, 805, 1325), radius=40, fill="#EAF4FB")
    draw.text((230, 1267), "BASELINE APLIKASI 25 AGUSTUS 2026", font=font("arial.ttf", 28), fill=PRIMARY_DARK)
    draw.text((190, 1610), "Versi dokumen 1.0", font=font("arial.ttf", 28), fill=MUTED)
    draw.text((190, 1665), "Sasaran: seluruh pengguna sesuai role dan cabang", font=font("arial.ttf", 28), fill=MUTED)
    draw.text((190, 1775), "SIVMI CRM - Catat, Kelola, Closing", font=font("arialbd.ttf", 34), fill=PRIMARY)
    image.save(COVER)


def make_navigation() -> None:
    image, draw = canvas("Peta Navigasi Pengguna", "Menu yang terlihat mengikuti role, cabang, dan kewenangan operasional", (1800, 1120))
    box(draw, (690, 100, 1110, 300), "SIVMI CRM", "Sidebar collapsible\npencarian, notifikasi, profil", "#EAF4FB", PRIMARY_DARK, 31, 21)
    groups = [
        (70, 500, 530, 930, "SIVMI CRM", "Dashboard\nLeads\nFollow-up\nInbox WhatsApp\nTarget Kinerja\nLaporan", PRIMARY),
        (670, 500, 1130, 930, "MASTER DATA", "Cabang\nSekolah\nPengguna\nPendaftaran\nPembayaran\nProgram Bimbel", PURPLE),
        (1270, 500, 1730, 930, "KONTROL", "Pengaturan\nAudit Aktivitas\nKoneksi WhatsApp\nNotifikasi\nProfil & logout", GREEN),
    ]
    for x1, y1, x2, y2, title, lines, color in groups:
        box(draw, (x1, y1, x2, y2), title, lines, "#F8FAFC", color, 29, 21)
        arrow(draw, (900, 300), ((x1 + x2) // 2, y1), color="#94A3B8", width=4)
    image.save(ASSETS / "peta-navigasi.png")


def make_login() -> None:
    image, draw = canvas("Alur Login dan Session", "Akses diberikan hanya kepada pengguna aktif dengan role yang sah", (1800, 1050))
    stages = [
        (80, 270, 380, 620, "Buka", "/admin/login\nHTTPS/localhost", PRIMARY),
        (480, 270, 780, 620, "Masuk", "Email + kata sandi\nopsi Ingat saya", PURPLE),
        (880, 270, 1180, 620, "Validasi", "akun aktif\nrole tersedia", AMBER),
        (1280, 270, 1720, 620, "Panel", "menu, data, filter, aksi\nsesuai role/cabang", GREEN),
    ]
    for x1, y1, x2, y2, title, lines, color in stages:
        box(draw, (x1, y1, x2, y2), title, lines, "#F8FAFC", color, 29, 21)
    for x in (380, 780, 1180):
        arrow(draw, (x, 445), (x + 100, 445))
    draw.rounded_rectangle((390, 760, 1410, 920), radius=22, fill="#FFF1F2", outline=ROSE, width=3)
    draw.text((900, 840), "Gagal masuk: periksa email, kata sandi, status akun, dan alamat aplikasi.", anchor="mm", font=font("arialbd.ttf", 23), fill=INK)
    image.save(ASSETS / "alur-login.png")


def make_role_scope() -> None:
    image, draw = canvas("Hak Akses Role dan Cabang", "Data global hanya untuk Superadmin dan Direksi; operasi Direksi bersifat baca", (1800, 1080))
    roles = [
        (60, 180, 430, 850, "SUPERADMIN", "Semua cabang\nKelola master data\nPengaturan sistem\nKoneksi WA\nAudit global", PRIMARY),
        (500, 180, 860, 850, "ADMIN CABANG", "Cabang sendiri\nKelola tim & operasi\nTarget cabang\nKoneksi WA cabang\nAudit cabang", PURPLE),
        (930, 180, 1290, 850, "STAFF", "Cabang sendiri\nLead milik/ditugaskan\nFollow-up & chat\nData operasional\nLaporan sendiri", GREEN),
        (1360, 180, 1730, 850, "DIREKSI", "Semua cabang\nDashboard & laporan\nMonitoring kinerja\nTidak mengubah\ndata operasional", AMBER),
    ]
    for x1, y1, x2, y2, title, lines, color in roles:
        box(draw, (x1, y1, x2, y2), title, lines, "#F8FAFC", color, 29, 20)
    draw.text((900, 955), "Jika menu atau data tidak terlihat, verifikasi role, cabang, status aktif, dan kepemilikan record.", anchor="mm", font=font("arialbd.ttf", 23), fill=MUTED)
    image.save(ASSETS / "role-scope.png")


def make_lead_cycle() -> None:
    image, draw = canvas("Siklus Lead", "Lead bergerak dari pencatatan menuju follow-up, pendaftaran, atau penutupan", (1800, 1080))
    nodes = [
        (70, 240, 350, 560, "COLD", "Lead baru\nmanual, CSV, WA", PRIMARY),
        (430, 240, 710, 560, "WARM", "Sudah dihubungi\nminat berkembang", AMBER),
        (790, 240, 1070, 560, "HOT", "Prospek kuat\nsiap diarahkan", ROSE),
        (1150, 240, 1430, 560, "DAFTAR", "Pendaftaran\ndan program", GREEN),
        (1510, 240, 1740, 560, "SELESAI", "Siswa aktif\natau tidak berminat", PURPLE),
    ]
    for x1, y1, x2, y2, title, lines, color in nodes:
        box(draw, (x1, y1, x2, y2), title, lines, "#F8FAFC", color, 28, 20)
    for x in (350, 710, 1070, 1430):
        arrow(draw, (x, 400), (x + 80, 400))
    draw.rounded_rectangle((370, 720, 1430, 910), radius=24, fill="#EAF4FB", outline=PRIMARY, width=3)
    draw.multiline_text((900, 815), "Setiap perubahan harus mempertahankan PIC, cabang, sumber, program diminati,\nriwayat follow-up, dan jejak audit.", anchor="mm", align="center", spacing=8, font=font("arialbd.ttf", 23), fill=INK)
    image.save(ASSETS / "siklus-lead.png")


def make_whatsapp() -> None:
    image, draw = canvas("Alur Inbox WhatsApp", "Percakapan mengikuti pemilik session dan mencegah duplikasi lintas cabang", (1800, 1080))
    stages = [
        (60, 220, 350, 560, "Pesan", "Nomor calon siswa\nke WA admin/staff", PRIMARY),
        (430, 220, 720, 560, "WAHA/GOWS", "Session WORKING\nwebhook HMAC", PURPLE),
        (800, 220, 1090, 560, "SIVMI", "Normalisasi nomor\nqueue + dedupe", AMBER),
        (1170, 220, 1460, 560, "Matching", "Matched / NewLead\nConflict / Unresolved", GREEN),
        (1540, 220, 1750, 560, "Inbox", "Baca, balas\nACK & unread", PRIMARY_DARK),
    ]
    for x1, y1, x2, y2, title, lines, color in stages:
        box(draw, (x1, y1, x2, y2), title, lines, "#F8FAFC", color, 27, 19)
    for x in (350, 720, 1090, 1460):
        arrow(draw, (x, 390), (x + 80, 390))
    draw.rounded_rectangle((300, 720, 1500, 930), radius=22, fill="#FFF7ED", outline=AMBER, width=3)
    draw.multiline_text((900, 825), "Nomor baru membuat lead Cold melalui kanal WhatsApp.\nNomor ganda atau lintas cabang ditandai konflik untuk pemeriksaan manual.", anchor="mm", align="center", spacing=8, font=font("arialbd.ttf", 23), fill=INK)
    image.save(ASSETS / "alur-whatsapp.png")


def make_transaction() -> None:
    image, draw = canvas("Alur Pendaftaran dan Pembayaran", "Transaksi selalu dimulai dari lead dan program yang valid", (1800, 1050))
    stages = [
        (80, 260, 380, 650, "Lead", "Status Hot/Daftar\nidentitas & cabang", PRIMARY),
        (480, 260, 780, 650, "Pendaftaran", "Program bimbel\ntanggal & status siswa", PURPLE),
        (880, 260, 1180, 650, "Pembayaran", "Nominal, metode\ntanggal & status", AMBER),
        (1280, 260, 1720, 650, "Hasil", "Siswa aktif\nomzet dashboard/laporan\naudit aktivitas", GREEN),
    ]
    for x1, y1, x2, y2, title, lines, color in stages:
        box(draw, (x1, y1, x2, y2), title, lines, "#F8FAFC", color, 29, 20)
    for x in (380, 780, 1180):
        arrow(draw, (x, 455), (x + 100, 455))
    draw.text((900, 825), "Omzet hanya menghitung pembayaran berstatus Lunas atau Terverifikasi.", anchor="mm", font=font("arialbd.ttf", 23), fill=MUTED)
    image.save(ASSETS / "alur-transaksi.png")


def make_dashboard_report() -> None:
    image, draw = canvas("Dashboard dan Laporan", "Filter global mengendalikan KPI, tren, target, ranking, sekolah, wilayah, dan laporan", (1800, 1080))
    box(draw, (650, 100, 1150, 310), "FILTER", "Cabang | Tahun | Bulan | Pengguna", "#EAF4FB", PRIMARY_DARK, 31, 22)
    items = [
        (60, 500, 390, 850, "KPI", "Leads, daftar\nsiswa, omzet", PRIMARY),
        (450, 500, 780, 850, "TREN", "Leads & pendaftaran\nharian/bulanan", PURPLE),
        (840, 500, 1170, 850, "TARGET", "Omzet & siswa\nprogress cabang/user", GREEN),
        (1230, 500, 1540, 850, "PROGRAM", "Kategori, tipe\nmetode, kelas, jenjang", AMBER),
        (1600, 500, 1760, 850, "TOP 5", "Sekolah\nwilayah", ROSE),
    ]
    for x1, y1, x2, y2, title, lines, color in items:
        box(draw, (x1, y1, x2, y2), title, lines, "#F8FAFC", color, 27, 19)
        arrow(draw, (900, 310), ((x1 + x2) // 2, 500), color="#94A3B8", width=4)
    image.save(ASSETS / "dashboard-laporan.png")


def add_figure(doc: Document, name: str, caption: str, width=6.15) -> None:
    b.add_figure(doc, ASSETS / name, caption, width)


def table(doc: Document, caption: str, headers: list[str], rows: list[list[str]], widths: list[float]) -> None:
    b.add_table(doc, headers, rows, widths, caption)


def callout(doc: Document, title: str, body: str, fill: str = b.LIGHT_BLUE) -> None:
    b.add_callout(doc, title, body, fill)
    row = doc.tables[-1].rows[0]
    row._tr.get_or_add_trPr().append(b.OxmlElement("w:cantSplit"))


def steps(doc: Document, caption: str, items: list[str]) -> None:
    table(doc, caption, ["Langkah", "Tindakan"], [[str(i), item] for i, item in enumerate(items, 1)], [0.75, 5.75])


def page(doc: Document) -> None:
    doc.add_page_break()


def build_document() -> None:
    for fn in (make_cover, make_navigation, make_login, make_role_scope, make_lead_cycle, make_whatsapp, make_transaction, make_dashboard_report):
        fn()

    doc = Document()
    sec = doc.sections[0]
    sec.page_width = Inches(8.5); sec.page_height = Inches(11)
    sec.top_margin = Inches(0.85); sec.bottom_margin = Inches(0.85)
    sec.left_margin = Inches(1); sec.right_margin = Inches(1)
    sec.header_distance = Inches(0.4); sec.footer_distance = Inches(0.4)
    b.configure_styles(doc)
    sec.header.paragraphs[0].text = "SIVMI CRM  |  BAB X: PANDUAN PENGGUNAAN"
    for run in sec.header.paragraphs[0].runs:
        b.set_run_font(run, 8.5, True, b.PRIMARY)
    b.add_page_number(sec.footer.paragraphs[0])

    p = doc.add_paragraph(); p.alignment = WD_ALIGN_PARAGRAPH.CENTER; p.paragraph_format.space_after = Pt(0)
    pic = p.add_run().add_picture(str(COVER), width=Inches(6.1))
    pic._inline.docPr.set("descr", "Sampul BAB X Panduan Penggunaan SIVMI CRM")
    page(doc)

    b.add_heading(doc, "KENDALI DOKUMEN", 1)
    table(doc, "Tabel 0-1. Kendali dokumen", ["Atribut", "Nilai"], [
        ["Judul", "BAB X: Panduan Penggunaan SIVMI CRM"],
        ["Versi", "1.0"],
        ["Tanggal baseline", "25 Agustus 2026"],
        ["Sasaran", "Superadmin, Admin Cabang, Staff, dan Direksi"],
        ["Lingkup", "Login, navigasi, dashboard, leads, CSV, follow-up, WhatsApp, pendaftaran, pembayaran, program, target, laporan, master data, pengaturan, notifikasi, audit, profil, dan tips operasional"],
        ["Baseline", "Laravel 12, Filament 5, Livewire 4, full Docker, MySQL, Redis, queue, scheduler, serta WAHA/GOWS"],
        ["Sumber kebenaran", "Resource/Page Filament, enum, policy/scope, form schema, import/export, dashboard/report query, WAHA runbook, dan test suite"],
    ], [1.55, 4.95])
    callout(doc, "Cara menggunakan panduan", "Ikuti langkah sesuai role. Jika menu, filter, atau aksi tidak tersedia, jangan mencoba melewati pembatasan; periksa role, cabang, kepemilikan record, status akun, dan kebijakan aplikasi melalui administrator.")
    b.add_heading(doc, "DAFTAR ISI", 1); b.add_toc(doc); page(doc)

    b.add_heading(doc, "RINGKASAN PENGGUNA", 1)
    b.add_body(doc, "SIVMI CRM memusatkan pencatatan lead, follow-up, chat WhatsApp, pendaftaran, pembayaran, target, dan laporan. Setiap record dibatasi oleh role serta cabang. Superadmin dan Direksi dapat melihat lintas cabang; Direksi berorientasi monitoring. Admin Cabang mengelola operasi cabangnya, sedangkan Staff berfokus pada lead dan pekerjaan yang ditugaskan kepadanya.")
    add_figure(doc, "peta-navigasi.png", "Gambar 10-1. Peta menu dan kelompok fungsi SIVMI CRM")
    table(doc, "Tabel 0-2. Prinsip penggunaan", ["Prinsip", "Penerapan"], [
        ["Satu sumber data", "Perbarui record yang sama; hindari duplikasi spreadsheet/chat terpisah"],
        ["Scope", "Pastikan cabang dan PIC benar sebelum menyimpan"],
        ["Kelengkapan", "Isi data wajib dan status sesuai kondisi aktual"],
        ["Keterlacakan", "Gunakan follow-up, pembayaran, chat, dan audit yang tersedia"],
        ["Privasi", "Jangan menyalin data calon siswa ke kanal yang tidak berwenang"],
        ["Verifikasi", "Periksa notifikasi sukses, daftar record, dan KPI setelah tindakan penting"],
    ], [1.5, 5.0])

    b.add_heading(doc, "BAB X: PANDUAN PENGGUNAAN", 1)
    b.add_heading(doc, "10.1 Login", 2)
    add_figure(doc, "alur-login.png", "Gambar 10-2. Alur autentikasi dan pembentukan scope panel")
    steps(doc, "Tabel 10-1. Langkah login", [
        "Buka alamat yang diberikan administrator, misalnya http://localhost:8000/admin/login untuk full Docker lokal.",
        "Masukkan alamat email akun dan kata sandi; gunakan Ingat saya hanya pada perangkat pribadi yang aman.",
        "Klik Masuk dan tunggu Dashboard tampil. Jangan memuat ulang berulang kali ketika koneksi lambat.",
        "Jika kredensial ditolak, periksa ejaan email dan hubungi administrator untuk status aktif/reset kata sandi.",
        "Setelah selesai bekerja pada perangkat bersama, pilih profil lalu Keluar.",
    ])
    table(doc, "Tabel 10-2. Gangguan login", ["Gejala", "Pemeriksaan pengguna", "Eskalasi"], [
        ["Kredensial tidak ditemukan", "Email/password dan Caps Lock", "Reset melalui administrator"],
        ["Kembali ke login", "Cookie, alamat/domain, waktu perangkat", "Periksa session/config"],
        ["502/halaman tidak tersedia", "Tunggu singkat dan cek koneksi", "Administrator memeriksa service"],
        ["Menu kosong", "Pastikan akun aktif dan role", "Administrator memperbaiki assignment"],
    ], [2.0, 2.4, 2.1])

    b.add_heading(doc, "10.2 Hak Akses Role", 2)
    add_figure(doc, "role-scope.png", "Gambar 10-3. Ringkasan hak akses role dan cabang")
    table(doc, "Tabel 10-3. Matriks penggunaan", ["Fungsi", "Superadmin", "Admin Cabang", "Staff", "Direksi"], [
        ["Dashboard/laporan", "Semua", "Cabang", "Diri/cabang", "Semua"],
        ["Lead/follow-up", "Kelola", "Kelola cabang", "Kelola scope", "Baca"],
        ["Pendaftaran/pembayaran", "Kelola", "Kelola cabang", "Kelola scope", "Baca"],
        ["Cabang", "Kelola", "Baca scope", "Terbatas", "Baca"],
        ["Sekolah/program", "Kelola", "Sesuai izin", "Sesuai izin", "Baca"],
        ["Pengguna", "Kelola", "Tim cabang", "Tidak", "Baca terbatas"],
        ["Pengaturan", "Kelola", "Tidak", "Tidak", "Tidak"],
        ["Koneksi WA", "Kelola", "Cabang", "Akun sendiri/sesuai izin", "Monitoring"],
        ["Audit", "Global", "Cabang", "Aktivitas sendiri", "Global"],
    ], [1.6, 1.2, 1.45, 1.1, 1.15])
    callout(doc, "Direksi", "Role Direksi memiliki akses global untuk monitoring tetapi tidak boleh mengubah data operasional. Pembatasan akhir mengikuti implementasi resource/policy; tampilan dapat berbeda bila administrator mengubah izin.", b.LIGHT_AMBER)

    b.add_heading(doc, "10.3 Navigasi dan Dashboard", 2)
    b.add_body(doc, "Sidebar desktop dapat diciutkan dan statusnya tersimpan pada browser; pada mobile sidebar menjadi drawer. Header memuat pencarian, notifikasi, dan profil. Dashboard menggunakan filter global Cabang, Tahun, Bulan, dan Pengguna.")
    add_figure(doc, "dashboard-laporan.png", "Gambar 10-4. Hubungan filter global dengan visual KPI dan laporan")
    steps(doc, "Tabel 10-4. Menggunakan dashboard", [
        "Pilih cabang; pengguna non-global akan tetap dikunci ke cabangnya.",
        "Pilih tahun dan bulan. Gunakan Semua bulan untuk ringkasan satu tahun.",
        "Pilih pengguna bila role mengizinkan; Staff otomatis dibatasi ke dirinya.",
        "Tinjau ringkasan hari ini, KPI, target omzet/siswa, tren, aktivitas, tindakan tertunda, dan ranking.",
        "Tinjau KPI program berdasarkan nama, kategori, tipe, metode, kelas, jenjang, serta Top 5 sekolah dan wilayah.",
        "Jika skeleton tidak selesai atau error state muncul, coba muat ulang sekali lalu eskalasi dengan waktu dan filter yang dipakai.",
    ])

    b.add_heading(doc, "10.4 Data Leads", 2)
    add_figure(doc, "siklus-lead.png", "Gambar 10-5. Siklus status lead dan jejak data")
    table(doc, "Tabel 10-5. Field lead utama", ["Field", "Ketentuan"], [
        ["Nama lengkap", "Wajib; gunakan identitas yang dapat dikenali"],
        ["Nomor WhatsApp", "Wajib; format 08..., 8..., atau 62... dinormalisasi"],
        ["Sekolah", "Pilih data aktif atau buat sekolah baru dari form bila belum tersedia"],
        ["Program diminati", "Pilih program aktif yang paling relevan"],
        ["Sumber", "Iklan, Instagram, Kunjungan, Saudara, Facebook, atau Tiktok"],
        ["Cabang", "Wajib; non-global mengikuti cabang pengguna"],
        ["PIC", "Wajib; pilih petugas aktif dalam scope cabang"],
        ["Status", "Cold, Warm, Hot, Daftar, atau Tidak Berminat"],
        ["Tanggal masuk", "Tanggal lead pertama diterima"],
    ], [1.75, 4.75])
    steps(doc, "Tabel 10-6. Membuat lead", [
        "Buka Leads lalu klik Buat lead.",
        "Isi nama dan nomor WhatsApp; periksa kemungkinan record yang sama.",
        "Pilih sekolah. Jika belum tersedia, gunakan aksi tambah dan pilih prefix SMAN, SMAS, SMKN, SMKS, MAN, atau ALUMNI/GAPYEAR.",
        "Pilih program, sumber, cabang, PIC, status, dan tanggal masuk.",
        "Klik Buat; pastikan notifikasi sukses dan record muncul pada daftar.",
    ])
    callout(doc, "NPSN dan sekolah", "NPSN tidak wajib. Nama sekolah baru wajib memakai prefix resmi. Gunakan ALUMNI/GAPYEAR ketika calon siswa tidak lagi terkait sekolah aktif; jangan membuat nama sekolah fiktif.")

    b.add_heading(doc, "10.5 Import Leads CSV", 2)
    table(doc, "Tabel 10-7. Persiapan CSV", ["Kontrol", "Panduan"], [
        ["Template", "Unduh/gunakan contoh kolom dari aksi import"],
        ["Encoding", "Simpan sebagai CSV UTF-8"],
        ["Nomor WA", "Satu nomor per baris; jangan gunakan formula"],
        ["Referensi", "Pastikan cabang, PIC, sekolah, dan program dapat dikenali"],
        ["Status/sumber", "Gunakan nilai enum yang didukung"],
        ["Scope", "Import hanya untuk cabang yang berwenang"],
        ["Batch", "Uji dengan beberapa baris sebelum file besar"],
    ], [1.45, 5.05])
    steps(doc, "Tabel 10-8. Prosedur import", [
        "Buka Leads dan pilih Import.",
        "Unggah CSV lalu cocokkan kolom bila dialog meminta mapping.",
        "Tinjau validasi dan jumlah baris; batalkan bila cabang/PIC keliru.",
        "Jalankan import dan tunggu notifikasi selesai; proses dapat menggunakan queue.",
        "Tinjau baris berhasil/gagal dan koreksi hanya data yang ditolak.",
        "Cari sampel lead untuk memastikan nomor, status, PIC, program, dan cabang benar.",
    ])

    b.add_heading(doc, "10.6 Export Leads", 2)
    b.add_body(doc, "Export mengikuti filter tabel, role, cabang, dan kebijakan aplikasi. Staff hanya dapat mengekspor laporan bila opsi staff_report_export diaktifkan Superadmin.")
    steps(doc, "Tabel 10-9. Prosedur export", [
        "Atur pencarian, filter, cabang, status, PIC, atau program pada daftar Leads.",
        "Pilih Export dan tentukan kolom bila tersedia.",
        "Tunggu notifikasi proses; unduh file dari notifikasi/aksi yang diberikan.",
        "Buka file dan verifikasi jumlah serta scope; jangan menyimpan pada folder publik.",
        "Hapus file lokal ketika kebutuhan selesai sesuai kebijakan retensi.",
    ])
    callout(doc, "Keamanan spreadsheet", "Aplikasi menetralkan nilai yang berpotensi menjadi formula spreadsheet. Tetap perlakukan file export sebagai data terbatas dan jangan mengunggahnya ke layanan pribadi.", b.LIGHT_AMBER)

    b.add_heading(doc, "10.7 Follow-up", 2)
    table(doc, "Tabel 10-10. Data follow-up", ["Field", "Cara isi"], [
        ["Lead", "Pilih lead dalam scope"],
        ["Waktu follow-up", "Waktu kontak dilakukan"],
        ["Metode", "Gunakan kanal komunikasi aktual"],
        ["Status kontak", "Catat hasil kontak secara objektif"],
        ["Follow-up berikutnya", "Isi bila tindakan lanjutan diperlukan"],
        ["Catatan hasil", "Ringkas, relevan, tanpa data sensitif berlebih"],
    ], [1.8, 4.7])
    steps(doc, "Tabel 10-11. Siklus follow-up", [
        "Buka Follow-up atau detail Lead, kemudian pilih tambah follow-up.",
        "Pastikan lead, metode, waktu, dan status kontak benar.",
        "Tuliskan hasil dan tanggal lanjutan; jangan mengganti catatan lama untuk menyembunyikan riwayat.",
        "Perbarui status lead menjadi Warm, Hot, Daftar, atau Tidak Berminat sesuai bukti.",
        "Periksa panel tindakan tertunda agar follow-up jatuh tempo tidak terlewat.",
    ])

    b.add_heading(doc, "10.8 Inbox WhatsApp", 2)
    add_figure(doc, "alur-whatsapp.png", "Gambar 10-6. Alur pesan, pencocokan lead, dan inbox")
    table(doc, "Tabel 10-12. Status pencocokan", ["Status", "Makna", "Tindakan"], [
        ["Matched", "Tepat satu lead sesuai scope", "Lanjutkan percakapan"],
        ["NewLead", "Nomor baru membuat lead Cold", "Lengkapi sekolah/program/sumber"],
        ["Conflict", "Nomor ganda atau lintas cabang", "Periksa manual; jangan buat duplikat"],
        ["Unresolved", "Identitas belum dapat dipastikan", "Periksa nomor/session/payload"],
        ["Ignored", "Group/newsletter/status", "Tidak ada tindakan"],
    ], [1.25, 3.0, 2.25])
    steps(doc, "Tabel 10-13. Mengelola chat", [
        "Buka Inbox WhatsApp dan gunakan pencarian/filter bila diperlukan.",
        "Pilih percakapan; periksa kontak, pemilik akun, cabang, lead, status, dan unread.",
        "Baca konteks sebelum membalas. Hindari mengirim data pribadi yang tidak perlu.",
        "Ketik pesan dan klik Kirim; tunggu status Sent/Delivered/Read atau Failed.",
        "Jika konflik, selesaikan pencocokan melalui pemeriksaan lead dan cabang sebelum tindakan lanjutan.",
    ])

    page(doc)
    b.add_heading(doc, "10.9 Pendaftaran (Data Siswa)", 2)
    b.add_body(doc, "Pada baseline saat ini, data siswa operasional direpresentasikan oleh Pendaftaran yang menghubungkan lead, cabang, dan program bimbel. Tidak ada menu Data Siswa terpisah; siswa aktif dihitung dari pendaftaran berstatus Aktif.")
    add_figure(doc, "alur-transaksi.png", "Gambar 10-7. Lead menjadi pendaftaran, pembayaran, dan KPI")
    steps(doc, "Tabel 10-14. Membuat pendaftaran", [
        "Pastikan lead tidak berstatus Tidak Berminat dan data identitasnya benar.",
        "Buka Pendaftaran lalu klik Buat pendaftaran, atau gunakan aksi dari lead bila tersedia.",
        "Pilih lead, cabang, program, tanggal daftar, status, dan data tambahan yang diminta.",
        "Simpan lalu verifikasi pendaftaran muncul dan status lead menjadi Daftar bila alur mengaturnya.",
        "Gunakan aksi Catat pembayaran ketika transaksi diterima.",
    ])

    b.add_heading(doc, "10.10 Pembayaran", 2)
    table(doc, "Tabel 10-15. Kontrol pembayaran", ["Data", "Ketentuan"], [
        ["Pendaftaran", "Wajib dan harus berada dalam scope"],
        ["Nominal", "Masukkan nilai transaksi aktual"],
        ["Tanggal", "Gunakan tanggal pembayaran diterima"],
        ["Metode", "Pilih metode yang digunakan"],
        ["Status", "Omzet hanya memasukkan Lunas atau Terverifikasi"],
        ["Bukti/keterangan", "Isi sesuai form dan kebijakan, tanpa data berlebih"],
    ], [1.6, 4.9])
    steps(doc, "Tabel 10-16. Mencatat pembayaran", [
        "Buka Pembayaran atau aksi Catat pembayaran pada Pendaftaran.",
        "Pilih pendaftaran dan periksa nama, program, serta cabang.",
        "Isi nominal, metode, tanggal, status, dan keterangan yang diperlukan.",
        "Simpan dan verifikasi record serta perubahan omzet pada periode/filter yang benar.",
        "Gunakan perubahan status hanya berdasarkan bukti penerimaan/verifikasi.",
    ])

    b.add_heading(doc, "10.11 Program Bimbel", 2)
    table(doc, "Tabel 10-17. Klasifikasi program", ["Komponen", "Fungsi"], [
        ["Kategori", "Unsur pertama nama program, misalnya SR"],
        ["Kelas program", "Unsur kedua, misalnya GOLD"],
        ["Metode belajar", "Unsur ketiga, misalnya ONLINE"],
        ["Tipe program", "Klasifikasi tambahan untuk operasional/KPI"],
        ["Jenjang", "Segmen pendidikan"],
        ["Tingkat kelas", "Tingkat siswa yang dituju"],
        ["Biaya/layanan", "Pendaftaran, pendidikan, diskon, pertemuan, fasilitas"],
    ], [1.7, 4.8])
    callout(doc, "Nama program otomatis", "Nama program hanya terdiri dari tiga unsur: Kategori + Kelas program + Metode belajar, misalnya SR GOLD ONLINE. Tipe, jenjang, dan tingkat kelas tetap disimpan sebagai dimensi tetapi tidak masuk nama.", b.LIGHT_GREEN)
    steps(doc, "Tabel 10-18. Membuat program", [
        "Buka Program Bimbel lalu klik Buat Program Bimbel.",
        "Pilih kategori, kelas program, metode belajar, tipe, jenjang, dan tingkat kelas.",
        "Isi biaya pendaftaran, biaya pendidikan, diskon, jumlah pertemuan, dan fasilitas.",
        "Aktifkan Program aktif hanya bila siap dipakai lead dan pendaftaran.",
        "Simpan dan periksa nama tiga unsur serta seluruh biaya.",
    ])

    b.add_heading(doc, "10.12 Target Kinerja", 2)
    b.add_body(doc, "Target Kinerja menyimpan sasaran omzet dan siswa per cabang, tahun, bulan, serta target user bila dibagi ke tim. Dashboard dan Laporan membandingkan realisasi terhadap target aktif.")
    steps(doc, "Tabel 10-19. Mengelola target", [
        "Buka Target Kinerja dan pilih cabang, tahun, bulan, serta status.",
        "Isi target omzet dan target siswa cabang.",
        "Bagi target kepada Admin Cabang/Staff melalui target user bila diperlukan.",
        "Pastikan total pembagian sesuai keputusan bisnis; sistem tidak menggantikan approval manajemen.",
        "Simpan dan periksa progress pada Dashboard menggunakan periode yang sama.",
    ])

    b.add_heading(doc, "10.13 Laporan", 2)
    table(doc, "Tabel 10-20. Isi laporan", ["Komponen", "Keterangan"], [
        ["KPI", "Leads, pendaftaran, konversi, omzet, rata-rata transaksi"],
        ["Tren", "Leads dan pendaftaran harian dalam bulan"],
        ["Funnel", "Distribusi status Cold, Warm, Hot, Daftar, Tidak Berminat"],
        ["Pembayaran", "Metode, jumlah transaksi, dan nilai"],
        ["Cabang", "Leads, daftar, konversi, omzet, dan progress target"],
        ["Program", "Pendaftaran dan omzet per program"],
    ], [1.55, 4.95])
    steps(doc, "Tabel 10-21. Membaca laporan", [
        "Pilih cabang, tahun, bulan, dan pengguna sesuai kewenangan.",
        "Pastikan label periode sesuai analisis yang dimaksud.",
        "Bandingkan KPI dan tren; jangan menafsirkan konversi tanpa volume lead.",
        "Periksa funnel, pembayaran, cabang, dan program untuk penyebab perubahan.",
        "Gunakan export hanya bila diizinkan dan simpan file secara aman.",
    ])

    b.add_heading(doc, "10.14 Master Data Cabang, Sekolah, dan Pengguna", 2)
    table(doc, "Tabel 10-22. Master data", ["Menu", "Aturan penggunaan"], [
        ["Cabang", "Gunakan nama resmi; baseline Bandung, JakSel, JakPus; nonaktifkan, jangan duplikasi"],
        ["Sekolah", "Prefix resmi; NPSN opsional; Grade A-E; kota/wilayah; status aktif"],
        ["Pengguna", "Nama, email unik, role, cabang, status aktif; satu akun per orang"],
    ], [1.25, 5.25])
    table(doc, "Tabel 10-23. Interpretasi grade sekolah", ["Grade", "Baseline pengertian"], [
        ["A", "Sering menyumbang siswa terbanyak meskipun bukan sekolah favorit menurut catatan pendidikan"],
        ["B", "Sedikit/jarang menyumbang siswa dan merupakan sekolah favorit"],
        ["C", "Belum pernah menyumbang siswa dan merupakan sekolah favorit"],
        ["D-E", "Kategori lanjutan yang harus ditetapkan organisasi secara konsisten"],
    ], [1.0, 5.5])
    steps(doc, "Tabel 10-24. Pemeriksaan sebelum menyimpan pengguna", [
        "Pastikan email belum dipakai dan identitas orang benar.",
        "Pilih role sesuai pekerjaan, bukan senioritas.",
        "Tetapkan cabang untuk Admin Cabang/Staff; jangan memberi scope global tanpa dasar.",
        "Aktifkan akun hanya setelah kredensial disampaikan melalui kanal aman.",
        "Nonaktifkan akun yang tidak lagi digunakan; jangan berbagi akun.",
    ])

    b.add_heading(doc, "10.15 Koneksi WhatsApp", 2)
    table(doc, "Tabel 10-25. Status session", ["Status", "Makna", "Tindakan"], [
        ["Disconnected", "Belum dimulai/terputus", "Siapkan session dan Mulai"],
        ["Connecting", "Inisialisasi/menunggu QR", "Tunggu dan scan QR"],
        ["Working", "Terhubung", "Aktifkan chat dan uji"],
        ["Failed", "API/session gagal", "Periksa log/key/jaringan; restart/sinkronkan"],
    ], [1.4, 2.2, 2.9])
    steps(doc, "Tabel 10-26. Menautkan nomor", [
        "Buka MASTER DATA > Koneksi WhatsApp dan buat koneksi untuk admin/staff pemilik nomor.",
        "Masukkan nomor, aktifkan integrasi, dan simpan. Satu pengguna hanya satu koneksi aktif.",
        "Klik Siapkan session, Mulai, lalu Lihat QR.",
        "Pada ponsel buka WhatsApp > Perangkat tertaut > Tautkan perangkat dan scan QR.",
        "Klik Sinkronkan sampai status Working, lalu Aktifkan chat.",
        "Kirim pesan uji dari nomor lain dan pastikan muncul di Inbox WhatsApp.",
    ])
    callout(doc, "Jenis WhatsApp", "Penautan tidak mewajibkan WhatsApp Business; WhatsApp personal maupun Business dapat digunakan selama fitur Perangkat tertaut tersedia dan kebijakan WhatsApp mengizinkan perangkat baru.")

    b.add_heading(doc, "10.16 Pengaturan", 2)
    b.add_body(doc, "Pengaturan hanya tersedia bagi Superadmin dan berlaku langsung pada panel. Perubahan dicatat dalam Audit Aktivitas.")
    table(doc, "Tabel 10-27. Kelompok pengaturan", ["Kelompok", "Isi"], [
        ["Identitas", "Nama aplikasi, organisasi, tagline dashboard"],
        ["Dukungan", "Email dan nomor dukungan"],
        ["Laporan", "Izin export laporan untuk Staff"],
        ["Program", "Kategori, kelas, tipe, metode belajar, jenjang, tingkat kelas"],
    ], [1.5, 5.0])
    steps(doc, "Tabel 10-28. Menyimpan pengaturan", [
        "Tinjau dampak terhadap form dan program yang sudah digunakan.",
        "Edit satu opsi per baris; hindari duplikasi beda huruf besar/kecil.",
        "Jangan menghapus opsi yang masih dipakai oleh program aktif; sistem akan menolak.",
        "Klik Simpan dan tunggu notifikasi berhasil.",
        "Buka form Program Bimbel untuk memverifikasi opsi baru.",
    ])

    b.add_heading(doc, "10.17 Notifikasi dan Audit Aktivitas", 2)
    table(doc, "Tabel 10-29. Notifikasi", ["Jenis", "Penggunaan"], [
        ["Sukses", "Konfirmasi create/update/import/export/pengaturan"],
        ["Peringatan", "Tindakan tertunda, konflik, atau kondisi perlu perhatian"],
        ["Error", "Validasi atau proses gagal; baca pesan sebelum mengulang"],
        ["Database notification", "Dapat dibaca dari ikon lonceng sesuai penerima"],
    ], [1.55, 4.95])
    table(doc, "Tabel 10-30. Filter audit", ["Filter", "Fungsi"], [
        ["Cabang", "Membatasi kejadian pada cabang; non-global dikunci"],
        ["Pengguna", "Melihat aktivitas actor tertentu; Staff dikunci ke diri"],
        ["Event", "Dibuat, Diperbarui, Dihapus, Masuk, Keluar"],
        ["Tanggal", "Rentang awal dan akhir"],
        ["Pencarian", "Kata pada deskripsi aktivitas"],
    ], [1.45, 5.05])
    callout(doc, "Audit bukan tempat rahasia", "Audit membantu keterlacakan, tetapi pengguna tetap tidak boleh menulis password, token, API key, isi chat sensitif, atau data pribadi berlebih pada catatan/keterangan.", b.LIGHT_AMBER)

    b.add_heading(doc, "10.18 Profil, Logout, dan Tips Operasional", 2)
    table(doc, "Tabel 10-31. Kebiasaan kerja yang disarankan", ["Situasi", "Tindakan"], [
        ["Mulai hari", "Periksa notifikasi, follow-up tertunda, lead Cold, dan session WhatsApp"],
        ["Input data", "Cari dahulu, verifikasi cabang/PIC, lalu simpan"],
        ["Follow-up", "Catat segera setelah kontak dan jadwalkan tindak lanjut"],
        ["Closing", "Buat pendaftaran, catat pembayaran berdasarkan bukti, cek KPI"],
        ["Import/export", "Uji kecil, review hasil, lindungi file"],
        ["Pergantian perangkat", "Logout, jangan simpan kredensial, laporkan akses mencurigakan"],
        ["Gangguan", "Catat waktu, menu, filter, langkah, dan screenshot tanpa secret"],
        ["Akhir hari", "Tutup follow-up, cek unread/chat, logout dari perangkat bersama"],
    ], [1.6, 4.9])
    table(doc, "Tabel 10-32. Jalur eskalasi pengguna", ["Masalah", "Bukti aman", "Tujuan"], [
        ["Form/validasi", "Field, pesan error, waktu", "Admin/Support aplikasi"],
        ["Scope/akses", "Role, cabang, menu; tanpa password", "Superadmin"],
        ["Data salah/duplikat", "ID/nama tersamarkan, kondisi", "Admin Cabang/Data owner"],
        ["WhatsApp", "Session, status, waktu, chat ID tersamarkan", "Admin WA/Teknis"],
        ["Outage", "URL, waktu, status browser", "Administrator/Incident owner"],
    ], [1.5, 3.0, 2.0])
    callout(doc, "Batas modul", "Baseline saat ini belum memiliki menu terpisah untuk Tim, Tugas, atau Pembelajaran. Aktivitas tim direpresentasikan melalui Pengguna, PIC, target, follow-up, dan audit; pembelajaran siswa berada di luar lingkup CRM saat ini.", b.LIGHT_AMBER)

    b.add_heading(doc, "LAMPIRAN A - CHECKLIST HARIAN", 1)
    table(doc, "Tabel A-1. Checklist pengguna", ["Waktu", "Checklist"], [
        ["Pagi", "Login aman; filter benar; notifikasi; WA Working; follow-up jatuh tempo; lead Cold"],
        ["Selama kerja", "Cari sebelum buat; lengkapi data; catat follow-up/chat; perbarui status; verifikasi transaksi"],
        ["Sebelum selesai", "Unread kosong/ditindak; follow-up terjadwal; data tersimpan; file export aman; logout"],
    ], [1.25, 5.25])

    b.add_heading(doc, "LAMPIRAN B - KAMUS STATUS", 1)
    table(doc, "Tabel B-1. Status penting", ["Domain", "Nilai", "Makna singkat"], [
        ["Lead", "Cold, Warm, Hot, Daftar, Tidak Berminat", "Tahap minat/konversi"],
        ["WA account", "Disconnected, Connecting, Working, Failed", "Kondisi session"],
        ["WA match", "Matched, NewLead, Conflict, Unresolved, Ignored", "Hasil pencocokan"],
        ["WA message", "Pending, Sent, Delivered, Read, Played, Failed", "Status pengiriman/ACK"],
        ["Pembayaran", "Termasuk Lunas/Terverifikasi", "Status yang dihitung sebagai omzet"],
        ["Sekolah", "Grade A-E", "Kluster kontribusi/potensi sekolah"],
    ], [1.25, 2.7, 2.55])

    b.add_heading(doc, "PENUTUP", 1)
    b.add_body(doc, "Panduan ini menggambarkan penggunaan SIVMI CRM berdasarkan implementasi tanggal 25 Agustus 2026. Menu, field, dan hak akses dapat berubah mengikuti pengembangan. Ketika perubahan terjadi, pengguna harus mengikuti notifikasi rilis dan dokumen versi terbaru, sedangkan administrator memperbarui role, data master, runbook, dan pelatihan.")
    callout(doc, "Prinsip akhir", "Catat data pada record yang benar, kelola tindak lanjut secara konsisten, dan lakukan closing berdasarkan bukti. Ketepatan cabang, PIC, status, serta waktu pencatatan menentukan mutu dashboard dan laporan.", b.LIGHT_GREEN)

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc.save(OUTPUT)
    print(f"Created: {OUTPUT}")


if __name__ == "__main__":
    build_document()
