from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.shared import Inches, Pt


def add_heading(doc, text, level=1):
    if level == 1:
        doc.add_paragraph(text, style='Heading 1')
    elif level == 2:
        doc.add_paragraph(text, style='Heading 2')
    else:
        doc.add_paragraph(text, style='Heading 3')


def add_bold_paragraph(doc, text):
    p = doc.add_paragraph()
    run = p.add_run(text)
    run.bold = True


doc = Document()
section = doc.sections[0]
section.top_margin = Inches(0.7)
section.bottom_margin = Inches(0.7)
section.left_margin = Inches(0.75)
section.right_margin = Inches(0.75)

style = doc.styles['Normal']
style.font.name = 'Calibri'
style.font.size = Pt(11)

# Title
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.CENTER
run = p.add_run('SKENARIO VIDEO RINGKASAN EKSEKUTIF\nSIVMI CRM')
run.bold = True
run.font.size = Pt(20)

# Metadata
metadata = [
    ('Judul video', 'Ringkasan Eksekutif SIVMI CRM'),
    ('Durasi target', '2 menit 30 detik - 3 menit'),
    ('Format output', '16:9, 1920x1080, MP4 final'),
    ('Target audiens', 'Direksi, manajemen cabang, tim operasional, mitra teknologi'),
    ('Gaya visual', 'Corporate, modern, data-driven, clean infographic motion'),
    ('Voiceover', 'Bahasa Indonesia formal dengan tempo sedang dan penekanan pada manfaat bisnis'),
]
for label, value in metadata:
    p = doc.add_paragraph()
    run = p.add_run(f'{label}: ')
    run.bold = True
    p.add_run(value)

doc.add_paragraph('Tujuan video: video ini menggambarkan bagaimana SIVMI CRM menjadi sistem operasi CRM bimbingan belajar yang terintegrasi, terukur, dan transparan dari lead sampai laporan akhir.')

doc.add_paragraph('SIVMI CRM adalah platform berbasis Laravel 12, Filament 5, Livewire, dan Tailwind yang menggabungkan manajemen lead, follow-up, pendaftaran, pembayaran, RBAC, WhatsApp, dashboard kinerja, dan laporan eksekutif dalam satu ekosistem.')

# Scene table
add_heading(doc, '1. URUTAN ADEGAN DAN NARASI', 2)
rows = [
    ['1', '0:00-0:15', 'Opening title + logo + motion background', 'Semua proses CRM terintegrasi dalam satu platform yang siap membantu operasional dan keputusan bisnis.', 'SIVMI CRM', 'Cut to black, logo reveal, motion title', 'Logo jelas, background soft gradient, tidak terlalu ramai.'],
    ['2', '0:15-0:35', 'Dashboard KPI dengan chart dan angka utama', 'Sistem menampilkan target, omzet, siswa aktif, dan performance cabang dalam satu layar yang mudah dibaca.', 'Dashboard Kinerja', 'Zoom in card KPI lalu move to chart', 'Gunakan warna biru-teal, angka besar, label pendek.'],
    ['3', '0:35-0:58', 'Lead-to-registration flow diagram', 'Lead masuk, dikelola, dan ditindaklanjuti secara konsisten sehingga tidak ada data yang hilang atau tercecer.', 'Lead → Follow-up → Pendaftaran', 'Animasi alur step by step', 'Diagram alur linear, highlight status utama.'],
    ['4', '0:58-1:20', 'WhatsApp WAHA integration', 'Komunikasi bisnis berjalan melalui WAHA dan terhubung ke CRM untuk pesan masuk, balasan, media, serta status pengiriman.', 'WhatsApp + WAHA', 'Chat bubbles animate in & out', 'Visual chat sederhana, tidak menampilkan detail teknis.'],
    ['5', '1:20-1:45', 'RBAC dan scope cabang infographic', 'Setiap role memiliki kewenangan yang jelas untuk menjaga keamanan data, akuntabilitas, dan pembagian tugas.', 'Role & Scope', 'Diagram role cards + branch permissions', 'Warna sesuai role: staff, keuangan, admin, direksi.'],
    ['6', '1:45-2:05', 'Pendaftaran & pembayaran verification', 'Pencatatan awal dan verifikasi transaksi dipisahkan untuk menjaga integritas data dan audit yang kuat.', 'Pendaftaran & Pembayaran', 'Progress bar dan status timeline', 'Highlight status verifikasi.'],
    ['7', '2:05-2:25', 'Laporan dan keputusan manajerial', 'Manajer bisa melihat kinerja organisasi dan cabang secara real-time untuk mengambil keputusan yang lebih cepat dan tepat.', 'Laporan & Insight', 'Dashboard report plus bar chart motion', 'Prioritaskan angka kunci dan insight.'],
    ['8', '2:25-2:45', 'Closing cinematic', 'SIVMI CRM membantu organisasi lebih terstruktur, lebih cepat, dan lebih terukur dalam menjalankan proses bisnis pendidikan.', 'Siap Operasi & Skalabel', 'Slow fade-out with final logo', 'Closing frame singkat, kuat, dan berkesan.'],
]

table = doc.add_table(rows=1, cols=7)
table.style = 'Table Grid'
headers = ['No', 'Durasi', 'Visual', 'Narasi', 'Teks layar', 'Transisi', 'Catatan produksi']
for idx, header in enumerate(headers):
    table.rows[0].cells[idx].text = header
    for paragraph in table.rows[0].cells[idx].paragraphs:
        for run in paragraph.runs:
            run.bold = True
            run.font.size = Pt(10)
for row in rows:
    cells = table.add_row().cells
    for idx, value in enumerate(row):
        cells[idx].text = value

# Each scene detail
add_heading(doc, '2. NARASI PENGISI SUARA PER ADEGAN', 2)
scenes_narration = [
    ('Adegan 1 – Pembuka', 'SIVMI CRM hadir untuk menyederhanakan pengelolaan pelanggan, proses operasional, dan eksekusi bisnis di lembaga pendidikan. Dalam satu platform, semua aktivitas penting dapat diawasi tanpa terpisah-pisah di banyak sistem.'),
    ('Adegan 2 – KPI dan dashboard', 'Di dashboard, eksekutif langsung melihat target omzet, jumlah siswa, pencapaian cabang, dan performa operasional. Informasi ini disajikan dalam format yang mudah dibaca agar keputusan dapat diambil secara cepat.'),
    ('Adegan 3 – Lead dan follow-up', 'Lead masuk dan dikelola dengan jejak aktivitas yang jelas. Tim dapat memantau status, membagi tugas, dan memastikan setiap prospek mendapatkan tindak lanjut yang tepat dalam waktu yang tepat.'),
    ('Adegan 4 – WhatsApp', 'Komunikasi dengan calon siswa dan siswa diterjemahkan ke dalam kanal yang efektif melalui WAHA. Pesan masuk, balasan, media, dan status pengiriman tersinkron dengan CRM sehingga interaksi tetap terstruktur.'),
    ('Adegan 5 – RBAC', 'Akses dibatasi sesuai peran. Staff fokus pada operasional, keuangan memverifikasi transaksi, admin cabang menjaga scope wilayah, dan direksi memantau hasil secara lintas cabang.'),
    ('Adegan 6 – Pembayaran', 'Proses pendaftaran dan pembayaran dibagi dalam kewenangan yang jelas untuk menjaga keakuratan data dan meningkatkan akuntabilitas. Setiap transaksi dapat dilacak hingga status akhirmya.'),
    ('Adegan 7 – Laporan', 'Laporan yang dihasilkan memberikan insight yang siap dipakai untuk strategi. Dengan data yang terkonsolidasi, tim manajemen dapat melihat tren, mengevaluasi target, dan menyusun keputusan dengan lebih baik.'),
    ('Adegan 8 – Penutup', 'Dengan SIVMI CRM, organisasi dapat bergerak lebih terstruktur, lebih cepat, dan lebih terukur. Transformasi operasional ini menciptakan fondasi yang kuat untuk pertumbuhan bisnis dan pelayanan yang lebih konsisten.'),
]
for title, text in scenes_narration:
    p = doc.add_paragraph()
    p.add_run(title).bold = True
    p = doc.add_paragraph(text)

# Infographic list
add_heading(doc, '3. INFOGRAFIS YANG PERLU DIBUAT', 2)
infografis = [
    'Peta alur satu layar: Lead → Follow-up → Pendaftaran → Pembayaran → Laporan',
    'Kartu KPI utama: target omzet, target siswa, persentase pencapaian, cabang aktif',
    'Diagram role-based access control dengan warna per role',
    'Timeline WAHA: pesan masuk, balasan, media, status delivery, lead match',
    'Panel scope cabang untuk membatasi data tiap region dan pengguna',
    'Ringkasan laporan per bulan dengan state indicator color-coded',
]
for item in infografis:
    doc.add_paragraph(item, style='List Bullet')

# On-screen text and transitions
add_heading(doc, '4. TEKS LAYAR DAN ARAHAN TRANSISI', 2)
text_blocks = [
    ('Teks layar', 'SIVMI CRM • One platform for lead, operations, and growth'),
    ('Transisi A', 'Cut + logo reveal, lalu zoom in ke dashboard KPI'),
    ('Transisi B', 'Slide left to diagram alur lead dan status follow-up'),
    ('Transisi C', 'Fade in to WhatsApp chat bubbles, then dissolve to RBAC infographic'),
    ('Transisi D', 'Cross-fade dari pembayaran ke laporan dengan motion chart'),
    ('Transisi E', 'Slow zoom out to final hero frame + brand tagline'),
]
for label, value in text_blocks:
    p = doc.add_paragraph()
    run = p.add_run(f'{label}: ')
    run.bold = True
    p.add_run(value)

# Production notes
add_heading(doc, '5. CATATAN PRODUKSI', 2)
production_notes = [
    'Format video: 16:9, 1920x1080, 24-30 fps, MP4 final.',
    'Tone visual: clean corporate, modern, premium, fokus pada informasi dan alur bisnis.',
    'Palette: biru, teal, abu-abu terang, dengan aksen kuning atau hijau untuk indikator sukses.',
    'Voiceover: bahasa Indonesia dengan tempo sedang, lembut tetapi tegas, tanpa terlalu banyak jeda panjang.',
    'Subjudul: singkat dan jelas, maksimal 5-8 kata di setiap frame.',
    'Music bed: ambient corporate, volume rendah, tetap menonjolkan narasi.',
    'Durasi ideal: 2 menit 30 detik agar seluruh pesan tersampaikan dengan ritme yang kuat.',
    'Final review: pastikan badan teks, diagram, ikon, dan label terbaca pada layar kecil maupun layar besar.',
]
for note in production_notes:
    doc.add_paragraph(note)

# Closing note
p = doc.add_paragraph()
p.alignment = WD_ALIGN_PARAGRAPH.RIGHT
p.add_run('Dibuat untuk kebutuhan review visual dan produksi video ringkasan eksekutif SIVMI CRM')

output = 'docs/SCENARIO-VIDEO-RINGKASAN-EKSEKUTIF.docx'
doc.save(output)
print(f'Created: {output}')
