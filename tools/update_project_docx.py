from __future__ import annotations

import argparse
import json
from copy import deepcopy
from pathlib import Path

from docx import Document
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_BREAK
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor
from docx.table import Table
from docx.text.paragraph import Paragraph


BLUE = "0B2C6B"
LIGHT_BLUE = "DCE6F1"
WHITE = "FFFFFF"
GRAY = "666666"


def find_paragraph(doc: Document, text: str) -> Paragraph:
    for paragraph in doc.paragraphs:
        if paragraph.text.strip() == text:
            return paragraph
    raise ValueError(f"Paragraph not found: {text}")


def copy_paragraph_format(source: Paragraph, target: Paragraph) -> None:
    if source._p.pPr is not None:
        if target._p.pPr is not None:
            target._p.remove(target._p.pPr)
        target._p.insert(0, deepcopy(source._p.pPr))


def set_run_font(run, *, size: float | None = None, bold: bool | None = None,
                 color: str | None = None, name: str = "Arial") -> None:
    run.font.name = name
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    if size is not None:
        run.font.size = Pt(size)
    if bold is not None:
        run.bold = bold
    if color is not None:
        run.font.color.rgb = RGBColor.from_string(color)


def new_paragraph_before(anchor: Paragraph, text: str = "", style: str | None = None) -> Paragraph:
    paragraph = anchor.insert_paragraph_before(text)
    if style:
        paragraph.style = style
    return paragraph


def new_paragraph_after(anchor: Paragraph, text: str = "", style: str | None = None) -> Paragraph:
    element = OxmlElement("w:p")
    anchor._p.addnext(element)
    paragraph = Paragraph(element, anchor._parent)
    if style:
        paragraph.style = style
    if text:
        paragraph.add_run(text)
    return paragraph


def insert_after_sequence(anchor: Paragraph, items: list[tuple[str, str]]) -> Paragraph:
    current = anchor
    for text, style in items:
        current = new_paragraph_after(current, text, style)
    return current


def add_page_break_before(anchor: Paragraph) -> Paragraph:
    paragraph = new_paragraph_before(anchor)
    paragraph.add_run().add_break(WD_BREAK.PAGE)
    return paragraph


def set_cell_shading(cell, fill: str) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    shading = tc_pr.find(qn("w:shd"))
    if shading is None:
        shading = OxmlElement("w:shd")
        tc_pr.append(shading)
    shading.set(qn("w:fill"), fill)


def set_cell_margins(cell, top: int = 100, start: int = 120, bottom: int = 100, end: int = 120) -> None:
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for edge, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{edge}"))
        if node is None:
            node = OxmlElement(f"w:{edge}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def set_repeat_table_header(row) -> None:
    tr_pr = row._tr.get_or_add_trPr()
    tbl_header = OxmlElement("w:tblHeader")
    tbl_header.set(qn("w:val"), "true")
    tr_pr.append(tbl_header)


def set_custom_list(doc: Document, paragraphs: list[Paragraph], *, num_format_value: str,
                    level_text_value: str, bullet_font: str | None = None) -> None:
    numbering = doc.part.numbering_part.element
    abstract_ids = [
        int(element.get(qn("w:abstractNumId")))
        for element in numbering.findall(qn("w:abstractNum"))
    ]
    num_ids = [
        int(element.get(qn("w:numId")))
        for element in numbering.findall(qn("w:num"))
    ]
    abstract_id = max(abstract_ids, default=0) + 1
    num_id = max(num_ids, default=0) + 1

    abstract = OxmlElement("w:abstractNum")
    abstract.set(qn("w:abstractNumId"), str(abstract_id))
    multi_level = OxmlElement("w:multiLevelType")
    multi_level.set(qn("w:val"), "singleLevel")
    abstract.append(multi_level)

    level = OxmlElement("w:lvl")
    level.set(qn("w:ilvl"), "0")
    start = OxmlElement("w:start")
    start.set(qn("w:val"), "1")
    level.append(start)
    num_format = OxmlElement("w:numFmt")
    num_format.set(qn("w:val"), num_format_value)
    level.append(num_format)
    level_text = OxmlElement("w:lvlText")
    level_text.set(qn("w:val"), level_text_value)
    level.append(level_text)
    level_justification = OxmlElement("w:lvlJc")
    level_justification.set(qn("w:val"), "left")
    level.append(level_justification)

    if bullet_font:
        run_properties = OxmlElement("w:rPr")
        fonts = OxmlElement("w:rFonts")
        fonts.set(qn("w:ascii"), bullet_font)
        fonts.set(qn("w:hAnsi"), bullet_font)
        fonts.set(qn("w:hint"), "default")
        run_properties.append(fonts)
        level.append(run_properties)

    paragraph_properties = OxmlElement("w:pPr")
    tabs = OxmlElement("w:tabs")
    tab = OxmlElement("w:tab")
    tab.set(qn("w:val"), "num")
    tab.set(qn("w:pos"), "720")
    tabs.append(tab)
    paragraph_properties.append(tabs)
    indent = OxmlElement("w:ind")
    indent.set(qn("w:left"), "720")
    indent.set(qn("w:hanging"), "360")
    paragraph_properties.append(indent)
    level.append(paragraph_properties)
    abstract.append(level)
    first_num = numbering.find(qn("w:num"))
    if first_num is None:
        numbering.append(abstract)
    else:
        first_num.addprevious(abstract)

    number = OxmlElement("w:num")
    number.set(qn("w:numId"), str(num_id))
    abstract_reference = OxmlElement("w:abstractNumId")
    abstract_reference.set(qn("w:val"), str(abstract_id))
    number.append(abstract_reference)
    numbering.append(number)

    for paragraph in paragraphs:
        p_pr = paragraph._p.get_or_add_pPr()
        num_pr = p_pr.find(qn("w:numPr"))
        if num_pr is None:
            num_pr = OxmlElement("w:numPr")
            p_pr.append(num_pr)
        for child in list(num_pr):
            num_pr.remove(child)
        ilvl = OxmlElement("w:ilvl")
        ilvl.set(qn("w:val"), "0")
        num_reference = OxmlElement("w:numId")
        num_reference.set(qn("w:val"), str(num_id))
        num_pr.append(ilvl)
        num_pr.append(num_reference)


def set_numbered_list(doc: Document, paragraphs: list[Paragraph]) -> None:
    set_custom_list(doc, paragraphs, num_format_value="decimal", level_text_value="%1.")


def set_bullet_list(doc: Document, paragraphs: list[Paragraph]) -> None:
    set_custom_list(doc, paragraphs, num_format_value="bullet", level_text_value="•", bullet_font="Symbol")


def set_table_widths(table: Table, widths: list[float]) -> None:
    table.autofit = False
    for row in table.rows:
        for index, width in enumerate(widths):
            row.cells[index].width = Inches(width)
            tc_pr = row.cells[index]._tc.get_or_add_tcPr()
            tc_w = tc_pr.find(qn("w:tcW"))
            if tc_w is None:
                tc_w = OxmlElement("w:tcW")
                tc_pr.append(tc_w)
            tc_w.set(qn("w:w"), str(round(width * 1440)))
            tc_w.set(qn("w:type"), "dxa")

    tbl_pr = table._tbl.tblPr
    tbl_w = tbl_pr.find(qn("w:tblW"))
    if tbl_w is None:
        tbl_w = OxmlElement("w:tblW")
        tbl_pr.append(tbl_w)
    tbl_w.set(qn("w:w"), str(round(sum(widths) * 1440)))
    tbl_w.set(qn("w:type"), "dxa")

    grid = table._tbl.tblGrid
    for child in list(grid):
        grid.remove(child)
    for width in widths:
        col = OxmlElement("w:gridCol")
        col.set(qn("w:w"), str(round(width * 1440)))
        grid.append(col)


def insert_table_before(doc: Document, anchor: Paragraph, headers: list[str], rows: list[list[str]],
                        widths: list[float]) -> Table:
    table = doc.add_table(rows=1, cols=len(headers))
    table.style = "Table Grid"
    table._tbl.getparent().remove(table._tbl)
    anchor._p.addprevious(table._tbl)
    set_table_widths(table, widths)
    set_repeat_table_header(table.rows[0])

    for index, header in enumerate(headers):
        cell = table.rows[0].cells[index]
        cell.text = header
        set_cell_shading(cell, BLUE)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        set_cell_margins(cell)
        paragraph = cell.paragraphs[0]
        paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
        paragraph.paragraph_format.space_after = Pt(0)
        for run in paragraph.runs:
            set_run_font(run, size=8.5, bold=True, color=WHITE)

    for row_index, values in enumerate(rows):
        cells = table.add_row().cells
        for column_index, value in enumerate(values):
            cell = cells[column_index]
            cell.text = value
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            set_cell_margins(cell)
            if row_index % 2 == 1:
                set_cell_shading(cell, "F4F7FB")
            paragraph = cell.paragraphs[0]
            paragraph.paragraph_format.space_after = Pt(0)
            paragraph.alignment = (
                WD_ALIGN_PARAGRAPH.CENTER
                if column_index in (0, len(headers) - 2)
                else WD_ALIGN_PARAGRAPH.LEFT
            )
            for run in paragraph.runs:
                set_run_font(run, size=8.3)
    return table


def style_heading_like(source: Paragraph, target: Paragraph, text: str, page_break: bool = False) -> None:
    copy_paragraph_format(source, target)
    target.clear()
    target.paragraph_format.page_break_before = page_break
    run = target.add_run(text)
    source_run = source.runs[0] if source.runs else None
    set_run_font(
        run,
        size=source_run.font.size.pt if source_run and source_run.font.size else 14,
        bold=True,
        color=str(source_run.font.color.rgb) if source_run and source_run.font.color and source_run.font.color.rgb else BLUE,
    )


def add_toc(doc: Document, page_map: dict[str, int] | None) -> None:
    first = doc.paragraphs[0]
    chapter_source = find_paragraph(doc, "BAB I: PENDAHULUAN")
    heading = new_paragraph_before(first)
    style_heading_like(chapter_source, heading, "DAFTAR ISI")

    subtitle = new_paragraph_before(first, "Dokumentasi Project CRM_VILLA MERAH")
    subtitle.paragraph_format.space_after = Pt(10)
    subtitle_run = subtitle.runs[0]
    set_run_font(subtitle_run, size=10, color=GRAY)

    entries = [
        ("BAB I: PENDAHULUAN", "BAB I: PENDAHULUAN"),
        ("BAB II: LANDASAN TEORI", "BAB II: LANDASAN TEORI"),
        ("BAB III: ANALISA KEBUTUHAN", "BAB III: ANALISA KEBUTUHAN"),
        ("BAB IV: ARSITEKTUR BISNIS", "BAB IV: ARSITEKTUR BISNIS"),
        ("BAB V: ARSITEKTUR DATA", "BAB V: ARSITEKTUR DATA"),
        ("BAB VI: ARSITEKTUR APLIKASI", "BAB VI: ARSITEKTUR APLIKASI"),
        ("BAB VII: ARSITEKTUR TEKNOLOGI", "BAB VII: ARSITEKTUR TEKNOLOGI"),
        ("BAB VIII: MANAJEMEN DEBUGGING", "BAB VIII: MANAJEMEN DEBUGGING"),
        ("BAB IX: DEPLOYMENT, MIGRASI, DAN PEMELIHARAAN SISTEM", "BAB IX: DEPLOYMENT, MIGRASI, DAN PEMELIHARAAN SISTEM"),
        ("BAB X: PANDUAN PENGGUNAAN", "BAB X: PANDUAN PENGGUNAAN"),
        ("BAB XI: PANDUAN ADMINISTRATOR", "BAB XI: PANDUAN ADMINISTRATOR"),
        ("BAB XII: STANDARD OPERATING PROCEDURE OPERASIONAL CRM", "BAB XII. STANDARD OPERATING PROCEDURE OPERASIONAL CRM"),
        ("LAMPIRAN A: API DAN REST API", "LAMPIRAN A: API DAN REST API"),
        ("LAMPIRAN B: LAPORAN TESTING DAN DEBUGGING", "LAMPIRAN B: LAPORAN TESTING DAN DEBUGGING"),
        ("LAMPIRAN C: MATRIKS ERD, RELASI, DAN KARDINALITAS", "LAMPIRAN C: MATRIKS ERD, RELASI, DAN KARDINALITAS"),
        ("LAMPIRAN D: USER ACCEPTANCE TESTING (UAT)", "LAMPIRAN D: USER ACCEPTANCE TESTING (UAT)"),
        ("GLOSARIUM", "Glosarium"),
    ]

    for display, key in entries:
        page = str(page_map[key]) if page_map and key in page_map else f"{{{{PAGE:{key}}}}}"
        paragraph = new_paragraph_before(first)
        paragraph.paragraph_format.space_after = Pt(3)
        paragraph.paragraph_format.keep_together = True
        p_pr = paragraph._p.get_or_add_pPr()
        tabs = OxmlElement("w:tabs")
        tab = OxmlElement("w:tab")
        tab.set(qn("w:val"), "right")
        tab.set(qn("w:leader"), "dot")
        tab.set(qn("w:pos"), "9360")
        tabs.append(tab)
        p_pr.append(tabs)
        run = paragraph.add_run(f"{display}\t{page}")
        set_run_font(run, size=9.5, bold=display.startswith(("BAB", "LAMPIRAN", "GLOSARIUM")))

    note = new_paragraph_before(first, "Nomor halaman disusun berdasarkan hasil render dokumen revisi.")
    note.paragraph_format.space_before = Pt(6)
    note.paragraph_format.space_after = Pt(0)
    set_run_font(note.runs[0], size=8.5, color=GRAY)
    add_page_break_before(first)


def add_api_school_updates(doc: Document) -> None:
    requirements_anchor = find_paragraph(doc, "Sekolah dapat dibuat dari form Lead saat belum tersedia tanpa meninggalkan data lead.")
    insert_after_sequence(requirements_anchor, [
        ("Pencarian sekolah dari form Lead dapat menggunakan API Sekolah Indonesia berdasarkan nama atau NPSN dengan minimal tiga karakter.", "No Spacing"),
        ("Sekolah yang dipilih dari API dinormalisasi dan disimpan ke master sekolah lokal berdasarkan NPSN agar dapat digunakan kembali tanpa membuat duplikasi.", "No Spacing"),
        ("Metadata hasil API meliputi bentuk pendidikan, akreditasi, alamat, kecamatan, kota/kabupaten, provinsi, kode wilayah, sumber data, dan waktu sinkronisasi terakhir.", "No Spacing"),
        ("Jika API tidak tersedia, pengguna tetap dapat memilih sekolah lokal atau menggunakan input manual sesuai kewenangan.", "No Spacing"),
    ])

    business_anchor = find_paragraph(doc, "Jika sekolah calon siswa belum tersedia, pengguna dapat membuat sekolah dari alur lead tanpa meninggalkan proses. NPSN bersifat opsional. Penamaan harus memakai prefix yang disediakan agar pencarian dan laporan konsisten.")
    insert_after_sequence(business_anchor, [
        ("Alur terbaru menyediakan aksi Cari dari API Sekolah Indonesia. Pengguna mencari minimal tiga karakter nama sekolah atau NPSN, memilih hasil yang sesuai, lalu sistem melakukan update-or-create berdasarkan NPSN. Data terpilih menjadi record master sekolah lokal dan langsung diisikan pada lead.", "Normal"),
        ("API berfungsi sebagai sumber referensi, sedangkan database CRM tetap menjadi sumber operasional. Cache pencarian mengurangi panggilan berulang; kegagalan API tidak boleh menghalangi input lead karena pilihan lokal dan input manual tetap tersedia.", "Normal"),
    ])

    technology_anchor = find_paragraph(doc, "Session, Cookie, dan Cache")
    heading = new_paragraph_before(technology_anchor, "Integrasi API Sekolah Indonesia", "Heading 2")
    heading.paragraph_format.keep_with_next = True
    for text, style in [
        ("Aplikasi memanggil endpoint HTTPS https://sekolah.devapi.id/sekolah melalui SchoolApiClient. Query dikirim sebagai nama atau NPSN, dibatasi jumlah hasil, diberi timeout dan retry, lalu hasil dinormalisasi sebelum digunakan.", "Normal"),
        ("Hasil pencarian disimpan sementara pada cache. Setelah pengguna memilih sekolah, SyncSchoolFromApi menjalankan updateOrCreate berdasarkan NPSN dan mencatat sumber_data=api-sekolah serta api_last_synced_at. Secret tidak diperlukan pada baseline API publik, tetapi URL dan parameter operasional tetap dikelola melalui environment.", "Normal"),
        ("Kontrol kegagalan: respons tidak sukses, struktur data tidak valid, timeout, atau exception dicatat tanpa payload sensitif; antarmuka menampilkan pesan gagal dan menyediakan jalur input manual.", "Normal"),
    ]:
        new_paragraph_before(technology_anchor, text, style)

    guide_anchor = find_paragraph(doc, "10.5 Import Leads CSV")
    heading = new_paragraph_before(guide_anchor, "10.4.1 Memilih Sekolah dari API Sekolah Indonesia", "Heading 2")
    heading.paragraph_format.keep_with_next = True
    steps = [
        "Pada form Buat/Ubah Lead, buka bagian Data calon siswa.",
        "Klik Cari dari API Sekolah Indonesia di bawah field Sekolah.",
        "Ketik minimal tiga karakter nama sekolah atau masukkan NPSN delapan karakter.",
        "Pilih hasil dengan memeriksa nama sekolah, NPSN, dan kota/kabupaten.",
        "Klik Gunakan data sekolah. Sistem menyimpan atau memperbarui sekolah pada database lokal dan mengisi field Sekolah pada lead.",
        "Lengkapi Nama lengkap dan Nomor WhatsApp, lalu simpan lead. Field lain tetap dapat dikosongkan sesuai kebijakan data minimum.",
    ]
    guide_step_paragraphs = []
    for step in steps:
        paragraph = new_paragraph_before(guide_anchor, step, "List Number")
        paragraph.paragraph_format.keep_together = True
        guide_step_paragraphs.append(paragraph)
    set_numbered_list(doc, guide_step_paragraphs)
    note = new_paragraph_before(guide_anchor, "Catatan: bila hasil tidak muncul, periksa koneksi internet/server dan konfigurasi API. Pengguna masih dapat memilih sekolah lokal atau menambahkan sekolah secara manual.")
    set_run_font(note.runs[0], size=9.5, color=GRAY)

    admin_anchor = find_paragraph(doc, "11.9 Administrasi User, Role, dan Cabang")
    heading = new_paragraph_before(admin_anchor, "11.8.1 Konfigurasi API Sekolah Indonesia", "Heading 2")
    heading.paragraph_format.keep_with_next = True
    new_paragraph_before(admin_anchor, "Tambahkan konfigurasi berikut pada .env server atau docker/.env, kemudian jalankan migration dan bersihkan cache konfigurasi.", "Normal")
    insert_table_before(doc, admin_anchor,
        ["Variabel", "Contoh/Default", "Fungsi"],
        [
            ["SCHOOL_API_ENABLED", "true", "Mengaktifkan atau menonaktifkan fitur pencarian API."],
            ["SCHOOL_API_URL", "https://sekolah.devapi.id/sekolah", "Endpoint penyedia data sekolah."],
            ["SCHOOL_API_TIMEOUT", "5", "Batas tunggu respons dalam detik."],
            ["SCHOOL_API_CACHE_TTL", "86400", "Masa cache hasil pencarian dalam detik."],
            ["SCHOOL_API_RESULT_LIMIT", "20", "Jumlah maksimum hasil per pencarian."],
        ],
        [1.65, 2.5, 3.15],
    )
    for command in [
        "Shared hosting/server: php artisan migrate --force",
        "Shared hosting/server: php artisan optimize:clear",
        "Docker lokal: docker compose --env-file docker/.env -f docker/compose.yml exec app php artisan migrate --force",
        "Docker lokal: docker compose --env-file docker/.env -f docker/compose.yml exec app php artisan optimize:clear",
    ]:
        paragraph = new_paragraph_before(admin_anchor, command, "List Bullet")
        for run in paragraph.runs:
            run.font.name = "Consolas"
            run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), "Consolas")
            run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), "Consolas")
            run.font.size = Pt(8.5)

    sop_anchor = find_paragraph(doc, "Program bimbel")
    heading = new_paragraph_before(sop_anchor, "Sinkronisasi sekolah dari API", "Heading 1")
    heading.paragraph_format.keep_with_next = True
    sop_step_paragraphs = []
    for step in [
        "Petugas mencari sekolah menggunakan nama atau NPSN minimal tiga karakter.",
        "Petugas memverifikasi kecocokan nama, NPSN, status, dan wilayah sebelum memilih.",
        "Sistem menyimpan data berdasarkan NPSN; data yang sudah ada diperbarui dan tidak dibuat sebagai duplikat baru.",
        "Petugas tidak mengubah NPSN hasil API tanpa bukti resmi.",
        "Jika API gagal, petugas memakai sekolah lokal atau input manual dan melaporkan gangguan kepada administrator.",
    ]:
        paragraph = new_paragraph_before(sop_anchor, step, "List Number")
        paragraph.paragraph_format.keep_together = True
        sop_step_paragraphs.append(paragraph)
    set_numbered_list(doc, sop_step_paragraphs)


def add_uat_appendix(doc: Document) -> None:
    glossary = find_paragraph(doc, "Glosarium")

    appendix_source = find_paragraph(doc, "LAMPIRAN C: MATRIKS ERD, RELASI, DAN KARDINALITAS")
    title = new_paragraph_before(glossary)
    style_heading_like(appendix_source, title, "LAMPIRAN D: USER ACCEPTANCE TESTING (UAT)", page_break=True)

    intro = new_paragraph_before(
        glossary,
        "Lampiran ini menjadi lembar kerja penerimaan pengguna untuk membuktikan bahwa fungsi CRM_VILLA MERAH sesuai kebutuhan operasional, hak akses, kualitas data, integrasi, dan hasil bisnis sebelum go-live.",
        "Normal",
    )
    intro.paragraph_format.space_after = Pt(8)

    h = new_paragraph_before(glossary, "D.1 Identitas Pelaksanaan", "Heading 1")
    h.paragraph_format.keep_with_next = True
    insert_table_before(doc, glossary,
        ["Informasi", "Isian UAT"],
        [
            ["Nama proyek", "CRM_VILLA MERAH"],
            ["Versi/release", "____________________________"],
            ["Environment", "UAT/Staging - bukan production"],
            ["Tanggal pelaksanaan", "____________________________"],
            ["Koordinator UAT", "____________________________"],
            ["Cabang uji", "____________________________"],
        ],
        [2.0, 5.3],
    )

    h = new_paragraph_before(glossary, "D.2 Tujuan, Peran, dan Kriteria Penerimaan", "Heading 1")
    h.paragraph_format.keep_with_next = True
    prerequisite_paragraphs = []
    acceptance_paragraphs = []
    for text in [
        "UAT dilaksanakan oleh perwakilan Superadmin, Admin Cabang, Staff, dan Direksi menggunakan akun serta cabang yang terpisah.",
        "Data uji tidak boleh menggunakan data pribadi calon siswa produksi. Nomor, nama, dokumen, dan bukti pembayaran harus berupa data sintetis.",
        "Skenario dinyatakan Lulus apabila hasil aktual sama dengan hasil yang diharapkan, tidak melanggar scope role/cabang, dan bukti uji tersedia.",
        "Temuan berisiko tinggi terhadap keamanan, kehilangan data, transaksi, atau akses lintas cabang menjadi blocker go-live.",
        "UAT selesai setelah seluruh skenario Must lulus, defect blocker/critical ditutup, dan persetujuan pemilik bisnis serta teknis ditandatangani.",
    ]:
        acceptance_paragraphs.append(new_paragraph_before(glossary, text, "List Bullet"))
    set_bullet_list(doc, acceptance_paragraphs)

    h = new_paragraph_before(glossary, "D.3 Prasyarat UAT", "Heading 1")
    h.paragraph_format.keep_with_next = True
    for text in [
        "Migration terbaru berhasil, termasuk metadata API Sekolah pada tabel sekolah.",
        "APP_ENV menggunakan staging/UAT, APP_DEBUG=false untuk simulasi produksi, HTTPS aktif, queue dan scheduler berjalan.",
        "Role, cabang, program, sumber lead, template WhatsApp, target, dan akun WAHA uji telah disiapkan.",
        "SCHOOL_API_ENABLED=true dan server dapat mengakses endpoint HTTPS API Sekolah Indonesia.",
        "Backup data UAT tersedia sebelum eksekusi skenario destruktif atau bulk action.",
        "Browser desktop dan mobile yang didukung tersedia, bersama lembar bukti screenshot/log yang sudah disanitasi.",
    ]:
        prerequisite_paragraphs.append(new_paragraph_before(glossary, text, "List Number"))
    set_numbered_list(doc, prerequisite_paragraphs)

    scenarios = [
        ("D.4 Autentikasi dan Otorisasi", [
            ["AUT-01", "Login akun aktif", "Masuk dengan email dan password valid.", "Dashboard sesuai role tampil; session terbentuk aman.", "___", "___"],
            ["AUT-02", "Akun tidak aktif", "Login memakai akun yang dinonaktifkan.", "Akses ditolak tanpa membuka informasi sensitif.", "___", "___"],
            ["RBAC-01", "Scope cabang", "Staff mencoba membuka lead cabang lain melalui URL/filter.", "Akses ditolak atau data tidak ditemukan.", "___", "___"],
            ["RBAC-02", "Direksi read-only", "Direksi membuka data lintas cabang dan mencoba mengubahnya.", "Data dapat dibaca; aksi operasional tidak tersedia/ditolak.", "___", "___"],
        ]),
        ("D.5 Lead dan Kualitas Data", [
            ["LEAD-01", "Input minimum", "Buat lead hanya dengan Nama dan Nomor WhatsApp.", "Lead tersimpan; field lain boleh kosong.", "___", "___"],
            ["LEAD-02", "Normalisasi WhatsApp", "Masukkan nomor sama dengan format 08, +62, dan 62.", "Sistem mengenali identitas yang sama.", "___", "___"],
            ["LEAD-03", "Cegah duplikat", "Buat lead dengan nomor yang telah tersimpan.", "Penyimpanan ditolak/ditandai sesuai aturan tanpa duplikasi.", "___", "___"],
            ["LEAD-04", "Filter lead", "Filter kota, sekolah, kelas sekolah, program, status, cabang, dan PIC.", "Hasil sesuai kombinasi filter dan scope pengguna.", "___", "___"],
            ["LEAD-05", "Riwayat kelas", "Ubah kelas sekolah pada lead.", "Nilai baru tersimpan dan histori perubahan dapat ditelusuri.", "___", "___"],
        ]),
        ("D.6 API Sekolah Indonesia", [
            ["SCH-API-01", "Pencarian nama", "Cari minimal 3 karakter nama sekolah dari form Lead.", "Daftar API menampilkan nama, NPSN, dan wilayah yang relevan.", "___", "___"],
            ["SCH-API-02", "Pencarian NPSN", "Cari menggunakan NPSN delapan karakter.", "Sekolah yang tepat ditemukan dan dapat dipilih.", "___", "___"],
            ["SCH-API-03", "Persistensi lokal", "Pilih hasil API lalu simpan lead.", "Sekolah disimpan pada master lokal dan sekolah_id lead terisi.", "___", "___"],
            ["SCH-API-04", "Update tanpa duplikat", "Pilih lagi NPSN yang sudah tersimpan.", "Record diperbarui berdasarkan NPSN; tidak ada duplikat baru.", "___", "___"],
            ["SCH-API-05", "Metadata", "Periksa record hasil sinkronisasi.", "Bentuk, akreditasi, wilayah, sumber data, dan waktu sinkron tersedia sesuai respons.", "___", "___"],
            ["SCH-API-06", "Fallback gangguan", "Nonaktifkan koneksi/hasilkan timeout API.", "Pesan gagal jelas; pilihan lokal dan input manual tetap berfungsi.", "___", "___"],
            ["SCH-API-07", "Validasi input", "Cari kurang dari 3 karakter atau pilih data tanpa NPSN valid.", "Tidak ada panggilan/persistensi tidak valid; pengguna mendapat arahan.", "___", "___"],
        ]),
        ("D.7 Import, Follow-up, dan Konversi", [
            ["IMP-01", "Import CSV valid", "Impor template lead dengan data valid.", "Baris valid tersimpan dan ringkasan hasil tersedia.", "___", "___"],
            ["IMP-02", "Import tidak valid", "Impor nomor/status/referensi tidak valid.", "Baris gagal dilaporkan dengan pesan Indonesia yang jelas.", "___", "___"],
            ["FU-01", "Follow-up", "Catat metode, hasil, jadwal berikutnya, dan catatan.", "Riwayat baru tersimpan tanpa menimpa histori lama.", "___", "___"],
            ["REG-01", "Pendaftaran", "Konversi lead ke program aktif.", "Pendaftaran terbentuk dan status lead menjadi Daftar.", "___", "___"],
            ["PAY-01", "Pembayaran", "Catat pembayaran dan lakukan verifikasi sesuai role.", "Nominal/status tersimpan, omzet mengikuti status yang sah.", "___", "___"],
        ]),
        ("D.8 WhatsApp, Dashboard, dan Audit", [
            ["WA-01", "Session WAHA", "Siapkan session GOWS dan pindai QR.", "Status menjadi Working dan akun aktif dapat digunakan.", "___", "___"],
            ["WA-02", "Pesan masuk", "Kirim pesan dari nomor uji baru dan yang telah cocok.", "Conversation/pesan tersimpan; pencocokan lead sesuai cabang.", "___", "___"],
            ["WA-03", "Idempotensi webhook", "Kirim event webhook yang sama dua kali.", "Event kedua tidak membuat pesan/lead duplikat.", "___", "___"],
            ["DASH-01", "Filter global", "Ubah cabang, tahun, bulan, dan pengguna.", "Seluruh KPI/widget memakai scope dan periode yang sama.", "___", "___"],
            ["REP-01", "Laporan/ekspor", "Ekspor laporan dengan filter aktif.", "Isi export sesuai filter, role, dan scope cabang.", "___", "___"],
            ["AUD-01", "Audit aktivitas", "Buat/ubah data penting lalu periksa log.", "Aktor, waktu, cabang, event, dan subjek tercatat tanpa secret.", "___", "___"],
        ]),
    ]

    for heading_text, rows in scenarios:
        heading = new_paragraph_before(glossary, heading_text, "Heading 1")
        heading.paragraph_format.keep_with_next = True
        insert_table_before(
            doc,
            glossary,
            ["ID", "Skenario", "Langkah uji", "Hasil yang diharapkan", "Status", "Bukti"],
            rows,
            [0.65, 1.15, 1.55, 2.35, 0.55, 0.75],
        )

    h = new_paragraph_before(glossary, "D.9 Daftar Defect dan Tindak Lanjut", "Heading 1")
    h.paragraph_format.keep_with_next = True
    insert_table_before(doc, glossary,
        ["Defect ID", "Skenario", "Severity", "Deskripsi/actual", "PIC", "Status"],
        [["", "", "Blocker/Critical/Major/Minor", "", "", "Open/Closed"] for _ in range(6)],
        [0.85, 1.0, 1.25, 2.5, 0.75, 0.95],
    )

    h = new_paragraph_before(glossary, "D.10 Persetujuan UAT", "Heading 1")
    h.paragraph_format.keep_with_next = True
    new_paragraph_before(glossary, "Dengan menandatangani tabel berikut, pihak terkait menyatakan hasil UAT telah ditinjau. Persetujuan dapat diberikan tanpa catatan atau dengan daftar pengecualian yang disepakati.", "Normal")
    insert_table_before(doc, glossary,
        ["Peran", "Nama", "Keputusan", "Tanggal", "Tanda tangan"],
        [
            ["Koordinator UAT", "", "Terima / Tolak", "", ""],
            ["Pemilik Bisnis/Direksi", "", "Terima / Tolak", "", ""],
            ["Superadmin/Administrator", "", "Terima / Tolak", "", ""],
            ["Perwakilan Admin Cabang", "", "Terima / Tolak", "", ""],
            ["Perwakilan Staff", "", "Terima / Tolak", "", ""],
        ],
        [1.45, 1.35, 1.25, 1.2, 2.05],
    )
    note = new_paragraph_before(glossary, "Catatan keputusan/pengecualian: ____________________________________________________________________________________________")
    note.paragraph_format.space_before = Pt(8)
    note.paragraph_format.space_after = Pt(10)
    set_run_font(note.runs[0], size=9)


def remove_existing_revision(doc: Document) -> None:
    markers = {
        "DAFTAR ISI",
        "LAMPIRAN D: USER ACCEPTANCE TESTING (UAT)",
        "10.4.1 Memilih Sekolah dari API Sekolah Indonesia",
        "11.8.1 Konfigurasi API Sekolah Indonesia",
    }
    if any(paragraph.text.strip() in markers for paragraph in doc.paragraphs):
        raise RuntimeError("Dokumen input sudah memuat revisi ini; gunakan dokumen sumber asli.")


def patch_page_map(input_path: Path, output_path: Path, page_map: dict[str, int]) -> None:
    doc = Document(input_path)
    for paragraph in doc.paragraphs:
        text = paragraph.text
        if "{{PAGE:" not in text:
            continue
        for key, page in page_map.items():
            token = f"{{{{PAGE:{key}}}}}"
            if token in paragraph.text:
                for run in paragraph.runs:
                    if token in run.text:
                        run.text = run.text.replace(token, str(page))
                if token in paragraph.text:
                    paragraph.text = paragraph.text.replace(token, str(page))
    doc.save(output_path)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("input", type=Path)
    parser.add_argument("output", type=Path)
    parser.add_argument("--page-map", type=Path)
    args = parser.parse_args()

    if args.page_map:
        page_map = json.loads(args.page_map.read_text(encoding="utf-8"))
        patch_page_map(args.input, args.output, page_map)
        return

    doc = Document(args.input)
    remove_existing_revision(doc)
    add_api_school_updates(doc)
    add_uat_appendix(doc)
    add_toc(doc, None)

    doc.core_properties.title = "Dokumentasi Project CRM_VILLA MERAH"
    doc.core_properties.subject = "Dokumentasi sistem, panduan, SOP, API Sekolah, dan UAT"
    doc.core_properties.comments = "Revisi 1 September 2026: daftar isi, API Sekolah Indonesia, dan Lampiran UAT."
    doc.settings.update_fields_on_open = True
    args.output.parent.mkdir(parents=True, exist_ok=True)
    doc.save(args.output)


if __name__ == "__main__":
    main()
