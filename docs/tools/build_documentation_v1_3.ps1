param(
    [string] $SourceDocx = "docs/Dokumentasi dan Pengembangan Sistem CRM SIVMI v1.2 - Sementara.docx",
    [string] $OutputDocx = "docs/Dokumentasi dan Pengembangan Sistem CRM SIVMI v1.3 - Sementara.docx"
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$source = (Resolve-Path (Join-Path $root $SourceDocx)).Path
$output = [System.IO.Path]::GetFullPath((Join-Path $root $OutputDocx))
$diagramDir = Join-Path $root 'docs\_diagram_v1_2'
$coverPath = Join-Path $root 'docs\_diagram_v1_3\cover-v1_3.png'

Copy-Item -LiteralPath $source -Destination $output -Force

$word = $null
$doc = $null
$sel = $null

function Set-BodyFont([object] $range, [double] $size = 10.5, [bool] $bold = $false, [int] $color = 0x513C33) {
    $range.Font.Name = 'Aptos'
    $range.Font.Size = $size
    $range.Font.Bold = [int] $bold
    $range.Font.Color = $color
}

function Add-Paragraph([string] $text, [string] $style = 'Normal', [int] $alignment = 3) {
    $script:sel.Style = $style
    $script:sel.ParagraphFormat.Alignment = $alignment
    $script:sel.TypeText($text)
    $script:sel.TypeParagraph()
}

function Add-Heading([string] $text, [int] $level) {
    Add-Paragraph $text "Heading $level" 0
}

function Add-Bullets([string[]] $items) {
    foreach ($item in $items) {
        $script:sel.Style = 'List Bullet'
        $script:sel.ParagraphFormat.Alignment = 0
        $script:sel.TypeText($item)
        $script:sel.TypeParagraph()
    }
    $script:sel.Style = 'Normal'
}

function Add-Numbers([string[]] $items) {
    foreach ($item in $items) {
        $script:sel.Style = 'List Number'
        $script:sel.ParagraphFormat.Alignment = 0
        $script:sel.TypeText($item)
        $script:sel.TypeParagraph()
    }
    $script:sel.Style = 'Normal'
}

function Add-Table([string[]] $headers, [object[]] $rows, [string] $caption = '') {
    if ($caption) { Add-Paragraph $caption 'Caption' 0 }
    $range = $script:doc.Range($script:sel.Start, $script:sel.Start)
    $table = $script:doc.Tables.Add($range, $rows.Count + 1, $headers.Count)
    $table.Style = 'Table Grid'
    $table.AllowAutoFit = $true
    $table.AutoFitBehavior(2)
    $table.Rows.Item(1).HeadingFormat = $true
    for ($c = 1; $c -le $headers.Count; $c++) {
        $cellRange = $table.Cell(1, $c).Range
        $cellRange.Text = $headers[$c - 1]
        $cellRange.Font.Bold = 1
        $cellRange.Font.Color = 0xFFFFFF
        $table.Cell(1, $c).Shading.BackgroundPatternColor = 0xB87614
        $cellRange.ParagraphFormat.Alignment = 1
    }
    for ($r = 0; $r -lt $rows.Count; $r++) {
        for ($c = 0; $c -lt $headers.Count; $c++) {
            $value = if ($c -lt $rows[$r].Count) { [string] $rows[$r][$c] } else { '' }
            $cellRange = $table.Cell($r + 2, $c + 1).Range
            $cellRange.Text = $value
            Set-BodyFont $cellRange 8.5 $false
            $cellRange.ParagraphFormat.Alignment = if ($c -eq 0 -and $headers.Count -gt 2) { 0 } else { 0 }
            $table.Cell($r + 2, $c + 1).VerticalAlignment = 1
            if (($r % 2) -eq 1) { $table.Cell($r + 2, $c + 1).Shading.BackgroundPatternColor = 0xFCF8F6 }
        }
    }
    $table.Range.ParagraphFormat.SpaceAfter = 3
    $script:sel.SetRange($table.Range.End, $table.Range.End)
    $script:sel.TypeParagraph()
}

function Add-Figure([string] $fileName, [string] $caption, [double] $width = 470) {
    $path = Join-Path $script:diagramDir $fileName
    if (-not (Test-Path -LiteralPath $path)) { return }
    $script:sel.ParagraphFormat.Alignment = 1
    $shape = $script:sel.InlineShapes.AddPicture($path, $false, $true)
    $shape.LockAspectRatio = -1
    if ($shape.Width -gt $width) { $shape.Width = $width }
    $script:sel.TypeParagraph()
    $script:sel.Style = 'Caption'
    $script:sel.TypeText($caption)
    $script:sel.TypeParagraph()
    $script:sel.Style = 'Normal'
    $script:sel.ParagraphFormat.Alignment = 0
}

function Add-Section([string] $number, [string] $title, [string] $body, [string[]] $bullets = @()) {
    Add-Heading "$number $title" 2
    Add-Paragraph $body
    if ($bullets.Count -gt 0) { Add-Bullets $bullets }
}

try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $doc = $word.Documents.Open($output, $false, $false)
    $doc.Content.Delete()
    $sel = $word.Selection
    $sel.HomeKey(6) | Out-Null

    # Sampul menggunakan aset yang telah disetujui pada versi sebelumnya.
    $sel.ParagraphFormat.Alignment = 1
    $cover = $sel.InlineShapes.AddPicture($coverPath, $false, $true)
    $cover.LockAspectRatio = 0
    $cover.Width = 495
    $cover.Height = 716
    $sel.TypeParagraph()
    $sel.InsertBreak(7)

    Add-Heading 'LEMBAR KENDALI DOKUMEN' 1
    Add-Table @('Atribut','Nilai') @(
        @('Nama dokumen','Dokumentasi dan Pengembangan Sistem CRM SIVMI'),
        @('Versi','Sementara 1.3'),
        @('Tanggal snapshot','24 Agustus 2026'),
        @('Status','As-built dengan catatan gap dan target arsitektur'),
        @('Acuan','Codebase, migration, test, ERD, konfigurasi Docker, dan dokumentasi v1.2'),
        @('Teknologi aktual','Laravel 12, Filament 5, Livewire 4, Tailwind CSS 4, MySQL, Redis'),
        @('Lokasi proyek','C:\laragon\www\sivmi.crm')
    ) 'Tabel 1. Identitas dan kendali dokumen'
    Add-Paragraph 'Catatan validasi: profil badan hukum, visi-misi resmi, struktur organisasi formal, definisi bisnis Grade D-E, target RPO/RTO, dan target SLA harus dikonfirmasi oleh manajemen sebelum dokumen dinyatakan final.'

    $sel.InsertBreak(7)
    Add-Heading 'DAFTAR ISI' 1
    $tocRange = $doc.Range($sel.Start, $sel.Start)
    $toc = $doc.TablesOfContents.Add($tocRange, $true, 1, 3)
    $sel.SetRange($toc.Range.End, $toc.Range.End)
    $sel.TypeParagraph()

    Add-Heading 'BAB I: PENDAHULUAN' 1
    Add-Section '1.1' 'Latar Belakang' 'SIVMI CRM dibangun untuk memusatkan proses akuisisi calon siswa bimbingan belajar mulai dari lead masuk, follow-up, pendaftaran, pembayaran, hingga pemantauan kinerja. Sebelum sistem terpusat, risiko utama adalah data tersebar, tindak lanjut tidak konsisten, kesulitan menelusuri kepemilikan lead, dan laporan lintas cabang yang lambat. Aplikasi web memberikan satu sumber data operasional dengan pembatasan role dan cabang.'
    Add-Section '1.2' 'Identifikasi Masalah' 'Permasalahan yang ditangani meliputi pencatatan lead yang tidak seragam, status prospek yang ambigu, follow-up yang terlewat, hubungan pendaftaran dan pembayaran yang sulit ditelusuri, serta KPI yang tidak memiliki sumber data tunggal.' @('Data cabang harus terisolasi sesuai kewenangan.','Riwayat aktivitas penting harus dapat diaudit.','Import/export harus aman, tervalidasi, dan mengikuti filter pengguna.','Dashboard harus responsif dan selalu bersumber dari query aktual, bukan angka produksi yang ditanam di kode.')
    Add-Section '1.3' 'Rumusan Masalah' 'Bagaimana membangun CRM bimbel yang mengelola siklus lead sampai transaksi, menyediakan informasi kinerja yang dapat dipertanggungjawabkan, mendukung empat role organisasi, dan dapat dijalankan konsisten pada Laragon maupun Docker tanpa mengorbankan keamanan data cabang?'
    Add-Section '1.4' 'Manfaat Sistem' 'Sistem membantu staff memprioritaskan tindak lanjut, admin cabang mengawasi kinerja tim, direksi membaca tren lintas cabang, dan superadmin menjaga master data serta konfigurasi. Jejak audit, notifikasi, dan filter global mengurangi pekerjaan manual sekaligus meningkatkan keterlacakan keputusan.'
    Add-Section '1.5' 'Tujuan Pembuatan' 'Tujuan pembuatan adalah menyediakan aplikasi CRM modern, responsif, terukur, dan mudah dipelihara untuk operasi SIVMI. Hasil yang diharapkan adalah data konsisten, proses konversi lead lebih terkendali, pelaporan lebih cepat, dan deployment dapat direproduksi.'
    Add-Section '1.6' 'Ruang Lingkup Sistem' 'Ruang lingkup as-built mencakup dashboard, leads, follow-up, target kinerja, laporan, cabang, sekolah, pengguna, pendaftaran, pembayaran, program bimbel, pengaturan, notifikasi, audit aktivitas, import/export CSV, RBAC, serta konfigurasi deployment Docker. LMS, tugas mandiri, data siswa terpisah, dan API publik dicatat sebagai target pengembangan.'

    Add-Heading 'BAB II: LANDASAN TEORI' 1
    $theory = @(
        @('2.1','Sistem Informasi','Sistem informasi menggabungkan manusia, proses, data, dan teknologi untuk menghasilkan informasi yang mendukung operasi serta keputusan. Dalam SIVMI, transaksi operasional diolah menjadi status lead, konversi, omzet, dan indikator kinerja.'),
        @('2.2','Aplikasi Berbasis Web','Aplikasi berbasis web diakses melalui browser dan memusatkan logika pada server. Pendekatan ini memudahkan pembaruan, pengendalian akses, dan pemakaian lintas perangkat tanpa memasang aplikasi desktop.'),
        @('2.3','PHP (Hypertext Preprocessor)','PHP adalah bahasa sisi server yang digunakan Laravel untuk menjalankan routing, validasi, logika bisnis, akses data, queue, scheduler, dan respons HTTP.'),
        @('2.4','MySQL','MySQL adalah basis data relasional yang menyimpan entitas CRM dalam tabel berelasi. Foreign key, unique index, transaksi, dan indeks pencarian membantu menjaga integritas serta kinerja.'),
        @('2.5','XAMPP','XAMPP merupakan paket Apache, MySQL/MariaDB, PHP, dan alat pendukung untuk pengembangan lokal. Proyek ini tidak bergantung pada XAMPP; baseline lokal aktual menggunakan Laragon, sedangkan Docker menjadi alternatif lingkungan yang lebih konsisten.'),
        @('2.6','HTML, CSS, JavaScript, dan Bootstrap','HTML membentuk struktur halaman, CSS mengatur presentasi, dan JavaScript menangani interaksi. Bootstrap dibahas sebagai kerangka CSS umum, namun implementasi aktual memakai Tailwind CSS dan komponen Filament/Livewire.'),
        @('2.7','Enterprise Architecture (EA)','Enterprise Architecture menyelaraskan tujuan bisnis, data, aplikasi, dan teknologi. Dokumentasi ini memakai sudut pandang tersebut agar keputusan modul, relasi data, deployment, dan kontrol operasional saling konsisten.'),
        @('2.8','Use Case Diagram','Use case memodelkan interaksi aktor dengan fungsi sistem. Diagram membantu memeriksa batas kewenangan Superadmin, Admin Cabang, Staff, dan Direksi.'),
        @('2.9','Data Flow Diagram (DFD)','DFD menunjukkan pergerakan data antara aktor, proses, dan penyimpanan. Level 0 menampilkan konteks; Level 1 memecah aliran lead, follow-up, pendaftaran, pembayaran, dan monitoring.'),
        @('2.10','Entity Relationship Diagram (ERD)','ERD menggambarkan entitas, atribut penting, foreign key, dan kardinalitas. ERD menjadi jembatan antara kebutuhan bisnis dan migration basis data.'),
        @('2.11','Flowchart','Flowchart memvisualkan urutan proses dan titik keputusan. Pada CRM, flowchart dipakai untuk menelusuri validasi lead, kelayakan pendaftaran, pembayaran, dan pembaruan KPI.'),
        @('2.12','Activity Diagram','Activity diagram menekankan aktivitas, percabangan, dan tanggung jawab antaraktor. Diagram ini membantu memastikan hand-off staff, admin cabang, sistem, dan manajemen tidak ambigu.'),
        @('2.13','Laravel','Laravel adalah framework PHP yang menyediakan routing, middleware, Eloquent ORM, validation, migration, queue, scheduler, notification, logging, dan fasilitas testing. SIVMI menggunakan Laravel 12.'),
        @('2.14','User Acceptance Testing (UAT)','UAT memverifikasi bahwa fungsi sistem memenuhi kebutuhan operasional pengguna. UAT harus mencakup role, cabang, perangkat, kondisi data kosong, loading, error, serta alur transaksi utama.'),
        @('2.15','Webhook dan API','API menyediakan kontrak komunikasi antarsistem, sedangkan webhook mengirim kejadian secara proaktif. Keduanya merupakan target arsitektur integrasi; API publik dan webhook produksi belum dinyatakan tersedia pada snapshot ini.'),
        @('2.16','Artificial Intelligence (AI) dalam Pengembangan Perangkat Lunak','AI dapat membantu analisis kebutuhan, pembuatan kode, review, pengujian, dan dokumentasi. Hasil AI tetap harus diverifikasi manusia, tidak boleh menerima secret atau data pribadi tanpa kontrol, dan tidak menggantikan persetujuan bisnis maupun keamanan.')
    )
    foreach ($item in $theory) { Add-Section $item[0] $item[1] $item[2] }

    Add-Heading 'BAB III: ANALISIS KEBUTUHAN' 1
    Add-Section '3.1' 'Kebutuhan Fungsional' 'Sistem harus menyediakan autentikasi, navigasi berbasis role, pengelolaan lead dan sumbernya, follow-up terjadwal, pendaftaran, pembayaran, master program, target kinerja, laporan, notifikasi, audit, pengaturan, dan pertukaran CSV. Widget dashboard mengikuti filter cabang, tahun, bulan termasuk Semua Bulan, dan pengguna.'
    Add-Table @('ID','Kebutuhan','Status') @(
        @('F-01','CRUD leads dengan status Cold, Warm, Hot, Daftar, Tidak Berminat','Tersedia'),
        @('F-02','Sumber lead: Iklan, Instagram, Kunjungan, Saudara, Facebook, TikTok','Tersedia'),
        @('F-03','Follow-up, reminder, notifikasi, dan audit aktivitas','Tersedia'),
        @('F-04','Import/export CSV sekolah dan leads dengan validasi/RBAC','Tersedia'),
        @('F-05','KPI berdasarkan program, kategori, tipe, metode, kelas, jenjang; Top 5 sekolah/kota','Tersedia'),
        @('F-06','API publik, webhook eksternal, LMS, dan modul tugas mandiri','Roadmap')
    ) 'Tabel 2. Kebutuhan fungsional utama'
    Add-Section '3.2' 'Kebutuhan Nonfungsional' 'Sistem harus responsif, aman, dapat diaudit, efisien, mudah dipelihara, dan dapat dipulihkan. Query memakai eager loading/agregasi serta branch scope; state sidebar tersimpan di localStorage; UI menyediakan skeleton, empty state, dan error state; produksi memakai APP_DEBUG=false, HTTPS, queue, scheduler, backup, health check, dan monitoring.'

    Add-Heading 'BAB IV: ARSITEKTUR BISNIS' 1
    Add-Section '4.1' 'Profil dan Visi Misi Perusahaan' 'SIVMI pada dokumen ini diposisikan sebagai penyelenggara layanan bimbingan belajar yang mengelola akuisisi, layanan, dan transaksi siswa melalui beberapa cabang. Nama badan hukum, sejarah, visi, misi, dan nilai resmi belum ditemukan pada codebase sehingga harus diisi setelah konfirmasi manajemen; dokumen tidak mengarang pernyataan korporat.'
    Add-Section '4.2' 'Struktur Organisasi dan Stakeholder' 'Stakeholder sistem terdiri dari Direksi sebagai pemilik informasi strategis, Superadmin sebagai pengendali global, Admin Cabang sebagai pengelola operasi lokal, Staff sebagai pelaksana hubungan calon siswa, serta calon siswa/orang tua sebagai pihak eksternal. Cabang awal adalah Bandung, JakSel, dan JakPus.'
    Add-Section '4.3' 'Proses dan Alur Kerja Bisnis' 'Alur inti dimulai dari lead masuk, validasi identitas dan sumber, penetapan PIC, rangkaian follow-up, keputusan minat, pendaftaran pada program, pencatatan/verifikasi pembayaran, lalu pembaruan KPI dan laporan. Lead yang tidak berminat tetap dipertahankan sesuai kebijakan retensi agar histori analisis tidak hilang.'
    Add-Figure 'diagram-04.png' 'Gambar 1. Flowmap proses bisnis CRM SIVMI'
    Add-Section '4.4' 'Pemodelan Aktor dan Key Performance Indicator (KPI)' 'KPI operasional mencakup jumlah lead, follow-up hari ini, pendaftaran, siswa aktif, omzet, pencapaian target siswa/omzet, kinerja pengguna, distribusi lead berdasarkan program dan klasifikasi, serta lima sekolah dan wilayah/kota penyumbang lead terbanyak. Semua agregasi mengikuti filter global dan cakupan akses.'
    Add-Section '4.5' 'Perancangan Enterprise Architecture (Overview)' 'Arsitektur enterprise menghubungkan proses bisnis akuisisi dan transaksi dengan data CRM, aplikasi Laravel/Filament, dan teknologi MySQL-Redis-Docker. Prinsip utamanya adalah sumber data tunggal, isolasi cabang, auditabilitas, deployment berulang, dan perubahan schema yang berversi.'
    Add-Figure 'diagram-01.png' 'Gambar 2. Overview arsitektur sistem SIVMI CRM'
    Add-Section '4.6' 'Matriks Sasaran, Target Terukur (Objective), dan Indikator Kinerja (KPI)' 'Sasaran diukur dengan indikator yang memiliki definisi, periode, sumber tabel, dan pemilik. Target omzet serta siswa disimpan per cabang/periode dan dapat diturunkan ke pengguna.'
    Add-Table @('Sasaran','Indikator','Sumber','Pemilik') @(
        @('Meningkatkan akuisisi','Lead masuk dan distribusi sumber','leads','Admin Cabang'),
        @('Meningkatkan konversi','Pendaftaran / total lead','leads, pendaftaran','Admin Cabang'),
        @('Meningkatkan pendapatan','Pembayaran valid dan pencapaian omzet','pembayaran, target','Direksi'),
        @('Menjaga disiplin tindak lanjut','Follow-up jatuh tempo dan terlambat','followup_riwayat','PIC'),
        @('Mengoptimalkan kanal/sekolah','Top kategori, program, sekolah, kota','leads + master','Manajemen')
    ) 'Tabel 3. Sasaran dan KPI'
    Add-Section '4.7' 'Aturan Bisnis' 'Satu lead dapat memiliki banyak riwayat follow-up dan paling banyak satu pendaftaran. Program aktif dipilih saat pendaftaran. Nama program dibentuk dari tiga komponen: Kategori + Kelas Program + Metode Belajar, misalnya SR GOLD ONLINE. NPSN sekolah opsional; kluster dibatasi Grade A-E; pembayaran dan perubahan sensitif mengikuti role serta audit.'

    Add-Heading 'BAB V: ARSITEKTUR DATA' 1
    Add-Section '5.1' 'RBAC (Role-Based Access Control)' 'RBAC menetapkan kemampuan berdasarkan role Superadmin, Admin Cabang, Staff, dan Direksi. Kontrol diterapkan pada visibilitas navigasi, policy/action, query Eloquent, pilihan relasi, import/export, serta widget. Direksi berorientasi baca; Admin Cabang dan Staff dibatasi cabang/penugasan; Superadmin memiliki cakupan global.'
    Add-Section '5.2' 'Matriks RBAC' 'Matriks berikut adalah baseline kewenangan dan harus diverifikasi kembali pada UAT setiap role.'
    Add-Table @('Fitur','Superadmin','Admin Cabang','Staff','Direksi') @(
        @('Dashboard/Laporan','Global','Cabang','Cabang/penugasan','Global baca'),
        @('Leads/Follow-up','Kelola','Kelola cabang','Kelola penugasan','Baca'),
        @('Pendaftaran/Pembayaran','Kelola','Kelola cabang','Buat/ubah terbatas','Baca'),
        @('Cabang/Pengguna','Kelola','Pengguna cabang','Baca terbatas','Baca'),
        @('Program/Pengaturan','Kelola','Baca/terbatas','Baca','Baca'),
        @('Audit aktivitas','Global','Cabang','Terbatas','Baca')
    ) 'Tabel 4. Matriks RBAC ringkas'
    Add-Section '5.3' 'Alur Proses Sistem' 'Permintaan pengguna diautentikasi, diperiksa role/cabang, divalidasi, lalu diproses oleh Page/Resource/Service. Eloquent menyimpan transaksi, observer menulis audit dan memicu notifikasi, sedangkan dashboard membaca agregasi dengan filter yang sama.'
    Add-Section '5.4' 'Flowchart' 'Flowchart menunjukkan keputusan apakah lead layak dilanjutkan ke pendaftaran serta apakah pembayaran valid. Jalur gagal kembali ke koreksi atau follow-up tanpa menghapus histori.'
    Add-Figure 'diagram-06.png' 'Gambar 3. Flowchart lead sampai pembayaran'
    Add-Section '5.5' 'Use Case' 'Use case utama meliputi autentikasi, mengelola lead, mencatat follow-up, mengonversi pendaftaran, mencatat pembayaran, mengelola master data, memantau KPI, mengekspor data, mengatur sistem, dan membaca audit. Setiap use case memiliki prasyarat akses serta cakupan cabang.'
    Add-Table @('Aktor','Use case utama') @(
        @('Superadmin','Konfigurasi global, semua master data, pengguna, audit, dan seluruh laporan'),
        @('Admin Cabang','Mengelola lead, follow-up, pendaftaran, pembayaran, tim, dan target cabang'),
        @('Staff','Mengelola lead penugasan, follow-up, pendaftaran, dan pembayaran terbatas'),
        @('Direksi','Membaca dashboard, KPI, target, laporan, dan audit sesuai kewenangan')
    ) 'Tabel 5. Use case per aktor'
    Add-Section '5.6' 'Activity Diagram' 'Activity diagram memperlihatkan koordinasi calon siswa, staff, admin cabang, sistem, dan direksi. Aktivitas paralel seperti notifikasi dan audit tidak mengubah keberhasilan transaksi utama, tetapi harus tercatat atau diulang melalui queue bila gagal.'
    Add-Figure 'diagram-05.png' 'Gambar 4. Activity/flowmap pembagian aktivitas operasional'
    Add-Section '5.7' 'Data Flow Diagram (DFD)' 'DFD Level 0 memosisikan CRM sebagai satu proses terhadap aktor eksternal. DFD Level 1 memecahnya menjadi pengelolaan lead, follow-up, pendaftaran, pembayaran, dan monitoring beserta data store terkait.'
    Add-Figure 'diagram-07.png' 'Gambar 5. DFD Level 0'
    Add-Figure 'diagram-08.png' 'Gambar 6. DFD Level 1'
    Add-Section '5.8' 'Entity Relationship Diagram (ERD)' 'ERD operasional menempatkan leads sebagai pusat hubungan sekolah, program, cabang, PIC, follow-up, pendaftaran, dan pembayaran. Entitas kontrol mencakup target, pengaturan, notifikasi, dan activity log.'
    Add-Figure 'erd-operasional-v1_2.png' 'Gambar 7. ERD operasional CRM SIVMI'
    Add-Section '5.9' 'Relasi dan Kardinalitas' 'Kardinalitas diselaraskan dengan foreign key dan unique index pada migration. Relasi opsional diberi 0..1 atau 0..N, sedangkan kepemilikan wajib diberi 1.'
    Add-Table @('Induk','Anak','Kardinalitas','Aturan') @(
        @('cabang','users','1 : 0..N','User dapat tidak memiliki cabang untuk role global'),
        @('cabang','leads','1 : 0..N','Setiap lead operasional memiliki cabang'),
        @('sekolah','leads','1 : 0..N','Sekolah pada lead bersifat opsional'),
        @('program_bimbel','leads','1 : 0..N','Minat program bersifat opsional'),
        @('leads','followup_riwayat','1 : 0..N','Histori tindak lanjut'),
        @('leads','pendaftaran','1 : 0..1','lead_id unik pada pendaftaran'),
        @('pendaftaran','pembayaran','1 : 0..N','Angsuran/transaksi pembayaran'),
        @('target_kinerja_cabang','target_kinerja_user','1 : 0..N','Target diturunkan per pengguna')
    ) 'Tabel 6. Relasi dan kardinalitas utama'
    Add-Section '5.10' 'Siklus Data Lead' 'Lead dibuat dengan status awal Cold dan sumber wajib, kemudian dapat bergerak ke Warm, Hot, Daftar, atau Tidak Berminat. Setiap follow-up menambah histori tanpa menimpa catatan sebelumnya. Perubahan menjadi Daftar harus konsisten dengan pendaftaran; data yang hilang minat tetap dipertahankan sesuai retensi.'
    Add-Section '5.11' 'Tabel Database' 'Tabel inti meliputi users, cabang, sekolah, program_bimbel, leads, followup_riwayat, pendaftaran, pembayaran, target_kinerja_cabang, target_kinerja_user, crm_settings, notifications, activity_logs, jobs, cache, dan sessions. Migration menjadi sumber kebenaran schema; ERD harus diperbarui jika migration berubah.'
    Add-Section '5.12' 'Keamanan Hak Akses Data' 'Keamanan data menggunakan autentikasi, CSRF, validasi, policy/action, branch scoping, query scope, audit, sanitasi CSV, rate/size limit, dan konfigurasi session aman. Ekspor tidak boleh melewati cakupan filter/role, dan formula spreadsheet dinetralkan untuk mencegah CSV injection.'

    Add-Heading 'BAB VI: ARSITEKTUR APLIKASI' 1
    $appSections = @(
        @('6.1','Pengertian','Arsitektur aplikasi adalah susunan komponen perangkat lunak dan aturan interaksinya untuk memenuhi proses bisnis serta kualitas sistem.'),
        @('6.2','Tujuan','Tujuannya adalah menjaga modularitas, konsistensi RBAC, kemudahan pengujian, performa query, dan evolusi fitur tanpa duplikasi logika.'),
        @('6.3','Ruang Lingkup','Cakupan meliputi panel admin, autentikasi, Resource/Page Filament, Livewire, model Eloquent, observer, import/export, notifikasi, queue, scheduler, dan penyimpanan file.'),
        @('6.4','Konteks Aplikasi','Browser mengakses /admin melalui Nginx/Apache. Laravel menangani request, MySQL menyimpan data, Redis mendukung cache/queue, dan worker/scheduler menjalankan proses latar.'),
        @('6.5','Prinsip Arsitektur Aplikasi','Prinsipnya: separation of concerns, least privilege, server-side validation, single source of truth, query efisien, auditability, idempotensi job, dan konfigurasi melalui environment.'),
        @('6.6','Gaya Arsitektur','Aplikasi memakai monolitik modular berbasis MVC dengan panel server-driven. Pendekatan ini sesuai untuk domain terpadu dan tim kecil-menengah, sambil tetap membuka jalur ekstraksi API/service bila skala meningkat.'),
        @('6.7','Layer dan Tanggung Jawab','Lapisan presentasi Filament/Blade/Tailwind; interaksi Livewire; aplikasi Resource/Page/Action/Service; domain model/enums/observer; data Eloquent/migration; infrastruktur web, database, Redis, queue, dan storage.'),
        @('6.8','Struktur Komponen','Komponen utama berada pada app/Filament, app/Models, app/Enums, app/Observers, app/Support, database/migrations, resources/views, resources/css, tests/Feature, docker, dan docs.'),
        @('6.9','Peta Modul Aplikasi','Kelompok SIVMI CRM berisi Dashboard, Leads, Follow-up, Target Kinerja, dan Laporan. MASTER DATA berisi Cabang, Sekolah, Pengguna, Pendaftaran, Pembayaran, Program Bimbel, Pengaturan, dan Audit Aktivitas.'),
        @('6.10','Alur Request Web','Request melewati web server, public/index.php, middleware session/CSRF/auth, panel Filament, komponen Livewire, validasi, action/service, Eloquent, observer, lalu menghasilkan HTML atau update komponen.'),
        @('6.11','Alur Follow Up dan Transaksi','Follow-up dipautkan ke lead dan user. Pendaftaran hanya untuk lead yang memenuhi syarat; pembayaran dipautkan ke pendaftaran dan diverifikasi sesuai role. Setiap perubahan memperbarui data dashboard melalui query, bukan cache angka statis.'),
        @('6.12','Arsitektur Data pada Aplikasi','Model Eloquent mendefinisikan relasi dan cast; enum menjaga nilai; migration menjaga evolusi schema; scope visibleTo/branch membatasi data; eager loading dan agregasi mengurangi N+1.'),
        @('6.13','Arsitektur API','Endpoint health /up tersedia. API publik dan webhook eksternal belum menjadi kontrak produksi. Target desain memerlukan versioning, autentikasi token, rate limit, idempotency key, signature webhook, retry, dan audit.'),
        @('6.14','Scheduler, Queue, dan Reminder','Scheduler memicu reminder follow-up/pembayaran; queue worker memproses pekerjaan asinkron. Job harus aman dijalankan ulang, memiliki retry/backoff, timeout, dan mekanisme failed_jobs.'),
        @('6.15','Penyimpanan File','File publik memakai storage link jika diperlukan; upload harus dibatasi tipe/ukuran dan nama disanitasi. Backup storage dipisahkan dari image aplikasi serta diuji restore.'),
        @('6.16','Presentasi dan UI','UI berbahasa Indonesia memakai sidebar gradasi biru, header putih, kartu radius 12-18 px, background #F6F8FC, skeleton loading, empty/error state, dan layout responsif. Collapse sidebar desktop disimpan pada localStorage.'),
        @('6.17','Error Handling dan Logging','Exception ditangani tanpa mengekspos stack trace di produksi. Log memuat korelasi waktu/user/cabang/event tanpa password, token, atau data sensitif berlebihan; error operasional diterjemahkan menjadi pesan yang dapat ditindaklanjuti.'),
        @('6.18','Pengujian Arsitektur Aplikasi','Pengujian mencakup routing, autentikasi, RBAC, scope cabang, CRUD, CSV, dashboard, notifikasi, audit, queue/scheduler, build aset, migration, dan smoke test container.'),
        @('6.19','Temuan dan Gap Arsitektur Aplikasi','Gap utama: UAT browser lintas perangkat belum ditutup, API/webhook belum publik, observability dan SLA produksi perlu difinalkan, definisi Grade D-E belum resmi, serta modul LMS/tugas/data siswa terpisah belum menjadi resource mandiri.')
    )
    foreach ($item in $appSections) { Add-Section $item[0] $item[1] $item[2] }
    Add-Figure 'diagram-03.png' 'Gambar 8. Peta modul/sitemap aplikasi'

    Add-Heading 'BAB VII: ARSITEKTUR TEKNOLOGI' 1
    $techSections = @(
        @('7.1','Pengertian','Arsitektur teknologi mendefinisikan runtime, jaringan, penyimpanan, deployment, observability, dan pemulihan yang menopang aplikasi.'),
        @('7.2','Baseline Teknologi','Baseline aktual: PHP 8.4 pada image produksi, Laravel 12, Filament 5, Livewire 4, Node 22 untuk build, Nginx, MySQL, Redis, Docker Compose, serta Vite/Tailwind. Laragon tetap didukung untuk pengembangan lokal.'),
        @('7.3','Topologi Teknologi Logis','Client menuju reverse proxy/TLS, lalu Nginx dan PHP-FPM. Aplikasi berkomunikasi dengan MySQL, Redis, queue worker, scheduler, SMTP, dan storage. Database/Redis tidak perlu dipublikasikan ke internet.'),
        @('7.4','Lingkungan','Development dapat memakai Laragon atau Docker. Testing memakai konfigurasi terisolasi. Staging meniru produksi untuk UAT. Production memakai image immutable, environment secret, APP_DEBUG=false, HTTPS, dan volume persisten.'),
        @('7.5','Session, Cookie, dan Cache','Session/cookie wajib secure, httpOnly, sameSite sesuai integrasi, serta domain benar. Redis disarankan untuk session/cache produksi; perubahan config diikuti cache rebuild terkontrol.'),
        @('7.6','Queue dan Scheduler','Worker dijalankan sebagai service terpisah dan direstart saat release. Scheduler berjalan terus atau memanggil schedule:run setiap menit. Health dan log keduanya harus dimonitor.'),
        @('7.7','Penyimpanan File','Source dan image aplikasi bersifat immutable; file runtime berada pada volume storage_data. Database, storage, dan konfigurasi penting masuk kebijakan backup terjadwal.'),
        @('7.8','Backup, Restore, RPO, dan RTO','Backup dilakukan sebelum migrasi dan berkala. RPO/RTO resmi belum ditetapkan; rekomendasi awal adalah RPO maksimal 24 jam dan RTO maksimal 4 jam, tetapi angka ini tidak berlaku sebagai SLA sebelum disetujui dan diuji melalui restore rehearsal.'),
        @('7.9','Jaringan dan Komunikasi','Produksi memakai HTTPS, firewall, DNS terkelola, dan internal network Compose. Hanya port web yang diekspos; koneksi SMTP/API eksternal memakai TLS dan secret dari environment.'),
        @('7.10','Logging, Monitoring, dan Health Check','Endpoint /up menjadi health check dasar. Monitoring harus mencakup response time, error rate, queue depth, failed jobs, kapasitas disk, koneksi DB, backup, dan uptime; alert diarahkan ke penanggung jawab.'),
        @('7.11','Ketersediaan dan Skalabilitas','Baseline satu server cukup untuk tahap awal. Skalabilitas berikutnya dapat memisahkan DB/Redis terkelola, menambah replica aplikasi stateless, shared storage/object storage, dan load balancer.'),
        @('7.12','Status As-Built dan Target Teknologi','As-built menyediakan Compose produksi multi-stage dengan app, web, database, Redis, queue, scheduler, health check, dan volume. Target berikutnya adalah CI/CD, registry image, TLS otomatis, monitoring, backup off-site, serta staging permanen.'),
        @('7.13','Dasar Verifikasi Project','Verifikasi bersumber dari composer.lock, package-lock.json, migration, route list, test, build Vite, Compose config, health endpoint, dan smoke test. Snapshot terakhir: 50 test dan 339 assertion lulus, build produksi berhasil, serta audit dependency tidak menemukan kerentanan yang diketahui.')
    )
    foreach ($item in $techSections) { Add-Section $item[0] $item[1] $item[2] }

    Add-Heading 'BAB VIII: MANAJEMEN DEBUGGING' 1
    $debugSections = @(
        @('8.1','Pengertian','Debugging adalah proses sistematis menemukan, mereproduksi, menganalisis, memperbaiki, dan memverifikasi defect.'),
        @('8.2','Tujuan','Tujuannya memulihkan fungsi dengan perubahan terkecil yang aman, mencegah regresi, dan membangun pengetahuan operasional.'),
        @('8.3','Prinsip Debugging','Gunakan bukti log/test, reproduksi deterministik, satu hipotesis setiap iterasi, lindungi data, dan pisahkan gejala dari akar masalah.'),
        @('8.4','Siklus Debugging dan QA','Siklusnya: deteksi, klasifikasi dampak, reproduksi, kumpulkan bukti, analisis akar, perbaiki, test ulang, review, dokumentasikan, lalu tutup.'),
        @('8.5','Deteksi dan Identifikasi','Sumber deteksi meliputi laporan pengguna, exception log, failed job, monitoring health, test gagal, build gagal, anomali KPI, dan audit aktivitas.'),
        @('8.6','Reproduksi Masalah','Catat role, cabang, URL, input, waktu, environment, langkah, hasil aktual, hasil harapan, dan bukti layar/log yang telah disanitasi.'),
        @('8.7','Analisis Akar Masalah','Telusuri lapisan UI, Livewire, validasi, authorization, query, schema, cache, queue, jaringan, dan dependency. Gunakan 5-Why bila penyebab melibatkan proses.'),
        @('8.8','Perbaikan Kode dan Refactoring','Perbaikan harus menjaga kompatibilitas data, menambah regression test, tidak mengubah area di luar scope, dan memisahkan refactoring besar dari hotfix bila berisiko.'),
        @('8.9','Pengujian Ulang','Jalankan test paling dekat, suite penuh, build, migration status, dan smoke test alur terdampak. Untuk UI, ulangi desktop dan mobile.'),
        @('8.10','Perintah Diagnostik','Perintah umum: php artisan about, route:list, migrate:status, test, queue:failed, schedule:list, optimize:clear; npm run build; docker compose ps/logs/config; serta pemeriksaan /up.'),
        @('8.11','Logging Aman','Jangan mencatat password, APP_KEY, token, cookie, OTP, isi kartu, atau data pribadi lengkap. Gunakan ID record, event, user/cabang, correlation ID, dan pesan ringkas.'),
        @('8.12','Triage Insiden dan Pemulihan','Prioritaskan insiden berdasarkan dampak data, akses, transaksi, jumlah pengguna, dan durasi. Lakukan containment, komunikasi, recovery, validasi, lalu post-incident review.'),
        @('8.13','Temuan Debugging yang Telah Ditangani','Riwayat proyek mencakup perbaikan redirect/login, koneksi database Laragon-Docker, aset login tanpa styling, grafik tren kosong, filter Semua Bulan, status/sumber lead, nama program tiga komponen, dan scope menu.'),
        @('8.14','Baseline Verifikasi Tercatat','Baseline terbaru mencatat test, assertion, build, migration, audit dependency, Compose health, login, dan health endpoint. Hasil harus diperbarui pada setiap release.'),
        @('8.15','Format Catatan Debugging','Catatan minimal berisi ID, tanggal, reporter, environment, severity, langkah reproduksi, expected/actual, bukti, root cause, perubahan, test, risiko, owner, dan status.'),
        @('8.16','Kriteria Penutupan Bug','Bug ditutup jika akar masalah dipahami, perbaikan direview, regression test lulus, tidak ada dampak samping kritis, dokumentasi diperbarui, dan pengguna/QA menerima hasil.'),
        @('8.17','Rekomendasi Debugging dan QA','Tambahkan CI otomatis, error tracking terpusat, dataset UAT stabil, matriks browser/perangkat, performance budget, dan rehearsal insiden/restore.')
    )
    foreach ($item in $debugSections) { Add-Section $item[0] $item[1] $item[2] }

    Add-Heading 'BAB IX: DEPLOYMENT, MIGRASI, DAN PEMELIHARAAN SISTEM' 1
    $deploySections = @(
        @('9.1','Prinsip Pengendalian','Release harus dapat diulang, diaudit, dibatalkan, dan tidak menghapus data persisten. Secret tidak masuk image/repository.'),
        @('9.2','Peran dan Tanggung Jawab','Developer menyiapkan build/migration; QA memverifikasi; administrator menjalankan deployment/backup; product owner menyetujui UAT; manajemen menyetujui risiko.'),
        @('9.3','Matriks RACI Ringkas','RACI membedakan Responsible, Accountable, Consulted, dan Informed agar keputusan release tidak bergantung pada satu orang.'),
        @('9.4','Lingkungan dan Baseline Teknologi','Development, testing, staging, dan production memakai konfigurasi terpisah. Compose produksi berada pada docker/compose.production.yml dengan contoh environment tanpa secret.'),
        @('9.5','Maintenance Mode','Gunakan php artisan down saat perubahan berpotensi mengganggu kompatibilitas; sediakan bypass untuk operator; akhiri dengan php artisan up setelah smoke test.'),
        @('9.6','Menjalankan Migrasi','Backup lebih dahulu, review migration, jalankan php artisan migrate --force satu kali pada release, lalu periksa migrate:status. Jangan menjalankan seeder demo di production.'),
        @('9.7','Cache, Storage, Queue, dan Scheduler','Bangun cache setelah environment final, pastikan storage link/volume, restart worker setelah kode berubah, dan periksa scheduler serta failed job.'),
        @('9.8','Smoke Test Pasca-Deployment','Uji /up, login, dashboard, filter role/cabang, create/update lead uji, follow-up, transaksi, import/export kecil, notifikasi, audit, dan pemuatan aset.'),
        @('9.9','Observasi Setelah Release','Pantau error, response time, CPU/memori, DB, disk, queue, email, serta feedback pengguna pada periode stabilisasi.'),
        @('9.10','Migrasi Basis Data dan Data','Migrasi data memakai script terversi, dry-run, validasi jumlah/hash, log baris gagal, dan rekonsiliasi sebelum serta sesudah cutover.'),
        @('9.11','Rollback dan Pemulihan Release','Gunakan image/tag sebelumnya untuk rollback aplikasi. Jika migration tidak backward-compatible, restore backup terverifikasi; jangan mengandalkan migrate:rollback tanpa analisis dampak.'),
        @('9.12','Backup dan Recovery Strategy','Backup mencakup MySQL, storage, dan konfigurasi yang diperlukan; disimpan off-site, dienkripsi, diretensi, dimonitor, dan diuji restore.'),
        @('9.13','Pemeliharaan Sistem','Jadwalkan patch dependency, rotasi log, cleanup job, pemeriksaan indeks, validasi backup, review akun/role, dan pembaruan dokumentasi.'),
        @('9.14','Monitoring, Performa, dan Kapasitas','Tetapkan baseline response time, query lambat, queue latency, error rate, disk growth, dan kapasitas DB; buat threshold serta owner alert.'),
        @('9.15','Pemeliharaan Keamanan','Audit dependency, patch OS/image, rotasi secret, review role, backup aman, TLS, rate limiting, dan simulasi respons insiden.'),
        @('9.16','Penanganan Insiden dan Pemeliharaan Darurat','Aktifkan triage, containment, backup bukti, hotfix terkontrol, komunikasi status, recovery, validasi data, dan postmortem tanpa menyalahkan individu.'),
        @('9.17','Rekomendasi Pengembangan Operasional','Prioritas: CI/CD, staging, monitoring/alert, central log, backup off-site otomatis, runbook on-call, registry image, dan release tag immutable.'),
        @('9.18','Kriteria Dokumen Dinyatakan Diterapkan','Dokumen dinyatakan diterapkan bila owner menyetujui, langkah telah diuji, bukti tersimpan, role dilatih, gap diberi target, dan hasil UAT/restore/release direkam.')
    )
    foreach ($item in $deploySections) { Add-Section $item[0] $item[1] $item[2] }
    Add-Table @('Aktivitas','Developer','QA','Admin','Owner') @(
        @('Build/test','R','A/C','I','I'),@('UAT','C','R','C','A'),@('Backup/deploy','C','C','R','A'),@('Rollback','C','C','R','A'),@('Persetujuan release','C','C','C','A')
    ) 'Tabel 7. Matriks RACI deployment'

    Add-Heading 'BAB X: PANDUAN PENGGUNAAN' 1
    $guide = @(
        @('10.1','Login','Buka /admin/login, masukkan email dan kata sandi akun aktif, pilih Ingat saya hanya pada perangkat pribadi, lalu tekan Masuk. Laporkan kegagalan berulang tanpa membagikan password.'),
        @('10.2','Hak Akses Role','Menu dan data menyesuaikan role serta cabang. Pengguna tidak boleh mencoba memakai akun bersama atau meminta perluasan akses tanpa persetujuan.'),
        @('10.3','Dashboard','Gunakan filter Cabang, Tahun, Bulan termasuk Semua Bulan, dan Pengguna. Tinjau ringkasan, KPI, target, tren, aktivitas, ranking, tindakan tertunda, distribusi lead berdasarkan enam dimensi, Top 5 sekolah, dan Top 5 kota.'),
        @('10.4','Data Leads','Buat lead dengan nama, WhatsApp, sekolah/program bila tersedia, cabang, PIC, status, sumber, dan tanggal masuk. Hindari duplikasi; perbarui status mengikuti Cold-Warm-Hot-Daftar/Tidak Berminat.'),
        @('10.5','Import Leads CSV','Unduh contoh CSV dari halaman Leads, isi header sesuai template, gunakan nilai enum yang valid, unggah maksimal sesuai batas sistem, tinjau ringkasan dan perbaiki baris gagal.'),
        @('10.6','Export Leads','Atur filter/pencarian, pilih Export CSV, tunggu notifikasi, lalu unduh. Ekspor mengikuti RBAC/cabang dan menetralkan formula spreadsheet.'),
        @('10.7','Follow Up','Pilih lead, waktu, metode, status kontak, hasil, dan jadwal berikutnya. Catatan harus objektif dan tidak memuat data sensitif yang tidak diperlukan.'),
        @('10.8','Data Siswa','Pada as-built, data siswa direpresentasikan oleh Pendaftaran aktif; belum tersedia resource Data Siswa terpisah. Gunakan daftar Pendaftaran untuk melihat lead yang telah dikonversi.'),
        @('10.9','Profil User','Gunakan menu profil Filament untuk mengelola informasi akun yang diizinkan. Perubahan role/cabang dilakukan administrator, bukan pengguna sendiri.'),
        @('10.10','TIM','Fungsi tim saat ini dipetakan melalui Pengguna, role, cabang, PIC, target pengguna, dan ranking. Belum ada halaman TIM mandiri.'),
        @('10.11','Tugas','Tugas operasional direpresentasikan oleh follow-up berikutnya dan panel tindakan tertunda. Modul task management terpisah belum tersedia.'),
        @('10.12','Laporan','Pilih periode/cabang/pengguna, verifikasi definisi KPI, lalu gunakan hasil untuk analisis. Jangan membandingkan periode berbeda tanpa menyamakan filter.'),
        @('10.13','Pembelajaran','Sistem mengelola katalog Program Bimbel, bukan LMS. Materi, kelas virtual, kehadiran, dan progres belajar merupakan roadmap jika diperlukan.'),
        @('10.14','Notifikasi','Buka ikon lonceng untuk membaca notifikasi lead, pembayaran, target, atau reminder. Tandai dibaca setelah tindakan dilakukan.'),
        @('10.15','Pengaturan','Role berwenang dapat mengelola konfigurasi CRM dan opsi klasifikasi Program Bimbel. Perubahan dicatat dalam audit.'),
        @('10.16','Backup dan Restore','Pengguna biasa tidak menjalankan backup/restore. Ajukan kebutuhan kepada administrator; proses harus mengikuti runbook dan persetujuan.'),
        @('10.17','Log Aktivitas','Audit Aktivitas menampilkan pelaku, waktu, cabang, event, subject, dan perubahan. Gunakan untuk penelusuran, bukan untuk mengubah data.'),
        @('10.18','Tips Operasional','Periksa tindakan tertunda setiap awal hari, isi sumber lead secara benar, hindari duplikasi, jadwalkan follow-up, gunakan filter sebelum ekspor, dan laporkan anomali segera.')
    )
    foreach ($item in $guide) { Add-Section $item[0] $item[1] $item[2] }

    Add-Heading 'BAB XI: PANDUAN ADMINISTRATOR' 1
    Add-Section '11.1' 'Instalasi Development' 'Laragon: arahkan document root ke public, gunakan DB_HOST=127.0.0.1, siapkan database, composer install, npm install, key:generate, migrate --seed untuk lokal, npm run build/dev. Docker: salin docker/.env, lalu docker compose --env-file docker/.env -f docker/compose.yml up -d --build dan jalankan migration di service app.'
    Add-Section '11.2' 'Queue dan Scheduler' 'Pastikan worker dan scheduler aktif, cek log serta failed_jobs, dan restart worker setelah release. Production tidak boleh bergantung pada terminal interaktif.'
    Add-Section '11.3' 'Client API' 'Belum tersedia API client publik pada snapshot. Jika ditambahkan, administrator mengelola client ID/secret, scope, rotasi, rate limit, IP policy, audit, expiry, dan pencabutan. Secret tidak dikirim melalui kanal tidak aman.'
    Add-Section '11.4' 'Backup' 'Jadwalkan dump konsisten MySQL dan salinan storage, enkripsi, kirim ke lokasi off-site, monitor hasil, tetapkan retensi, dan lakukan restore rehearsal.'
    Add-Section '11.5' 'Permission Server' 'User service hanya memerlukan akses tulis ke storage dan bootstrap/cache. Source, .env, private key, dan backup dibatasi; Nginx hanya melayani public; database/Redis tidak diekspos publik.'
    Add-Section '11.6' 'Maintenance Mode' 'Aktifkan php artisan down saat perubahan berisiko, informasikan pengguna, gunakan bypass secret terbatas untuk verifikasi, dan jalankan php artisan up setelah health/smoke test berhasil.'

    Add-Heading 'BAB XII: SOP OPERASIONAL' 1
    $sops = @(
        @('12.1','Lead Masuk',@('Terima identitas dan persetujuan kontak.','Periksa duplikasi nomor WhatsApp.','Isi sumber lead, cabang, minat, PIC, dan tanggal.','Simpan dengan status Cold lalu jadwalkan follow-up.')),
        @('12.2','SOP Distribusi Lead',@('Admin Cabang menilai wilayah, beban PIC, dan program.','Tetapkan PIC dalam cabang yang berwenang.','Sistem mencatat perubahan pada audit.','PIC mengakui penugasan dan membuat jadwal pertama.')),
        @('12.3','SOP Follow Up',@('Buka daftar jatuh tempo.','Hubungi melalui metode yang disetujui.','Catat waktu, status kontak, hasil, dan langkah berikutnya.','Ubah Cold/Warm/Hot sesuai bukti interaksi.','Jadwalkan ulang atau eskalasi bila diperlukan.')),
        @('12.4','SOP Closing',@('Pastikan lead Hot dan program sesuai.','Buat pendaftaran satu kali untuk lead.','Catat pembayaran dan bukti.','Role berwenang memverifikasi.','Ubah status lead menjadi Daftar dan pastikan KPI konsisten.')),
        @('12.5','SOP Lost Lead',@('Pastikan alasan tidak berminat dicatat objektif.','Ubah status menjadi Tidak Berminat.','Batalkan reminder yang tidak relevan.','Pertahankan histori sesuai retensi.','Gunakan data agregat untuk evaluasi kanal tanpa spam kontak.')),
        @('12.6','SOP Retensi dan Keluhan',@('Catat keluhan dan tingkat urgensi.','Tetapkan owner serta tenggat.','Lindungi data pribadi.','Komunikasikan progres.','Tutup setelah solusi dikonfirmasi dan evaluasi akar masalah.')),
        @('12.7','SOP Insiden Keamanan',@('Laporkan segera dan jangan menghapus bukti.','Batasi akses/containment.','Rotasi kredensial yang terpapar.','Analisis dampak data dan kewajiban pemberitahuan.','Pulihkan dari sumber tepercaya.','Dokumentasikan timeline, tindakan, dan pencegahan.'))
    )
    foreach ($sop in $sops) {
        Add-Heading "$($sop[0]) $($sop[1])" 2
        Add-Numbers $sop[2]
    }

    Add-Heading 'GLOSARIUM' 1
    Add-Table @('Istilah','Definisi') @(
        @('CRM','Customer Relationship Management; pengelolaan hubungan calon siswa sampai transaksi.'),
        @('Lead','Calon siswa/orang tua yang masuk proses akuisisi.'),
        @('PIC','Person in Charge yang bertanggung jawab atas lead.'),
        @('RBAC','Kontrol akses berbasis role.'),
        @('Branch scoping','Pembatasan query dan aksi berdasarkan cabang.'),
        @('KPI','Indikator kinerja utama yang memiliki definisi dan sumber data.'),
        @('Eloquent','ORM Laravel untuk model, relasi, dan query.'),
        @('Filament','Framework panel administrasi berbasis Laravel/Livewire.'),
        @('Livewire','Komponen UI reaktif yang diproses server.'),
        @('Migration','Perubahan schema basis data yang berversi.'),
        @('Seeder','Kode pembentuk data awal/demonstrasi nonproduksi.'),
        @('Queue','Antrean pekerjaan asinkron.'),
        @('Scheduler','Penjadwal tugas berulang Laravel.'),
        @('Webhook','Pemberitahuan HTTP berbasis event ke sistem lain.'),
        @('RPO','Batas kehilangan data yang dapat diterima.'),
        @('RTO','Batas waktu pemulihan layanan.'),
        @('UAT','Pengujian penerimaan oleh pengguna/perwakilan bisnis.'),
        @('As-built','Kondisi yang benar-benar telah diterapkan pada snapshot.'),
        @('Roadmap','Target pengembangan yang belum dinyatakan tersedia.')
    ) 'Tabel 8. Glosarium'

    Add-Paragraph '--- Akhir dokumen ---' 'Normal' 1

    foreach ($field in $doc.Fields) { $field.Update() | Out-Null }
    if ($doc.TablesOfContents.Count -gt 0) { $doc.TablesOfContents.Item(1).Update() | Out-Null }
    $doc.Save()
}
finally {
    if ($null -ne $doc) {
        $doc.Close($false)
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($doc) | Out-Null
    }
    if ($null -ne $word) {
        $word.Quit()
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}

Write-Output $output
