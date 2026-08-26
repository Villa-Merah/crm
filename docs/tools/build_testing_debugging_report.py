from __future__ import annotations

from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "storage" / "app" / "doc-tools"))

from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Inches, Pt, RGBColor


OUTPUT = ROOT / "docs" / "LAPORAN-TESTING-DAN-DEBUGGING-SIVMI-CRM-FINAL-2026-08-26.docx"

PRIMARY = "1476B8"
PRIMARY_DARK = "075985"
INK = "0B2545"
MUTED = "64748B"
PALE_BLUE = "EAF4FB"
PALE_GREEN = "ECFDF5"
PALE_AMBER = "FFFBEB"
PALE_ROSE = "FFF1F2"
GREEN = "10B981"
AMBER = "F59E0B"
ROSE = "F43F5E"
WHITE = "FFFFFF"
LIGHT = "F6F8FC"
BORDER = "D7E1EC"


def set_cell_shading(cell, fill: str) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_border(cell, color: str = BORDER, size: str = "6") -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    borders = tc_pr.first_child_found_in("w:tcBorders")
    if borders is None:
        borders = OxmlElement("w:tcBorders")
        tc_pr.append(borders)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        tag = "w:" + edge
        element = borders.find(qn(tag))
        if element is None:
            element = OxmlElement(tag)
            borders.append(element)
        element.set(qn("w:val"), "single")
        element.set(qn("w:sz"), size)
        element.set(qn("w:color"), color)


def set_cell_margin(cell, top=90, start=110, bottom=90, end=110) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for key, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{key}"))
        if node is None:
            node = OxmlElement(f"w:{key}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def keep_with_next(paragraph, value=True) -> None:
    p_pr = paragraph._p.get_or_add_pPr()
    node = p_pr.find(qn("w:keepNext"))
    if value and node is None:
        p_pr.append(OxmlElement("w:keepNext"))
    elif not value and node is not None:
        p_pr.remove(node)


def set_repeat_table_header(row) -> None:
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def set_row_cant_split(row) -> None:
    tr_pr = row._tr.get_or_add_trPr()
    tr_pr.append(OxmlElement("w:cantSplit"))


def set_col_widths(table, widths_cm: list[float]) -> None:
    for row in table.rows:
        for idx, width in enumerate(widths_cm):
            if idx < len(row.cells):
                row.cells[idx].width = Cm(width)


def style_run(run, *, size=9.2, bold=False, color=INK, font="Aptos") -> None:
    run.font.name = font
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = RGBColor.from_string(color)


def add_text(document, text: str, *, bold=False, color=INK, size=9.4,
             align=None, before=0, after=5, line=1.08):
    p = document.add_paragraph()
    p.paragraph_format.space_before = Pt(before)
    p.paragraph_format.space_after = Pt(after)
    p.paragraph_format.line_spacing = line
    if align is not None:
        p.alignment = align
    style_run(p.add_run(text), size=size, bold=bold, color=color)
    return p


def add_heading(document, text: str, level: int = 1):
    p = document.add_paragraph()
    p.paragraph_format.space_before = Pt(10 if level == 1 else 7)
    p.paragraph_format.space_after = Pt(5)
    keep_with_next(p)
    sizes = {1: 16, 2: 12.5, 3: 10.5}
    colors = {1: PRIMARY_DARK, 2: PRIMARY, 3: INK}
    style_run(p.add_run(text), size=sizes[level], bold=True, color=colors[level])
    if level == 1:
        p_pr = p._p.get_or_add_pPr()
        border = OxmlElement("w:pBdr")
        bottom = OxmlElement("w:bottom")
        bottom.set(qn("w:val"), "single")
        bottom.set(qn("w:sz"), "10")
        bottom.set(qn("w:space"), "4")
        bottom.set(qn("w:color"), PRIMARY)
        border.append(bottom)
        p_pr.append(border)
    return p


def add_bullets(document, items: list[str], *, color=INK):
    for item in items:
        p = document.add_paragraph(style="List Bullet")
        p.paragraph_format.left_indent = Cm(0.45)
        p.paragraph_format.first_line_indent = Cm(-0.2)
        p.paragraph_format.space_after = Pt(2.5)
        p.paragraph_format.line_spacing = 1.05
        style_run(p.add_run(item), size=9.2, color=color)


def add_numbered(document, items: list[str]):
    for item in items:
        p = document.add_paragraph(style="List Number")
        p.paragraph_format.left_indent = Cm(0.55)
        p.paragraph_format.first_line_indent = Cm(-0.25)
        p.paragraph_format.space_after = Pt(3)
        style_run(p.add_run(item), size=9.2)


def add_code(document, lines: list[str]):
    table = document.add_table(rows=1, cols=1)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    cell = table.cell(0, 0)
    set_cell_shading(cell, "0F172A")
    set_cell_border(cell, "0F172A")
    set_cell_margin(cell, 110, 140, 110, 140)
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    p.paragraph_format.line_spacing = 1.03
    for idx, line in enumerate(lines):
        run = p.add_run(line + ("\n" if idx < len(lines) - 1 else ""))
        style_run(run, size=7.7, color="E2E8F0", font="Cascadia Mono")
    document.add_paragraph().paragraph_format.space_after = Pt(0)
    return table


def add_table(document, headers: list[str], rows: list[list[str]], widths: list[float], *, compact=False):
    table = document.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    header = table.rows[0]
    set_repeat_table_header(header)
    set_row_cant_split(header)
    for idx, heading in enumerate(headers):
        cell = header.cells[idx]
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        set_cell_shading(cell, PRIMARY_DARK)
        set_cell_border(cell, PRIMARY_DARK)
        set_cell_margin(cell, 75, 90, 75, 90)
        p = cell.paragraphs[0]
        p.paragraph_format.space_after = Pt(0)
        style_run(p.add_run(heading), size=8.0 if compact else 8.3, bold=True, color=WHITE)
    for r_idx, values in enumerate(rows):
        row = table.add_row()
        set_row_cant_split(row)
        for idx, value in enumerate(values):
            cell = row.cells[idx]
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.TOP
            set_cell_shading(cell, WHITE if r_idx % 2 == 0 else LIGHT)
            set_cell_border(cell)
            set_cell_margin(cell, 65 if compact else 80, 85, 65 if compact else 80, 85)
            p = cell.paragraphs[0]
            p.paragraph_format.space_after = Pt(0)
            p.paragraph_format.line_spacing = 1.0
            style_run(p.add_run(str(value)), size=7.7 if compact else 8.1)
    set_col_widths(table, widths)
    document.add_paragraph().paragraph_format.space_after = Pt(0)
    return table


def add_callout(document, title: str, body: str, *, fill=PALE_BLUE, color=PRIMARY_DARK):
    table = document.add_table(rows=1, cols=1)
    cell = table.cell(0, 0)
    set_cell_shading(cell, fill)
    set_cell_border(cell, color, "10")
    set_cell_margin(cell, 115, 135, 115, 135)
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(2)
    style_run(p.add_run(title + "\n"), size=9.5, bold=True, color=color)
    style_run(p.add_run(body), size=8.8, color=INK)
    document.add_paragraph().paragraph_format.space_after = Pt(0)
    return table


def add_page_break(document) -> None:
    # Biarkan Word melakukan pagination alami. Page break manual setelah tabel
    # berisiko menghasilkan halaman yang sangat kosong ketika tabel telah pindah.
    return None


def configure_document() -> Document:
    doc = Document()
    section = doc.sections[0]
    section.page_width = Cm(21.0)
    section.page_height = Cm(29.7)
    section.top_margin = Cm(1.45)
    section.bottom_margin = Cm(1.45)
    section.left_margin = Cm(1.55)
    section.right_margin = Cm(1.55)
    section.header_distance = Cm(0.55)
    section.footer_distance = Cm(0.55)

    normal = doc.styles["Normal"]
    normal.font.name = "Aptos"
    normal.font.size = Pt(9.2)
    normal.font.color.rgb = RGBColor.from_string(INK)

    for section in doc.sections:
        header = section.header
        p = header.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
        style_run(p.add_run("SIVMI CRM  /  LAPORAN QA  /  25 AGUSTUS 2026"), size=7.4, bold=True, color=MUTED)
        footer = section.footer
        p = footer.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        style_run(p.add_run("Dokumen internal • Kredensial dan secret tidak dicantumkan • Halaman "), size=7.2, color=MUTED)
        fld = OxmlElement("w:fldSimple")
        fld.set(qn("w:instr"), "PAGE")
        p._p.append(fld)
    return doc


def add_masthead(doc: Document) -> None:
    table = doc.add_table(rows=1, cols=2)
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.autofit = False
    left, right = table.rows[0].cells
    set_cell_shading(left, PRIMARY_DARK)
    set_cell_shading(right, PRIMARY)
    set_cell_border(left, PRIMARY_DARK)
    set_cell_border(right, PRIMARY)
    set_cell_margin(left, 200, 190, 200, 190)
    set_cell_margin(right, 200, 170, 200, 170)
    left.width = Cm(12.2)
    right.width = Cm(5.3)
    p = left.paragraphs[0]
    p.paragraph_format.space_after = Pt(3)
    style_run(p.add_run("SIVMI CRM\n"), size=10, bold=True, color="BAE6FD")
    style_run(p.add_run("LAPORAN TESTING\nDAN DEBUGGING"), size=22, bold=True, color=WHITE)
    p = right.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    style_run(p.add_run("STATUS\n"), size=8, bold=True, color="BAE6FD")
    style_run(p.add_run("LULUS\n"), size=18, bold=True, color=WHITE)
    style_run(p.add_run("26 Agustus 2026\nBaseline final lokal"), size=8.1, color=WHITE)
    add_text(doc, "Memo teknis QA — hasil akhir setelah perbaikan test form Filament/Livewire", bold=True,
             color=PRIMARY_DARK, size=10.5, before=7, after=3)
    add_text(doc, "Lingkup: source code, test otomatis, build frontend, migrasi, service Docker, HTTP smoke test, WAHA, queue, dan log runtime.",
             color=MUTED, size=8.8, after=8)


def build() -> None:
    doc = configure_document()
    add_masthead(doc)

    add_heading(doc, "1. Ringkasan Eksekutif", 1)
    add_callout(
        doc,
        "KESIMPULAN: BASELINE SIAP DILANJUTKAN UNTUK PENGEMBANGAN LOKAL",
        "Suite lokal terbaru lulus 83/83 test dengan 499 assertion. Build frontend Vite 8.2.1 berhasil, composer.json valid, dan empat route API aktif terpetakan. Verifikasi Docker penuh tidak dapat diulang pada sesi 26 Agustus karena akses daemon tidak tersedia; bukti Docker 72 test/448 assertion tanggal 24 Agustus dipertahankan sebagai snapshot historis, bukan hasil eksekusi hari ini.",
        fill=PALE_GREEN,
        color=GREEN,
    )
    add_table(doc, ["Gate", "Hasil akhir", "Status"], [
        ["PHPUnit lokal", "83/83 test; 499 assertion; 24,461 detik", "LULUS"],
        ["PHPUnit Docker", "Snapshot 24 Agustus: 72 test; 448 assertion", "HISTORIS"],
        ["Composer", "composer.json valid", "LULUS"],
        ["Frontend build", "Vite 8.2.1; manifest dan asset produksi terbentuk", "LULUS"],
        ["REST API", "GET list/detail, POST leads, dan webhook WAHA terdaftar", "LULUS"],
        ["Runtime Docker", "Tidak dijalankan ulang pada sesi final", "PERLU ULANG"],
    ], [4.0, 10.2, 3.2])
    add_text(doc, "Pengujian menggunakan SQLite in-memory dan tidak mengubah skema produksi, data operasional, atau kredensial.",
             bold=True, color=PRIMARY_DARK, size=9.1)

    add_heading(doc, "2. Tujuan, Ruang Lingkup, dan Kriteria", 1)
    add_heading(doc, "2.1 Tujuan", 2)
    add_bullets(doc, [
        "Mendeteksi kegagalan pada source, konfigurasi, dependency, database, build, dan integrasi utama.",
        "Mereproduksi kegagalan secara terisolasi, menentukan akar masalah, dan menerapkan patch minimum.",
        "Membuktikan perbaikan melalui targeted test, full regression, lint, build, service check, dan HTTP smoke test.",
        "Menyediakan langkah yang dapat diulang oleh developer atau administrator tanpa mengekspos secret.",
    ])
    add_heading(doc, "2.2 Ruang lingkup", 2)
    add_table(doc, ["Area", "Cakupan verifikasi"], [
        ["Aplikasi", "Laravel 12.66.0, Filament 5.7.6, Livewire 4.4.0, route admin, policy dan RBAC"],
        ["Data", "SQLite in-memory untuk test; MySQL 8.4 pada Docker untuk status migrasi dan runtime"],
        ["Frontend", "Tailwind/Vite production build dan manifest asset"],
        ["Operasional", "Nginx, PHP-FPM, queue, scheduler, Redis, MySQL, WAHA GOWS"],
        ["Fitur kritis", "Dashboard, leads, CSV, follow-up, sekolah, pendaftaran, pembayaran, target, laporan, audit, notifikasi, WhatsApp"],
    ], [4.2, 13.2])
    add_heading(doc, "2.3 Kriteria lulus", 2)
    add_bullets(doc, [
        "Tidak ada test gagal pada suite lokal terisolasi.",
        "Tidak ada syntax error PHP dan build frontend selesai dengan exit code 0.",
        "Konfigurasi Docker, migrasi runtime, health endpoint, queue, dan WAHA diperiksa kembali sebelum deployment.",
    ])

    add_page_break(doc)
    add_heading(doc, "3. Baseline dan Topologi Pengujian", 1)
    add_table(doc, ["Komponen", "Host Windows", "Docker/Linux"], [
        ["PHP", "8.4.13", "8.4.24"],
        ["Laravel", "12.66.0", "12.66.0"],
        ["Filament / Livewire", "5.7.6 / 4.4.0", "5.7.6 / 4.4.0"],
        ["PHPUnit", "12.5.x", "Snapshot terdokumentasi"],
        ["Database test", "SQLite :memory:", "SQLite :memory:"],
        ["Database runtime", "Tidak digunakan oleh test final", "MySQL 8.4 pada konfigurasi Compose"],
        ["Web", "Tidak dijadikan baseline test", "Nginx 1.27 pada konfigurasi Compose"],
        ["WhatsApp", "HTTP difake pada test", "WAHA GOWS pada konfigurasi Compose"],
    ], [4.1, 6.6, 6.7], compact=True)
    add_callout(doc, "ISOLASI DATA TEST", "phpunit.xml memaksa APP_ENV=testing, SQLite in-memory, cache array, queue sync, dan session array. Test tidak memakai database operasional Docker.", fill=PALE_BLUE, color=PRIMARY)
    add_heading(doc, "3.1 Service Docker pada baseline konfigurasi", 2)
    add_table(doc, ["Service", "Peran", "Status akhir"], [
        ["app", "PHP-FPM Laravel", "Didefinisikan; perlu smoke ulang"],
        ["web", "Nginx reverse proxy", "Didefinisikan; port 8000"],
        ["database", "MySQL 8.4", "Didefinisikan; port 3307"],
        ["redis", "Cache/queue backend", "Didefinisikan"],
        ["node", "Vite dev service", "Didefinisikan; port 5173"],
        ["queue", "Laravel queue worker", "Profile workers"],
        ["scheduler", "Laravel scheduler", "Profile workers"],
        ["waha", "WhatsApp HTTP API — GOWS", "Didefinisikan; port 3000"],
    ], [3.2, 8.8, 5.4], compact=True)

    add_heading(doc, "4. Cara Menjalankan Testing", 1)
    add_heading(doc, "4.1 Persiapan aman", 2)
    add_numbered(doc, [
        "Buka PowerShell pada root proyek C:\\laragon\\www\\sivmi.crm.",
        "Pastikan docker/.env tersedia dan berisi key lokal yang valid. Jangan menyalin nilainya ke log atau laporan.",
        "Jalankan Docker Desktop, lalu pastikan engine aktif sebelum memakai docker compose.",
        "Gunakan database test terisolasi dari phpunit.xml; jangan mengubahnya ke database operasional.",
    ])
    add_heading(doc, "4.2 Pemeriksaan konfigurasi dan service", 2)
    add_code(doc, [
        "composer validate --no-check-publish",
        "php artisan route:list",
        "docker compose --env-file docker\\.env -f docker\\compose.yml config --quiet",
        "docker compose --env-file docker\\.env -f docker\\compose.yml ps",
        "docker compose --env-file docker\\.env -f docker\\compose.yml exec -T app php artisan migrate:status",
    ])
    add_heading(doc, "4.3 Test, lint, dan build", 2)
    add_code(doc, [
        "php artisan test",
        "docker compose --env-file docker\\.env -f docker\\compose.yml exec -T app php artisan test",
        "$files = @(rg --files app database routes tests -g \"*.php\")",
        "foreach ($file in $files) { php -l $file }",
        "npm run build",
    ])
    add_heading(doc, "4.4 Smoke test dan integrasi", 2)
    add_code(doc, [
        "Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8000/up",
        "Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8000/admin/login",
        "# WAHA: panggil /health dan /api/server/status dengan key dari docker/.env",
        "# Jangan echo WAHA_API_KEY ke terminal atau artefak CI.",
    ])

    add_page_break(doc)
    add_heading(doc, "5. Hasil Pengujian Terperinci", 1)
    add_heading(doc, "5.1 Test otomatis", 2)
    add_table(doc, ["Kelompok", "Contoh cakupan", "Hasil"], [
        ["Database isolation", "SQLite memory dan pemisahan runtime", "Lulus"],
        ["Audit & notifikasi", "Sanitasi field sensitif, reminder idempotent, scope cabang", "Lulus"],
        ["Dashboard", "Auth, filter role/cabang/periode, KPI agregat", "Lulus"],
        ["Lead & follow-up", "Scope staff/admin/direksi, status lead, render halaman", "Lulus"],
        ["CSV leads/sekolah", "Import, validasi, permission, export aman dari formula", "Lulus"],
        ["Master data", "RBAC cabang, sekolah, pengguna", "Lulus"],
        ["Program & target", "Nama program, katalog, target unik dan alokasi staff", "Lulus"],
        ["Pendaftaran & bayar", "Scope, transisi lead, pembayaran belum terverifikasi", "Lulus"],
        ["Laporan & settings", "Metrik nyata, export, validasi konfigurasi", "Lulus"],
        ["WAHA", "HMAC, deduplikasi lead, chat text/media, ACK, QR, webhook, RBAC", "Lulus"],
    ], [4.2, 10.3, 2.9], compact=True)
    add_heading(doc, "5.2 Build, database, dan runtime", 2)
    add_table(doc, ["Pemeriksaan", "Bukti", "Kesimpulan"], [
        ["Composer", "composer.json valid", "Lulus"],
        ["PHPUnit", "83 test; 499 assertion; exit code 0", "Lulus"],
        ["Vite", "Build produksi selesai; manifest dan asset terbentuk", "Lulus dengan catatan"],
        ["REST API", "Empat route terdaftar: 3 leads + 1 webhook WAHA", "Lulus"],
        ["Docker/runtime", "Tidak dapat diakses dari sesi pengujian final", "Perlu verifikasi ulang"],
    ], [4.0, 10.4, 3.0], compact=True)
    add_callout(doc, "CATATAN BUILD", "Vite menampilkan peringatan optional package fontaine untuk optimized font fallbacks. Build tetap selesai dengan exit code 0 dan asset produksi valid. Ini bukan blocker.", fill=PALE_AMBER, color=AMBER)

    add_heading(doc, "6. Proses Debugging dan Akar Masalah", 1)
    add_table(doc, ["ID", "Gejala", "Akar masalah", "Tindakan / hasil"], [
        ["DBG-01", "Host migrate menolak koneksi 127.0.0.1:3306", "MySQL Laragon tidak aktif; bukan error migrasi", "Verifikasi migrasi melalui MySQL Docker; 16/16 Ran"],
        ["DBG-02", "WAHA server status merespons 401", "Pemeriksaan awal memakai example/default key", "Ambil key aktual dari docker/.env secara tersanitasi; retest 200"],
        ["DBG-03", "5 test form lulus di Windows tetapi gagal di Linux Docker", "Helper fillForm/callFormComponentAction tidak konsisten menghidrasi state action/select Filament 5 pada runtime tersebut", "Gunakan state path eksplisit data dan mountedActions.0.data; 74/74 lulus di kedua runtime"],
        ["DBG-04", "Peringatan fontaine saat build", "Dependency optimasi font fallback bersifat opsional", "Dicatat sebagai peningkatan; build tidak diblokir"],
        ["DBG-05", "public/storage host belum linked", "Host Laragon bukan baseline aktif saat test", "Container sudah linked; jika kembali ke Laragon jalankan php artisan storage:link"],
    ], [1.5, 4.4, 5.5, 6.0], compact=True)

    add_page_break(doc)
    add_heading(doc, "7. Perbaikan yang Diterapkan", 1)
    add_text(doc, "Patch mengubah cara test mengisi state form; kode produksi dan aturan bisnis tidak diubah.", bold=True, color=PRIMARY_DARK)
    add_table(doc, ["File test", "Perubahan"], [
        ["CreateSchoolFromLeadTest.php", "Mount createOption lalu isi mountedActions.0.data sebelum memanggil action"],
        ["MasterDataAccessTest.php", "Isi state data secara eksplisit untuk skenario role dan cabang"],
        ["ProgramTargetTest.php", "Isi state data eksplisit untuk cabang/periode target"],
        ["RegistrationPaymentTest.php", "Isi state data eksplisit untuk pendaftaran dan pembayaran"],
        ["WahaIntegrationTest.php", "Isi state data eksplisit untuk akun WhatsApp"],
    ], [6.3, 11.1])
    add_heading(doc, "7.1 Contoh pola sebelum dan sesudah", 2)
    add_code(doc, [
        "// Sebelum: helper abstraksi dapat kehilangan state pada Linux/Docker",
        "->fillForm(['lead_id' => $leadId, ...])",
        "",
        "// Sesudah: state path Filament 5 dinyatakan eksplisit",
        "->set('data', ['lead_id' => $leadId, ...])",
        "",
        "// Modal action createOption",
        "->mountFormComponentAction('sekolah_id', 'createOption')",
        "->set('mountedActions.0.data', [...])",
        "->callMountedFormComponentAction()",
    ])
    add_heading(doc, "7.2 Alasan pendekatan", 2)
    add_bullets(doc, [
        "Menguji state aktual yang digunakan komponen Filament 5 dan mengurangi ketergantungan pada helper yang ambigu.",
        "Memberi perilaku konsisten lintas Windows host dan Linux container.",
        "Mempertahankan assertions terhadap validasi RBAC, cabang, field wajib, normalisasi nomor, dan data hasil simpan.",
        "Tidak melemahkan validasi produksi dan tidak melewati authorization aplikasi.",
    ])

    add_heading(doc, "8. Regression Matrix Setelah Perbaikan", 1)
    add_table(doc, ["Tahap", "Perintah/teknik", "Hasil akhir"], [
        ["Targeted — sekolah", "CreateSchoolFromLeadTest", "6 test lulus"],
        ["Targeted — empat form lain", "MasterDataAccess + ProgramTarget + RegistrationPayment + WAHA", "28 test host lulus; targeted Docker lulus"],
        ["Full lokal", "php artisan test --compact", "83 test / 499 assertion lulus"],
        ["Full Docker", "docker\\test-full.ps1", "Snapshot 24 Agustus: 72 test / 448 assertion"],
        ["Konfigurasi", "composer validate --no-check-publish", "Lulus"],
        ["Asset", "npm run build", "Lulus"],
        ["Runtime", "compose ps + migration + HTTP + WAHA + queue/log", "Belum diulang pada sesi final"],
    ], [4.2, 8.5, 4.7], compact=True)

    add_heading(doc, "9. Temuan Non-Blocker dan Risiko Tersisa", 1)
    add_table(doc, ["Prioritas", "Temuan", "Rekomendasi"], [
        ["Rendah", "Peringatan optional fontaine pada Vite", "Install fontaine atau nonaktifkan optimizedFallbacks bila ingin build tanpa warning"],
        ["Rendah", "Storage link host Laragon belum ada", "Jalankan php artisan storage:link hanya bila runtime kembali ke Laragon"],
        ["Sedang", "UAT visual lintas viewport belum menjadi bukti pada putaran ini", "Lakukan browser QA desktop/tablet/mobile untuk alur login, dashboard, form, chat, CSV"],
        ["Sedang", "Test otomatis memakai SQLite, sedangkan runtime memakai MySQL", "Tambahkan suite integrasi MySQL terpisah di CI tanpa mengganti unit/feature isolation"],
        ["Sedang", "WAHA bergantung pada session WhatsApp dan jaringan", "Monitor status session, webhook, retry, dan antrian setelah perubahan konfigurasi"],
    ], [2.2, 7.2, 8.0], compact=True)

    add_page_break(doc)
    add_heading(doc, "10. Checklist Debugging yang Dapat Diulang", 1)
    add_numbered(doc, [
        "Catat waktu, environment, URL/command, actor/role/cabang, expected, actual, dan dampak.",
        "Reproduksi dengan data minimum dan database test; jangan mencoba langsung pada data produksi.",
        "Jalankan targeted test dan baca error path lengkap, termasuk data.*, mountedActions.*, atau validation rule.",
        "Bandingkan source checksum, versi dependency, PHP, php.ini, environment, dan status cache antara host/container.",
        "Tentukan apakah masalah berasal dari kode produksi, test harness, konfigurasi, service, data, atau integrasi.",
        "Terapkan patch minimum menggunakan state dan kontrak framework terbaru.",
        "Jalankan targeted regression, full suite pada host dan Docker, lint, build, migration status, dan smoke test.",
        "Periksa queue, webhook, serta log tersanitasi; jangan menyalin API key, password, token, cookie, atau payload pribadi.",
        "Catat temuan non-blocker dan keputusan apakah menjadi backlog atau gate release.",
    ])

    add_heading(doc, "11. Kriteria Penutupan dan Rekomendasi", 1)
    add_callout(doc, "STATUS PENUTUPAN", "Bug test form Filament/Livewire dinyatakan DITUTUP. Suite lokal terbaru lulus penuh; verifikasi runtime Docker tetap menjadi gate operasional yang wajib diulang sebelum deployment.", fill=PALE_GREEN, color=GREEN)
    add_heading(doc, "11.1 Rekomendasi sebelum deployment VPS", 2)
    add_bullets(doc, [
        "Tambahkan pipeline CI Linux yang menjalankan composer validate, PHP lint, PHPUnit, dan npm run build.",
        "Tambahkan integration test MySQL dan health check untuk Nginx, PHP-FPM, MySQL, Redis, queue, scheduler, serta WAHA.",
        "Pisahkan secret melalui environment/secret manager; rotasi key yang pernah dipakai sebagai nilai contoh.",
        "Aktifkan backup database/file, uji restore, logging terpusat, dan alert untuk failed_jobs serta webhook gagal.",
        "Lakukan UAT visual lintas perangkat dan alur bisnis utama sebelum release candidate dinyatakan siap produksi.",
    ])
    add_heading(doc, "11.2 Approval ringkas", 2)
    add_table(doc, ["Peran", "Nama / paraf", "Tanggal", "Keputusan"], [
        ["Developer", "", "", ""],
        ["QA/UAT", "", "", ""],
        ["Product owner", "", "", ""],
    ], [4.2, 5.4, 3.0, 4.8])

    add_heading(doc, "Lampiran A — Ringkasan Bukti", 1)
    add_table(doc, ["Bukti", "Nilai"], [
        ["Tanggal verifikasi", "26 Agustus 2026, Asia/Jakarta"],
        ["Test lokal", "83 passed; 499 assertions; 24.461 ms"],
        ["Test Docker", "Snapshot 24 Agustus: 72 passed; 448 assertions"],
        ["Composer", "composer.json valid"],
        ["Build", "Vite 8.2.1; success"],
        ["REST API", "4 route terdaftar"],
        ["Runtime Docker", "Perlu verifikasi ulang sebelum deployment"],
    ], [5.5, 11.9], compact=True)
    add_text(doc, "Dokumen ini tidak memuat password, APP_KEY, API key WAHA, webhook secret, cookie, token, atau isi percakapan pengguna.",
             bold=True, color=ROSE, size=8.7, before=4)

    add_heading(doc, "Lampiran B — Perintah Verifikasi Ulang", 1)
    add_code(doc, [
        "# Host",
        "php artisan test",
        "npm run build",
        "",
        "# Docker",
        "docker compose --env-file docker\\.env -f docker\\compose.yml ps",
        "docker compose --env-file docker\\.env -f docker\\compose.yml exec -T app php artisan migrate:status",
        "docker compose --env-file docker\\.env -f docker\\compose.yml exec -T app php artisan test",
        "",
        "# Smoke",
        "Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8000/up",
        "Invoke-WebRequest -UseBasicParsing http://127.0.0.1:8000/admin/login",
    ])
    add_heading(doc, "Lampiran C — Template Catatan Bug", 1)
    add_table(doc, ["Field", "Isi yang harus dicatat"], [
        ["ID / judul", "Kode unik dan ringkasan gejala"],
        ["Waktu / environment", "Timestamp Asia/Jakarta; host, Docker, test, staging, atau production"],
        ["Aktor / scope", "Role, cabang, modul; identitas pribadi dianonimkan"],
        ["Langkah reproduksi", "Data awal minimum dan langkah bernomor"],
        ["Expected / actual", "Hasil yang seharusnya dan hasil aktual"],
        ["Bukti", "Test, log tersanitasi, HTTP status, screenshot, correlation ID"],
        ["Akar masalah", "Kode, data, konfigurasi, dependency, infrastruktur, integrasi, atau proses"],
        ["Patch / risiko", "File berubah, dampak, compatibility, rollback"],
        ["Verifikasi", "Targeted test, full regression, build, smoke, UAT"],
        ["Keputusan", "Open, monitoring, fixed, closed, beserta approver"],
    ], [4.4, 13.0], compact=True)

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc.save(OUTPUT)
    print(OUTPUT)


if __name__ == "__main__":
    build()
