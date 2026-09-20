from __future__ import annotations

import sys
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Pt

sys.path.insert(0, str(Path(__file__).resolve().parent))
import build_project_documentation_20260916 as base
import diagrams_extra as extra

ROOT = Path(__file__).resolve().parents[2]
DOCX_OUT = ROOT / "docs" / "DOKUMENTASI-PROJECT-SIVMI-CRM-v4.0-STANDAR-15-BAB-2026-09-20.docx"
DRAWIO_OUT = ROOT / "docs" / "DIAGRAM-DAN-TABEL-PROJECT-SIVMI-CRM-v4.0-2026-09-20.drawio"
ASSET_DIR = ROOT / "docs" / "_project_20260920_assets"

MUTED = base.MUTED
NAVY = base.NAVY
_chapter_count = 0


def chapter(doc: Document, number: str, title: str, intro: str) -> None:
    global _chapter_count
    base.chapter(doc, number, title, intro)
    if _chapter_count == 0:
        # Daftar diagram sudah berakhir pada batas halaman; page-break tambahan
        # akan menghasilkan satu halaman kosong sebelum BAB I pada Word.
        doc.paragraphs[-3].paragraph_format.page_break_before = False
    _chapter_count += 1


def build_docx(diagrams: dict[str, Path]) -> None:
    doc = Document()
    base.configure_styles(doc)
    base.set_headers_footers(doc)

    section = doc.sections[0]
    section.different_first_page_header_footer = True
    cover = doc.add_paragraph()
    cover.paragraph_format.space_before = Pt(125)
    cover.alignment = WD_ALIGN_PARAGRAPH.CENTER
    base.set_run_font(cover.add_run("DOKUMENTASI PROJECT"), 18, True, base.BLUE)
    title = doc.add_paragraph(style="Title")
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    title.paragraph_format.space_after = Pt(8)
    base.set_run_font(title.add_run("SIVMI CRM"), 34, True, NAVY)
    subtitle = doc.add_paragraph()
    subtitle.alignment = WD_ALIGN_PARAGRAPH.CENTER
    subtitle.paragraph_format.space_after = Pt(22)
    base.set_run_font(subtitle.add_run("Dokumentasi Sistem Informasi - Standar 15 Bab"), 13, False, base.TEXT)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    base.set_run_font(p.add_run("Baseline aplikasi 2.4\nEdisi dokumentasi 4.0\n20 September 2026"), 11, True, MUTED)
    doc.add_paragraph().paragraph_format.space_after = Pt(90)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    base.set_run_font(p.add_run("CRM VILLA MERAH\nDokumen bisnis, arsitektur, data, pengembangan, testing, operasional, dan tata kelola"), 12, True, NAVY)

    doc.add_page_break()
    base.add_heading(doc, "Kontrol Dokumen", 1)
    base.add_table(doc, ["Atribut", "Nilai"], [
        ["Judul", "Dokumentasi Project SIVMI CRM - Standar 15 Bab"],
        ["Baseline aplikasi", "Versi 2.4"],
        ["Edisi dokumentasi", "4.0"],
        ["Tanggal baseline", "20 September 2026"],
        ["Referensi struktur", "docs/Standar Pembuatan Dokumentasi SI.xlsx"],
        ["Struktur", "Standar Pembuatan Dokumentasi SI (Business, Requirement, Process, Architecture, Data, Application, Development, Testing, Deployment, Security, Operation, User, Governance, Knowledge Transfer, Improvement)"],
        ["Status", "As-built kode dan konfigurasi dengan rekomendasi target operasi"],
        ["Audiens", "Pemilik sistem, direksi, administrator, pengembang, QA, dan operator"],
        ["Sumber", "Kode aplikasi, migration, route, dependency lock, Docker Compose, test suite, git history, dan dokumentasi operasional"],
    ], [1.55, 5.0])
    base.add_heading(doc, "Tujuan Dokumen", 2)
    base.add_body(doc, "Dokumen ini menjelaskan SIVMI CRM secara terpadu dari konteks bisnis sampai operasi harian berdasarkan standar sistematika dokumentasi sistem informasi (15 bab). Pembaca memperoleh satu baseline yang dapat dipakai untuk pengembangan, audit, deployment, pelatihan, pengujian, pemeliharaan, dan integrasi dengan sistem lain.")
    base.add_heading(doc, "Kesimpulan Utama", 2)
    base.add_body(doc, "SIVMI CRM telah memenuhi karakteristik aplikasi CRM vertikal untuk bimbingan belajar. Implementasinya berbentuk modular monolith Laravel yang mengelola lead, follow-up, komunikasi WhatsApp, pendaftaran, pembayaran, target, laporan, audit, dan integrasi publik. Quality gate kode pada baseline ini lulus: 118 test dengan 739 assertion, build asset, validasi dependency, pemeriksaan sintaks, serta formatter berhasil. Rilis produksi tetap mensyaratkan UAT lingkungan produksi, verifikasi tunnel WAHA, backup/restore, monitoring, dan persetujuan pemilik proses.")

    doc.add_page_break()
    base.add_heading(doc, "Daftar Isi", 1)
    toc = doc.add_paragraph()
    base.add_field(toc, 'TOC \\o "1-3" \\h \\z \\u', "Daftar isi akan diperbarui saat dokumen dibuka di Microsoft Word.")
    base.add_heading(doc, "Daftar Diagram Utama", 2)
    base.add_table(doc, ["No", "Diagram", "Bab"], [
        [1, "Diagram use case SIVMI CRM", "II"], [2, "Siklus bisnis lead hingga siswa", "III"], [3, "Flowchart proses bisnis utama", "III"],
        [4, "DFD Level 0 (konteks aliran data)", "III"], [5, "DFD Level 1 (aliran data internal)", "III"], [6, "Konteks sistem", "IV"],
        [7, "WAHA GOWS dan tunnel", "IV"], [8, "Arsitektur sistem", "IV"], [9, "Model data inti", "V"],
        [10, "ERD logis", "V"], [11, "ERD fisik - domain inti", "V"], [12, "ERD fisik - integrasi dan governance", "V"],
        [13, "Arsitektur aplikasi", "VI"], [14, "Alur REST API", "VI"], [15, "Strategi testing", "VIII"],
        [16, "Topologi Docker", "IX"], [17, "Pertahanan berlapis", "X"], [18, "Roadmap", "XV"],
    ], [0.55, 4.8, 1.0])

    chapter(doc, "I", "Business - Bisnis dan Kebutuhan Usaha (BRD)", "Bab ini menetapkan tujuan bisnis, masalah yang dihadapi, kebutuhan usaha, stakeholder, ruang lingkup, serta manfaat dan KPI yang ingin dicapai.")
    base.add_heading(doc, "1.1 Latar Belakang Bisnis", 2)
    base.add_body(doc, "Operasional pemasaran dan penerimaan siswa bimbingan belajar membutuhkan riwayat calon siswa yang konsisten, penanggung jawab yang jelas, serta hubungan antara komunikasi, pendaftaran, pembayaran, dan target. Jika data tersebar pada spreadsheet, catatan pribadi, dan percakapan WhatsApp, organisasi sulit mengendalikan duplikasi, keterlambatan follow-up, kehilangan konteks, serta ketidaksesuaian laporan.")
    base.add_body(doc, "SIVMI CRM memusatkan perjalanan calon siswa sejak akuisisi sampai menjadi siswa. Sistem dibangun sebagai aplikasi web dengan antarmuka administrasi, REST API, integrasi sekolah, dashboard peta, dan WhatsApp melalui WAHA GOWS. Seluruh aktivitas penting dibatasi berdasarkan role dan cabang serta dicatat untuk audit.")
    base.add_heading(doc, "1.2 Identifikasi Masalah", 2)
    base.add_bullets(doc, [
        "Data lead berpotensi duplikat karena format nomor WhatsApp tidak konsisten.",
        "Follow-up sulit dipantau apabila jadwal dan hasil komunikasi tidak dicatat terpusat.",
        "Kewenangan transaksi harus dipisahkan antara pencatatan awal dan verifikasi pembayaran.",
        "Percakapan WhatsApp perlu dikaitkan dengan pengguna, cabang, lead, dan audit tanpa membuka data lintas cabang.",
        "Integrasi eksternal memerlukan autentikasi, idempotensi, rate limit, HMAC, timeout, cache, dan fallback.",
        "Shared hosting tidak sepenuhnya sesuai untuk WAHA dan worker yang membutuhkan proses jangka panjang.",
    ])
    base.add_heading(doc, "1.3 Kebutuhan Bisnis", 2)
    base.add_numbers(doc, [
        "Menyediakan sumber data operasional tunggal untuk calon siswa dan siswa terdaftar.",
        "Menstandardisasi proses akuisisi, follow-up, konversi, pembayaran, dan pelaporan.",
        "Menerapkan hak akses berdasarkan role, cabang, kepemilikan data, dan pemisahan tugas.",
        "Menghubungkan WhatsApp, API Sekolah, peta, dan REST API secara terkontrol.",
        "Menyediakan baseline arsitektur, deployment, testing, keamanan, dan SOP yang dapat diaudit.",
    ])
    base.add_heading(doc, "1.4 Stakeholder", 2)
    base.add_table(doc, ["Stakeholder", "Peran", "Manfaat yang diharapkan"], [
        ["Staff", "Pengguna operasional utama", "Daftar kerja, riwayat komunikasi, dan tindak lanjut lebih terstruktur."],
        ["Admin Cabang", "Manajer unit cabang", "Visibilitas kualitas data, beban tim, target, dan performa cabang."],
        ["Keuangan", "Pengelola pembayaran", "Transaksi dapat diperiksa, diverifikasi, ditolak, dan dikoreksi dalam scope cabang."],
        ["Direksi", "Pengambil keputusan", "KPI, tren, funnel, laporan, dan audit lintas cabang tersedia tanpa mutasi operasional."],
        ["Tim TI", "Pengelola sistem", "Arsitektur, konfigurasi, kontrak API, test, dan prosedur pemulihan terdokumentasi."],
    ], [1.7, 2.0, 2.85])
    base.add_heading(doc, "1.5 Ruang Lingkup dan Batasan", 2)
    base.add_body(doc, "Cakupan as-built meliputi dashboard, Leads, Follow-up, Inbox WhatsApp, Target Kinerja, Laporan, Cabang, Sekolah, Pengguna, Pendaftaran, Pembayaran, Program Bimbel, Pengaturan, Audit Aktivitas, Backup dan Restore, Koneksi WhatsApp, REST API leads/siswa, webhook WAHA, API Sekolah Indonesia, serta peta OpenStreetMap.")
    base.add_body(doc, "Sistem belum diposisikan sebagai LMS, sistem akademik penuh, akuntansi umum, payment gateway, atau CRM generik lintas industri. WAHA merupakan integrasi WhatsApp non-resmi yang harus dikelola dengan mitigasi risiko akun, keamanan endpoint, dan kesinambungan operasi.")
    base.add_heading(doc, "1.6 Manfaat dan KPI", 2)
    base.add_table(doc, ["KPI", "Definisi", "Sumber data"], [
        ["Lead masuk", "Jumlah lead pada periode dan scope", "leads.tanggal_masuk"],
        ["Konversi", "Pendaftaran dibanding total lead", "pendaftaran dan leads"],
        ["Siswa aktif", "Pendaftaran berstatus Aktif", "pendaftaran.status_pendaftaran"],
        ["Omzet tervalidasi", "Pembayaran Lunas/Terverifikasi", "pembayaran"],
        ["Follow-up tepat waktu", "Jadwal yang diselesaikan sebelum jatuh tempo", "followup_riwayat"],
        ["Pencapaian target", "Realisasi omzet/siswa dibanding target", "target_kinerja_*"],
        ["Kualitas data", "Kelengkapan, duplikasi, dan konflik pencocokan", "leads dan WhatsApp"],
    ], [1.5, 3.15, 1.9])

    chapter(doc, "II", "Requirement - Spesifikasi Kebutuhan (SRS)", "Bab ini merinci kebutuhan fungsional, nonfungsional, aturan bisnis, use case, kriteria penerimaan, dan keterlacakan kebutuhan.")
    base.add_heading(doc, "2.1 Kebutuhan Fungsional", 2)
    base.add_table(doc, ["ID", "Kebutuhan"], [
        ["FR-01", "Sistem mengelola lead dengan nama dan nomor WhatsApp wajib; atribut lain opsional."],
        ["FR-02", "Nomor WhatsApp dinormalisasi dan dicegah duplikasinya."],
        ["FR-03", "Lead dapat difilter berdasarkan kota, sekolah, kelas, program, status, cabang, dan PIC."],
        ["FR-04", "Sekolah dapat dicari dari API dan disimpan ke database lokal."],
        ["FR-05", "Follow-up mencatat metode, status kontak, catatan, dan jadwal berikutnya."],
        ["FR-06", "Staff dapat mengubah lead menjadi pendaftaran dan pembayaran awal."],
        ["FR-07", "Keuangan memverifikasi/menolak/koreksi pembayaran dalam scope cabang."],
        ["FR-08", "Dashboard menampilkan KPI, target, tren, sekolah, dan peta wilayah."],
        ["FR-09", "Sistem mengelola satu session WAHA per pengguna operasional."],
        ["FR-10", "Pesan WAHA diproses queue, dideduplikasi, dan dicocokkan dengan lead."],
        ["FR-11", "REST API menyediakan read/write lead dan read siswa dengan ability."],
        ["FR-12", "Aktivitas penting, notifikasi, backup, dan restore dikelola terkontrol."],
    ], [0.85, 5.7])
    base.add_heading(doc, "2.2 Kebutuhan Nonfungsional", 2)
    base.add_table(doc, ["Kategori", "Kebutuhan", "Kontrol atau ukuran"], [
        ["Keamanan", "Least privilege dan pemisahan tugas", "Role, policy, scope, HMAC, token ability"],
        ["Kinerja", "Halaman dan query tetap responsif", "Pagination, eager loading, index, cache"],
        ["Keandalan", "Retry dan pemulihan gangguan sementara", "Queue retry, backoff, health check"],
        ["Ketersediaan", "Service kritis dipantau dan auto-restart", "Docker restart, monitoring, cron/worker"],
        ["Integritas", "Cegah duplikasi dan data tidak konsisten", "Unique key, normalisasi, transaksi"],
        ["Auditabilitas", "Perubahan penting dapat ditelusuri", "Activity log, request ID, timestamp"],
        ["Portabilitas", "Lingkungan dapat direplikasi", "Docker Compose dan environment file"],
        ["Pemulihan", "Database dan storage dapat dikembalikan", "Backup, restore test, rollback"],
    ], [1.25, 2.9, 2.4])
    base.add_heading(doc, "2.3 Aturan Bisnis", 2)
    base.add_bullets(doc, [
        "Nama dan nomor WhatsApp wajib untuk lead baru; atribut lain dapat diisi bertahap.",
        "Sekolah hanya mewajibkan nama; detail API atau manual bersifat opsional.",
        "Satu nomor WhatsApp ternormalisasi tidak boleh membentuk lead duplikat.",
        "Satu lead mempunyai paling banyak satu pendaftaran aktif pada tabel pendaftaran.",
        "Admin Cabang dan Superadmin hanya melihat Pendaftaran serta Pembayaran.",
        "Staff dapat membuat pendaftaran dan mencatat pembayaran Menunggu miliknya.",
        "Keuangan wajib memiliki cabang dan tidak boleh menghapus pembayaran.",
        "Direksi bersifat baca-saja untuk data operasional.",
        "Satu pengguna hanya memiliki satu akun/session WhatsApp aktif.",
        "Group, newsletter, dan status broadcast WhatsApp diabaikan dari alur lead.",
    ])
    base.add_heading(doc, "2.4 Use Case", 2)
    base.add_table(doc, ["ID", "Use case", "Aktor utama"], [
        ["UC-01", "Mengelola lead (tambah, ubah, cari, bulk action)", "Staff, Admin Cabang, Superadmin"],
        ["UC-02", "Impor/ekspor lead melalui CSV", "Staff, Admin Cabang"],
        ["UC-03", "Melakukan follow-up dan menjadwalkan ulang", "Staff"],
        ["UC-04", "Konversi lead menjadi pendaftaran", "Staff"],
        ["UC-05", "Mencatat pembayaran awal (Menunggu)", "Staff"],
        ["UC-06", "Memverifikasi, menolak, mengoreksi pembayaran", "Keuangan"],
        ["UC-07", "Mengelola master data cabang, sekolah, program, pengguna", "Superadmin, Admin Cabang"],
        ["UC-08", "Mengelola target kinerja cabang dan pengguna", "Superadmin, Admin Cabang"],
        ["UC-09", "Mengelola koneksi dan session WhatsApp", "Superadmin, Admin Cabang"],
        ["UC-10", "Mengakses REST API leads dan students", "API Client"],
        ["UC-11", "Melihat dashboard, laporan, dan audit", "Semua role sesuai scope"],
        ["UC-12", "Melakukan backup dan restore", "Superadmin"],
    ], [0.8, 3.1, 2.65])
    base.add_figure(doc, diagrams["usecase"], "Diagram 1. Diagram use case SIVMI CRM")
    base.add_heading(doc, "2.5 Acceptance Criteria (UAT)", 2)
    base.add_body(doc, "Kriteria penerimaan minimum didefinisikan pada Lampiran A (Matriks UAT). Intinya, fungsi kritis harus lulus pada environment uji, termasuk pembatasan role dan cabang, nomor WhatsApp duplikat, alur WAHA, API publik, peta dashboard, dan kemampuan restore. UAT ditandatangani pemilik proses sebelum rilis produksi.")
    base.add_heading(doc, "2.6 Traceability", 2)
    base.add_table(doc, ["Kebutuhan", "Implementasi", "Pengujian"], [
        ["FR-01 s.d. FR-04", "LeadResource, importer, SchoolApiClient, PhoneNormalizer", "LeadManagementTest, LeadCsvTest, SchoolApiIntegrationTest"],
        ["FR-05", "FollowupRiwayatResource dan model scope", "Feature test operasional dan RBAC"],
        ["FR-06 s.d. FR-07", "PendaftaranResource, PembayaranResource, UserRole", "RegistrationPaymentTest"],
        ["FR-08", "Dashboard Page dan Leaflet", "DashboardTest, IndonesiaCityCoordinatesTest"],
        ["FR-09 s.d. FR-10", "WahaClient, WahaSessionService, webhook job", "WahaIntegrationTest"],
        ["FR-11", "LeadController, StudentController, middleware API", "PublicLeadApiTest, PublicStudentApiTest"],
        ["FR-12", "Observer, Audit, BackupRestore", "AuditNotificationTest, BackupRestoreTest"],
    ], [1.05, 3.0, 2.5])

    chapter(doc, "III", "Process - Proses Bisnis dan Workflow", "Bab ini memetakan proses bisnis end-to-end, alur BPMN/flowchart, RACI, alur persetujuan, dan penanganan kondisi pengecualian.")
    base.add_figure(doc, diagrams["business"], "Diagram 2. Siklus bisnis lead hingga siswa")
    base.add_heading(doc, "3.1 Proses Utama", 2)
    base.add_numbers(doc, [
        "Lead masuk melalui input manual, impor CSV, WhatsApp, atau REST API.",
        "Sistem menormalisasi nomor, memeriksa duplikasi, dan menyimpan sumber serta kanal masuk.",
        "Cabang dan PIC ditetapkan sesuai kewenangan; data opsional dilengkapi bertahap.",
        "Staff melakukan follow-up, mencatat hasil, dan menjadwalkan tindakan berikutnya.",
        "Lead yang siap dikonversi dibuatkan pendaftaran dan program bimbel.",
        "Staff mencatat pembayaran awal sebagai Menunggu; Keuangan memeriksa status akhir.",
        "Dashboard, target, laporan, audit, dan notifikasi diperbarui dari data transaksi.",
    ])
    base.add_figure(doc, diagrams["flowchart"], "Diagram 3. Flowchart proses bisnis utama SIVMI CRM")
    base.add_heading(doc, "3.2 Workflow Persetujuan dan Pembayaran", 2)
    base.add_table(doc, ["Tahap", "Pelaksana", "Tindakan"], [
        ["Pendaftaran", "Staff", "Pilih lead milik/penugasan, program, tanggal, angkatan, dan status."],
        ["Pembayaran awal", "Staff", "Catat nominal/metode/referensi dengan status Menunggu."],
        ["Pemeriksaan", "Keuangan", "Cocokkan pendaftaran, nominal, referensi, dan bukti di luar sistem bila berlaku."],
        ["Keputusan", "Keuangan", "Ubah menjadi Terverifikasi/Lunas atau Ditolak; beri catatan koreksi."],
        ["Monitoring", "Admin/Direksi", "Lihat hasil pada dashboard/laporan tanpa mengubah transaksi."],
    ], [1.25, 1.3, 4.0])
    base.add_heading(doc, "3.3 RACI", 2)
    base.add_table(doc, ["Aktivitas", "Superadmin", "Admin", "Staff", "Keuangan", "Direksi"], [
        ["Kelola lead/follow-up", "A", "A", "R", "-", "I"],
        ["Buat pendaftaran", "I", "I", "R", "I", "I"],
        ["Catat pembayaran awal", "I", "I", "R", "R", "I"],
        ["Verifikasi pembayaran", "I", "I", "C", "R/A", "I"],
        ["Kelola pengguna/cabang", "R/A", "R cabang", "-", "-", "I"],
        ["Konfigurasi WAHA", "A", "R cabang", "C", "-", "I"],
        ["Audit dan laporan", "R", "R cabang", "I", "I cabang", "A/I"],
        ["Backup/restore", "R/A", "-", "-", "-", "I"],
    ], [2.0, 0.9, 0.9, 0.8, 0.95, 0.85])
    base.add_heading(doc, "3.4 Workflow Session WhatsApp", 2)
    base.add_numbers(doc, [
        "Admin membuat akun WhatsApp untuk pengguna dan cabang.",
        "CRM membuat session WAHA tanpa memulai otomatis.",
        "Session dimulai dan QR diambil melalui endpoint CRM yang membutuhkan login.",
        "Pengguna memindai QR dari WhatsApp Perangkat Tertaut.",
        "Status disinkronkan menjadi Working.",
        "Aksi Aktifkan chat memasang daftar event, URL webhook, dan HMAC.",
        "Scheduler menyinkronkan status; queue memproses event; widget memantau kesehatan.",
    ])
    base.add_heading(doc, "3.5 Penanganan Pengecualian", 2)
    base.add_bullets(doc, [
        "Nomor WhatsApp duplikat ditolak dan diarahkan ke data yang sudah ada, bukan membuat lead baru.",
        "API Sekolah tidak tersedia: sistem memakai cache/fallback dan tetap mengizinkan sekolah manual.",
        "Webhook WAHA gagal: event disimpan, dideduplikasi dengan payload hash, dan diproses ulang melalui queue.",
        "Pembayaran tidak dapat diverifikasi karena referensi tidak cocok: ditolak dengan catatan koreksi.",
        "Restore gagal: proses dihentikan, bukti dipantau, dan safety backup digunakan untuk mengembalikan kondisi.",
        "Konteks lintas proses dijaga dengan RBAC, scope cabang, audit, notifikasi, quality gate, dan backup.",
    ])
    base.add_heading(doc, "3.6 Data Flow Diagram (DFD)", 2)
    base.add_body(doc, "DFD menggambarkan siapa yang memberi atau menerima data, proses yang mengubah data, dan tempat penyimpanan data. Level 0 (konteks) menampilkan SIVMI CRM sebagai satu proses dengan entitas eksternal; Level 1 mendekomposisi proses internal menjadi enam subproses utama.")
    base.add_figure(doc, diagrams["dfd_level0"], "Diagram 4. DFD Level 0 - konteks aliran data SIVMI CRM")
    base.add_figure(doc, diagrams["dfd_level1"], "Diagram 5. DFD Level 1 - aliran data internal SIVMI CRM")
    base.add_table(doc, ["Kode", "Proses", "Data utama yang diperbarui"], [
        ["1.0", "Kelola Lead dan Follow-up", "leads, followup_riwayat"],
        ["2.0", "Kelola Pendaftaran dan Pembayaran", "pendaftaran, pembayaran"],
        ["3.0", "Dashboard, Target, dan Laporan", "target_kinerja_cabang, target_kinerja_user"],
        ["4.0", "Integrasi WhatsApp (WAHA)", "whatsapp_conversations, whatsapp_messages, waha_webhook_events"],
        ["5.0", "REST API Publik", "api_clients, api_idempotency_keys"],
        ["6.0", "Audit, Notifikasi, dan Backup", "activity_logs, notifications, imports, exports"],
    ], [0.95, 3.0, 2.6])

    chapter(doc, "IV", "Architecture - Arsitektur Sistem", "Bab ini menjelaskan identitas sistem, konteks, aktor, modul, klasifikasi arsitektur, stack teknologi, dan topologi server/jaringan.")
    base.add_figure(doc, diagrams["context"], "Diagram 6. Konteks sistem dan integrasi SIVMI CRM")
    base.add_heading(doc, "4.1 Identitas Sistem", 2)
    base.add_table(doc, ["Atribut", "Baseline terbaru"], [
        ["Nama", "SIVMI CRM dengan identitas antarmuka CRM VILLA MERAH"],
        ["Jenis", "CRM vertikal berbasis web untuk bimbingan belajar"],
        ["Arsitektur", "Modular monolith Laravel dengan service pendukung"],
        ["Backend", "PHP 8.3+; baseline runtime 8.4; Laravel 12.66.0"],
        ["Admin UI", "Filament 5.7.6 dan Livewire 4.4.0"],
        ["Frontend", "Blade, Tailwind CSS 4.3.3, Vite 8.2.1, Leaflet 1.9.4"],
        ["Database", "MySQL 8.4 pada Docker; Eloquent ORM"],
        ["Integrasi", "WAHA GOWS, API Sekolah Indonesia, OpenStreetMap, REST API v1"],
        ["Locale", "Bahasa Indonesia, Asia/Jakarta"],
        ["URL produksi", "https://crm.sivmi.id"],
    ], [1.65, 4.9])
    base.add_heading(doc, "4.2 Aktor dan Tanggung Jawab", 2)
    base.add_table(doc, ["Aktor", "Cakupan", "Tanggung jawab utama"], [
        ["Superadmin", "Global", "Konfigurasi, pengguna, cabang, integrasi, audit, backup; Pendaftaran/Pembayaran baca-saja."],
        ["Admin Cabang", "Cabang", "Operasional penjualan, staff, target, WAHA cabang; transaksi baca-saja."],
        ["Staff", "Milik/penugasan", "Lead, follow-up, pendaftaran, dan pembayaran awal berstatus Menunggu."],
        ["Keuangan", "Cabang", "Melihat pendaftaran serta mencatat, memverifikasi, menolak, dan mengoreksi pembayaran."],
        ["Direksi", "Global baca-saja", "Dashboard, KPI, laporan, dan audit untuk pengambilan keputusan."],
        ["API Client", "Ability dan cabang", "Mengakses lead atau siswa melalui token yang di-hash."],
        ["WAHA", "Session user", "Pertukaran pesan, media, status, dan event webhook."],
    ], [1.25, 1.35, 3.95])
    base.add_heading(doc, "4.3 Katalog Modul", 2)
    base.add_table(doc, ["Modul", "Fungsi", "Data utama"], [
        ["Dashboard", "Ringkasan KPI, funnel, tren, target, sekolah, wilayah, dan peta", "Lead, pendaftaran, pembayaran, target"],
        ["Leads", "CRUD, filter, impor/ekspor, bulk action, assignment", "Lead, sekolah, program, sumber, user, cabang"],
        ["Follow-up", "Riwayat kontak dan jadwal berikutnya", "Lead, user, metode, status kontak"],
        ["Inbox WhatsApp", "Percakapan dua arah dan media", "Account, conversation, message, lead"],
        ["Pendaftaran", "Konversi lead menjadi siswa", "Lead, program, cabang, status"],
        ["Pembayaran", "Pencatatan dan verifikasi transaksi", "Pendaftaran, nominal, metode, status"],
        ["Master Data", "Cabang, sekolah, program, pengguna", "Referensi organisasi dan produk"],
        ["Kontrol", "Target, laporan, pengaturan, audit, backup", "KPI, settings, activity log, SQL backup"],
    ], [1.35, 3.05, 2.15])
    base.add_heading(doc, "4.4 Klasifikasi Arsitektur", 2)
    base.add_body(doc, "SIVMI CRM adalah modular monolith. Seluruh domain berada pada satu codebase dan satu deployment aplikasi Laravel, tetapi dipisahkan melalui model, resource, service, controller, request, observer, middleware, job, dan command. WAHA, MySQL, Redis, Nginx, Node, queue, dan scheduler merupakan komponen runtime pendukung; keberadaan beberapa container tidak menjadikan aplikasi microservices karena domain bisnis belum dipisahkan menjadi service otonom dengan data dan siklus rilis sendiri.")
    base.add_heading(doc, "4.5 Stack Teknologi", 2)
    base.add_table(doc, ["Komponen", "Versi", "Fungsi"], [
        ["PHP", "^8.3; runtime 8.4.13", "Bahasa dan PHP-FPM"],
        ["Laravel", "12.66.0", "Framework aplikasi"],
        ["Filament", "5.7.6", "Admin panel"],
        ["Livewire", "4.4.0", "Komponen reaktif"],
        ["Tailwind CSS", "4.3.3", "Styling"],
        ["Vite", "8.2.1", "Build asset"],
        ["Leaflet", "1.9.4", "Peta dashboard"],
        ["MySQL", "8.4", "Database"],
        ["Redis", "7.4", "Service cache/queue opsional"],
        ["WAHA", "image gows", "Integrasi WhatsApp"],
    ], [1.55, 1.55, 3.45])
    base.add_heading(doc, "4.6 Topologi dan Keamanan Jaringan", 2)
    base.add_figure(doc, diagrams["waha"], "Diagram 7. WAHA GOWS dan Cloudflare Named Tunnel")
    base.add_body(doc, "Untuk topologi CRM di server dan WAHA di Windows lokal, Named Tunnel menghubungkan waha.sivmi.id ke http://127.0.0.1:3000. Server memakai WAHA_BASE_URL=https://waha.sivmi.id, sedangkan webhook tetap menuju https://crm.sivmi.id/api/webhooks/waha. Quick Tunnel trycloudflare.com hanya boleh digunakan untuk diagnosis karena URL berubah. Seluruh komunikasi publik dilindungi HTTPS/TLS dan secret dikelola lewat environment.")
    base.add_heading(doc, "4.7 Arsitektur Sistem", 2)
    base.add_body(doc, "SIVMI CRM berjalan sebagai aplikasi Laravel (Nginx dan PHP-FPM) dengan data pada MySQL 8.4 dan optional Redis 7.4. Aplikasi memakai queue dan scheduler untuk proses asinkron; seluruh integrasi eksternal (WAHA GOWS, REST API klien, API Sekolah, peta OpenStreetMap, dan tunnel Cloudflare) masuk melalui endpoint yang terlindungi. Komponen operasional mencakup queue worker, scheduler, monitoring, serta backup dan restore.")
    base.add_figure(doc, diagrams["architecture"], "Diagram 8. Arsitektur sistem SIVMI CRM")
    base.add_table(doc, ["Komponen", "Peran", "Teknologi"], [
        ["Klien pengguna", "Akses panel dan dashboard", "Browser, Filament 5, Livewire 4, Tailwind 4, Leaflet"],
        ["API Client", "Integrasi data lead/siswa", "REST API v1 dengan bearer token dan idempotensi"],
        ["WAHA GOWS", "Koneksi WhatsApp", "Webhook HMAC melalui Named Tunnel waha.sivmi.id"],
        ["Server aplikasi", "Bisnis dan presentasi", "Nginx, PHP-FPM 8.4, Laravel 12, Eloquent"],
        ["Data dan runtime", "Persistensi dan cache", "MySQL 8.4, Redis 7.4 (opsional), storage media"],
        ["Proses asinkron", "Kinerja dan penjadwalan", "Queue worker, scheduler, command reminder"],
        ["Integrasi eksternal", "Data pelengkap dan peta", "API Sekolah Indonesia, OpenStreetMap tile"],
        ["Operasional", "Ketersediaan dan recovery", "Monitoring, core backup, backup dan restore SQL"],
    ], [1.8, 2.7, 3.45])

    chapter(doc, "V", "Data - Arsitektur Data", "Bab ini menjelaskan domain data, model konseptual, ERD, relasi, katalog tabel, master data, kualitas data, retensi, dan backup.")
    base.add_figure(doc, diagrams["data"], "Diagram 9. Model data inti SIVMI CRM")
    base.add_heading(doc, "5.1 Domain Data", 2)
    base.add_table(doc, ["Domain", "Tabel utama", "Tujuan"], [
        ["Identitas", "cabang, users", "Organisasi, role, dan scope"],
        ["Master", "sekolah, program_bimbel, sumber_leads", "Referensi pendidikan, produk, sumber"],
        ["CRM", "leads, followup_riwayat", "Akuisisi dan hubungan calon siswa"],
        ["Closing", "pendaftaran, pembayaran", "Konversi dan transaksi"],
        ["Kinerja", "target_kinerja_cabang, target_kinerja_user", "Target dan realisasi"],
        ["WhatsApp", "user_whatsapp_accounts, conversations, messages, webhook_events", "Komunikasi dan event"],
        ["Integrasi", "api_clients, api_idempotency_keys, imports, exports", "Akses API dan pertukaran file"],
        ["Governance", "activity_logs, notifications, crm_settings", "Audit, notifikasi, konfigurasi"],
        ["Runtime", "sessions, cache, jobs, failed_jobs", "State dan proses framework"],
    ], [1.2, 3.05, 2.3])
    base.add_heading(doc, "5.2 Relasi dan Kardinalitas", 2)
    base.add_table(doc, ["Entitas", "Relasi", "Kardinalitas"], [
        ["Cabang - Pengguna", "Pengguna terdaftar pada cabang", "1:N"],
        ["Cabang - Lead", "Lead dimiliki satu cabang", "1:N"],
        ["Pengguna - Lead", "Lead dicatat/diinput oleh pengguna", "mencatat 1:N"],
        ["Sekolah - Lead", "Lead berasal dari sekolah", "asal 1:N"],
        ["Program - Lead", "Lead meminati satu program", "minat 1:N"],
        ["Lead - Follow-up", "Riwayat tindak lanjut", "1:N"],
        ["Lead - Pendaftaran", "Konversi menjadi siswa", "1:0..1"],
        ["Pendaftaran - Pembayaran", "Tagihan/cicilan pendaftaran", "1:N"],
        ["Lead - WhatsApp", "Percakapan terkait nomor ternormalisasi", "0..1:N"],
        ["Pengguna - Audit", "Aktivitas yang dicatat", "1:N"],
    ], [2.1, 3.0, 1.45])
    base.add_figure(doc, diagrams["erd_logis"], "Diagram 10. ERD logis SIVMI CRM")
    base.add_heading(doc, "5.3 Katalog Tabel Inti", 2)
    base.add_table(doc, ["Tabel", "Key", "Relasi atau atribut penting"], [
        ["cabang", "PK cabang_id; UK kode", "nama, kota, kontak, aktif"],
        ["users", "PK id; UK email; FK cabang", "role, aktif, password hash"],
        ["sekolah", "PK sekolah_id; UK npsn nullable", "nama, wilayah, grade, metadata API"],
        ["program_bimbel", "PK program_bimbel_id", "kategori, kelas, tipe, metode, biaya, diskon"],
        ["sumber_leads", "PK sumber_lead_id; UK kode/nama", "warna, urutan, aktif"],
        ["leads", "PK lead_id; UK no_wa_normalized", "FK sekolah, program, sumber, cabang, inputter, PIC"],
        ["followup_riwayat", "PK followup_riwayat_id", "FK lead dan user; jadwal, metode, hasil"],
        ["pendaftaran", "PK pendaftaran_id; UK lead_id", "FK program dan cabang; status, angkatan"],
        ["pembayaran", "PK pembayaran_id", "FK pendaftaran; nominal, metode, status, referensi"],
        ["target_kinerja_*", "PK masing-masing; UK periode/user", "target omzet, siswa, status"],
    ], [1.65, 1.9, 3.0])
    base.add_heading(doc, "5.4 Master Data", 2)
    base.add_table(doc, ["Master", "Sumber", "Validasi"], [
        ["Cabang", "Dikelola Superadmin", "Kode unik; menentukan scope data"],
        ["Sekolah", "API Sekolah atau manual", "Nama wajib; NPSN opsional; disimpan lokal"],
        ["Program Bimbel", "Dikelola admin", "Kategori, kelas, tipe, metode, biaya, diskon"],
        ["Sumber Leads", "Dikelola admin", "Kode/nama unik; warna dan urutan"],
        ["Pengguna", "Dikelola Superadmin/Admin", "Email unik; role; cabang; status aktif"],
    ], [1.6, 2.6, 2.35])
    base.add_heading(doc, "5.5 Katalog Integrasi dan Governance", 2)
    base.add_table(doc, ["Tabel", "Fungsi", "Kontrol integritas"], [
        ["user_whatsapp_accounts", "Akun WAHA per user", "Unique user, phone, session; FK cabang"],
        ["whatsapp_conversations", "Thread per akun dan chat", "Unique account plus chat_id"],
        ["whatsapp_messages", "Pesan masuk/keluar", "Unique account plus external_message_id"],
        ["waha_webhook_events", "Inbox event mentah", "Unique payload_hash; status proses"],
        ["api_clients", "Kredensial integrasi", "Key hash unik, ability, user, cabang, aktif"],
        ["api_idempotency_keys", "Replay-safe POST", "Unique client plus key; request hash dan TTL"],
        ["activity_logs", "Audit perubahan", "User/cabang dan polymorphic subject"],
        ["notifications", "Notifikasi database", "Polymorphic notifiable"],
        ["imports/exports", "Status pertukaran CSV", "User, file, hasil, error per baris"],
    ], [1.75, 2.45, 2.35])
    base.add_heading(doc, "5.6 Kualitas Data dan Retensi", 2)
    base.add_bullets(doc, [
        "Nomor WhatsApp disimpan dalam format normalisasi untuk pencarian dan unique constraint.",
        "Nama sekolah dapat berasal dari API atau input manual, kemudian dipersistenkan lokal.",
        "Kelas sekolah dipisahkan dari kelas atau angkatan program bimbel.",
        "Sumber lead dinormalisasi menjadi foreign key sumber_lead_id.",
        "Import CSV memberikan pesan validasi Bahasa Indonesia dan mencatat baris gagal.",
        "Scope cabang diterapkan pada query, tidak hanya pada tampilan antarmuka.",
        "Event dan pesan eksternal memakai unique key atau payload hash untuk deduplikasi.",
    ])
    base.add_body(doc, "Backup database menggunakan mysqldump dengan transaksi konsisten. Restore hanya dilakukan Superadmin dengan konfirmasi dan safety backup. Cakupan produksi meliputi database, storage/media, environment, serta volume WAHA; restore drill berkala wajib membuktikan RPO dan RTO. Kamus data lengkap tersedia pada dokumen KAMUS-DATA-DATABASE-SIVMI-CRM-LENGKAP-v1.1 dan TABEL-DATABASE-SIVMI-CRM-LENGKAP-v1.0.")
    base.add_heading(doc, "5.7 ERD Fisik dan Struktur Tabel", 2)
    base.add_body(doc, "ERD fisik menjabarkan implementasi database: tabulasi, primary key, unique key, foreign key, dan constraint utama. Diagram pertama mencakup domain inti CRM (identitas, master, lead, follow-up, pendaftaran, pembayaran, dan target); diagram kedua mencakup integrasi WhatsApp, REST API, dan governance (audit, notifikasi, pengaturan, serta pertukaran CSV).")
    base.add_figure(doc, diagrams["erd_fisik_inti"], "Diagram 11. ERD fisik - domain inti CRM")
    base.add_figure(doc, diagrams["erd_fisik_integrasi"], "Diagram 12. ERD fisik - integrasi dan governance")
    base.add_table(doc, ["Tabel", "Primary key", "Unique", "Foreign key utama"], [
        ["cabang", "cabang_id", "kode_cabang", "-"],
        ["users", "id", "email", "cabang_id"],
        ["sekolah", "sekolah_id", "npsn", "-"],
        ["program_bimbel", "program_bimbel_id", "-", "-"],
        ["sumber_leads", "sumber_lead_id", "kode, nama", "-"],
        ["leads", "lead_id", "no_wa_normalized (app)", "sekolah_id, minat_program_id, sumber_lead_id, cabang_id, diinput_oleh_user_id, diserahkan_ke_user_id"],
        ["followup_riwayat", "followup_riwayat_id", "-", "lead_id, user_id"],
        ["pendaftaran", "pendaftaran_id", "lead_id", "program_bimbel_id, cabang_id"],
        ["pembayaran", "pembayaran_id", "-", "pendaftaran_id"],
        ["target_kinerja_cabang", "target_kinerja_cabang_id", "cabang_id+tahun+bulan", "cabang_id, created_by"],
        ["target_kinerja_user", "target_kinerja_user_id", "target_cabang+user", "target_kinerja_cabang_id, user_id"],
        ["user_whatsapp_accounts", "id", "user_id, phone_e164, session_name", "user_id, cabang_id"],
        ["whatsapp_conversations", "id", "whatsapp_account_id+chat_id", "whatsapp_account_id, lead_id"],
        ["whatsapp_messages", "id", "whatsapp_account_id+external_message_id", "conversation_id, whatsapp_account_id, lead_id"],
        ["waha_webhook_events", "id", "payload_hash", "-"],
        ["api_clients", "id", "key_hash", "user_id, cabang_id"],
        ["api_idempotency_keys", "id", "api_client_id+key", "api_client_id"],
        ["activity_logs", "activity_log_id", "-", "user_id, cabang_id"],
        ["crm_settings", "key", "key", "updated_by"],
        ["imports / exports", "id", "-", "user_id"],
    ], [1.9, 1.35, 1.75, 2.75])

    chapter(doc, "VI", "Application - Dokumentasi Teknis Aplikasi", "Bab ini menjelaskan struktur source code, framework dan dependency, konfigurasi, request lifecycle, proses asinkron, serta kontrak REST API dan integrasi.")
    base.add_figure(doc, diagrams["application"], "Diagram 13. Arsitektur aplikasi modular monolith")
    base.add_heading(doc, "6.1 Struktur Source Code", 2)
    base.add_table(doc, ["Lokasi", "Isi utama"], [
        ["app/Filament/Resources", "CRUD Leads, Follow-up, Cabang, Sekolah, User, Program, Target, Pendaftaran, Pembayaran, WAHA"],
        ["app/Filament/Pages", "Dashboard, Laporan, Pengaturan, Audit, Backup dan profil"],
        ["app/Services/WhatsApp", "Client API, session, chat, lead matcher"],
        ["app/Services/Schools", "Client API sekolah, sinkronisasi, pembuatan lokal"],
        ["app/Http/Controllers/Api/V1", "Lead API dan Student API"],
        ["app/Http/Middleware", "Public API auth, idempotensi, verifikasi HMAC"],
        ["app/Observers", "Audit aktivitas dan notifikasi CRM"],
        ["app/Console/Commands", "API client, sinkronisasi WAHA, reminder"],
        ["database/migrations", "Skema relasional dan perubahan basis data"],
        ["routes", "Route web, panel Filament, dan API publik"],
        ["config", "Konfigurasi framework dan integrasi"],
        ["docker/", "Docker Compose dan Dockerfile lingkungan"],
        ["tests/", "Unit, feature, dan integration test"],
    ], [2.05, 4.5])
    base.add_heading(doc, "6.2 Framework dan Dependency", 2)
    base.add_table(doc, ["Kategori", "Paket", "Versi terpasang"], [
        ["Runtime PHP", "PHP", "^8.3 (runtime 8.4.13)"],
        ["Framework", "laravel/framework", "12.66.0"],
        ["Admin panel", "filament/filament", "5.7.6"],
        ["Reactive UI", "livewire/livewire", "4.4.0"],
        ["Styling", "tailwindcss", "4.3.3"],
        ["Asset build", "vite, laravel-vite-plugin", "8.2.1, 3.1"],
        ["Peta", "leaflet", "1.9.4"],
    ], [1.4, 2.2, 2.95])
    base.add_heading(doc, "6.3 Konfigurasi Aplikasi", 2)
    base.add_table(doc, ["Variabel", "Lokal full Docker", "Server/produksi", "Rahasia"], [
        ["APP_URL", "http://localhost:8000", "https://crm.sivmi.id", "Tidak"],
        ["APP_DEBUG", "true", "false", "Tidak"],
        ["DB_HOST", "database", "Host database produksi", "Sebagian"],
        ["QUEUE_CONNECTION", "database", "database/redis sesuai arsitektur", "Tidak"],
        ["WAHA_BASE_URL", "http://waha:3000", "https://waha.sivmi.id", "Tidak"],
        ["WAHA_API_KEY", "Unik development", "Unik production", "Ya"],
        ["WAHA_WEBHOOK_URL", "http://web/api/webhooks/waha", "https://crm.sivmi.id/api/webhooks/waha", "Tidak"],
        ["WAHA_WEBHOOK_HMAC_SECRET", "Unik development", "Unik production", "Ya"],
        ["SCHOOL_API_URL", "https://sekolah.devapi.id/sekolah", "Sama atau proxy resmi", "Tidak"],
        ["PUBLIC_API_ENABLED", "false default", "true bila digunakan", "Tidak"],
    ], [1.75, 1.9, 2.3, 0.65])
    base.add_heading(doc, "6.4 Request Lifecycle", 2)
    base.add_numbers(doc, [
        "Request masuk melalui Nginx dan Laravel router.",
        "Middleware memvalidasi session, token, ability, rate limit, idempotensi, atau HMAC sesuai jalur.",
        "Request/form object memvalidasi payload dan aturan data.",
        "Resource/Page/Controller memanggil model atau service domain.",
        "Eloquent menerapkan relasi dan scope sesuai pengguna/client.",
        "Observer, notifikasi, audit, dan job asinkron dijalankan setelah perubahan.",
        "Response dikembalikan sebagai HTML/Livewire atau JSON terstruktur.",
    ])
    base.add_heading(doc, "6.5 Proses Asinkron", 2)
    base.add_body(doc, "Queue digunakan agar webhook dan pekerjaan yang berpotensi lambat tidak menahan response. Scheduler menjalankan sinkronisasi status WAHA dan reminder. Baseline environment memakai driver database untuk queue, cache, dan session; Redis tersedia pada Compose tetapi belum menjadi driver utama.")
    base.add_heading(doc, "6.6 REST API dan Integrasi", 2)
    base.add_figure(doc, diagrams["api"], "Diagram 14. Alur request REST API publik")
    base.add_table(doc, ["Metode", "Endpoint", "Ability", "Keterangan"], [
        ["GET", "/api/v1/leads", "leads:read", "Daftar lead dengan filter dan pagination"],
        ["GET", "/api/v1/leads/{lead}", "leads:read", "Detail lead sesuai scope"],
        ["POST", "/api/v1/leads", "leads:write", "Membuat lead; membutuhkan Idempotency-Key"],
        ["GET", "/api/v1/students", "students:read", "Siswa terdaftar, program, cabang, sekolah, pembayaran"],
        ["POST", "/api/webhooks/waha", "HMAC", "Event WAHA; bukan bearer API publik"],
    ], [0.75, 2.3, 1.2, 2.3])
    base.add_heading(doc, "6.7 Autentikasi dan Idempotensi API", 2)
    base.add_body(doc, "API Client menggunakan bearer token. Token tidak disimpan dalam bentuk plain pada database; aplikasi menyimpan key prefix untuk identifikasi dan SHA-256 hash untuk verifikasi. Ability membatasi operasi dan scope cabang membatasi record. Client global hanya boleh menggunakan filter cabang apabila tidak dikunci pada satu cabang.")
    base.add_body(doc, "POST lead memakai Idempotency-Key. Middleware menyimpan client, key, request hash, response status/body, dan expiry. Pengulangan key dengan payload yang sama dapat mengembalikan response tersimpan, sedangkan penggunaan key yang sama untuk payload berbeda ditolak. Kontrol ini mencegah duplikasi akibat retry jaringan.")
    base.add_heading(doc, "6.8 Integrasi Eksternal", 2)
    base.add_numbers(doc, [
        "API Sekolah: pencarian lokal terlebih dahulu dan/atau panggilan API dengan timeout, cache, batas hasil, normalisasi, dan penyimpanan lokal; fallback sekolah manual dengan nama wajib.",
        "Peta dashboard: Leaflet 1.9.4 yang disajikan dari public/vendor/leaflet dan tile OpenStreetMap dari internet; kota asal lead menjadi sumber utama, kota sekolah fallback; resolver menangani variasi nama wilayah.",
        "WAHA GOWS: client HTTP dengan timeout/retry dan X-Api-Key; session, webhook HMAC SHA-512, serta deduplikasi event untuk pencocokan lead.",
    ])
    base.add_heading(doc, "6.9 Contoh Request Siswa", 2)
    base.add_code(doc, "GET /api/v1/students?status=Aktif&per_page=100&page=1 HTTP/1.1\nHost: crm.sivmi.id\nAccept: application/json\nAuthorization: Bearer <TOKEN_DENGAN_STUDENTS_READ>")

    chapter(doc, "VII", "Development - Pengembangan dan Versioning", "Bab ini menjelaskan metodologi, kontrol versi, riwayat perubahan, coding standard, build, dan manajemen rilis.")
    base.add_heading(doc, "7.1 Metodologi Pengembangan", 2)
    base.add_body(doc, "Pengembangan SIVMI CRM dijalankan secara inkremental dengan rilis bertahap pada satu basis kode. Setiap penambahan kemampuan dilakukan melalui komit yang memperjelas segmen kerja (dokumentasikan pada METODOLOGI-PENGEMBANGAN-SIVMI-CRM). Estimasi fungsi memakai function points dan story points sebagai dasar prioritas (dokumen PENGUKURAN-FUNCTION-POINTS-DAN-STORY-POINTS-SIVMI-CRM).")
    base.add_heading(doc, "7.2 Kontrol Versi dan Branching", 2)
    base.add_table(doc, ["Aspek", "Baseline"], [
        ["Repository", "github.com/inifauzan-maker/crm.git (remote origin)"],
        ["Branch utama", "main (branch pusat dan produksi)"],
        ["Branch lain / tag", "Belum ada; tag versi belum digunakan"],
        ["Kebijakan yang disarankan", "Buat branch fitur untuk pekerjaan besar, review sebelum merge ke main, dan tambahkan tag versi pada tiap rilis"],
        ["File yang dikecualikan", "Dokumentasi dan file drawio dikecualikan dari sebagian proses build; secret dan file environment tidak pernah masuk Git"],
    ], [1.9, 4.65])
    base.add_heading(doc, "7.3 Riwayat Perubahan", 2)
    git_rows = [
        ["f4a82c9", "2026-08-26", "first commit (inisialisasi proyek)"],
        ["ac5a6e5", "2026-08-27", "complete SIVMI CRM and WhatsApp integration"],
        ["8ee987d", "2026-08-27", "exclude documentation and drawio files"],
        ["e7a23ee", "2026-08-28", "add database backup and lead origin city"],
        ["af55f22", "2026-08-30", "Update CRM Villa Merah branding"],
        ["445697b", "2026-08-30", "Add lead class field and import validation messages"],
        ["c29066b", "2026-08-30", "tingkatkan kualitas data leads"],
        ["0ff25ed", "2026-08-30", "ringkas nama program tatap muka"],
        ["19e5041", "2026-08-30", "jadikan data lead selain kontak opsional"],
        ["8cc2a22", "2026-08-31", "jadikan detail sekolah opsional"],
        ["dfce7ec", "2026-08-31", "tambah aksi massal data sekolah"],
        ["c3c167f", "2026-09-01", "Normalize lead sources with foreign key"],
        ["4d60ead", "2026-09-01", "integrasikan API data sekolah"],
        ["31c387e", "2026-09-01", "tambah peta dan aksi massal leads"],
        ["3234eef", "2026-09-02", "stabilkan peta dashboard di produksi"],
        ["2993c4d", "2026-09-02", "kompatibelkan agregasi kota dashboard"],
        ["6e3bdb8", "2026-09-02", "tambah koordinat wilayah peta leads"],
        ["14e803b", "2026-09-08", "perluas API siswa dan administrasi CRM"],
        ["d00d3b7", "2026-09-08", "lengkapi checklist rilis dan verifikasi"],
    ]
    base.add_table(doc, ["Hash", "Tanggal", "Perubahan"], git_rows, [0.95, 1.05, 4.55])
    base.add_heading(doc, "7.4 Coding Standard", 2)
    base.add_bullets(doc, [
        "PHP lint (php -l) dan format Laravel Pint dijalankan sebelum merilis.",
        "Validasi dependency dengan composer validate/install dan npm ci/build.",
        "Konvensi Filament: Resource, Page, dan Action mengikuti pola bawaan panel.",
        "Model memakai scope query dan relasi eksplisit; index dan foreign key didefinisikan pada migration.",
        "Error dan log tidak boleh memuat secret atau data siswa.",
    ])
    base.add_heading(doc, "7.5 Build dan Release Management", 2)
    base.add_numbers(doc, [
        "Komit ke main setelah melewati lint, format, dan test setidaknya pada lingkup perubahan.",
        "Jalankan composer install --no-dev pada produksi dan npm run build untuk asset.",
        "Terapkan migration --force bersama rencana rollback dan maintenance bila perlu.",
        "Ikuti RELEASE-CHECKLIST-DAN-VERIFIKASI-SIVMI-CRM untuk verifikasi rilis.",
        "Catat versi, ringkasan perubahan, hasil test, dan tanggal rilis (change log).",
        "Tambahkan tag semantik pada tiap rilis yang stabil.",
    ])
    base.add_heading(doc, "7.6 Change Log Terbaru", 2)
    base.add_table(doc, ["Versi basis", "Ringkasan", "Tanggal"], [
        ["2.1", "API sekolah, normalisasi sumber lead, aksi massal, peta leads, koordinat wilayah", "2026-09-01 s.d. 2026-09-02"],
        ["2.2", "Perluasan API siswa dan administrasi CRM, koordinat peta", "2026-09-08"],
        ["2.3", "Checklist rilis dan verifikasi, dokumentasi as-built", "2026-09-08 s.d. 2026-09-16"],
        ["2.4", "Penguatan webhook, pemulihan relasi pesan WhatsApp, identitas dinamis, dan quality gate penuh", "2026-09-20"],
    ], [1.15, 4.1, 1.3])

    chapter(doc, "VIII", "Testing - Pengujian dan UAT", "Bab ini menjelaskan status baseline pengujian, lapisan testing, known issue, siklus debugging, quality gate, dan UAT.")
    base.add_figure(doc, diagrams["testing"], "Diagram 15. Strategi testing dan quality gate")
    base.add_heading(doc, "8.1 Status Baseline Pengujian", 2)
    base.add_table(doc, ["Metrik", "Hasil 20 September 2026"], [
        ["Total test", "118"],
        ["Lulus", "118"],
        ["Belum lulus", "0"],
        ["Assertion", "739"],
        ["Pemeriksaan tambahan", "composer validate/audit, npm production audit/build, php -l, Laravel Pint"],
        ["Status quality gate kode", "Lulus"],
    ], [2.25, 4.3])
    base.add_heading(doc, "8.2 Temuan Debugging yang Ditutup", 2)
    base.add_table(doc, ["Area", "Akar masalah", "Penanganan terverifikasi"], [
        ["Pengaturan identitas", "Cache nilai CrmSetting dapat bertahan pada lifecycle proses test dan worker", "Cache invalidation dan reset state diperkuat; regresi SettingsTest lulus"],
        ["Fixture nomor WhatsApp", "Data uji dapat bertabrakan dengan aturan unique setelah normalisasi", "Generator fixture memakai nomor unik dan eksplisit; regresi ReportTest lulus"],
        ["Webhook WAHA", "Pesan dapat tiba sebelum relasi lead tersedia sehingga foreign key gagal", "Pemrosesan melakukan pencocokan/pemulihan relasi dan retry aman; tidak ada failed job atau orphan pada verifikasi akhir"],
    ], [1.35, 3.0, 2.2])
    base.add_heading(doc, "8.3 Lapisan Pengujian", 2)
    base.add_table(doc, ["Lapisan", "Contoh", "Kriteria"], [
        ["Static", "php -l, Composer validate, Pint", "Tidak ada error atau format kritis"],
        ["Build", "npm run build", "Asset production berhasil dibuat"],
        ["Unit", "Normalizer dan koordinat", "Deterministik dan cepat"],
        ["Feature", "Filament, RBAC, database", "HTTP/Livewire dan state benar"],
        ["Integration", "WAHA, API sekolah, public API", "Kontrak, error, keamanan, retry"],
        ["Smoke", "Login, dashboard, CRUD, health", "Fungsi kritis setelah deploy"],
        ["UAT", "Skenario role dan proses bisnis", "Diterima pemilik proses"],
    ], [1.2, 2.75, 2.6])
    base.add_heading(doc, "8.4 Siklus Debugging", 2)
    base.add_numbers(doc, [
        "Reproduksi masalah dengan input, role, cabang, environment, dan waktu yang diketahui.",
        "Kumpulkan request ID, log, status queue, status service, dan data minimum tanpa membocorkan secret.",
        "Pisahkan masalah UI, otorisasi, query, integrasi, jaringan, cache, atau fixture test.",
        "Buat hipotesis dan uji satu perubahan pada satu waktu.",
        "Tambahkan atau perbaiki test yang gagal sebelum mengubah implementasi.",
        "Jalankan test terfokus, kemudian full suite, build, migration check, dan smoke test.",
        "Catat akar masalah, risiko regresi, dan cara pemulihan.",
    ])
    base.add_heading(doc, "8.5 Quality Gate Rilis", 2)
    base.add_bullets(doc, [
        "Full test suite harus lulus 100 persen.",
        "Build asset dan validasi dependency harus berhasil.",
        "Migration diuji pada salinan database dan mempunyai rencana rollback.",
        "UAT kritis role, transaksi, API, WAHA, backup, dan restore telah ditandatangani.",
        "Tidak ada secret atau data siswa dalam Git, log, artefak, dan screenshot.",
        "Monitoring dan owner insiden tersedia pada waktu rilis.",
    ])
    base.add_heading(doc, "8.6 Matriks UAT", 2)
    base.add_table(doc, ["ID", "Skenario", "Hasil yang diharapkan"], [
        ["UAT-01", "Staff membuat lead hanya dengan nama dan nomor", "Berhasil; nomor dinormalisasi"],
        ["UAT-02", "Nomor WhatsApp yang sama dimasukkan ulang", "Ditolak dan diarahkan ke data yang ada"],
        ["UAT-03", "Sekolah dicari dari API", "Pilihan tampil dan tersimpan lokal setelah dipilih"],
        ["UAT-04", "Staff membuat pendaftaran", "Berhasil hanya untuk data yang dapat diakses"],
        ["UAT-05", "Admin/Superadmin membuka create transaksi", "Akses ditolak"],
        ["UAT-06", "Keuangan memverifikasi pembayaran cabangnya", "Berhasil dan tercatat audit"],
        ["UAT-07", "Keuangan mengakses Leads melalui URL", "Akses ditolak"],
        ["UAT-08", "API students dengan ability benar", "200 dan data sesuai scope"],
        ["UAT-09", "API students tanpa ability", "403"],
        ["UAT-10", "Webhook tanpa HMAC", "401"],
        ["UAT-11", "Session WAHA dipindai dan diaktifkan", "Working dan webhook terpasang"],
        ["UAT-12", "Pesan masuk nomor baru", "Conversation dan lead Cold dibuat"],
        ["UAT-13", "Peta dashboard periode berdata", "Tile dan marker tampil"],
        ["UAT-14", "Backup lalu restore pada environment uji", "Data dan aplikasi pulih"],
        ["UAT-15", "Full test suite", "118 dari 118 lulus; 739 assertion"],
    ], [0.8, 3.25, 2.5])

    chapter(doc, "IX", "Deployment - Panduan Pemasangan dan Rilis", "Bab ini menjelaskan lingkungan, service Docker, instalasi awal, prosedur deployment, migrasi/rollback, dan kapasitas.")
    base.add_figure(doc, diagrams["deployment"], "Diagram 16. Topologi full Docker SIVMI CRM")
    base.add_heading(doc, "9.1 Lingkungan", 2)
    base.add_table(doc, ["Lingkungan", "Arsitektur", "Catatan"], [
        ["Lokal utama", "Full Docker Compose", "Tidak membutuhkan Laragon runtime; Vite development aktif"],
        ["Lokal legacy", "Laragon plus WAHA Docker", "Hanya kompatibilitas dan diagnosis"],
        ["Shared hosting", "PHP web dan MySQL", "Worker via cron; WAHA eksternal/lokal; keterbatasan proses"],
        ["Target", "VPS Docker Compose", "Nginx, app, DB/managed DB, Redis, worker, scheduler, WAHA"],
    ], [1.45, 2.15, 2.95])
    base.add_heading(doc, "9.2 Service Docker", 2)
    base.add_table(doc, ["Service", "Image/runtime", "Port", "Persistensi"], [
        ["web", "nginx:1.27-alpine", "8000:80", "Stateless"],
        ["app", "sivmi-crm-php:local", "internal 9000", "Source/vendor mount"],
        ["database", "mysql:8.4", "3307:3306", "mysql_data"],
        ["redis", "redis:7.4-alpine", "internal 6379", "redis_data"],
        ["node", "node:22-alpine", "5173", "node_modules"],
        ["queue", "app image", "internal", "Database queue"],
        ["scheduler", "app image", "internal", "Tidak ada"],
        ["waha", "devlikeapro/waha:gows", "3000", "waha_sessions"],
    ], [1.25, 2.25, 1.35, 1.7])
    base.add_heading(doc, "9.3 Instalasi Awal", 2)
    base.add_numbers(doc, [
        "Siapkan Docker Desktop (atau Docker Engine) dan git; ikuti PANDUAN-INSTALASI-PERTAMA-LARAGON-DOCKER.md.",
        "Salin environment development dari contoh; isi APP_KEY, database, dan kredensial integrasi.",
        "Jalankan service build web, app, dan node; instal vendor dan module npm.",
        "Jalankan migration dan seed awal untuk role, cabang, dan master data.",
        "Mulai queue dan scheduler, lalu verifikasi login serta dashboard.",
    ])
    base.add_heading(doc, "9.4 Prosedur Deployment", 2)
    base.add_numbers(doc, [
        "Backup database, storage penting, dan file environment sebelum perubahan.",
        "Tarik commit/tag yang telah melalui review dan catat image/version yang akan dirilis.",
        "Instal dependency production dan bangun asset Vite.",
        "Aktifkan maintenance bila perubahan berisiko atau membutuhkan migrasi panjang.",
        "Jalankan migration --force dan optimasi cache setelah environment final.",
        "Restart/recreate app, queue, scheduler, dan service yang berubah.",
        "Jalankan health check, smoke test, RBAC, API, peta, WAHA, dan transaksi.",
        "Catat hasil, rollback bila kriteria tidak terpenuhi, lalu nonaktifkan maintenance.",
    ])
    base.add_heading(doc, "9.5 Migrasi dan Rollback", 2)
    base.add_bullets(doc, [
        "Setiap migration diuji pada salinan database sebelum produksi.",
        "Rencanakan arah rollback (down) untuk setiap perubahan skema; backup menjadi jaring pengaman utama.",
        "Untuk perubahan data besar, gunakan maintenance dan verifikasi jumlah baris sebelum/ sesudah.",
        "Setelah rollback, verifikasi status migration, login, transaksi, dan integrasi.",
    ])
    base.add_heading(doc, "9.6 Kapasitas 5-20 Admin", 2)
    base.add_table(doc, ["Skenario", "CPU", "RAM", "Storage awal", "Catatan"], [
        ["5 admin", "2 vCPU", "4 GB", "60-80 GB SSD", "Web, worker, WAHA ringan; backup offsite"],
        ["10 admin", "4 vCPU", "8 GB", "100-150 GB SSD", "Pisahkan DB bila media tumbuh"],
        ["20 admin", "6-8 vCPU", "12-16 GB", "200-300 GB SSD", "DB managed/terpisah, Redis driver, monitoring"],
    ], [1.2, 0.8, 0.8, 1.35, 2.4])
    base.add_body(doc, "Sizing adalah baseline simulasi, bukan jaminan. Kapasitas aktual harus ditentukan dari jumlah session WAHA, volume media, concurrent request, ukuran database, queue throughput, retensi backup, dan hasil load test.")

    chapter(doc, "X", "Security - Keamanan Sistem", "Bab ini merangkum model ancaman, pertahanan berlapis, RBAC, autentikasi/otorisasi, enkripsi, kontrol API/webhook, secret, audit trail, dan respons insiden.")
    base.add_figure(doc, diagrams["security"], "Diagram 17. Model pertahanan berlapis SIVMI CRM")
    base.add_heading(doc, "10.1 Prinsip", 2)
    base.add_bullets(doc, [
        "Least privilege: pengguna hanya memperoleh fungsi dan data yang diperlukan.",
        "Defense in depth: kontrol diterapkan pada UI, route, query, action, database, dan integrasi.",
        "Separation of duties: pencatatan dan verifikasi pembayaran dipisahkan.",
        "Secure by default: produksi memakai APP_DEBUG=false, HTTPS, secret kuat, dan dashboard WAHA terbatas.",
        "Auditability: tindakan penting menyimpan aktor, cabang, objek, waktu, IP, dan metadata terbatas.",
    ])
    base.add_heading(doc, "10.2 RBAC dan Matriks Akses", 2)
    base.add_table(doc, ["Role", "Scope", "Pendaftaran", "Pembayaran", "Larangan penting"], [
        ["Superadmin", "Global", "Baca", "Baca", "Tidak membuat/mengubah transaksi"],
        ["Admin Cabang", "Cabang", "Baca", "Baca", "Tidak membuat/mengubah transaksi"],
        ["Staff", "Milik", "Buat/ubah", "Catat Menunggu", "Tidak verifikasi status final"],
        ["Keuangan", "Cabang", "Baca", "Catat/verifikasi/koreksi", "Tidak hapus; tidak akses sales/master"],
        ["Direksi", "Global", "Baca", "Baca", "Tidak mutasi operasional"],
    ], [1.15, 1.0, 1.25, 1.75, 1.9])
    base.add_heading(doc, "10.3 Autentikasi dan Otorisasi", 2)
    base.add_bullets(doc, [
        "Login pengguna memakai credential dan session database; akun nonaktif ditolak.",
        "Role disimpan pada users; kewenangan dikontrol policy, query scope, branch scoping, dan action guard.",
        "API Client memakai bearer token dengan key hanya disimpan sebagai SHA-256 hash dan prefix.",
        "Ability membatasi operasi (leads:read, leads:write, students:read); scope cabang membatasi record.",
    ])
    base.add_heading(doc, "10.4 Kontrol API dan Webhook", 2)
    base.add_table(doc, ["Kontrol", "Penerapan", "Ancaman"], [
        ["Token hash", "SHA-256 key_hash dan prefix", "Kebocoran token dari database"],
        ["Ability", "leads:read, leads:write, students:read", "Akses fungsi berlebihan"],
        ["Scope cabang", "Client/user/cabang pada query", "Akses lintas cabang"],
        ["Rate limit", "Public API sesuai client; webhook WAHA default 120 request/menit", "Abuse dan beban berlebih"],
        ["Idempotency", "Key, request hash, response cache", "Duplikasi akibat retry"],
        ["HMAC", "X-Webhook-Hmac SHA-512", "Webhook palsu/tampering"],
        ["Deduplikasi", "payload_hash dan external_message_id", "Replay event/pesan"],
    ], [1.45, 2.95, 2.1])
    base.add_heading(doc, "10.5 Enkripsi dan Secret", 2)
    base.add_body(doc, "Komunikasi dilindungi HTTPS/TLS; sensitive credential (APP_KEY, password database, API key WAHA, HMAC secret, token tunnel, bearer token) di-hash atau disimpan pada environment dengan permission minimum dan tidak pernah masuk Git, log, screenshot, atau dokumen distribusi umum. Secret yang pernah terekspos harus dirotasi dan session terkait dievaluasi.")
    base.add_heading(doc, "10.6 Audit Trail", 2)
    base.add_body(doc, "Aktivitas penting disimpan pada activity_logs secara polymorphic lengkap dengan aktor, cabang, objek, waktu, IP, dan metadata terbatas. Log ini dipakai untuk investigasi, verifikasi UAT, dan basis kebijakan audit. Risiko akun WhatsApp mengacu pada MITIGASI-RISIKO-BANNED-WHATSAPP-NON-OFFICIAL-SIVMI-CRM.")
    base.add_heading(doc, "10.7 Respons Insiden", 2)
    base.add_numbers(doc, [
        "Identifikasi layanan, cabang, pengguna, waktu mulai, dan tingkat dampak.",
        "Batasi dampak: nonaktifkan token/session, hentikan tunnel, atau isolasi account bila perlu.",
        "Pertahankan bukti log tanpa menyalin data sensitif secara berlebihan.",
        "Pulihkan dari konfigurasi atau backup yang tervalidasi.",
        "Rotasi secret dan verifikasi seluruh integrasi setelah pemulihan.",
        "Dokumentasikan akar masalah, tindakan permanen, dan test regresi.",
    ])

    chapter(doc, "XI", "Operation - Operasional dan Pemeliharaan", "Bab ini menjelaskan monitoring, maintenance, SOP operasional, troubleshooting, dan prosedur backup serta pemulihan.")
    base.add_heading(doc, "11.1 Monitoring", 2)
    base.add_table(doc, ["Objek", "Indikator", "Frekuensi"], [
        ["Aplikasi CRM", "Health, HTTP error, response time", "Harian"],
        ["Database", "Connection, slow query, backup, restore test", "Harian/mingguan"],
        ["WAHA", "Working session, webhook, pesan gagal", "Harian"],
        ["Queue/Scheduler", "Backlog, failed job, last run", "Harian"],
        ["Disk dan storage", "Penggunaan volume dan media", "Harian"],
    ], [1.9, 2.85, 1.75])
    base.add_heading(doc, "11.2 SOP Operasional Harian", 2)
    base.add_numbers(doc, [
        "Periksa dashboard health, status WAHA Working, queue failed, disk, dan backup terakhir.",
        "Pantau konflik pencocokan lead dan pesan yang belum terproses pada inbox.",
        "Pastikan scheduler menjalankan sinkronisasi dan reminder tepat waktu.",
        "Tindak lanjuti insiden sesuai severity (tabel 11.4) dan catat penyelesaiannya.",
    ])
    base.add_heading(doc, "11.3 SOP Backup dan Restore", 2)
    base.add_bullets(doc, [
        "Backup dilakukan sebelum deployment, migration, restore, dan perubahan data berisiko.",
        "File backup disimpan di lokasi terbatas dan disalin offsite secara terenkripsi.",
        "Restore hanya oleh Superadmin yang berwenang dan harus membuat safety backup.",
        "Setelah restore, jalankan migration status, login, dashboard, transaksi, queue, dan verifikasi data sampel.",
        "Uji restore berkala dicatat sebagai bukti, bukan hanya mengandalkan keberadaan file backup.",
    ])
    base.add_heading(doc, "11.4 SOP Insiden", 2)
    base.add_table(doc, ["Severity", "Contoh", "Respons awal"], [
        ["P1", "Aplikasi/database mati atau data lintas cabang terbuka", "Isolasi, eskalasi segera, aktifkan recovery"],
        ["P2", "WAHA/queue gagal luas atau pembayaran tidak dapat diverifikasi", "Triage dalam 30 menit, workaround, owner teknis"],
        ["P3", "Satu modul/cabang terganggu", "Catat, diagnosis, perbaiki dalam jam kerja"],
        ["P4", "Label, ekspor tertentu, bantuan penggunaan", "Masukkan backlog/support request"],
    ], [0.75, 3.5, 2.3])
    base.add_heading(doc, "11.5 Troubleshooting", 2)
    base.add_table(doc, ["Gejala", "Kemungkinan penyebab", "Tindakan"], [
        ["Pesan WAHA tidak terkirim/masuk", "WAHA lokal mati, tunnel berubah, session tidak Working", "Cek status, mulai ulang WAHA/cloudflared, sinkronkan session"],
        ["Queue menumpuk", "Worker berhenti, retry infinite, beban berat", "Periksa failed_jobs dan logs; restart worker; perbaiki job"],
        ["Peta tidak muncul", "Tile OSM diblokir, koordinat kosong, Leaflet tidak terload", "Cek konsol browser, resolver koordinat, fallback kota sekolah"],
        ["Webhook ditolak", "HMAC tidak cocok, URL lama, secret berubah", "Bandingkan secret dan URL webhook; aktifkan chat ulang"],
        ["Gagal login", "Akun nonaktif, cache/session bermasalah", "Verifikasi status akun dan session; clear cache"],
    ], [1.8, 2.7, 2.1])
    base.add_heading(doc, "11.6 Jadwal Pemeliharaan", 2)
    base.add_table(doc, ["Frekuensi", "Aktivitas"], [
        ["Harian", "Health app/WAHA, queue failed, disk, backup, session Working"],
        ["Mingguan", "Log error, konflik lead, data tidak lengkap, dependency alert, kapasitas"],
        ["Bulanan", "Review role/cabang, restore test, secret inventory, KPI/SLA, patch planning"],
        ["Triwulanan", "UAT regresi, tabletop incident, capacity review, audit data, roadmap"],
        ["Tahunan", "DR drill, review arsitektur, vendor/risk review, retensi dan kebijakan"],
    ], [1.25, 5.3])

    chapter(doc, "XII", "User - Panduan Pengguna dan Administrator", "Bab ini menyediakan panduan penggunaan untuk Staff, Keuangan, Admin Cabang, dan Superadmin.")
    base.add_heading(doc, "12.1 Panduan Staff (Lead dan Follow-up)", 2)
    base.add_numbers(doc, [
        "Masuk menggunakan akun aktif dan pastikan cabang/PIC sesuai.",
        "Buka Leads lalu pilih Buat Lead; isi nama dan nomor WhatsApp; lengkapi atribut bila tersedia.",
        "Cari sekolah melalui API atau buat sekolah manual bila tidak ditemukan.",
        "Simpan; jika nomor sudah digunakan, buka lead yang sudah ada dan jangan membuat duplikat.",
        "Buka lead atau daftar Follow-up sesuai scope; pilih metode dan catat status kontak.",
        "Tentukan jadwal berikutnya jika proses belum selesai; perbarui status berdasarkan bukti komunikasi.",
    ])
    base.add_heading(doc, "12.2 Panduan Keuangan (Verifikasi Pembayaran)", 2)
    base.add_numbers(doc, [
        "Buka menu Pembayaran pada scope cabang; pastikan data pendaftaran tersedia.",
        "Cocokkan pendaftaran, nominal, metode, referensi, dan bukti di luar sistem bila berlaku.",
        "Ubah status menjadi Terverifikasi/Lunas atau Ditolak dengan catatan koreksi.",
        "Tidak menghapus pembayaran dan tidak membuka modul sales/master.",
        "Pantau daftar pembayaran yang masih Menunggu untuk ditindaklanjuti.",
    ])
    base.add_heading(doc, "12.3 Panduan Admin Cabang", 2)
    base.add_numbers(doc, [
        "Kelola pengguna cabang, target kinerja, dan data master cabang.",
        "Siapkan akun WhatsApp dan session untuk pengguna cabang (lihat alur session pada bab III).",
        "Pantau performa tim melalui laporan dan target; koreksi kualitas data cabang.",
        "Tidak membuat atau mengubah transaksi pembayaran (baca-saja).",
    ])
    base.add_heading(doc, "12.4 Panduan Superadmin", 2)
    base.add_numbers(doc, [
        "Kelola konfigurasi aplikasi, cabang, pengguna, integrasi, dan audit.",
        "Lakukan backup sebelum perubahan besar; restore hanya pada kondisi terverifikasi.",
        "Pantau health, secret inventory, dan quality gate sebelum rilis.",
        "Beri akses sesuai least privilege dan lakukan review berkala.",
    ])
    base.add_heading(doc, "12.5 Referensi Panduan", 2)
    base.add_bullets(doc, [
        "BUKU-PANDUAN-PENGGUNA-CRM-VILLA-MERAH-v1.0",
        "PANDUAN-PENGGUNA-LENGKAP-CRM-VILLA-MERAH-v1.0/1.1/1.2",
        "SOP-DAN-MATERI-PELATIHAN-USER-SIVMI-CRM",
        "Panduan integrasi: PANDUAN-KONFIGURASI-WAHA, PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS, dan PANDUAN-INTEGRASI-API-DATA-SISWA",
    ])

    chapter(doc, "XIII", "Governance - Tata Kelola IT", "Bab ini menjelaskan ownership layanan, change management, service management, register risiko, SLA/SLO, audit dan kontrol.")
    base.add_heading(doc, "13.1 Ownership Layanan", 2)
    base.add_table(doc, ["Layanan", "Owner yang disarankan", "Indikator"], [
        ["Aplikasi CRM", "Technical Lead", "Health, HTTP error, response time"],
        ["Database", "Database Owner", "Connection, slow query, backup, restore test"],
        ["WAHA", "Integration Owner", "Working session, webhook, pesan gagal"],
        ["Queue/Scheduler", "Operations Owner", "Backlog, failed job, last run"],
        ["Keamanan", "Security Owner", "Review access, unauthorized, secret rotation"],
        ["Data/KPI", "Product Owner", "Freshness, kualitas, definisi metrik"],
        ["Dukungan", "Service Desk/Branch Owner", "Response dan resolution time"],
    ], [1.55, 2.2, 2.8])
    base.add_heading(doc, "13.2 Change Management", 2)
    base.add_body(doc, "Perubahan migration, role, payment workflow, API contract, webhook, WAHA, environment, dan deployment harus memiliki owner, alasan, dampak, risiko, test plan, rollback plan, waktu pelaksanaan, approver, dan hasil verifikasi. Emergency change tetap wajib direkonstruksi sesudah pemulihan. Detail tambahan tersedia pada GOVERNANCE-DAN-SERVICE-MANAGEMENT-SIVMI-CRM.")
    base.add_heading(doc, "13.3 Service Management", 2)
    base.add_bullets(doc, [
        "Katalog layanan: aplikasi, database, WAHA, integrasi, dan dukungan pengguna.",
        "Eskalasi: P1 segera, P2 dalam 30 menit, P3 dalam jam kerja, P4 backlog.",
        "Rilis direncanakan pada jendela waktu dengan komunikasi dan owner on-call.",
        "Dokumentasi setiap insiden dan resolusi sebagai knowledge base.",
    ])
    base.add_heading(doc, "13.4 Register Risiko", 2)
    base.add_table(doc, ["ID", "Risiko", "Level", "Mitigasi"], [
        ["R-01", "WAHA lokal atau tunnel berhenti", "Tinggi", "Auto-start, monitoring, UPS, target VPS"],
        ["R-02", "Queue shared hosting tidak permanen", "Tinggi", "Cron dengan flock; target worker permanen"],
        ["R-03", "Secret/token terekspos", "Tinggi", "Permission, rotasi, tidak commit, audit"],
        ["R-04", "Duplikasi/kualitas data buruk", "Sedang", "Normalisasi, unique key, validation, review"],
        ["R-05", "API Sekolah/OSM tidak tersedia", "Sedang", "Cache, timeout, fallback, aset lokal"],
        ["R-06", "Backup tidak dapat direstore", "Tinggi", "Restore drill dan backup offsite"],
        ["R-07", "Regresi setelah perubahan lintas modul", "Sedang", "Full suite, build, smoke test, UAT, dan rollback plan"],
    ], [0.7, 2.75, 0.85, 2.25])
    base.add_heading(doc, "13.5 SLA dan SLO", 2)
    base.add_table(doc, ["Layanan", "SLA/SLO target (rekomendasi)", "Metrik"], [
        ["Aplikasi CRM", "Uptime 99% mulai VPS", "Health check dan incident duration"],
        ["Response insiden P1/P2", "Mulai tanggap ≤ 30 menit", "Triage time"],
        ["Restore database", "RPO ≤ 24 jam; RTO sesuai kemampuan restore drill", "Backup freshness dan restore time"],
        ["Perbaikan bug kritis", "Dijadwalkan pada rilis berikutnya", "Backlog dan release cycle"],
    ], [1.7, 2.75, 2.1])
    base.add_heading(doc, "13.6 Audit dan Kontrol", 2)
    base.add_body(doc, "Audit dilakukan berbasis activity_logs, riwayat Git, hasil test, backup/restore drill, dan review role. Kontrol berkala mencakup review akses, inventaris secret, kualitas data, ketergantungan dependency, dan kepatuhan pada SOP serta kebijakan rilis.")

    chapter(doc, "XIV", "Knowledge Transfer - Transfer Pengetahuan dan Serah Terima", "Bab ini menyediakan daftar materi pelatihan, rencana transfer pengetahuan, akses repository dan lingkungan, serta prosedur serah terima.")
    base.add_heading(doc, "14.1 Daftar Materi Transfer", 2)
    base.add_table(doc, ["Materi", "Tujuan", "Audiens"], [
        ["BUKU-PANDUAN-PENGGUNA-CRM-VILLA-MERAH-v1.0", "Cara memakai fitur harian", "Staff, Admin"],
        ["PANDUAN-PENGGUNA-LENGKAP-CRM-VILLA-MERAH-v1.0/1.1/1.2", "Panduan lengkap setelan user", "Semua role"],
        ["SOP-DAN-MATERI-PELATIHAN-USER-SIVMI-CRM", "Standar operasional dan pelatihan", "Operator, trainer"],
        ["PANDUAN-KONFIGURASI-WAHA", "Konfigurasi dan session WhatsApp", "Admin, TI"],
        ["PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS", "Topologi WAHA lokal + tunnel", "TI"],
        ["PANDUAN-INTEGRASI-API-DATA-SISWA / SPESIFIKASI-REST-API-SIVMI-CRM-v1", "Kontrak dan penggunaan API", "Integrator"],
        ["PANDUAN-INSTALASI-PERTAMA-LARAGON-DOCKER", "Instalasi lingkungan development", "Developer"],
        ["RELEASE-CHECKLIST-DAN-VERIFIKASI-SIVMI-CRM", "Verifikasi sebelum rilis", "QA, TI"],
        ["Dokumentasi ini (15 bab)", "Baseline sistem dan operasional", "Seluruh pemangku kepentingan"],
    ], [2.6, 2.4, 1.55])
    base.add_heading(doc, "14.2 Rencana Pelatihan", 2)
    base.add_table(doc, ["Sesi", "Topik", "Durasi (rekomendasi)"], [
        ["1", "Konsep sistem, dashboard, dan navigasi", "1 jam"],
        ["2", "Lead, follow-up, dan alur konversi (praktik)", "2 jam"],
        ["3", "Pendaftaran dan verifikasi pembayaran untuk Keuangan", "1,5 jam"],
        ["4", "Manajemen cabang, target, dan laporan untuk Admin", "2 jam"],
        ["5", "Konfigurasi WAHA dan tunnel untuk TI", "2 jam"],
        ["6", "Backup, restore, dan penanganan insiden dasar", "1 jam"],
    ], [0.7, 3.4, 2.45])
    base.add_heading(doc, "14.3 Akses Repository dan Lingkungan", 2)
    base.add_bullets(doc, [
        "Repository: github.com/inifauzan-maker/crm.git, branch main; remote origin.",
        "Struktur dokumentasi dan skrip generator tersedia pada folder docs/ dan docs/tools/.",
        "Environment development/produksi disimpan terpisah dari repository; kredensial diberikan berdasar kebutuhan.",
        "Postman collection dan template request publik tersedia pada folder postman/.",
        "Dokumen build terbaru dibangkitkan dari docs/tools/build_documentation_v4_standard.py.",
    ])
    base.add_heading(doc, "14.4 Prosedur Serah Terima", 2)
    base.add_numbers(doc, [
        "Susun inventaris: repository, branch, credentials, environment, URL produksi, dan daftar owner.",
        "Salin dokumen baseline dan panduan; verifikasi build lokal dapat direproduksi.",
        "Jalankan pelatihan sesuai 14.2 dan serahkan materi setelah sesi selesai.",
        "Jalankan UAT/checklist rilis bersama penerima; tanda tangani hasil.",
        "Serahkan backup valid dan prosedur restore; dokumentasikan RPO/RTO aktual.",
        "Catat daftar kontak dan pembagian tanggung jawab (ownership) versi baru.",
    ])
    base.add_heading(doc, "14.5 Kontak dan Owner", 2)
    base.add_body(doc, "Daftar kontak operasional (support, database, keamanan, integrasi, product owner) mengikuti tabel ownership pada 13.1 dan harus diperbarui setiap kali terjadi perubahan orang. Informasi kontak faktual disimpan terpisah dari dokumen ini untuk menghindari paparan data pribadi.")

    chapter(doc, "XV", "Improvement - Evaluasi, Roadmap, dan Perubahan", "Bab ini menyajikan evaluasi, lessons learned, technical debt, prioritas tindak lanjut, roadmap, dan kriteria evolusi arsitektur.")
    base.add_figure(doc, diagrams["roadmap"], "Diagram 18. Roadmap penguatan SIVMI CRM")
    base.add_heading(doc, "15.1 Evaluasi dan Lessons Learned", 2)
    base.add_bullets(doc, [
        "Modular monolith terbukti cukup untuk domain CRM vertikal; container pendukung tidak otomatis merupakan microservices.",
        "Kualitas data bergantung pada normalisasi nomor dan unique key; perubahan disiplin data mengurangi duplikasi.",
        "Integrasi non-resmi (WAHA) adalah risiko operasional utama sehingga perlu monitoring, backup session, dan target VPS.",
        "Test suite menjadi alat pengendali regresi; baseline 20 September 2026 lulus penuh dan harus tetap dijalankan sebelum setiap rilis.",
        "Dokumentasi as-built yang dibangun dari kode dan konfigurasi terbukti lebih akurat daripada dokumen manual.",
    ])
    base.add_heading(doc, "15.2 Technical Debt", 2)
    base.add_table(doc, ["Utang teknis", "Dampak", "Rencana pelunasan"], [
        ["Dua migration memakai prefix timestamp yang sama", "Urutan historis kurang jelas saat audit", "Jangan mengganti nama migration yang sudah diterapkan; gunakan timestamp unik untuk migration berikutnya"],
        ["Worker di shared hosting tidak permanen", "Queue bisa berhenti", "Cron dengan flock; pindah ke VPS worker"],
        ["WAHA berjalan pada komputer lokal", "Ketersediaan tidak terjamin", "Named tunnel, auto-start, target VPS"],
        ["Redis belum menjadi driver utama", "State memakai database", "Benchmark lalu migrasi bertahap"],
        ["Belum ada tag versi", "Rilis sulit direkonstruksi", "Adopsi semantic versioning dan tag"],
    ], [2.5, 2.15, 1.9])
    base.add_heading(doc, "15.3 Prioritas 0-30 Hari", 2)
    base.add_bullets(doc, [
        "Pertahankan quality gate 118 test/739 assertion dan tambah regresi untuk setiap bug produksi.",
        "Jalankan UAT role Keuangan, transaksi, API siswa, dan URL langsung.",
        "Aktifkan Named Tunnel waha.sivmi.id serta uji restart komputer lokal.",
        "Pastikan queue/scheduler server berjalan dan mempunyai monitoring.",
        "Lakukan backup serta restore drill dan dokumentasikan RPO/RTO aktual.",
        "Tentukan kebijakan Git untuk dokumentasi penting di folder docs.",
    ])
    base.add_heading(doc, "15.4 Prioritas 1-3 Bulan", 2)
    base.add_bullets(doc, [
        "Pindahkan deployment penuh ke VPS Docker agar worker dan WAHA selalu aktif.",
        "Aktifkan Redis sebagai driver terukur setelah benchmark dan rencana migrasi state.",
        "Tambahkan monitoring terpusat untuk error, queue, webhook, backup, disk, dan uptime.",
        "Formalize SLA/SLO, incident register, change approval, dan ownership layanan.",
        "Tambahkan API outbound atau webhook siswa bila sistem tujuan memerlukan push near-real-time.",
    ])
    base.add_heading(doc, "15.5 Prioritas 3-12 Bulan", 2)
    base.add_bullets(doc, [
        "Lakukan load test berbasis pola penggunaan nyata dan media WhatsApp.",
        "Pisahkan database atau storage bila pertumbuhan, keamanan, atau availability membutuhkannya.",
        "Bangun data quality dashboard dan alert untuk duplikasi, kelengkapan, dan konflik.",
        "Evaluasi WhatsApp Business Platform resmi untuk proses yang kritis atau berskala tinggi.",
        "Pertimbangkan pemisahan service hanya jika ada kebutuhan skala, isolasi, atau ownership tim yang terukur.",
    ])
    base.add_heading(doc, "15.6 Kriteria Keputusan Microservices", 2)
    base.add_body(doc, "Microservices belum direkomendasikan hanya karena aplikasi menggunakan beberapa container. Pemisahan service baru layak ketika satu domain membutuhkan skala, availability, data ownership, keamanan, atau siklus rilis yang berbeda; tim mampu mengelola observability, distributed transaction, API governance, deployment independen, dan biaya operasional tambahan.")
    base.add_heading(doc, "15.7 Riwayat Versioning", 2)
    base.add_table(doc, ["Dokumen baseline", "Fokus", "Tanggal"], [
        ["BAB I-XI v1.0", "Dokumentasi awal departemen", "2026-08-26 s.d. 2026-09-01"],
        ["Dokumen Kerja CRM 01092026", "Penyatuan draft dan daftar isi", "2026-09-01"],
        ["V2.1 Final", "API sekolah, peta, kualitas data", "2026-09-01 s.d. 2026-09-02"],
        ["V2.2", "Testing, debugging, dan security", "2026-09-08"],
        ["V2.3", "Perluasan API siswa, checklist rilis", "2026-09-08"],
        ["3.0", "Struktur standar 15 bab as-built", "2026-09-17"],
        ["2.4 / 4.0 (dokumen ini)", "Quality gate penuh, penguatan WAHA, keamanan, dan dokumentasi terbaru", "2026-09-20"],
    ], [2.0, 3.3, 1.25])

    chapter(doc, "LAMPIRAN A", "Matriks Konfigurasi Environment", "Lampiran ini membedakan konfigurasi lokal, server, dan rahasia yang tidak boleh masuk repository.")
    base.add_table(doc, ["Variabel", "Lokal full Docker", "Server/produksi", "Rahasia"], [
        ["APP_URL", "http://localhost:8000", "https://crm.sivmi.id", "Tidak"],
        ["APP_DEBUG", "true", "false", "Tidak"],
        ["DB_HOST", "database", "Host database produksi", "Sebagian"],
        ["QUEUE_CONNECTION", "database", "database/redis sesuai arsitektur", "Tidak"],
        ["WAHA_BASE_URL", "http://waha:3000", "https://waha.sivmi.id", "Tidak"],
        ["WAHA_API_KEY", "Unik development", "Unik production", "Ya"],
        ["WAHA_WEBHOOK_URL", "http://web/api/webhooks/waha", "https://crm.sivmi.id/api/webhooks/waha", "Tidak"],
        ["WAHA_WEBHOOK_HMAC_SECRET", "Unik development", "Unik production", "Ya"],
        ["SCHOOL_API_URL", "https://sekolah.devapi.id/sekolah", "Sama atau proxy resmi", "Tidak"],
        ["PUBLIC_API_ENABLED", "false default", "true bila digunakan", "Tidak"],
    ], [1.75, 1.9, 2.3, 0.65])

    chapter(doc, "LAMPIRAN B", "Glosarium", "Lampiran ini menyamakan istilah yang dipakai di seluruh dokumen.")
    base.add_table(doc, ["Istilah", "Definisi"], [
        ["Lead", "Calon siswa atau calon pelanggan yang masih berada pada pipeline."],
        ["PIC", "Pengguna yang bertanggung jawab menindaklanjuti lead."],
        ["RBAC", "Role Based Access Control untuk membatasi fungsi berdasarkan peran."],
        ["Scope cabang", "Pembatasan record berdasarkan cabang pengguna atau API Client."],
        ["Idempotensi", "Jaminan bahwa retry request tidak membuat efek ganda."],
        ["HMAC", "Signature berbasis secret untuk memverifikasi integritas dan asal webhook."],
        ["GOWS", "Engine WAHA berbasis Go untuk koneksi WhatsApp."],
        ["Named Tunnel", "Cloudflare Tunnel dengan hostname DNS tetap."],
        ["Modular monolith", "Satu aplikasi dan codebase dengan domain yang dipisahkan secara logis."],
        ["RPO/RTO", "Target kehilangan data maksimum dan waktu pemulihan maksimum."],
        ["SLA/SLO", "Perjanjian dan target tingkat layanan yang dapat diukur."],
        ["UAT", "User Acceptance Test untuk menerima fungsi dari sisi pengguna."],
    ], [1.55, 5.0])

    chapter(doc, "LAMPIRAN C", "Struktur Fisik Tabel - Kamus Data", "Lampiran ini merangkum tipe data, constraint, dan relasi fisik tiap tabel sesuai migration as-built.")
    base.add_table(doc, ["Tabel", "Kolom atau constraint penting"], [
        ["cabang", "PK cabang_id BIGINT AUTO_INCREMENT; UQ kode_cabang VARCHAR(20); nama VARCHAR(150); kota VARCHAR(100) NULL; alamat TEXT; no_telepon, email; aktif TINYINT default 1; timestamps."],
        ["users", "PK id BIGINT; UQ email VARCHAR(255); FK cabang_id NULL -> cabang; name; role VARCHAR(30); password hash; aktif TINYINT default 1; timestamps."],
        ["sekolah", "PK sekolah_id BIGINT; UQ npsn VARCHAR(20) NULL; nama_sekolah VARCHAR(200); kota, provinsi, kecamatan NULL; grade; api_reference; sumber_data default 'manual'; timestamps."],
        ["program_bimbel", "PK program_bimbel_id; kategori_program, program_kelas, tipe_program, metode_belajar; nama_program VARCHAR(150); jenjang_program VARCHAR(30); biaya_pendidikan DECIMAL(15,2); biaya_pendaftaran DECIMAL; diskon DECIMAL; aktif."],
        ["sumber_leads", "PK sumber_lead_id; UQ kode, UQ nama; warna; urutan; aktif; timestamps."],
        ["leads", "PK lead_id; UK no_wa_normalized VARCHAR(20); FK sekolah_id, minat_program_id, sumber_lead_id, cabang_id, diinput_oleh_user_id, diserahkan_ke_user_id; nama, nama_ortu; status_lead, kanal_masuk VARCHAR(30); tanggal_masuk DATE; kelas, km, jadwal_pertemuan; catatan; timestamps; indeks gabungan untuk filter umum."],
        ["followup_riwayat", "PK followup_riwayat_id; FK lead_id -> leads ON DELETE CASCADE; FK user_id; tanggal_followup DATETIME; metode_followup; status_kontak; catatan; tanggal_followup_berikutnya DATETIME NULL; timestamps."],
        ["pendaftaran", "PK pendaftaran_id; FK lead_id UQ; FK program_bimbel_id; FK cabang_id; tanggal_daftar DATE; angkatan, status_pendaftaran; catatan; timestamps."],
        ["pembayaran", "PK pembayaran_id; FK pendaftaran_id -> pendaftaran ON DELETE CASCADE; tanggal_pembayaran DATETIME; nominal DECIMAL(15,2); metode_pembayaran; status_pembayaran; nomor_referensi; diinput_oleh_user_id; diverifikasi_oleh_user_id; catatan; timestamps."],
        ["target_kinerja_cabang", "PK target_kinerja_cabang_id; FK cabang_id; UQ (cabang_id, tahun, bulan); target_omzet DECIMAL(15,2); target_siswa INT; FK created_by NULL -> users; timestamps."],
        ["target_kinerja_user", "PK target_kinerja_user_id; FK target_kinerja_cabang_id; FK user_id; UQ (target_kinerja_cabang_id, user_id); target_omzet; target_siswa; timestamps."],
        ["user_whatsapp_accounts", "PK id; FK user_id UQ -> users; FK cabang_id; UQ phone_e164 VARCHAR(20); UQ session_name VARCHAR(100); status enum(draft, starting, working, failed); aktif; qr_created_at; last_error; timestamps."],
        ["whatsapp_conversations", "PK id; FK whatsapp_account_id; FK lead_id NULL -> leads; UQ (whatsapp_account_id, chat_id); unread_count INT; match_status; last_message_at; timestamps."],
        ["whatsapp_messages", "PK id; FK conversation_id -> whatsapp_conversations; FK whatsapp_account_id; FK lead_id NULL; UQ (whatsapp_account_id, external_message_id); direction, body TEXT, media JSON NULL; processing_status; timestamps."],
        ["waha_webhook_events", "PK id; UQ payload_hash CHAR(64); event_name, session_name; status default 'pending'; payload JSON; attempts INT; error; received_at; timestamps."],
        ["api_clients", "PK id; FK user_id -> users; FK cabang_id NULL -> cabang; UQ key_hash CHAR(64); key_prefix; name; abilities JSON; rate_limit json; aktif; timestamps."],
        ["api_idempotency_keys", "PK id; FK api_client_id -> api_clients; UQ (api_client_id, key VARCHAR(120)); request_hash CHAR(64); response_status; expires_at TIMESTAMP; timestamps."],
        ["activity_logs", "PK activity_log_id; FK user_id NULL, cabang_id NULL; subject_type, subject_id; event VARCHAR(100); description TEXT; old_value, new_value JSON NULL; ip_address; user_agent; timestamps."],
        ["notifications", "PK id UUID; notifiable_type, notifiable_id; type VARCHAR(255); data JSON; read_at NULL; timestamps."],
        ["crm_settings", "PK key VARCHAR(100); value TEXT; type; group VARCHAR(50); FK updated_by NULL -> users; timestamps."],
        ["imports / exports", "PK id; FK user_id; importer/exporter; file_name; total_rows; successful_rows; status; started_at, finished_at; data JSON."],
        ["failed_import_rows", "PK id; FK import_id -> imports; data JSON; validation_error."],
    ], [2.0, 5.55])

    base.add_heading(doc, "Referensi Internal", 2)
    base.add_bullets(doc, [
        "README.md dan docker/README.md",
        "docs/PROFIL-SISTEM-DAN-INFRASTRUKTUR-TERBARU.md",
        "docs/DEPLOYMENT.md",
        "docs/SPESIFIKASI-REST-API-SIVMI-CRM-v1.md",
        "docs/PANDUAN-INTEGRASI-API-DATA-SISWA.md",
        "docs/PANDUAN-AKTIVASI-WAHA-GOWS-LOKAL-DENGAN-TUNNEL-DNS.md",
        "docs/RELEASE-CHECKLIST-DAN-VERIFIKASI-SIVMI-CRM.md",
        "Dokumen pendukung: BUKU-PANDUAN-PENGGUNA, PANDUAN-PENGGUNA-LENGKAP, SOP-DAN-MATERI-PELATIHAN, KAMUS-DATA, TABEL-DATABASE, GOVERNANCE-DAN-SERVICE-MANAGEMENT, MITIGASI-RISIKO-BANNED-WHATSAPP",
        "database/migrations, routes, config, app, tests, composer.lock, package-lock.json, dan Docker Compose",
    ])
    base.add_heading(doc, "Referensi Eksternal", 2)
    base.add_bullets(doc, [
        "Laravel 12 Documentation - https://laravel.com/docs/12.x",
        "Filament 5 Documentation - https://filamentphp.com/docs",
        "WAHA Documentation - https://waha.devlike.pro/docs/overview/quick-start/",
        "Cloudflare Tunnel Documentation - https://developers.cloudflare.com/cloudflare-one/networks/connectors/cloudflare-tunnel/",
        "OpenStreetMap Tile Usage Policy - https://operations.osmfoundation.org/policies/tiles/",
        "OWASP Application Security Verification Standard - https://owasp.org/www-project-application-security-verification-standard/",
    ])

    properties = doc.core_properties
    properties.title = "Dokumentasi Project SIVMI CRM - Standar 15 Bab 2026"
    properties.subject = "Dokumentasi bisnis, requirement, process, architecture, data, application, development, testing, deployment, security, operation, user, governance, knowledge transfer, dan improvement"
    properties.author = "SIVMI CRM"
    properties.keywords = "SIVMI CRM, Laravel, Filament, WAHA, REST API, arsitektur, keamanan, SOP, standar dokumentasi"
    properties.comments = "Baseline as-built 20 September 2026"
    update_fields = OxmlElement("w:updateFields")
    update_fields.set(qn("w:val"), "true")
    doc.settings.element.append(update_fields)
    doc.save(DOCX_OUT)


def main() -> None:
    base.ASSET_DIR = ASSET_DIR
    ASSET_DIR.mkdir(parents=True, exist_ok=True)
    base.DRAWIO_OUT = DRAWIO_OUT
    diagrams = {
        "context": base.save_system_context(),
        "business": base.save_business_flow(),
        "application": base.save_application_architecture(),
        "data": base.save_data_model(),
        "api": base.save_api_flow(),
        "waha": base.save_waha_flow(),
        "deployment": base.save_deployment(),
        "security": base.save_security(),
        "testing": base.save_testing(),
        "roadmap": base.save_roadmap(),
        **extra.save_all(),
    }
    base.build_drawio()
    extra.append_drawio_pages(DRAWIO_OUT)
    build_docx(diagrams)
    print(f"DOCX: {DOCX_OUT}")
    print(f"DRAWIO: {DRAWIO_OUT}")


if __name__ == "__main__":
    main()
