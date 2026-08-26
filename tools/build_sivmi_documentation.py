from __future__ import annotations

import math
import shutil
import sys
import textwrap
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont
from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


REFERENCE = Path(sys.argv[1]).resolve()
OUTPUT = Path(sys.argv[2]).resolve()
WORK = Path(sys.argv[3]).resolve()
WORK.mkdir(parents=True, exist_ok=True)

BLUE = "14357A"
MID_BLUE = "28559E"
PRIMARY = "1476B8"
LIGHT_BLUE = "EAF4FB"
PALE = "F6F8FC"
TEXT = "334155"
MUTED = "64748B"
WHITE = "FFFFFF"
GREEN = "10B981"
AMBER = "F59E0B"
RED = "F43F5E"
PURPLE = "A855F7"


def set_run_font(run, name="Aptos", size=10.5, bold=None, color=TEXT):
    run.font.name = name
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    run.font.size = Pt(size)
    run.font.bold = bold
    if color:
        run.font.color.rgb = RGBColor.from_string(color)


def shade_cell(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = tc_pr.find(qn("w:shd"))
    if shd is None:
        shd = OxmlElement("w:shd")
        tc_pr.append(shd)
    shd.set(qn("w:fill"), fill)


def set_cell_margins(cell, top=90, start=120, bottom=90, end=120):
    tc_pr = cell._tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in("w:tcMar")
    if tc_mar is None:
        tc_mar = OxmlElement("w:tcMar")
        tc_pr.append(tc_mar)
    for tag, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        node = tc_mar.find(qn(f"w:{tag}"))
        if node is None:
            node = OxmlElement(f"w:{tag}")
            tc_mar.append(node)
        node.set(qn("w:w"), str(value))
        node.set(qn("w:type"), "dxa")


def set_table_geometry(table, widths_in):
    widths = [int(value * 1440) for value in widths_in]
    total = sum(widths)
    table.autofit = False
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    tbl_pr = table._tbl.tblPr
    tbl_w = tbl_pr.find(qn("w:tblW"))
    if tbl_w is None:
        tbl_w = OxmlElement("w:tblW")
        tbl_pr.append(tbl_w)
    tbl_w.set(qn("w:w"), str(total))
    tbl_w.set(qn("w:type"), "dxa")
    tbl_ind = tbl_pr.find(qn("w:tblInd"))
    if tbl_ind is None:
        tbl_ind = OxmlElement("w:tblInd")
        tbl_pr.append(tbl_ind)
    tbl_ind.set(qn("w:w"), "120")
    tbl_ind.set(qn("w:type"), "dxa")
    grid = table._tbl.tblGrid
    for child in list(grid):
        grid.remove(child)
    for width in widths:
        col = OxmlElement("w:gridCol")
        col.set(qn("w:w"), str(width))
        grid.append(col)
    for row in table.rows:
        for index, cell in enumerate(row.cells):
            width = widths[min(index, len(widths) - 1)]
            tc_pr = cell._tc.get_or_add_tcPr()
            tc_w = tc_pr.find(qn("w:tcW"))
            if tc_w is None:
                tc_w = OxmlElement("w:tcW")
                tc_pr.append(tc_w)
            tc_w.set(qn("w:w"), str(width))
            tc_w.set(qn("w:type"), "dxa")
            set_cell_margins(cell)


def clear_document_body(document):
    body = document._element.body
    sect_pr = body.sectPr
    for child in list(body):
        if child is not sect_pr:
            body.remove(child)


def clear_header_footer(part):
    element = part._element
    for child in list(element):
        element.remove(child)
    paragraph = OxmlElement("w:p")
    element.append(paragraph)


def add_field(paragraph, instruction, placeholder=""):
    run = paragraph.add_run()
    begin = OxmlElement("w:fldChar")
    begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = instruction
    separate = OxmlElement("w:fldChar")
    separate.set(qn("w:fldCharType"), "separate")
    text = OxmlElement("w:t")
    text.text = placeholder
    end = OxmlElement("w:fldChar")
    end.set(qn("w:fldCharType"), "end")
    run._r.extend([begin, instr, separate, text, end])
    return run


def add_page_field(paragraph):
    return add_field(paragraph, "PAGE \\* MERGEFORMAT", "1")


def add_toc(paragraph):
    add_field(paragraph, 'TOC \\o "1-3" \\h \\z \\u', "Klik kanan lalu pilih Update Field bila daftar isi belum diperbarui.")


def add_paragraph(document, text="", *, style=None, bold_lead=None, align=None):
    paragraph = document.add_paragraph(style=style)
    if bold_lead and text.startswith(bold_lead):
        lead = paragraph.add_run(bold_lead)
        set_run_font(lead, bold=True)
        rest = paragraph.add_run(text[len(bold_lead):])
        set_run_font(rest)
    else:
        run = paragraph.add_run(text)
        set_run_font(run)
    if align is not None:
        paragraph.alignment = align
    return paragraph


def add_bullets(document, items, level=0):
    for item in items:
        paragraph = document.add_paragraph(style="List Bullet" if level == 0 else "List Bullet 2")
        set_run_font(paragraph.add_run(item))


def add_numbers(document, items):
    for index, item in enumerate(items, start=1):
        paragraph = document.add_paragraph()
        paragraph.paragraph_format.left_indent = Inches(0.25)
        paragraph.paragraph_format.first_line_indent = Inches(-0.25)
        paragraph.paragraph_format.space_before = Pt(0)
        paragraph.paragraph_format.space_after = Pt(0)
        paragraph.paragraph_format.line_spacing = 1.0
        set_run_font(paragraph.add_run(f"{index}.  {item}"))


def add_heading(document, text, level=1):
    paragraph = document.add_heading(text, level=level)
    return paragraph


def set_image_alt(shape, title, description):
    properties = shape._inline.docPr
    properties.set("title", title)
    properties.set("descr", description)


def add_table(document, headers, rows, widths, caption=None):
    if caption:
        paragraph = document.add_paragraph()
        paragraph.paragraph_format.keep_with_next = True
        run = paragraph.add_run(caption)
        set_run_font(run, size=9.5, bold=True, color=BLUE)
    table = document.add_table(rows=1, cols=len(headers))
    table.style = "Table Grid"
    set_table_geometry(table, widths)
    for index, header in enumerate(headers):
        cell = table.rows[0].cells[index]
        shade_cell(cell, BLUE)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        paragraph = cell.paragraphs[0]
        paragraph.paragraph_format.space_after = Pt(0)
        run = paragraph.add_run(str(header))
        set_run_font(run, size=9, bold=True, color=WHITE)
    table.rows[0]._tr.get_or_add_trPr().append(OxmlElement("w:tblHeader"))
    for row_index, row_data in enumerate(rows):
        cells = table.add_row().cells
        for index, value in enumerate(row_data):
            cell = cells[index]
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.TOP
            if row_index % 2:
                shade_cell(cell, PALE)
            paragraph = cell.paragraphs[0]
            paragraph.paragraph_format.space_after = Pt(0)
            run = paragraph.add_run(str(value))
            set_run_font(run, size=8.6)
    set_table_geometry(table, widths)
    document.add_paragraph().paragraph_format.space_after = Pt(0)
    return table


def add_note(document, title, text, color=LIGHT_BLUE):
    table = document.add_table(rows=1, cols=1)
    set_table_geometry(table, [6.80])
    cell = table.cell(0, 0)
    shade_cell(cell, color)
    paragraph = cell.paragraphs[0]
    run = paragraph.add_run(title + " ")
    set_run_font(run, bold=True, color=BLUE)
    set_run_font(paragraph.add_run(text))
    return table


def font(size, bold=False):
    candidates = [
        ("C:/Windows/Fonts/Aptos.ttf", "C:/Windows/Fonts/Aptos-Bold.ttf"),
        ("C:/Windows/Fonts/segoeui.ttf", "C:/Windows/Fonts/seguisb.ttf"),
        ("C:/Windows/Fonts/arial.ttf", "C:/Windows/Fonts/arialbd.ttf"),
    ]
    for regular, strong in candidates:
        path = Path(strong if bold else regular)
        if path.exists():
            return ImageFont.truetype(str(path), size)
    return ImageFont.load_default()


def gradient_cover(path):
    width, height = 1240, 1754
    image = Image.new("RGB", (width, height), "#075985")
    pixels = image.load()
    for y in range(height):
        ratio = y / max(1, height - 1)
        for x in range(width):
            horizontal = x / max(1, width - 1)
            r = int(7 + 13 * (1 - ratio) + 8 * horizontal)
            g = int(89 + 29 * (1 - ratio) + 17 * horizontal)
            b = int(133 + 51 * (1 - ratio) + 18 * horizontal)
            pixels[x, y] = (r, g, b)
    draw = ImageDraw.Draw(image, "RGBA")
    for cx, cy, radius, alpha in [(1070, 210, 310, 34), (110, 1440, 410, 25), (940, 1480, 220, 22)]:
        draw.ellipse((cx-radius, cy-radius, cx+radius, cy+radius), outline=(255,255,255,alpha), width=3)
    for i in range(9):
        x = 770 + i * 55
        draw.line((x, 0, 1240, 380 + i * 28), fill=(255,255,255,28), width=2)
    draw.rounded_rectangle((80, 90, 320, 150), radius=22, fill=(255,255,255,35), outline=(255,255,255,75), width=2)
    draw.text((112, 105), "SIVMI CRM", font=font(28, True), fill="white")
    draw.text((82, 380), "DOKUMENTASI DAN", font=font(34, True), fill=(188, 230, 255))
    draw.text((82, 430), "PENGEMBANGAN SISTEM", font=font(48, True), fill="white")
    draw.text((82, 500), "CRM SIVMI", font=font(88, True), fill="white")
    draw.rounded_rectangle((82, 640, 1110, 650), radius=5, fill=(255,255,255,120))
    draw.text((82, 700), "Customer Relationship Management", font=font(32), fill=(221, 242, 255))
    draw.text((82, 750), "untuk operasional bimbingan belajar", font=font(32), fill=(221, 242, 255))
    metadata = ["Versi sementara 1.1", "Status: Dokumentasi berjalan", "20 Agustus 2026"]
    for index, line in enumerate(metadata):
        draw.text((84, 1330 + index * 48), line, font=font(24, index == 0), fill=(235,248,255))
    draw.text((84, 1580), "Laravel 12  |  Filament 5  |  Livewire  |  Tailwind CSS", font=font(22), fill=(188,230,255))
    image.save(path)


def simple_diagram(path, title, columns):
    width, height = 1500, 780
    image = Image.new("RGB", (width, height), "white")
    draw = ImageDraw.Draw(image)
    draw.text((60, 42), title, font=font(38, True), fill="#14357A")
    palette = ["#EAF4FB", "#EAFBF5", "#FFF7E6", "#F5EEFF", "#FFEFF2"]
    left = 45
    gap = 24
    box_width = int((width - 90 - gap * (len(columns)-1)) / len(columns))
    for index, (heading, items) in enumerate(columns):
        x1 = left + index * (box_width + gap)
        x2 = x1 + box_width
        draw.rounded_rectangle((x1, 130, x2, 700), radius=22, fill=palette[index % len(palette)], outline="#1476B8", width=3)
        draw.rounded_rectangle((x1, 130, x2, 215), radius=22, fill="#1476B8")
        draw.rectangle((x1, 180, x2, 215), fill="#1476B8")
        draw.text((x1 + 22, 156), heading, font=font(26, True), fill="white")
        y = 250
        for item in items:
            draw.ellipse((x1 + 24, y + 8, x1 + 36, y + 20), fill="#10B981")
            draw.text((x1 + 50, y), item, font=font(20), fill="#334155")
            y += 58
    image.save(path)


def draw_wrapped(draw, xy, text, *, size=22, bold=False, fill="#334155", width=26, anchor=None, align="left"):
    value = "\n".join(textwrap.wrap(text, width=width))
    draw.multiline_text(xy, value, font=font(size, bold), fill=fill, spacing=6, anchor=anchor, align=align)


def draw_box(draw, box, title, lines=(), *, fill="#EAF4FB", outline="#1476B8", title_fill="#1476B8"):
    x1, y1, x2, y2 = box
    draw.rounded_rectangle(box, radius=18, fill=fill, outline=outline, width=3)
    draw.rounded_rectangle((x1, y1, x2, y1 + 54), radius=18, fill=title_fill)
    draw.rectangle((x1, y1 + 35, x2, y1 + 54), fill=title_fill)
    draw.text(((x1 + x2) / 2, y1 + 27), title, font=font(21, True), fill="white", anchor="mm")
    y = y1 + 72
    for line in lines:
        draw.text((x1 + 16, y), line, font=font(16), fill="#334155")
        y += 27


def draw_connector(draw, start, end, label="", *, color="#64748B", width=4):
    draw.line((*start, *end), fill=color, width=width)
    angle = math.atan2(end[1] - start[1], end[0] - start[0])
    length = 15
    wing = 7
    points = [
        end,
        (end[0] - length * math.cos(angle) + wing * math.sin(angle), end[1] - length * math.sin(angle) - wing * math.cos(angle)),
        (end[0] - length * math.cos(angle) - wing * math.sin(angle), end[1] - length * math.sin(angle) + wing * math.cos(angle)),
    ]
    draw.polygon(points, fill=color)
    if label:
        mx, my = (start[0] + end[0]) / 2, (start[1] + end[1]) / 2
        bounds = draw.textbbox((mx, my), label, font=font(15, True), anchor="mm")
        draw.rounded_rectangle((bounds[0] - 7, bounds[1] - 4, bounds[2] + 7, bounds[3] + 4), radius=7, fill="white", outline="#CBD5E1")
        draw.text((mx, my), label, font=font(15, True), fill="#334155", anchor="mm")


def add_figure(document, path, width, title, description):
    paragraph = document.add_paragraph()
    paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
    paragraph.paragraph_format.keep_with_next = True
    shape = paragraph.add_run().add_picture(str(path), width=Inches(width))
    set_image_alt(shape, title, description)
    caption = document.add_paragraph()
    caption.alignment = WD_ALIGN_PARAGRAPH.CENTER
    caption.paragraph_format.space_before = Pt(3)
    caption.paragraph_format.space_after = Pt(8)
    set_run_font(caption.add_run(title), size=9, bold=True, color=BLUE)
    return shape


def sitemap_diagram(path):
    image = Image.new("RGB", (1500, 920), "white")
    draw = ImageDraw.Draw(image)
    draw.text((60, 40), "Sitemap Panel Administrasi SIVMI CRM", font=font(38, True), fill="#14357A")
    root = (575, 105, 925, 190)
    draw_box(draw, root, "/admin", ["Autentikasi pengguna"])
    crm = (130, 300, 670, 390)
    master = (830, 300, 1370, 390)
    draw_box(draw, crm, "SIVMI CRM", ["Operasional dan monitoring"], fill="#EAF4FB")
    draw_box(draw, master, "MASTER DATA", ["Referensi dan kontrol sistem"], fill="#EAFBF5", title_fill="#075985")
    draw_connector(draw, (650, 190), (400, 300))
    draw_connector(draw, (850, 190), (1100, 300))
    crm_items = ["Dashboard", "Leads", "Follow-up", "Target Kinerja", "Laporan"]
    master_items = ["Cabang", "Sekolah", "Pengguna", "Pendaftaran", "Pembayaran", "Program Bimbel", "Pengaturan", "Audit Aktivitas"]
    for index, label in enumerate(crm_items):
        y = 470 + index * 78
        draw.rounded_rectangle((160, y, 640, y + 54), radius=14, fill="#F6F8FC", outline="#1476B8", width=2)
        draw.text((400, y + 27), label, font=font(19, True), fill="#334155", anchor="mm")
        draw_connector(draw, (400, 390), (400, y), color="#94A3B8", width=2)
    for index, label in enumerate(master_items):
        col = index % 2
        row = index // 2
        x = 850 + col * 270
        y = 470 + row * 98
        draw.rounded_rectangle((x, y, x + 240, y + 58), radius=14, fill="#F6F8FC", outline="#075985", width=2)
        draw.text((x + 120, y + 29), label, font=font(17, True), fill="#334155", anchor="mm")
        draw_connector(draw, (1100, 390), (x + 120, y), color="#94A3B8", width=2)
    image.save(path)


def erd_operational_diagram(path):
    image = Image.new("RGB", (1800, 1200), "white")
    draw = ImageDraw.Draw(image)
    draw.text((55, 35), "ERD Operasional CRM", font=font(40, True), fill="#14357A")
    nodes = {
        "cabang": (60, 150, 390, 360),
        "users": (470, 100, 820, 355),
        "sekolah": (60, 650, 390, 890),
        "program": (470, 900, 820, 1150),
        "leads": (790, 435, 1170, 755),
        "followup": (1250, 120, 1645, 405),
        "pendaftaran": (1250, 520, 1645, 805),
        "pembayaran": (1250, 900, 1645, 1145),
    }
    draw_box(draw, nodes["cabang"], "cabang", ["PK cabang_id", "kode_cabang", "nama", "aktif"])
    draw_box(draw, nodes["users"], "users", ["PK id", "FK cabang_id (nullable)", "role", "aktif"])
    draw_box(draw, nodes["sekolah"], "sekolah", ["PK sekolah_id", "npsn (nullable, unique)", "nama_sekolah", "kluster_sekolah"])
    draw_box(draw, nodes["program"], "program_bimbel", ["PK program_bimbel_id", "nama_program", "biaya_pendidikan", "aktif"])
    draw_box(draw, nodes["leads"], "leads", ["PK lead_id", "FK sekolah_id (nullable)", "FK minat_program_id (nullable)", "FK cabang_id", "FK diinput_oleh_user_id", "FK diserahkan_ke_user_id (nullable)", "status_lead", "tanggal_masuk"])
    draw_box(draw, nodes["followup"], "followup_riwayat", ["PK followup_riwayat_id", "FK lead_id", "FK user_id", "tanggal_followup", "status_kontak"])
    draw_box(draw, nodes["pendaftaran"], "pendaftaran", ["PK pendaftaran_id", "FK lead_id (unique)", "FK program_bimbel_id", "FK cabang_id", "status_pendaftaran"])
    draw_box(draw, nodes["pembayaran"], "pembayaran", ["PK pembayaran_id", "FK pendaftaran_id", "tanggal_pembayaran", "nominal", "status_pembayaran"])
    draw_connector(draw, (390, 235), (470, 235), "1 : 0..N")
    draw_connector(draw, (390, 310), (790, 520), "1 : 0..N")
    draw_connector(draw, (390, 760), (790, 660), "1 : 0..N")
    draw_connector(draw, (820, 240), (790, 500), "1 : 0..N")
    draw_connector(draw, (820, 1010), (790, 710), "1 : 0..N")
    draw_connector(draw, (1170, 520), (1250, 310), "1 : 0..N")
    draw_connector(draw, (1170, 640), (1250, 650), "1 : 0..1")
    draw_connector(draw, (820, 1060), (1250, 730), "1 : 0..N")
    draw_connector(draw, (1450, 805), (1450, 900), "1 : 0..N")
    image.save(path)


def erd_control_diagram(path):
    image = Image.new("RGB", (1700, 980), "white")
    draw = ImageDraw.Draw(image)
    draw.text((55, 35), "ERD Kinerja, Pengaturan, dan Audit", font=font(40, True), fill="#14357A")
    boxes = {
        "cabang": (60, 160, 370, 350), "users": (60, 570, 370, 790),
        "target_c": (570, 120, 1040, 400), "target_u": (1220, 150, 1640, 400),
        "settings": (570, 590, 1040, 840), "activity": (1200, 530, 1640, 860),
    }
    draw_box(draw, boxes["cabang"], "cabang", ["PK cabang_id", "nama", "aktif"])
    draw_box(draw, boxes["users"], "users", ["PK id", "FK cabang_id", "role", "aktif"])
    draw_box(draw, boxes["target_c"], "target_kinerja_cabang", ["PK target_kinerja_cabang_id", "FK cabang_id", "FK created_by (nullable)", "tahun + bulan (unique/cabang)", "target_omzet", "target_siswa"])
    draw_box(draw, boxes["target_u"], "target_kinerja_user", ["PK target_kinerja_user_id", "FK target_kinerja_cabang_id", "FK user_id", "target_omzet", "target_siswa"])
    draw_box(draw, boxes["settings"], "crm_settings", ["PK key", "FK updated_by (nullable)", "value", "type", "group"])
    draw_box(draw, boxes["activity"], "activity_logs", ["PK activity_log_id", "FK user_id (nullable)", "FK cabang_id (nullable)", "subject_type + subject_id", "event", "properties"])
    draw_connector(draw, (370, 250), (570, 250), "1 : 0..N")
    draw_connector(draw, (1040, 260), (1220, 260), "1 : 0..N")
    draw_connector(draw, (370, 650), (1220, 350), "1 : 0..N")
    draw_connector(draw, (370, 700), (570, 700), "1 : 0..N")
    draw_connector(draw, (370, 745), (1200, 690), "1 : 0..N")
    draw_connector(draw, (370, 300), (1200, 780), "1 : 0..N")
    image.save(path)


def dfd_context_diagram(path):
    image = Image.new("RGB", (1600, 920), "white")
    draw = ImageDraw.Draw(image)
    draw.text((55, 35), "DFD Level 0 - Diagram Konteks", font=font(40, True), fill="#14357A")
    draw.ellipse((570, 250, 1030, 710), fill="#EAF4FB", outline="#1476B8", width=5)
    draw.text((800, 430), "0", font=font(34, True), fill="#1476B8", anchor="mm")
    draw_wrapped(draw, (800, 500), "SISTEM CRM SIVMI", size=32, bold=True, fill="#14357A", width=18, anchor="mm", align="center")
    entities = [
        ((60, 160, 420, 300), "Calon Siswa / Orang Tua", ["identitas, minat", "konfirmasi & status"]),
        ((60, 650, 420, 790), "Staff / Admin Cabang", ["lead, follow-up, transaksi", "tugas & notifikasi"]),
        ((1180, 160, 1540, 300), "Superadmin", ["master data & konfigurasi", "status sistem"]),
        ((1180, 650, 1540, 790), "Direksi", ["filter & permintaan laporan", "KPI & laporan"]),
    ]
    for box, title, lines in entities:
        draw_box(draw, box, title, lines, fill="#F6F8FC")
    draw_connector(draw, (420, 235), (570, 360), "data calon siswa")
    draw_connector(draw, (570, 430), (420, 275), "status layanan")
    draw_connector(draw, (420, 720), (570, 610), "data operasional")
    draw_connector(draw, (570, 650), (420, 760), "tugas/notifikasi")
    draw_connector(draw, (1180, 235), (1030, 360), "master & aturan")
    draw_connector(draw, (1030, 430), (1180, 275), "hasil konfigurasi")
    draw_connector(draw, (1180, 720), (1030, 610), "permintaan analisis")
    draw_connector(draw, (1030, 650), (1180, 760), "KPI/laporan")
    image.save(path)


def dfd_level1_diagram(path):
    image = Image.new("RGB", (1800, 1180), "white")
    draw = ImageDraw.Draw(image)
    draw.text((55, 30), "DFD Level 1 - Dekomposisi Proses CRM", font=font(40, True), fill="#14357A")
    processes = [
        ((470, 130, 900, 270), "1.0 Kelola Lead", "validasi, penugasan, status"),
        ((470, 340, 900, 480), "2.0 Kelola Follow-up", "kontak, catatan, reminder"),
        ((470, 550, 900, 690), "3.0 Kelola Pendaftaran", "konversi lead ke siswa"),
        ((470, 760, 900, 900), "4.0 Kelola Pembayaran", "catat dan verifikasi transaksi"),
        ((470, 970, 900, 1110), "5.0 Monitoring", "KPI, target, laporan, audit"),
    ]
    for box, title, subtitle in processes:
        draw_box(draw, box, title, [subtitle], fill="#EAF4FB")
    draw_box(draw, (50, 180, 350, 340), "Pengguna Operasional", ["Staff", "Admin Cabang"], fill="#F6F8FC")
    draw_box(draw, (50, 850, 350, 1010), "Manajemen", ["Superadmin", "Direksi"], fill="#F6F8FC")
    stores = [
        ((1120, 120, 1710, 230), "D1 Leads & Master"),
        ((1120, 330, 1710, 440), "D2 Riwayat Follow-up"),
        ((1120, 540, 1710, 650), "D3 Pendaftaran"),
        ((1120, 750, 1710, 860), "D4 Pembayaran"),
        ((1120, 960, 1710, 1070), "D5 Target, Notifikasi & Audit"),
    ]
    for box, title in stores:
        x1, y1, x2, y2 = box
        draw.rectangle(box, fill="#FFF7E6", outline="#F59E0B", width=3)
        draw.line((x1 + 12, y1, x1 + 12, y2), fill="#F59E0B", width=3)
        draw.text(((x1 + x2) / 2, (y1 + y2) / 2), title, font=font(20, True), fill="#334155", anchor="mm")
    for index, (box, _, _) in enumerate(processes):
        cy = (box[1] + box[3]) // 2
        draw_connector(draw, (350, 260 if index < 2 else 930), (470, cy), "input/otorisasi" if index in (0, 4) else "", width=3)
        draw_connector(draw, (900, cy), (1120, (stores[index][0][1] + stores[index][0][3]) // 2), "simpan/baca", width=3)
        if index < len(processes) - 1:
            draw_connector(draw, (685, box[3]), (685, processes[index + 1][0][1]), "status", width=3)
    image.save(path)


def flowmap_diagram(path):
    image = Image.new("RGB", (1700, 1150), "white")
    draw = ImageDraw.Draw(image)
    draw.text((55, 30), "Flowmap Proses Akuisisi hingga Monitoring", font=font(40, True), fill="#14357A")
    lanes = ["CALON SISWA", "STAFF", "ADMIN CABANG", "SISTEM", "DIREKSI"]
    lane_height = 190
    top = 120
    for index, lane in enumerate(lanes):
        y = top + index * lane_height
        draw.rectangle((40, y, 1660, y + lane_height), fill="#F8FAFC" if index % 2 == 0 else "#EFF6FF", outline="#CBD5E1", width=2)
        draw.rectangle((40, y, 250, y + lane_height), fill="#1476B8" if index < 4 else "#075985")
        draw.text((145, y + lane_height / 2), lane, font=font(19, True), fill="white", anchor="mm")
    steps = [
        (0, 300, "1. Menyampaikan\nminat & identitas"),
        (1, 520, "2. Mencatat lead\ndan penanggung jawab"),
        (3, 760, "3. Validasi, simpan,\ncatat audit"),
        (1, 990, "4. Follow-up dan\njadwal berikutnya"),
        (2, 1190, "5. Review konversi /\nverifikasi pembayaran"),
        (3, 1390, "6. Perbarui KPI, target,\nnotifikasi, laporan"),
        (4, 1500, "7. Pantau performa\ndan tindak lanjut"),
    ]
    centers = []
    for lane, x, label in steps:
        y = top + lane * lane_height + 55
        w = 250 if x < 1400 else 150
        box = (x, y, x + w, y + 82)
        draw.rounded_rectangle(box, radius=16, fill="white", outline="#1476B8", width=3)
        draw.multiline_text(((box[0] + box[2]) / 2, (box[1] + box[3]) / 2), label, font=font(16, True), fill="#334155", anchor="mm", align="center", spacing=4)
        centers.append(((box[0] + box[2]) / 2, (box[1] + box[3]) / 2))
    for start, end in zip(centers, centers[1:]):
        draw_connector(draw, start, end, width=4)
    image.save(path)


def flowchart_diagram(path):
    image = Image.new("RGB", (1300, 1650), "white")
    draw = ImageDraw.Draw(image)
    draw.text((55, 25), "Flowchart Lead hingga Pembayaran", font=font(38, True), fill="#14357A")
    def process(y, text, fill="#EAF4FB"):
        box = (350, y, 950, y + 100)
        draw.rounded_rectangle(box, radius=25, fill=fill, outline="#1476B8", width=4)
        draw_wrapped(draw, (650, y + 50), text, size=21, bold=True, width=38, anchor="mm", align="center")
        return box
    def decision(y, text):
        points = [(650, y), (970, y + 110), (650, y + 220), (330, y + 110)]
        draw.polygon(points, fill="#FFF7E6", outline="#F59E0B")
        draw.line(points + [points[0]], fill="#F59E0B", width=4)
        draw_wrapped(draw, (650, y + 110), text, size=20, bold=True, width=28, anchor="mm", align="center")
        return points
    start = process(110, "Mulai: lead diterima", "#EAFBF5")
    a = process(270, "Validasi identitas, cabang, sekolah, dan minat program")
    b = process(430, "Tetapkan penanggung jawab dan lakukan follow-up")
    d1 = decision(600, "Lead siap mendaftar?")
    c = process(880, "Pilih program dan buat pendaftaran")
    d2 = decision(1040, "Pembayaran valid dan berwenang diverifikasi?")
    e = process(1320, "Perbarui status siswa, omzet, target, notifikasi, dan audit", "#EAFBF5")
    end = process(1480, "Selesai", "#F5EEFF")
    for y1, y2 in [(210,270),(370,430),(530,600),(820,880),(980,1040),(1260,1320),(1420,1480)]:
        draw_connector(draw, (650, y1), (650, y2))
    draw.text((690, 845), "Ya", font=font(18, True), fill="#10B981")
    draw.text((690, 1280), "Ya", font=font(18, True), fill="#10B981")
    draw.line((330, 710, 170, 710, 170, 480, 350, 480), fill="#F43F5E", width=4)
    draw_connector(draw, (170, 480), (350, 480), "Tidak: follow-up ulang", color="#F43F5E")
    draw.line((970, 1150, 1130, 1150, 1130, 930, 950, 930), fill="#F43F5E", width=4)
    draw_connector(draw, (1130, 930), (950, 930), "Tidak: koreksi", color="#F43F5E")
    image.save(path)


shutil.copy2(REFERENCE, OUTPUT)
document = Document(OUTPUT)
clear_document_body(document)

section = document.sections[0]
section.page_width = Inches(8.268)
section.page_height = Inches(11.693)
section.left_margin = Inches(0.985)
section.right_margin = Inches(0.394)
section.top_margin = Inches(0.394)
section.bottom_margin = Inches(0.394)
section.header_distance = Inches(0.28)
section.footer_distance = Inches(0.25)
section.different_first_page_header_footer = True

for part in [section.header, section.first_page_header, section.footer, section.first_page_footer]:
    clear_header_footer(part)

normal = document.styles["Normal"]
normal.font.name = "Aptos"
normal._element.rPr.rFonts.set(qn("w:ascii"), "Aptos")
normal._element.rPr.rFonts.set(qn("w:hAnsi"), "Aptos")
normal.font.size = Pt(10.5)
normal.font.color.rgb = RGBColor.from_string(TEXT)
normal.paragraph_format.space_after = Pt(6)
normal.paragraph_format.line_spacing = 1.05

for style_name, size, color, before, after in [
    ("Heading 1", 16, BLUE, 16, 8),
    ("Heading 2", 13, MID_BLUE, 12, 5),
    ("Heading 3", 11, TEXT, 9, 4),
]:
    style = document.styles[style_name]
    style.font.name = "Aptos Display"
    style._element.rPr.rFonts.set(qn("w:ascii"), "Aptos Display")
    style._element.rPr.rFonts.set(qn("w:hAnsi"), "Aptos Display")
    style.font.size = Pt(size)
    style.font.bold = True
    style.font.color.rgb = RGBColor.from_string(color)
    style.paragraph_format.space_before = Pt(before)
    style.paragraph_format.space_after = Pt(after)
    style.paragraph_format.keep_with_next = True
document.styles["Heading 1"].paragraph_format.page_break_before = True

header = section.header
header_p = header.paragraphs[0]
header_p.alignment = WD_ALIGN_PARAGRAPH.LEFT
set_run_font(header_p.add_run("SIVMI CRM"), size=8.5, bold=True, color=PRIMARY)
set_run_font(header_p.add_run("  |  Dokumentasi dan Pengembangan Sistem"), size=8.5, color=MUTED)

footer = section.footer
footer_p = footer.paragraphs[0]
footer_p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
set_run_font(footer_p.add_run("Dokumentasi CRM SIVMI  |  "), size=8, color=MUTED)
page_run = add_page_field(footer_p)
set_run_font(page_run, size=8, color=PRIMARY)

settings = document.settings._element
update_fields = settings.find(qn("w:updateFields"))
if update_fields is None:
    update_fields = OxmlElement("w:updateFields")
    settings.append(update_fields)
update_fields.set(qn("w:val"), "true")

cover_path = WORK / "sivmi-cover.png"
architecture_path = WORK / "architecture.png"
flow_path = WORK / "business-flow.png"
data_path = WORK / "data-map.png"
sitemap_path = WORK / "sitemap.png"
erd_operational_path = WORK / "erd-operational.png"
erd_control_path = WORK / "erd-control.png"
dfd_context_path = WORK / "dfd-level-0.png"
dfd_level1_path = WORK / "dfd-level-1.png"
flowmap_path = WORK / "flowmap.png"
flowchart_path = WORK / "flowchart.png"
gradient_cover(cover_path)
simple_diagram(architecture_path, "Arsitektur Berlapis SIVMI CRM", [
    ("PRESENTASI", ["Filament 5", "Blade", "Tailwind CSS", "Responsif"]),
    ("INTERAKSI", ["Livewire", "Form & Table", "Global filter", "Notifikasi"]),
    ("APLIKASI", ["Resource", "Page", "Observer", "Support service"]),
    ("DATA", ["Eloquent ORM", "Query scope", "Migration", "Import/Export"]),
    ("PLATFORM", ["Laravel 12", "PHP 8.3+", "Queue/Scheduler", "Vite 8"]),
])
simple_diagram(flow_path, "Alur Utama Akuisisi sampai Pembayaran", [
    ("AKUISISI", ["Lead masuk", "Sumber lead", "Cabang", "Penanggung jawab"]),
    ("TINDAK LANJUT", ["Jadwal follow-up", "Metode kontak", "Status kontak", "Riwayat hasil"]),
    ("KONVERSI", ["Pilih program", "Tanggal daftar", "Status siswa", "Cabang"]),
    ("TRANSAKSI", ["Nominal", "Metode bayar", "Verifikasi", "Referensi"]),
    ("MONITORING", ["Dashboard", "Target kinerja", "Laporan", "Audit aktivitas"]),
])
simple_diagram(data_path, "Peta Entitas Inti", [
    ("MASTER", ["Cabang", "Sekolah", "Pengguna", "Program bimbel"]),
    ("CRM", ["Lead", "Follow-up", "Pendaftaran", "Pembayaran"]),
    ("KINERJA", ["Target cabang", "Target pengguna", "Laporan", "Dashboard"]),
    ("KONTROL", ["Pengaturan", "Notifikasi", "Audit aktivitas", "Job batch"]),
])
sitemap_diagram(sitemap_path)
erd_operational_diagram(erd_operational_path)
erd_control_diagram(erd_control_path)
dfd_context_diagram(dfd_context_path)
dfd_level1_diagram(dfd_level1_path)
flowmap_diagram(flowmap_path)
flowchart_diagram(flowchart_path)

cover_paragraph = document.add_paragraph()
cover_paragraph.alignment = WD_ALIGN_PARAGRAPH.CENTER
cover_paragraph.paragraph_format.space_after = Pt(0)
cover_shape = cover_paragraph.add_run().add_picture(str(cover_path), width=Inches(6.88), height=Inches(9.95))
set_image_alt(cover_shape, "Sampul dokumentasi SIVMI CRM", "Sampul biru dokumentasi dan pengembangan sistem CRM SIVMI versi sementara 1.0.")
document.add_page_break()

toc_title = document.add_paragraph()
toc_title.alignment = WD_ALIGN_PARAGRAPH.CENTER
toc_title.paragraph_format.space_after = Pt(14)
set_run_font(toc_title.add_run("DAFTAR ISI"), name="Aptos Display", size=18, bold=True, color=BLUE)
toc_p = document.add_paragraph()
add_toc(toc_p)
document.add_page_break()

add_heading(document, "BAB I: PENDAHULUAN", 1)
add_heading(document, "1.1 Latar Belakang", 2)
add_paragraph(document, "SIVMI CRM adalah sistem informasi berbasis web untuk memusatkan proses pengelolaan calon siswa, tindak lanjut, pendaftaran, pembayaran, program bimbingan belajar, target kinerja, dan pelaporan. Sistem dirancang untuk mengurangi data tersebar, memperjelas kepemilikan tindak lanjut, dan menyediakan informasi operasional yang konsisten bagi cabang maupun direksi.", align=WD_ALIGN_PARAGRAPH.JUSTIFY)
add_paragraph(document, "Aplikasi menggunakan pendekatan monolitik modular pada Laravel. Filament menangani panel administrasi dan komponen CRUD, Livewire menyediakan interaksi server-driven, Eloquent mengelola akses basis data, dan Tailwind CSS membentuk antarmuka responsif. Seluruh fitur utama berada dalam satu codebase sehingga alur bisnis, hak akses, dan audit dapat dijaga secara konsisten.", align=WD_ALIGN_PARAGRAPH.JUSTIFY)
add_heading(document, "1.2 Tujuan Dokumentasi", 2)
add_bullets(document, [
    "Menjadi acuan fungsional bagi Superadmin, Admin Cabang, Staff, dan Direksi.",
    "Mencatat arsitektur aplikasi, struktur data, kontrol akses, dan alur operasional yang telah diterapkan.",
    "Menyediakan panduan deployment, pengujian, pemeliharaan, serta pengembangan berikutnya.",
    "Menjaga kesesuaian antara implementasi, ERD, dan kebutuhan bisnis SIVMI.",
])
add_heading(document, "1.3 Ruang Lingkup", 2)
add_table(document, ["Area", "Cakupan saat ini", "Status"], [
    ["CRM operasional", "Leads, follow-up, pendaftaran, pembayaran", "Tersedia"],
    ["Master data", "Cabang, sekolah, pengguna, program bimbel", "Tersedia"],
    ["Manajemen kinerja", "Target cabang, target pengguna, dashboard, laporan", "Tersedia"],
    ["Kontrol sistem", "RBAC, pembatasan cabang, notifikasi, audit aktivitas", "Tersedia"],
    ["Pertukaran data", "Impor dan ekspor sekolah melalui CSV", "Tersedia"],
    ["CRUD lanjutan", "Modul tambahan di luar shell dashboard dan resource inti", "Bertahap"],
], [1.35, 4.35, 1.10], "Tabel 1. Ruang lingkup implementasi")
add_heading(document, "1.4 Identitas Dokumen", 2)
add_table(document, ["Atribut", "Nilai"], [
    ["Nama sistem", "SIVMI CRM"],
    ["Jenis dokumen", "Dokumentasi dan Pengembangan Sistem"],
    ["Versi dokumen", "Versi sementara 1.1"],
    ["Tanggal snapshot", "20 Agustus 2026"],
    ["Lokasi proyek", r"C:\laragon\www\sivmi.crm"],
    ["Acuan data", "erd v3 - rekomendasi.drawio dan migration aplikasi"],
], [1.65, 5.15])

add_heading(document, "BAB II: GAMBARAN SISTEM DAN TEKNOLOGI", 1)
add_heading(document, "2.1 Ringkasan Arsitektur", 2)
add_paragraph(document, "Browser mengakses panel Filament melalui rute /admin. Middleware panel menjalankan autentikasi, session, CSRF, substitusi binding, dan setup panel. Resource dan Page Filament membentuk antarmuka; Livewire mengirim pembaruan komponen; model Eloquent menerapkan relasi dan query scope; observer mencatat aktivitas serta menghasilkan notifikasi.", align=WD_ALIGN_PARAGRAPH.JUSTIFY)
add_figure(document, architecture_path, 6.75, "Gambar 1. Arsitektur berlapis SIVMI CRM", "Diagram lima lapisan: presentasi, interaksi, aplikasi, data, dan platform.")
add_heading(document, "2.2 Tumpukan Teknologi", 2)
add_table(document, ["Lapisan", "Teknologi", "Peran"], [
    ["Runtime", "PHP 8.3+", "Menjalankan aplikasi Laravel"],
    ["Framework", "Laravel 12", "Routing, middleware, Eloquent, queue, scheduler, testing"],
    ["Panel", "Filament 5", "Resource, form, table, action, import/export, notifikasi"],
    ["Interaksi", "Livewire", "Komponen reaktif tanpa SPA terpisah"],
    ["Tampilan", "Tailwind CSS 4", "Layout responsif dan design system"],
    ["Build", "Vite 8", "Kompilasi aset CSS dan JavaScript"],
    ["Pengujian", "PHPUnit 12", "Feature dan unit test"],
], [1.15, 1.55, 4.10])
add_heading(document, "2.3 Karakter Antarmuka", 2)
add_bullets(document, [
    "Tema terang modern dengan background #F6F8FC dan primary #1476B8.",
    "Sidebar gradasi biru, dapat dipersempit pada desktop dan menjadi drawer pada mobile.",
    "Status sidebar desktop disimpan pada localStorage dengan key sivmi.sidebar.collapsed.",
    "Header putih menyediakan pencarian global, notifikasi basis data, dan profil pengguna.",
    "Card menggunakan border tipis, shadow lembut, radius 12-18 px, dan transisi halus.",
])
add_heading(document, "2.4 Struktur Navigasi", 2)
add_table(document, ["Kelompok", "Menu", "Keterangan"], [
    ["SIVMI CRM", "Dashboard", "Ringkasan, KPI, tren, aktivitas, tindakan tertunda"],
    ["SIVMI CRM", "Leads", "Daftar, buat, lihat, ubah"],
    ["SIVMI CRM", "Follow-up", "Daftar dan pencatatan tindak lanjut"],
    ["SIVMI CRM", "Target Kinerja", "Target cabang dan target pengguna"],
    ["SIVMI CRM", "Laporan", "Analisis periode dan ekspor"],
    ["MASTER DATA", "Cabang / Sekolah / Pengguna", "Data referensi organisasi"],
    ["MASTER DATA", "Pendaftaran", "Daftar pendaftaran; aksi tambah tersedia pada halaman"],
    ["MASTER DATA", "Pembayaran", "Daftar pembayaran; aksi catat tersedia pada halaman"],
    ["MASTER DATA", "Program Bimbel", "Katalog program; aksi tambah sesuai hak akses"],
    ["MASTER DATA", "Pengaturan / Audit Aktivitas", "Konfigurasi sistem, opsi Program Bimbel, dan jejak perubahan"],
], [1.15, 1.65, 4.00])
add_heading(document, "2.5 Sitemap", 2)
add_paragraph(document, "Sitemap berikut menunjukkan hierarki halaman panel setelah navigasi disederhanakan. Seluruh item bersifat menu langsung tanpa submenu; visibilitasnya tetap mengikuti role dan cakupan cabang pengguna.")
add_figure(document, sitemap_path, 6.75, "Gambar 2. Sitemap panel administrasi SIVMI CRM", "Hierarki /admin yang terbagi menjadi kelompok SIVMI CRM dan MASTER DATA beserta seluruh halaman utamanya.")

add_heading(document, "BAB III: ANALISIS KEBUTUHAN DAN RBAC", 1)
add_heading(document, "3.1 Aktor Sistem", 2)
add_table(document, ["Role", "Cakupan data", "Tanggung jawab utama"], [
    ["Superadmin", "Semua cabang", "Konfigurasi global, pengguna, cabang, program, seluruh data operasional"],
    ["Admin Cabang", "Cabang sendiri", "Mengelola tim dan proses operasional cabang"],
    ["Staff", "Cabang/penugasan sendiri", "Mengelola lead, follow-up, pendaftaran, dan pembayaran yang diizinkan"],
    ["Direksi", "Semua cabang", "Monitoring dan laporan; akses baca untuk data operasional"],
], [1.25, 1.55, 4.00])
add_heading(document, "3.2 Cabang Awal", 2)
add_bullets(document, ["Bandung", "Jakarta Selatan (JakSel)", "Jakarta Pusat (JakPus)"])
add_heading(document, "3.3 Matriks Hak Akses Ringkas", 2)
add_table(document, ["Fitur", "Superadmin", "Admin Cabang", "Staff", "Direksi"], [
    ["Dashboard dan laporan", "Global", "Cabang", "Terbatas", "Global baca"],
    ["Leads dan follow-up", "Kelola", "Kelola cabang", "Kelola penugasan", "Baca"],
    ["Pendaftaran", "Kelola", "Kelola cabang", "Kelola", "Baca"],
    ["Pembayaran", "Kelola", "Kelola cabang", "Buat; ubah saat Menunggu", "Baca"],
    ["Program bimbel", "Kelola", "Baca", "Baca", "Baca"],
    ["Sekolah", "Kelola + impor", "Kelola + impor", "Baca + ekspor", "Baca + ekspor"],
    ["Cabang", "Kelola", "Baca", "Baca", "Baca"],
    ["Pengguna", "Global", "Kelola cabang", "Baca terbatas", "Baca"],
], [1.55, 1.20, 1.45, 1.25, 1.35])
add_note(document, "Prinsip akses.", "Pembatasan tidak hanya dilakukan pada tampilan tombol. Query Eloquent juga dibatasi dengan scope visibleTo atau pemeriksaan cabang/role agar data di luar kewenangan tidak ikut terambil.")
add_heading(document, "3.4 Kebutuhan Nonfungsional", 2)
add_table(document, ["Kategori", "Kebutuhan"], [
    ["Responsif", "Berfungsi pada mobile, tablet, laptop, dan desktop"],
    ["Keamanan", "Autentikasi, CSRF, session, RBAC, branch scoping, validasi input"],
    ["Kinerja", "Eager loading, aggregate query, withCount/withSum, pagination"],
    ["Keandalan", "Migration, seeder, test otomatis, error state, empty state"],
    ["Usability", "Bahasa Indonesia, pencarian, filter, badge status, quick action"],
    ["Auditabilitas", "Activity log dan notifikasi basis data"],
], [1.35, 5.45])

add_heading(document, "BAB IV: ARSITEKTUR BISNIS", 1)
add_heading(document, "4.1 Alur Operasional Utama", 2)
add_figure(document, flow_path, 6.75, "Gambar 3. Alur utama akuisisi sampai pembayaran", "Diagram tahap akuisisi, tindak lanjut, konversi, transaksi, dan monitoring.")
add_numbers(document, [
    "Pengguna membuat lead dan menentukan cabang, sekolah asal, sumber lead, serta penanggung jawab.",
    "Staff menjadwalkan dan mencatat follow-up berikut metode kontak, status kontak, hasil, dan jadwal berikutnya.",
    "Lead yang siap dikonversi didaftarkan ke satu program bimbel aktif.",
    "Pembayaran dicatat berdasarkan pendaftaran dan diverifikasi sesuai kewenangan role.",
    "Dashboard, target kinerja, laporan, notifikasi, dan audit aktivitas memperbarui konteks monitoring.",
])
add_heading(document, "4.2 Proses Bisnis End-to-End", 2)
add_table(document, ["Tahap", "Pelaku utama", "Input", "Proses dan keluaran"], [
    ["Akuisisi", "Staff/Admin Cabang", "Identitas dan minat calon siswa", "Lead tervalidasi, memiliki cabang serta penanggung jawab"],
    ["Tindak lanjut", "Staff", "Lead dan jadwal kontak", "Riwayat follow-up, status kontak, dan jadwal berikutnya"],
    ["Konversi", "Staff/Admin Cabang", "Lead siap daftar dan program aktif", "Satu pendaftaran siswa untuk satu lead"],
    ["Transaksi", "Staff/Admin Cabang", "Pendaftaran dan pembayaran", "Pembayaran tercatat lalu diverifikasi sesuai kewenangan"],
    ["Monitoring", "Admin/Direksi", "Data operasional dan target", "KPI, tren, laporan, notifikasi, dan tindakan tertunda"],
], [1.05, 1.25, 2.05, 2.45])
add_note(document, "Kontrol proses.", "Setiap transaksi penting dicatat pada audit aktivitas. Query, pilihan form, menu, dan tindakan dibatasi oleh RBAC serta branch scoping.")
document.add_page_break()
add_heading(document, "4.3 Flowmap Lintas Aktor", 2)
add_paragraph(document, "Flowmap memetakan perpindahan pekerjaan dan informasi antaraktor. Sistem bertindak sebagai pengendali validasi, penyimpanan, audit, notifikasi, dan agregasi dashboard.")
add_figure(document, flowmap_path, 6.75, "Gambar 4. Flowmap proses CRM SIVMI", "Swimlane calon siswa, staff, admin cabang, sistem, dan direksi dari penyampaian minat sampai monitoring.")
document.add_page_break()
add_heading(document, "4.4 Flowchart Lead hingga Pembayaran", 2)
add_paragraph(document, "Flowchart menekankan dua keputusan bisnis utama: kesiapan lead untuk mendaftar dan validitas pembayaran untuk diverifikasi. Hasil negatif mengembalikan pekerjaan ke follow-up atau koreksi transaksi.")
add_figure(document, flowchart_path, 5.35, "Gambar 5. Flowchart lead hingga pembayaran", "Alur dari penerimaan lead, validasi, follow-up, keputusan pendaftaran, pembayaran, hingga pembaruan KPI dan audit.")
document.add_page_break()
add_heading(document, "4.5 DFD Level 0", 2)
add_paragraph(document, "Pada level konteks, SIVMI CRM diperlakukan sebagai satu proses tunggal yang bertukar data dengan calon siswa/orang tua, pengguna operasional, superadmin, dan direksi.")
add_figure(document, dfd_context_path, 6.75, "Gambar 6. DFD Level 0 - diagram konteks", "Aliran data antara sistem CRM SIVMI dan empat entitas eksternal utama.")
add_heading(document, "4.6 DFD Level 1", 2)
add_paragraph(document, "DFD Level 1 mendekomposisi sistem menjadi proses pengelolaan lead, follow-up, pendaftaran, pembayaran, dan monitoring. Setiap proses membaca atau menulis data store yang relevan.")
add_figure(document, dfd_level1_path, 6.75, "Gambar 7. DFD Level 1 - dekomposisi proses", "Lima proses utama CRM dan lima kelompok data store operasional, transaksi, serta kontrol.")
add_heading(document, "4.7 Siklus Leads", 2)
add_table(document, ["Tahap", "Data utama", "Kontrol"], [
    ["Akuisisi", "Nama, nomor WhatsApp, sumber, sekolah, jenjang", "Validasi dan cabang"],
    ["Distribusi", "User penanggung jawab", "Scope user/cabang"],
    ["Follow-up", "Waktu, metode, status kontak, catatan", "Jadwal lanjutan dan reminder"],
    ["Konversi", "Program, tanggal daftar, status", "Satu pendaftaran per lead"],
    ["Pembayaran", "Nominal, metode, status, referensi", "Verifikasi dan audit"],
], [1.20, 3.65, 1.95])
add_heading(document, "4.8 Notifikasi dan Tindakan Tertunda", 2)
add_bullets(document, [
    "Reminder follow-up yang telah melewati jadwal.",
    "Pembayaran baru berstatus Menunggu yang perlu diperiksa.",
    "Lead baru yang belum diproses.",
    "Notifikasi disimpan di database dan dipolling oleh panel setiap 30 detik.",
    "Command crm:send-reminders dijadwalkan setiap jam tanpa overlapping.",
])
document.add_page_break()
add_heading(document, "4.9 Dashboard dan Filter Global", 2)
add_table(document, ["Komponen", "Isi"], [
    ["Hero", "Ucapan selamat datang dan ringkasan hari ini"],
    ["Filter", "Cabang, tahun, bulan (termasuk Semua bulan), pengguna"],
    ["KPI", "Total leads, pendaftaran, siswa aktif, omzet"],
    ["Target", "Progress omzet dan siswa"],
    ["Analitik", "Tren leads dan pendaftaran"],
    ["Operasional", "Aktivitas terbaru, quick actions, peringkat user, tindakan tertunda"],
], [1.40, 5.40])
add_note(
    document,
    "Konsistensi filter.",
    "Perubahan cabang, tahun, bulan, atau pengguna dikirim sebagai state global Livewire. "
    "Setiap widget membaca state yang sama dan menerapkan scope role serta cabang sebelum menjalankan agregasi.",
)
add_paragraph(document, "Saat Semua bulan dipilih, periode query mencakup satu tahun penuh. Target dijumlahkan selama tahun terpilih dan grafik tren berubah menjadi agregasi bulanan; pilihan dipertahankan pada URL Livewire.")
add_table(document, ["Kondisi", "Perilaku antarmuka"], [
    ["Memuat", "Skeleton card dan panel ditampilkan sampai query widget selesai"],
    ["Tanpa data", "Empty state kontekstual tanpa angka dummy produksi"],
    ["Gagal", "Error state yang dapat dipahami dan opsi untuk memuat ulang"],
    ["Hak akses", "Widget dan pilihan filter dibatasi berdasarkan role serta cabang pengguna"],
], [1.40, 5.40])
add_bullets(document, [
    "Agregasi menggunakan query Eloquent terfilter, eager loading, withCount, atau withSum sesuai kebutuhan.",
    "Filter pengguna hanya menampilkan akun yang berada dalam cakupan cabang aktif.",
    "Nilai omzet berasal dari pembayaran yang memenuhi status bisnis terverifikasi atau lunas.",
])

add_heading(document, "BAB V: ARSITEKTUR DATA", 1)
add_heading(document, "5.1 Peta Entitas", 2)
add_figure(document, data_path, 6.75, "Gambar 8. Peta entitas inti SIVMI CRM", "Diagram kelompok entitas master, CRM, kinerja, dan kontrol.")
add_heading(document, "5.2 ERD Operasional", 2)
add_paragraph(document, "ERD operasional berikut disusun dari foreign key migration, relasi model Eloquent, serta acuan erd v3 - rekomendasi.drawio. Notasi 0..1 menunjukkan relasi opsional tunggal, sedangkan 0..N menunjukkan tidak ada atau banyak record anak.")
add_figure(document, erd_operational_path, 6.75, "Gambar 9. ERD operasional CRM SIVMI", "Entitas cabang, pengguna, sekolah, program, leads, follow-up, pendaftaran, dan pembayaran beserta kardinalitasnya.")
add_heading(document, "5.3 ERD Kinerja, Pengaturan, dan Audit", 2)
add_figure(document, erd_control_path, 6.75, "Gambar 10. ERD kinerja dan kontrol", "Entitas target cabang, target pengguna, pengaturan, serta audit yang terhubung ke cabang dan pengguna.")
add_heading(document, "5.4 Entitas dan Relasi Inti", 2)
add_table(document, ["Entitas", "Kunci/relasi penting", "Fungsi"], [
    ["cabang", "cabang_id; users, leads, pendaftaran, target", "Unit organisasi dan batas akses data"],
    ["users", "cabang_id; role", "Akun, role, dan kepemilikan operasional"],
    ["sekolah", "sekolah_id; NPSN opsional", "Sekolah asal lead"],
    ["leads", "cabang_id, sekolah_id, diserahkan_ke_user_id", "Calon siswa"],
    ["followup_riwayat", "lead_id, user_id", "Riwayat interaksi dan jadwal berikutnya"],
    ["program_bimbel", "program_bimbel_id", "Katalog layanan dan biaya"],
    ["pendaftaran", "lead_id, program_bimbel_id, cabang_id", "Konversi lead menjadi siswa"],
    ["pembayaran", "pendaftaran_id", "Transaksi dan status verifikasi"],
    ["target_kinerja_cabang", "cabang_id, created_by", "Target omzet dan siswa per periode"],
    ["target_kinerja_user", "target cabang, user", "Distribusi target per pengguna"],
    ["activity_logs", "user, cabang, subject polymorphic", "Jejak perubahan data"],
    ["notifications", "notifiable", "Notifikasi basis data"],
], [1.45, 2.65, 2.70])
add_heading(document, "5.5 Relasi dan Kardinalitas", 2)
add_table(document, ["Entitas induk", "Entitas anak", "Kardinalitas", "Aturan implementasi"], [
    ["cabang", "users", "1 : 0..N", "users.cabang_id nullable; null untuk akses global"],
    ["cabang", "leads", "1 : 0..N", "Setiap lead wajib berada pada satu cabang"],
    ["cabang", "pendaftaran", "1 : 0..N", "Setiap pendaftaran wajib berada pada satu cabang"],
    ["cabang", "target_kinerja_cabang", "1 : 0..N", "Unik per cabang, tahun, dan bulan"],
    ["sekolah", "leads", "1 : 0..N", "Sekolah pada lead bersifat opsional; nullOnDelete"],
    ["program_bimbel", "leads", "1 : 0..N", "Minat program bersifat opsional; nullOnDelete"],
    ["users", "leads", "1 : 0..N", "Relasi input wajib; penugasan dapat kosong"],
    ["leads", "followup_riwayat", "1 : 0..N", "Follow-up dihapus berantai ketika lead dihapus"],
    ["users", "followup_riwayat", "1 : 0..N", "Setiap follow-up dicatat oleh satu pengguna"],
    ["leads", "pendaftaran", "1 : 0..1", "Unique lead_id membatasi satu pendaftaran per lead"],
    ["program_bimbel", "pendaftaran", "1 : 0..N", "Satu program dapat dipilih banyak pendaftaran"],
    ["pendaftaran", "pembayaran", "1 : 0..N", "Pembayaran dihapus berantai bersama pendaftaran"],
    ["target_kinerja_cabang", "target_kinerja_user", "1 : 0..N", "Unik per target cabang dan pengguna"],
    ["users", "target_kinerja_user", "1 : 0..N", "Pengguna dapat memiliki target setiap periode"],
    ["users", "crm_settings", "1 : 0..N", "updated_by opsional dan nullOnDelete"],
    ["users/cabang", "activity_logs", "1 : 0..N", "Kedua foreign key opsional; subject polymorphic"],
    ["users", "notifications", "1 : 0..N", "Relasi notifiable polymorphic milik Laravel"],
], [1.45, 1.65, 1.05, 2.65])
add_heading(document, "5.6 Kluster Sekolah", 2)
add_table(document, ["Nilai enum", "Definisi bisnis"], [
    ["Grade A", "Sekolah sering menyumbang siswa terbanyak, walaupun bukan sekolah favorit menurut catatan Kementerian Pendidikan."],
    ["Grade B", "Sekolah sedikit atau jarang menyumbang siswa dan merupakan sekolah favorit menurut catatan Kementerian Pendidikan."],
    ["Grade C", "Sekolah tidak pernah menyumbang siswa dan merupakan sekolah favorit menurut catatan Kementerian Pendidikan."],
    ["Grade D", "Definisi bisnis belum ditetapkan."],
    ["Grade E", "Definisi bisnis belum ditetapkan."],
], [1.25, 5.55])
add_note(document, "NPSN.", "NPSN bersifat opsional. Bila diisi, aplikasi memvalidasi tepat 8 digit. Nilai kosong dinormalisasi menjadi NULL sehingga beberapa sekolah tanpa NPSN dapat disimpan tanpa bentrok pada unique index.")
add_heading(document, "5.7 Import dan Export Sekolah", 2)
add_table(document, ["Kolom CSV", "Ketentuan"], [
    ["npsn", "Opsional; 8 digit jika diisi"],
    ["nama_sekolah", "Wajib; maksimum 200 karakter"],
    ["status_sekolah", "Negeri, Swasta, atau Lainnya"],
    ["alamat", "Opsional"], ["kecamatan", "Opsional"],
    ["kota", "Wajib"], ["provinsi", "Opsional"],
    ["kluster_sekolah", "Grade A, Grade B, Grade C, Grade D, atau Grade E"],
    ["aktif", "1 atau 0"],
], [2.05, 4.75])
add_bullets(document, [
    "Import melakukan upsert berdasarkan NPSN jika NPSN tersedia.",
    "Sekolah tanpa NPSN selalu dibuat sebagai record baru.",
    "Maksimum 5.000 baris per file dan diproses per chunk 100 baris.",
    "Baris gagal dapat diunduh untuk diperbaiki.",
    "Ekspor dan file gagal dilindungi dari formula injection CSV.",
])

add_heading(document, "BAB VI: ARSITEKTUR APLIKASI", 1)
add_heading(document, "6.1 Struktur Folder Utama", 2)
structure = """sivmi.crm/
|-- app/
|   |-- Enums/                 UserRole dan SchoolGrade
|   |-- Filament/              Page, Resource, Importer, Exporter
|   |-- Models/                Model Eloquent dan relasi
|   |-- Observers/             Audit dan notifikasi
|   |-- Providers/             Registrasi panel dan observer
|   `-- Support/               Notifier dan template CSV
|-- database/
|   |-- migrations/            Evolusi schema
|   `-- seeders/               Data demonstrasi
|-- resources/
|   |-- css/filament/admin/    Theme panel
|   `-- views/filament/        Dashboard, laporan, pengaturan
|-- tests/Feature/             Pengujian fitur dan RBAC
|-- docs/                      Referensi, contoh CSV, dokumentasi
`-- erd v3 - rekomendasi.drawio"""
paragraph = document.add_paragraph()
paragraph.paragraph_format.left_indent = Inches(0.25)
paragraph.paragraph_format.space_after = Pt(8)
run = paragraph.add_run(structure)
set_run_font(run, name="Consolas", size=8.5, color=TEXT)
shade = OxmlElement("w:shd")
shade.set(qn("w:fill"), PALE)
paragraph._p.get_or_add_pPr().append(shade)
add_heading(document, "6.2 Resource Filament", 2)
add_table(document, ["Resource/Page", "Slug", "Operasi utama"], [
    ["LeadResource", "/admin/leads", "List, create, view, edit, follow-up relation"],
    ["FollowupRiwayatResource", "/admin/follow-up", "List, create, edit"],
    ["PendaftaranResource", "/admin/pendaftaran", "List, create, edit"],
    ["PembayaranResource", "/admin/pembayaran", "List, create, edit"],
    ["ProgramBimbelResource", "/admin/program-bimbel", "List, create, edit"],
    ["TargetKinerjaCabangResource", "/admin/target-kinerja", "Target cabang dan target user"],
    ["CabangResource", "/admin/cabang", "Master cabang"],
    ["SekolahResource", "/admin/sekolah", "Master, import, export CSV"],
    ["UserResource", "/admin/pengguna", "Master pengguna"],
    ["Dashboard/Laporan/Pengaturan", "/admin/...", "Page khusus"],
], [2.05, 2.05, 2.70])
add_heading(document, "6.3 Observer dan Service", 2)
add_bullets(document, [
    "CrmActivityObserver mencatat create/update/delete untuk model utama dan mengaitkannya dengan user serta cabang.",
    "CrmNotificationObserver memicu notifikasi untuk lead, pembayaran, dan target pengguna.",
    "CrmNotifier memilih penerima berdasarkan role dan cabang serta mencegah notifikasi ganda dengan key deterministik.",
    "SendCrmReminders memeriksa follow-up terlambat dan pembayaran tertunda.",
])
add_heading(document, "6.4 Query Efisien", 2)
add_bullets(document, [
    "Relasi yang diperlukan dimuat dengan with agar menghindari N+1 query.",
    "Jumlah dan total dihitung menggunakan withCount, withSum, atau select aggregate.",
    "Pencarian pilihan dibatasi maksimum 50 hasil.",
    "Tabel menggunakan pagination dan kolom dapat disortir/difilter.",
    "Filter cabang, periode, dan pengguna diterapkan pada query sumber widget.",
])

add_heading(document, "BAB VII: ANTARMUKA DAN PENGALAMAN PENGGUNA", 1)
add_heading(document, "7.1 Design System", 2)
add_table(document, ["Token", "Nilai", "Penggunaan"], [
    ["Primary", "#1476B8", "Aksi utama, link, grafik"],
    ["Primary dark", "#075985", "Sidebar dan gradien"],
    ["Background", "#F6F8FC", "Latar panel"],
    ["Success", "#10B981", "Status berhasil/aktif"],
    ["Warning", "#F59E0B", "Peringatan dan pending"],
    ["Danger", "#F43F5E", "Error dan ditolak"],
    ["Purple", "#A855F7", "Aksen pembayaran/analitik"],
], [1.35, 1.25, 4.20])
add_heading(document, "7.2 Responsivitas", 2)
add_bullets(document, [
    "Mobile: sidebar menjadi drawer; tabel dapat digulir horizontal; card tersusun satu kolom.",
    "Tablet: filter dan card menyesuaikan menjadi dua kolom.",
    "Laptop/desktop: sidebar persisten, dapat dipersempit, dan grid memanfaatkan ruang lebar.",
    "State collapse desktop disimpan di localStorage dan dipulihkan saat Alpine diinisialisasi.",
])
add_heading(document, "7.3 State Antarmuka", 2)
add_table(document, ["State", "Perilaku"], [
    ["Loading", "Skeleton card dan panel ditampilkan saat data belum siap"],
    ["Empty", "Pesan kontekstual dan arahan tindakan bila tidak ada data"],
    ["Error", "Pesan kegagalan tanpa menampilkan detail sensitif"],
    ["Unauthorized", "Aksi disembunyikan dan endpoint tetap menolak akses"],
], [1.35, 5.45])
add_heading(document, "7.4 Navigasi Master Data Langsung", 2)
add_paragraph(document, "Pendaftaran, Pembayaran, Program Bimbel, Pengaturan, dan Audit Aktivitas ditampilkan sebagai item langsung pada kelompok MASTER DATA tanpa parent item atau submenu. Setiap item membuka halaman resource terkait. Aksi tambah, catat, konfigurasi, dan audit tetap dievaluasi berdasarkan kewenangan masing-masing role.")

add_heading(document, "BAB VIII: PANDUAN PENGGUNAAN", 1)
add_heading(document, "8.1 Masuk ke Sistem", 2)
add_numbers(document, [
    "Buka /admin/login pada domain aplikasi.",
    "Masukkan email dan password akun aktif.",
    "Sistem mengarahkan pengguna ke /admin/dashboard.",
    "Menu dan data ditampilkan sesuai role serta cabang pengguna.",
])
add_heading(document, "8.2 Mengelola Leads", 2)
add_numbers(document, [
    "Buka menu Leads dan gunakan pencarian atau filter untuk menemukan calon siswa.",
    "Pilih Buat lead untuk menambahkan identitas, sumber, sekolah, cabang, dan penanggung jawab.",
    "Buka detail lead untuk membaca profil dan riwayat follow-up.",
    "Gunakan Ubah bila data perlu dikoreksi dan hak akses mengizinkan.",
])
add_heading(document, "8.3 Mencatat Follow-up", 2)
add_numbers(document, [
    "Pilih lead yang akan dihubungi.",
    "Isi waktu follow-up, metode, dan status kontak.",
    "Masukkan catatan hasil secara ringkas dan objektif.",
    "Atur follow-up berikutnya bila masih diperlukan.",
    "Simpan dan periksa riwayat pada detail lead.",
])
add_heading(document, "8.4 Pendaftaran dan Pembayaran", 2)
add_numbers(document, [
    "Buka Master Data > Pendaftaran, pilih aksi buat, lalu pilih lead yang belum memiliki pendaftaran.",
    "Pilih program bimbel aktif, tanggal daftar, kelas/angkatan, dan status.",
    "Buka Master Data > Pembayaran, pilih aksi buat, atau gunakan aksi Catat pembayaran dari tabel pendaftaran.",
    "Masukkan nominal, metode, status, dan nomor referensi bila tersedia.",
    "Staff memulai pembayaran pada status Menunggu; verifikasi dilakukan oleh role yang berwenang.",
])
add_heading(document, "8.5 Import Sekolah CSV", 2)
add_numbers(document, [
    "Buka Master Data > Sekolah.",
    "Unduh Contoh CSV untuk memastikan nama dan urutan kolom.",
    "Klik Impor CSV, unggah file, dan periksa pemetaan kolom.",
    "Jalankan impor; sistem memvalidasi maksimal 5.000 baris.",
    "Jika ada baris gagal, unduh file kegagalan, perbaiki, lalu impor ulang.",
])
add_heading(document, "8.6 Export Sekolah CSV", 2)
add_numbers(document, [
    "Atur pencarian atau filter tabel sesuai data yang diperlukan.",
    "Klik Ekspor CSV dan tunggu notifikasi selesai.",
    "Unduh hasil dari notifikasi ekspor.",
    "Buka file menggunakan aplikasi spreadsheet yang mendukung UTF-8.",
])
add_heading(document, "8.7 Laporan dan Dashboard", 2)
add_bullets(document, [
    "Pilih cabang, tahun, bulan, dan pengguna pada filter global.",
    "Pastikan periode filter sesuai sebelum membandingkan KPI dan target.",
    "Direksi dapat memilih semua cabang; role cabang hanya melihat lingkup yang diizinkan.",
    "Gunakan Laporan untuk rincian tren, performa cabang, dan performa program.",
])

add_heading(document, "BAB IX: DEPLOYMENT, MIGRASI, DAN PEMELIHARAAN", 1)
add_heading(document, "9.1 Prasyarat", 2)
add_table(document, ["Komponen", "Kebutuhan minimum"], [
    ["PHP", "8.3 atau lebih baru"],
    ["Database", "MySQL/MariaDB atau SQLite untuk pengujian lokal"],
    ["Composer", "Menyediakan dependency backend"],
    ["Node.js/npm", "Membangun aset Vite/Tailwind"],
    ["Web server", "Nginx/Apache/Laragon dengan document root public"],
    ["Scheduler", "Menjalankan php artisan schedule:run setiap menit"],
], [1.50, 5.30])
add_heading(document, "9.2 Instalasi Awal", 2)
add_numbers(document, [
    "Salin source code ke server aplikasi.",
    "Jalankan composer install --no-dev --optimize-autoloader untuk production.",
    "Salin .env.example menjadi .env dan isi konfigurasi tanpa memasukkan secret ke repository.",
    "Jalankan php artisan key:generate.",
    "Jalankan php artisan migrate --force.",
    "Jalankan npm ci kemudian npm run build.",
    "Atur permission storage dan bootstrap/cache.",
    "Jalankan php artisan storage:link bila aplikasi menggunakan disk public.",
])
add_heading(document, "9.3 Prosedur Release", 2)
add_table(document, ["Urutan", "Perintah/kegiatan", "Verifikasi"], [
    ["1", "Backup database dan file penting", "Backup tersedia dan dapat dibaca"],
    ["2", "Aktifkan maintenance mode bila diperlukan", "Pengguna mendapat halaman maintenance"],
    ["3", "Deploy source dan composer install", "Dependency konsisten"],
    ["4", "php artisan migrate --force", "migrate:status seluruhnya Ran"],
    ["5", "npm run build", "Manifest dan aset terbentuk"],
    ["6", "Clear/cache config dan view", "Tidak ada cache stale"],
    ["7", "Jalankan test/smoke test", "Login, dashboard, CRUD kritis normal"],
    ["8", "Nonaktifkan maintenance mode", "Aplikasi dapat diakses"],
], [0.65, 3.70, 2.45])
add_heading(document, "9.4 Scheduler dan Reminder", 2)
add_paragraph(document, "Scheduler Laravel menjalankan command crm:send-reminders setiap jam dengan withoutOverlapping. Production perlu memiliki cron atau scheduled task yang memanggil php artisan schedule:run setiap menit. Bila queue connection diubah dari sync, sediakan worker dan mekanisme restart saat release.")
add_heading(document, "9.5 Backup dan Pemulihan", 2)
add_bullets(document, [
    "Backup basis data sebelum migrasi dan secara berkala sesuai retensi organisasi.",
    "Backup file upload privat/publik yang tidak dapat dibentuk ulang.",
    "Simpan salinan di lokasi berbeda dari server aplikasi.",
    "Lakukan restore rehearsal berkala dan dokumentasikan waktu pemulihan.",
    "Jangan mengandalkan keberadaan backup tanpa verifikasi integritas dan uji restore.",
])

add_heading(document, "BAB X: PENGUJIAN DAN JAMINAN KUALITAS", 1)
add_heading(document, "10.1 Strategi Pengujian", 2)
add_table(document, ["Suite", "Fokus"], [
    ["DashboardTest", "Shell, filter, state sidebar, widget dashboard"],
    ["LeadManagementTest", "CRUD dan pembatasan lead"],
    ["MasterDataAccessTest", "Cabang, sekolah, pengguna, role"],
    ["RegistrationPaymentTest", "Pendaftaran, pembayaran, navigasi langsung, RBAC"],
    ["ProgramTargetTest", "Program dan target kinerja"],
    ["ReportTest", "Perhitungan dan akses laporan"],
    ["AuditNotificationTest", "Activity log dan notifikasi"],
    ["SchoolCsvTest", "Import/export, NPSN opsional, grade, keamanan CSV"],
    ["SettingsTest", "Pengaturan sistem dan opsi klasifikasi Program Bimbel"],
], [2.15, 4.65])
add_heading(document, "10.2 Snapshot Hasil Uji", 2)
add_note(document, "Status terakhir.", "40 test dengan 267 assertion lulus setelah penambahan filter Semua bulan dan perbaikan tampilan grafik tren Dashboard. Build produksi Vite juga berhasil. Angka ini merupakan snapshot 20 Agustus 2026 dan harus diperbarui setiap kali suite berubah.", color="EAFBF5")
add_heading(document, "10.3 Checklist UAT", 2)
add_table(document, ["Area", "Skenario penerimaan", "Status"], [
    ["Login", "Akun aktif masuk; akun tidak sah ditolak", "Perlu UAT visual"],
    ["RBAC", "Menu, tombol, endpoint, dan query sesuai role/cabang", "Automated test tersedia"],
    ["Dashboard", "Filter memperbarui semua widget", "Automated test; visual lanjutan"],
    ["Mobile", "Sidebar drawer dan tabel dapat digunakan", "Perlu UAT perangkat"],
    ["Sekolah CSV", "Contoh, impor, kegagalan, dan ekspor berfungsi", "Automated test tersedia"],
    ["Notifikasi", "Reminder dan pembayaran pending diterima role tepat", "Automated test tersedia"],
    ["Audit", "Perubahan data penting tercatat", "Automated test tersedia"],
], [1.25, 4.35, 1.20])
add_heading(document, "10.4 Perintah Verifikasi", 2)
for command in ["php artisan test", "php artisan migrate:status", "php artisan route:list", "npm run build", "php artisan view:clear", "php artisan view:cache"]:
    paragraph = document.add_paragraph()
    run = paragraph.add_run(command)
    set_run_font(run, name="Consolas", size=9, color=TEXT)
    shade = OxmlElement("w:shd")
    shade.set(qn("w:fill"), PALE)
    paragraph._p.get_or_add_pPr().append(shade)

add_heading(document, "BAB XI: PENGEMBANGAN BERIKUTNYA", 1)
add_heading(document, "11.1 Status dan Batasan Saat Ini", 2)
add_bullets(document, [
    "Dokumen ini merupakan versi sementara dan mengikuti kondisi codebase pada tanggal snapshot.",
    "UAT visual lintas perangkat perlu diulang ketika browser QA tersedia.",
    "Definisi bisnis Grade D dan Grade E belum ditetapkan.",
    "Dokumentasi API belum menjadi ruang lingkup karena aplikasi saat ini berfokus pada panel web Filament.",
    "README proyek masih berupa README default Laravel dan perlu diganti dengan panduan proyek khusus SIVMI.",
])
add_heading(document, "11.2 Roadmap Rekomendasi", 2)
add_table(document, ["Prioritas", "Pengembangan", "Kriteria selesai"], [
    ["P1", "UAT visual mobile/tablet/laptop/desktop", "Screenshot dan checklist per role/perangkat"],
    ["P1", "Definisi Grade D-E", "Definisi disetujui dan aturan segmentasi terdokumentasi"],
    ["P1", "Backup/restore rehearsal", "Restore berhasil pada lingkungan terpisah"],
    ["P2", "Import/export modul lain bila dibutuhkan", "Validasi, RBAC, dan test setara sekolah"],
    ["P2", "Observability production", "Log, error tracking, health, dan alert"],
    ["P2", "Performance baseline", "Batas response time dan query ditetapkan"],
    ["P3", "Integrasi eksternal", "Kontrak API dan keamanan terdefinisi"],
], [0.75, 3.25, 2.80])
add_heading(document, "11.3 Aturan Pemeliharaan Dokumen", 2)
add_numbers(document, [
    "Perbarui versi dan tanggal snapshot pada setiap release dokumentasi.",
    "Bandingkan isi dengan migration, model, resource, route, test, dan ERD terbaru.",
    "Perbarui daftar isi melalui Microsoft Word setelah heading berubah.",
    "Jalankan pemeriksaan visual agar tabel, gambar, header, footer, dan page break tetap bersih.",
    "Arsipkan versi final yang disetujui dan pertahankan changelog ringkas.",
])

add_heading(document, "LAMPIRAN A: REFERENSI OPERASIONAL", 1)
add_heading(document, "A.1 Rute Utama", 2)
add_table(document, ["Rute", "Kegunaan"], [
    ["/admin/dashboard", "Dashboard CRM"], ["/admin/leads", "Leads"],
    ["/admin/follow-up", "Follow-up"], ["/admin/pendaftaran", "Pendaftaran"],
    ["/admin/pembayaran", "Pembayaran"], ["/admin/program-bimbel", "Program bimbel"],
    ["/admin/target-kinerja", "Target kinerja"], ["/admin/laporan", "Laporan"],
    ["/admin/cabang", "Master cabang"], ["/admin/sekolah", "Master sekolah"],
    ["/admin/pengguna", "Master pengguna"], ["/admin/audit-aktivitas", "Audit aktivitas"],
    ["/up", "Health endpoint Laravel"],
], [2.35, 4.45])
add_heading(document, "A.2 Nilai Referensi", 2)
add_table(document, ["Data", "Nilai"], [
    ["Role", "superadmin, admin_cabang, staff, direksi"],
    ["Cabang awal", "Bandung, JakSel, JakPus"],
    ["Status sekolah", "Negeri, Swasta, Lainnya"],
    ["Grade sekolah", "Grade A, Grade B, Grade C, Grade D, Grade E"],
    ["Status pembayaran", "Menunggu, Terverifikasi, Lunas, Ditolak"],
    ["Metode pembayaran", "Transfer Bank, Tunai, QRIS, Kartu Debit, Lainnya"],
], [1.70, 5.10])

add_heading(document, "GLOSARIUM", 1)
add_table(document, ["Istilah", "Definisi"], [
    ["CRM", "Customer Relationship Management; pengelolaan hubungan dan proses calon siswa."],
    ["Lead", "Calon siswa yang masuk ke proses akuisisi."],
    ["Follow-up", "Aktivitas tindak lanjut kepada lead."],
    ["RBAC", "Role-Based Access Control; hak akses berdasarkan peran."],
    ["Branch scoping", "Pembatasan data berdasarkan cabang pengguna."],
    ["Eloquent", "ORM Laravel untuk query dan relasi basis data."],
    ["Filament Resource", "Definisi panel untuk form, table, action, dan halaman model."],
    ["Livewire", "Mekanisme komponen reaktif berbasis server."],
    ["Seeder", "Kode untuk membentuk data awal/demonstrasi."],
    ["Migration", "Perubahan schema basis data yang berversi."],
    ["UAT", "User Acceptance Testing oleh pengguna atau perwakilan bisnis."],
], [1.60, 5.20])

document.core_properties.title = "Dokumentasi dan Pengembangan Sistem CRM SIVMI"
document.core_properties.subject = "Dokumentasi teknis, fungsional, operasional, dan pengembangan"
document.core_properties.author = "Tim Pengembangan SIVMI"
document.core_properties.comments = "Versi sementara 1.1 - snapshot 20 Agustus 2026"
document.save(OUTPUT)
print(OUTPUT)
