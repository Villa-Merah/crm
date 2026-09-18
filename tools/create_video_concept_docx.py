from __future__ import annotations

from pathlib import Path
from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT, WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "docs" / "RENCANA-DAN-KONSEP-VIDEO-PENGENALAN-SIVMI-CRM.docx"

NAVY = "0B2545"
BLUE = "1476B8"
BLUE_DARK = "075985"
LIGHT = "EAF4FB"
PALE = "F6F8FC"
MUTED = "64748B"
WHITE = "FFFFFF"
GREEN = "047857"
AMBER = "9A6700"
RED = "B42318"
BORDER = "D7E1EC"
WIDTH_DXA = 9360


def font(run, size=10.5, bold=False, color=NAVY, name="Aptos", italic=False):
    run.font.name = name
    run._element.get_or_add_rPr().rFonts.set(qn("w:ascii"), name)
    run._element.get_or_add_rPr().rFonts.set(qn("w:hAnsi"), name)
    run.font.size = Pt(size)
    run.bold = bold
    run.italic = italic
    run.font.color.rgb = RGBColor.from_string(color)


def shade(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    node = tc_pr.find(qn("w:shd"))
    if node is None:
        node = OxmlElement("w:shd")
        tc_pr.append(node)
    node.set(qn("w:fill"), fill)


def borders(cell, color=BORDER, size="6"):
    tc_pr = cell._tc.get_or_add_tcPr()
    nodes = tc_pr.first_child_found_in("w:tcBorders")
    if nodes is None:
        nodes = OxmlElement("w:tcBorders")
        tc_pr.append(nodes)
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        node = nodes.find(qn(f"w:{edge}"))
        if node is None:
            node = OxmlElement(f"w:{edge}")
            nodes.append(node)
        node.set(qn("w:val"), "single")
        node.set(qn("w:sz"), size)
        node.set(qn("w:color"), color)


def margins(cell, top=90, start=120, bottom=90, end=120):
    tc_pr = cell._tc.get_or_add_tcPr()
    node = tc_pr.first_child_found_in("w:tcMar")
    if node is None:
        node = OxmlElement("w:tcMar")
        tc_pr.append(node)
    for edge, value in (("top", top), ("start", start), ("bottom", bottom), ("end", end)):
        item = node.find(qn(f"w:{edge}"))
        if item is None:
            item = OxmlElement(f"w:{edge}")
            node.append(item)
        item.set(qn("w:w"), str(value))
        item.set(qn("w:type"), "dxa")


def geometry(table, widths):
    table.autofit = False
    table.alignment = WD_TABLE_ALIGNMENT.LEFT
    values = [int(width * 1440) for width in widths]
    props = table._tbl.tblPr
    width = props.find(qn("w:tblW"))
    if width is None:
        width = OxmlElement("w:tblW")
        props.append(width)
    width.set(qn("w:w"), str(sum(values)))
    width.set(qn("w:type"), "dxa")
    grid = table._tbl.tblGrid
    for child in list(grid):
        grid.remove(child)
    for value in values:
        col = OxmlElement("w:gridCol")
        col.set(qn("w:w"), str(value))
        grid.append(col)
    for row in table.rows:
        for index, cell in enumerate(row.cells):
            tc_w = cell._tc.get_or_add_tcPr().find(qn("w:tcW"))
            if tc_w is None:
                tc_w = OxmlElement("w:tcW")
                cell._tc.get_or_add_tcPr().append(tc_w)
            tc_w.set(qn("w:w"), str(values[index]))
            tc_w.set(qn("w:type"), "dxa")
            margins(cell)


def repeat_header(row):
    node = OxmlElement("w:tblHeader")
    node.set(qn("w:val"), "true")
    row._tr.get_or_add_trPr().append(node)


def keep_next(paragraph):
    paragraph._p.get_or_add_pPr().append(OxmlElement("w:keepNext"))


def heading(doc, text, level=1):
    paragraph = doc.add_paragraph(style=f"Heading {level}")
    keep_next(paragraph)
    font(paragraph.add_run(text), size={1: 16, 2: 13, 3: 11.5}[level], bold=True, color=BLUE if level < 3 else BLUE_DARK)
    paragraph.paragraph_format.space_before = Pt(12 if level == 1 else 8)
    paragraph.paragraph_format.space_after = Pt(5)
    return paragraph


def para(doc, text="", *, bold_lead=None, italic=False, align=None, after=6):
    paragraph = doc.add_paragraph()
    paragraph.paragraph_format.space_after = Pt(after)
    paragraph.paragraph_format.line_spacing = 1.15
    if align is not None:
        paragraph.alignment = align
    if bold_lead and text.startswith(bold_lead):
        font(paragraph.add_run(bold_lead), bold=True)
        font(paragraph.add_run(text[len(bold_lead):]), italic=italic)
    else:
        font(paragraph.add_run(text), italic=italic)
    return paragraph


def bullets(doc, items):
    for item in items:
        paragraph = doc.add_paragraph(style="List Bullet")
        paragraph.paragraph_format.space_after = Pt(3)
        paragraph.paragraph_format.line_spacing = 1.1
        font(paragraph.add_run(item))


def numbers(doc, items):
    for item in items:
        paragraph = doc.add_paragraph(style="List Number")
        paragraph.paragraph_format.space_after = Pt(3)
        paragraph.paragraph_format.line_spacing = 1.1
        font(paragraph.add_run(item))


def table(doc, headers, rows, widths):
    tbl = doc.add_table(rows=1, cols=len(headers))
    tbl.style = "Table Grid"
    repeat_header(tbl.rows[0])
    for index, header in enumerate(headers):
        cell = tbl.rows[0].cells[index]
        shade(cell, BLUE)
        borders(cell, BLUE_DARK)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        p = cell.paragraphs[0]
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_after = Pt(0)
        font(p.add_run(header), size=8.8, bold=True, color=WHITE)
    for row_index, values in enumerate(rows):
        row = tbl.add_row()
        for index, value in enumerate(values):
            cell = row.cells[index]
            shade(cell, WHITE if row_index % 2 == 0 else PALE)
            borders(cell)
            cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.TOP
            p = cell.paragraphs[0]
            p.paragraph_format.space_after = Pt(0)
            p.paragraph_format.line_spacing = 1.05
            font(p.add_run(str(value)), size=8.4)
    geometry(tbl, widths)
    doc.add_paragraph().paragraph_format.space_after = Pt(1)
    return tbl


def callout(doc, title, text, fill=LIGHT, accent=BLUE_DARK):
    tbl = doc.add_table(rows=1, cols=1)
    cell = tbl.cell(0, 0)
    shade(cell, fill)
    borders(cell, accent, "10")
    margins(cell, 120, 150, 120, 150)
    p = cell.paragraphs[0]
    p.paragraph_format.space_after = Pt(0)
    p.paragraph_format.line_spacing = 1.1
    font(p.add_run(title + "\n"), size=9.5, bold=True, color=accent)
    font(p.add_run(text), size=9.2)
    geometry(tbl, [6.5])
    doc.add_paragraph().paragraph_format.space_after = Pt(1)


def footer(section):
    p = section.footer.paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    font(p.add_run("SIVMI CRM | Rencana dan Konsep Video Pengenalan Sistem | "), size=8, color=MUTED)
    run = p.add_run()
    begin = OxmlElement("w:fldChar")
    begin.set(qn("w:fldCharType"), "begin")
    instr = OxmlElement("w:instrText")
    instr.set(qn("xml:space"), "preserve")
    instr.text = "PAGE"
    end = OxmlElement("w:fldChar")
    end.set(qn("w:fldCharType"), "end")
    run._r.extend([begin, instr, end])


def build():
    doc = Document()
    section = doc.sections[0]
    section.page_width = Inches(8.5)
    section.page_height = Inches(11)
    section.top_margin = Inches(0.85)
    section.bottom_margin = Inches(0.8)
    section.left_margin = Inches(1)
    section.right_margin = Inches(1)
    footer(section)
    doc.core_properties.title = "Rencana dan Konsep Video Pengenalan SIVMI CRM"
    doc.core_properties.subject = "Dokumen perencanaan produksi video"
    doc.core_properties.author = "SIVMI CRM"

    normal = doc.styles["Normal"]
    normal.font.name = "Aptos"
    normal.font.size = Pt(10.5)
    normal.font.color.rgb = RGBColor.from_string(NAVY)

    cover = doc.add_paragraph()
    cover.alignment = WD_ALIGN_PARAGRAPH.CENTER
    cover.paragraph_format.space_before = Pt(90)
    font(cover.add_run("SIVMI CRM"), size=30, bold=True, color=BLUE)
    para(doc, "RENCANA DAN KONSEP VIDEO PENGENALAN SISTEM", align=WD_ALIGN_PARAGRAPH.CENTER, after=8)
    para(doc, "Dokumen acuan pra-produksi", align=WD_ALIGN_PARAGRAPH.CENTER, italic=True, after=30)
    callout(doc, "KONSEP UTAMA", "Satu sistem untuk mengelola leads, sekolah, siswa, program bimbel, follow-up, pendaftaran, pembayaran, dan performa tim secara terhubung.", fill=LIGHT)
    para(doc, "Versi 1.0 | 18 September 2026", align=WD_ALIGN_PARAGRAPH.CENTER, after=3)
    para(doc, "Status: Disetujui untuk penyusunan naskah dan produksi", align=WD_ALIGN_PARAGRAPH.CENTER, after=18)
    doc.add_page_break()

    heading(doc, "Daftar Isi", 1)
    para(doc, "Dokumen ini menggunakan heading Word agar daftar isi dapat diperbarui otomatis melalui fitur Update Field di Microsoft Word.", italic=True)
    toc = doc.add_paragraph()
    add_field = OxmlElement("w:fldSimple")
    add_field.set(qn("w:instr"), 'TOC \\o "1-3" \\h \\z \\u')
    toc._p.append(add_field)
    doc.add_page_break()

    heading(doc, "1. Ringkasan Eksekutif", 1)
    para(doc, "Video ini memperkenalkan SIVMI CRM sebagai sistem informasi operasional yang menyatukan proses pengelolaan calon siswa, sekolah, program bimbel, follow-up, pendaftaran, pembayaran, laporan, dan kinerja tim. Video dirancang sebagai gabungan antara video pengenalan dan panduan penggunaan awal.")
    table(doc, ["Komponen", "Keputusan konsep"], [
        ["Judul", "SIVMI CRM: Satu Sistem untuk Mengelola Leads, Sekolah, Siswa, dan Performa Tim"],
        ["Format", "Screen recording aplikasi, voice-over, teks penanda, dan transisi sederhana"],
        ["Durasi", "8-12 menit untuk video utama; dapat dipecah menjadi video modul"],
        ["Bahasa", "Bahasa Indonesia, istilah menu mengikuti aplikasi"],
        ["Rasio", "16:9 untuk video utama; potongan 9:16 untuk materi promosi internal"],
        ["Audiens", "Super Admin, Direksi, Admin Cabang, dan Staff"],
    ], [1.35, 5.15])

    heading(doc, "2. Maksud dan Tujuan", 1)
    para(doc, "Video dibuat sebagai materi pengenalan resmi agar pengguna memahami alasan keberadaan sistem, fungsi tiap modul, alur kerja antarfitur, serta manfaat penggunaan data yang terpusat.")
    heading(doc, "2.1 Tujuan komunikasi", 2)
    bullets(doc, [
        "Menjelaskan masalah operasional yang diselesaikan oleh SIVMI CRM.",
        "Memperkenalkan struktur menu dan fitur utama secara runtut.",
        "Menunjukkan hubungan antara lead, follow-up, pendaftaran, dan pembayaran.",
        "Mendorong penggunaan data yang konsisten, terdokumentasi, dan dapat diaudit.",
        "Membantu pengguna baru mencapai pemahaman dasar tanpa pendampingan tatap muka penuh.",
    ])
    heading(doc, "2.2 Indikator keberhasilan video", 2)
    bullets(doc, [
        "Penonton dapat menyebutkan tujuan utama SIVMI CRM.",
        "Penonton memahami perbedaan fungsi dashboard, leads, follow-up, pendaftaran, pembayaran, dan laporan.",
        "Penonton dapat mengikuti alur lead baru sampai tercatat sebagai pendaftaran.",
        "Penonton mengetahui bahwa akses menu dan data mengikuti role serta cakupan cabang.",
    ])

    heading(doc, "3. Sasaran Penonton dan Pesan Utama", 1)
    table(doc, ["Audiens", "Kebutuhan utama", "Pesan yang harus diterima"], [
        ["Super Admin", "Konfigurasi, user, master data, audit", "Sistem dapat dikendalikan secara terpusat dan aman."],
        ["Direksi", "Dashboard, laporan, KPI, omzet", "Data operasional dapat dipantau untuk keputusan bisnis."],
        ["Admin Cabang", "Data cabang, leads, pendaftaran, pembayaran", "Proses cabang menjadi lebih tertib dan mudah ditelusuri."],
        ["Staff", "Input lead, follow-up, inbox, status", "Pekerjaan harian memiliki alur, PIC, dan riwayat yang jelas."],
    ], [1.25, 2.45, 2.8])
    callout(doc, "PESAN UTAMA", "SIVMI CRM bukan hanya tempat menyimpan data. Sistem ini menghubungkan aktivitas tim dengan hasil bisnis sehingga setiap lead dapat ditindaklanjuti, diukur, dan ditingkatkan.", fill="ECFDF5", accent=GREEN)

    heading(doc, "4. Struktur dan Alur Video", 1)
    table(doc, ["Segmen", "Materi", "Durasi", "Tujuan"], [
        ["1", "Pembukaan dan masalah operasional", "0:00-0:45", "Membangun konteks dan perhatian."],
        ["2", "Maksud, tujuan, dan pengguna", "0:45-1:45", "Menjelaskan alasan sistem dibuat serta role pengguna."],
        ["3", "Dashboard dan navigasi", "1:45-2:45", "Memberi orientasi terhadap tampilan utama."],
        ["4", "Leads dan follow-up", "2:45-4:30", "Menunjukkan proses kerja staff sehari-hari."],
        ["5", "Sekolah dan Program Bimbel", "4:30-5:45", "Menjelaskan master data pendukung."],
        ["6", "Pendaftaran dan pembayaran", "5:45-7:00", "Menjelaskan alur konversi dan transaksi."],
        ["7", "Laporan, target, dan pengaturan", "7:00-8:30", "Menunjukkan kontrol manajemen dan Super Admin."],
        ["8", "Manfaat, alur lengkap, dan penutup", "8:30-10:00", "Mengikat seluruh manfaat dan ajakan penggunaan."],
    ], [0.55, 2.25, 1.15, 2.55])

    heading(doc, "5. Storyboard dan Naskah Video", 1)
    para(doc, "Storyboard berikut menjadi acuan screen recording. Setiap adegan menampilkan satu gagasan utama, menghindari perpindahan menu yang terlalu cepat, dan menggunakan data demo yang aman.")
    scenes = [
        ["1", "Opening", "Logo SIVMI CRM, background sistem, judul video", "SIVMI CRM membantu tim mengelola proses calon siswa dan operasional bimbel dalam satu sistem yang terhubung.", "Animasi logo, musik pembuka ringan"],
        ["2", "Masalah operasional", "Visual data tersebar, spreadsheet, chat, dan catatan follow-up", "Sebelum terpusat, data lead, sekolah, follow-up, pendaftaran, dan pembayaran mudah tersebar. Akibatnya, informasi sulit ditelusuri dan kinerja sulit dibandingkan.", "Gunakan ilustrasi sederhana atau montage screen"],
        ["3", "Maksud dan tujuan", "Dashboard masuk dan ringkasan modul", "SIVMI CRM dibuat untuk memusatkan data, mempercepat follow-up, menghubungkan proses sampai pembayaran, serta membantu pimpinan memantau performa.", "Highlight empat tujuan dengan teks singkat"],
        ["4", "Role pengguna", "Menu berbeda untuk Super Admin, Direksi, Admin Cabang, Staff", "Setiap pengguna melihat menu dan data sesuai role. Super Admin mengelola sistem, Direksi memantau, Admin Cabang mengelola operasional cabang, dan Staff menjalankan proses harian.", "Tampilkan tabel role atau perpindahan akun demo"],
        ["5", "Dashboard", "KPI, filter periode/cabang, grafik, peta", "Dashboard merangkum leads, pendaftaran, pembayaran, omzet, target, serta persebaran wilayah. Gunakan filter untuk membaca kondisi sesuai periode dan cakupan yang dipilih.", "Zoom pada filter dan perubahan angka"],
        ["6", "Membuat lead", "Form Create Lead", "Saat lead baru masuk, isi nama, nomor WhatsApp, sekolah, program yang diminati, sumber lead, cabang, dan PIC. Simpan data setelah memastikan informasi wajib lengkap.", "Cursor highlight pada field wajib"],
        ["7", "Mengelola sekolah", "Master Sekolah, pencarian API, import/export", "Sekolah dapat dipilih dari data API, dibuat manual bila diperlukan, diimpor melalui CSV, dan diekspor untuk kebutuhan administrasi.", "Tampilkan validasi dan fallback manual"],
        ["8", "Follow-up", "Detail lead dan riwayat follow-up", "Catat metode komunikasi, status kontak, hasil percakapan, dan jadwal tindak lanjut berikutnya. Riwayat ini membantu tim mengetahui langkah terakhir dan langkah selanjutnya.", "Animasi timeline follow-up"],
        ["9", "Program Bimbel", "Master Program Bimbel", "Program memiliki klasifikasi, jenjang, metode belajar, biaya, jumlah pertemuan, fasilitas, dan status aktif sehingga pilihan pada lead dan pendaftaran tetap konsisten.", "Tampilkan detail program demo"],
        ["10", "Pendaftaran", "Create Pendaftaran dari lead", "Ketika calon siswa siap, buat pendaftaran dengan memilih program, cabang, tanggal, kelas atau angkatan, dan status pendaftaran.", "Hubungkan lead ke pendaftaran"],
        ["11", "Pembayaran", "Create/verify pembayaran", "Pembayaran dicatat dengan nominal, metode, status, dan referensi. Role berwenang dapat melakukan verifikasi sehingga laporan omzet menggunakan data yang dapat dipertanggungjawabkan.", "Tekankan status dan audit"],
        ["12", "Laporan dan target", "Laporan, target cabang, target user", "Pimpinan dapat membandingkan capaian dengan target omzet dan target siswa, melihat performa cabang atau PIC, lalu mengekspor laporan sesuai hak akses.", "Tampilkan filter dan export"],
        ["13", "Pengaturan Super Admin", "Pengaturan identitas, program, kebijakan export, background login", "Super Admin mengelola identitas aplikasi, opsi klasifikasi, kebijakan laporan, dan tampilan background login. Background yang diunggah sebaiknya minimal 1600 kali 900 piksel dengan rasio 16 banding 9.", "Tampilkan preview upload dan panduan ukuran"],
        ["14", "Alur lengkap", "Montage lead -> follow-up -> daftar -> bayar -> laporan", "Alur kerja SIVMI CRM dimulai dari lead, dilanjutkan follow-up, pendaftaran, pembayaran, dan akhirnya terbaca pada dashboard serta laporan.", "Gunakan garis alur dan transisi cepat"],
        ["15", "Penutup", "Dashboard dan logo", "Dengan data yang terpusat dan proses yang terdokumentasi, SIVMI CRM membantu tim bekerja lebih cepat, terukur, dan konsisten. Gunakan sistem sesuai role dan ikuti standar data yang berlaku.", "Logo, kontak bantuan, dan call to action"],
    ]
    table(doc, ["No", "Adegan", "Visual layar", "Naskah voice-over", "Catatan produksi"], scenes, [0.35, 0.85, 1.55, 2.95, 0.8])

    heading(doc, "6. Panduan Demonstrasi Fitur", 1)
    heading(doc, "6.1 Persiapan akun dan data demo", 2)
    numbers(doc, [
        "Gunakan database demo, bukan database produksi.",
        "Siapkan akun minimal Super Admin, Direksi, Admin Cabang, dan Staff.",
        "Gunakan data anonim: nama contoh, nomor contoh, dan sekolah contoh.",
        "Pastikan data demo mencakup lead, follow-up, pendaftaran, pembayaran, program, dan sekolah dari beberapa wilayah.",
        "Atur browser pada resolusi 1920 x 1080 atau 1366 x 768 dan sembunyikan informasi sensitif.",
    ])
    heading(doc, "6.2 Urutan screen recording", 2)
    bullets(doc, [
        "Rekam tiap modul sebagai klip terpisah agar mudah diedit.",
        "Mulai setiap klip dengan tampilan menu dan nama fitur.",
        "Gunakan gerakan mouse lambat, klik sekali, dan beri jeda setelah perubahan halaman.",
        "Jangan merekam password, token, API key, URL internal, atau data produksi.",
        "Ulangi adegan bila muncul error, loading panjang, atau data tidak sesuai naskah.",
    ])

    heading(doc, "7. Gaya Visual, Audio, dan Editing", 1)
    table(doc, ["Aspek", "Arahan"], [
        ["Warna", "Gunakan identitas biru SIVMI, putih, slate, hijau untuk status berhasil, amber untuk perhatian, dan merah untuk error."],
        ["Tipografi", "Gunakan font yang sama dengan aplikasi; teks overlay singkat dan mudah dibaca."],
        ["Animasi", "Gunakan fade, slide pendek, cursor highlight, zoom area fitur, dan transisi antarsegmen yang konsisten."],
        ["Voice-over", "Nada tenang, jelas, profesional, kecepatan sekitar 130-150 kata per menit."],
        ["Musik", "Musik instrumental ringan dengan volume rendah; turunkan saat voice-over berbicara."],
        ["Caption", "Sediakan subtitle Bahasa Indonesia untuk aksesibilitas dan pemutaran tanpa suara."],
        ["Screen recording", "Gunakan 1080p, 30 fps, cursor terlihat, dan area browser bersih."],
    ], [1.25, 5.25])

    heading(doc, "8. Rencana Produksi", 1)
    table(doc, ["Tahap", "Kegiatan", "Output", "Penanggung jawab"], [
        ["Pra-produksi", "Finalisasi tujuan, naskah, storyboard, akun, data demo", "Naskah v1 dan daftar shot", "Product owner + narator"],
        ["Perekaman", "Screen recording tiap modul dan retake bila perlu", "Klip mentah terstruktur", "Screen recorder"],
        ["Voice-over", "Rekaman naskah sesuai timecode", "Audio voice-over bersih", "Narator"],
        ["Editing", "Potong klip, sinkronisasi audio, overlay, caption, musik", "Video draft", "Video editor"],
        ["Review", "Cek fitur, istilah, hak akses, dan kualitas visual", "Daftar revisi", "QA + product owner"],
        ["Finalisasi", "Perbaikan, export master, subtitle, thumbnail", "Video final dan arsip", "Video editor"],
    ], [1.1, 2.8, 1.5, 1.1])
    heading(doc, "8.1 Estimasi waktu", 2)
    para(doc, "Estimasi awal adalah 3-5 hari kerja: satu hari untuk pra-produksi, satu hari untuk recording, satu hari untuk voice-over dan rough cut, serta satu sampai dua hari untuk review dan finalisasi. Durasi dapat berubah sesuai jumlah revisi dan kebutuhan versi pendek.")

    heading(doc, "9. Daftar Aset yang Dibutuhkan", 1)
    bullets(doc, [
        "Logo SIVMI CRM dalam format PNG transparan dan versi horizontal.",
        "Akun demo untuk setiap role pengguna.",
        "Database demo dengan data yang aman dan konsisten.",
        "Naskah voice-over dan subtitle.",
        "Musik pembuka/penutup berlisensi atau bebas digunakan.",
        "Template lower-third, cursor highlight, dan callout fitur.",
        "Thumbnail video dan gambar cover internal.",
        "Kontak bantuan atau alamat administrator yang akan ditampilkan pada penutup.",
    ])

    heading(doc, "10. Risiko dan Pengendalian", 1)
    table(doc, ["Risiko", "Dampak", "Pengendalian"], [
        ["Data produksi ikut terekam", "Kebocoran data", "Gunakan database demo, anonimisasi, dan review frame per frame."],
        ["Fitur berubah setelah recording", "Video cepat usang", "Simpan project editing dan rekam ulang klip modular."],
        ["Voice-over terlalu cepat", "Penonton sulit mengikuti", "Gunakan naskah bertimecode dan uji ke pengguna non-teknis."],
        ["API atau layanan eksternal tidak tersedia", "Demo terhenti", "Siapkan alur fallback sekolah lokal dan screenshot cadangan."],
        ["Akses role tidak sesuai", "Pesan video menyesatkan", "Verifikasi setiap adegan menggunakan akun role yang tepat."],
    ], [1.65, 1.6, 3.25])

    heading(doc, "11. Checklist Persetujuan Produksi", 1)
    numbers(doc, [
        "Tujuan dan audiens video disetujui.",
        "Durasi target dan format output disetujui.",
        "Naskah voice-over telah direview oleh pemilik produk.",
        "Storyboard dan urutan demo sesuai alur bisnis.",
        "Akun serta database demo siap digunakan.",
        "Tidak ada data sensitif pada screen recording.",
        "Istilah menu dan role sesuai aplikasi terbaru.",
        "Voice-over, subtitle, musik, dan visual telah diperiksa.",
        "Video diuji pada desktop dan mobile.",
        "File master, subtitle, thumbnail, dan project editing diarsipkan.",
    ])

    heading(doc, "12. Persetujuan Konsep", 1)
    para(doc, "Dokumen ini menjadi acuan pembuatan naskah final dan produksi video. Perubahan besar pada fitur, role, atau alur bisnis setelah dokumen disetujui perlu dicatat sebagai revisi versi berikutnya.")
    table(doc, ["Peran", "Nama", "Tanggal", "Paraf/Status"], [
        ["Pemilik produk", "", "", ""],
        ["Perwakilan operasional", "", "", ""],
        ["Tim teknis", "", "", ""],
        ["Tim komunikasi/video", "", "", ""],
    ], [1.5, 2.0, 1.3, 1.7])

    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    doc.save(OUTPUT)
    print(OUTPUT)


def main():
    build()


if __name__ == "__main__":
    main()
