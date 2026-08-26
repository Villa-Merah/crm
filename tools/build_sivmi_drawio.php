<?php

declare(strict_types=1);

$output = $argv[1] ?? null;

if (! $output) {
    fwrite(STDERR, "Usage: php tools/build_sivmi_drawio.php <output.drawio>\n");
    exit(1);
}

function xmlAttr(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

final class DrawioPage
{
    private int $counter = 2;
    private array $cells = [
        '<mxCell id="0"/>',
        '<mxCell id="1" parent="0"/>',
    ];

    public function vertex(string $value, int $x, int $y, int $width, int $height, string $style, ?string $id = null): string
    {
        $id ??= 'v'.$this->counter++;
        $this->cells[] = sprintf(
            '<mxCell id="%s" value="%s" style="%s" vertex="1" parent="1"><mxGeometry x="%d" y="%d" width="%d" height="%d" as="geometry"/></mxCell>',
            xmlAttr($id), xmlAttr($value), xmlAttr($style), $x, $y, $width, $height,
        );

        return $id;
    }

    public function edge(string $source, string $target, string $value = '', string $style = ''): string
    {
        $id = 'e'.$this->counter++;
        $style = $style ?: 'edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;endArrow=block;endFill=1;strokeWidth=2;strokeColor=#64748B;fontSize=13;labelBackgroundColor=#FFFFFF;';
        $this->cells[] = sprintf(
            '<mxCell id="%s" value="%s" style="%s" edge="1" parent="1" source="%s" target="%s"><mxGeometry relative="1" as="geometry"/></mxCell>',
            $id, xmlAttr($value), xmlAttr($style), xmlAttr($source), xmlAttr($target),
        );

        return $id;
    }

    public function xml(): string
    {
        return '<mxGraphModel dx="1600" dy="1000" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="1654" pageHeight="1169" math="0" shadow="0"><root>'.implode('', $this->cells).'</root></mxGraphModel>';
    }
}

function title(DrawioPage $page, string $text, string $subtitle = ''): void
{
    $page->vertex($text, 40, 25, 1500, 50, 'text;html=1;strokeColor=none;fillColor=none;fontSize=28;fontStyle=1;fontColor=#14357A;align=left;verticalAlign=middle;');
    if ($subtitle !== '') {
        $page->vertex($subtitle, 40, 75, 1500, 38, 'text;html=1;strokeColor=none;fillColor=none;fontSize=14;fontColor=#64748B;align=left;verticalAlign=middle;');
    }
}

function boxStyle(string $fill = '#EAF4FB', string $stroke = '#1476B8'): string
{
    return "rounded=1;whiteSpace=wrap;html=1;arcSize=14;fillColor={$fill};strokeColor={$stroke};strokeWidth=2;fontSize=16;fontColor=#334155;spacing=10;";
}

function entityStyle(string $fill = '#EAF4FB'): string
{
    return "shape=swimlane;startSize=38;horizontal=1;rounded=1;html=1;fillColor={$fill};swimlaneFillColor=#1476B8;strokeColor=#1476B8;strokeWidth=2;fontColor=#FFFFFF;fontStyle=1;fontSize=15;align=center;spacing=8;";
}

function processStyle(): string
{
    return 'rounded=1;whiteSpace=wrap;html=1;arcSize=18;fillColor=#EAF4FB;strokeColor=#1476B8;strokeWidth=2;fontSize=15;fontStyle=1;fontColor=#334155;spacing=10;';
}

function dataStoreStyle(): string
{
    return 'shape=datastore;whiteSpace=wrap;html=1;fillColor=#FFF7E6;strokeColor=#F59E0B;strokeWidth=2;fontSize=14;fontStyle=1;fontColor=#334155;spacing=10;';
}

function addPage(array &$pages, string $name, callable $draw): void
{
    $page = new DrawioPage();
    $draw($page);
    $pages[] = '<diagram id="'.xmlAttr('sivmi-'.(count($pages) + 1)).'" name="'.xmlAttr($name).'">'.$page->xml().'</diagram>';
}

$pages = [];

addPage($pages, '01 - Arsitektur Sistem', function (DrawioPage $p): void {
    title($p, 'Arsitektur Berlapis SIVMI CRM', 'Laravel 12, Filament 5, Livewire, Tailwind CSS, Eloquent ORM');
    $layers = [
        ['PRESENTASI', 'Filament 5<br>Blade<br>Tailwind CSS<br>Responsif', '#EAF4FB'],
        ['INTERAKSI', 'Livewire<br>Form dan Table<br>Filter global<br>Notifikasi', '#EAFBF5'],
        ['APLIKASI', 'Resource<br>Page<br>Observer<br>Support service', '#FFF7E6'],
        ['DATA', 'Eloquent ORM<br>Query scope<br>Migration<br>Import/Export', '#F5EEFF'],
        ['PLATFORM', 'Laravel 12<br>PHP 8.3+<br>Queue/Scheduler<br>Vite 8', '#FFEFF2'],
    ];
    $previous = null;
    foreach ($layers as $index => [$name, $items, $fill]) {
        $id = $p->vertex("<b>{$name}</b><br><br>{$items}", 45 + ($index * 305), 190, 270, 510, boxStyle($fill));
        if ($previous) $p->edge($previous, $id, 'mengalir ke');
        $previous = $id;
    }
    $p->vertex('<b>Kontrol lintas lapisan</b><br>Autentikasi | RBAC | Branch scoping | Validasi | Audit aktivitas | Error/empty/loading state', 200, 790, 1200, 90, boxStyle('#F6F8FC', '#075985'));
});

addPage($pages, '02 - Sitemap', function (DrawioPage $p): void {
    title($p, 'Sitemap Panel Administrasi SIVMI CRM', 'Seluruh menu berupa item langsung tanpa submenu');
    $root = $p->vertex('<b>/admin</b><br>Autentikasi pengguna', 650, 125, 300, 90, boxStyle('#1476B8', '#075985'));
    $crm = $p->vertex('<b>SIVMI CRM</b><br>Operasional dan monitoring', 230, 300, 420, 90, boxStyle('#EAF4FB'));
    $master = $p->vertex('<b>MASTER DATA</b><br>Referensi dan kontrol sistem', 950, 300, 420, 90, boxStyle('#EAFBF5', '#075985'));
    $p->edge($root, $crm);
    $p->edge($root, $master);
    foreach (['Dashboard', 'Leads', 'Follow-up', 'Inbox WhatsApp', 'Target Kinerja', 'Laporan'] as $i => $label) {
        $item = $p->vertex($label, 250, 460 + ($i * 95), 380, 55, boxStyle('#F6F8FC'));
        $p->edge($crm, $item, '', 'edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=none;strokeColor=#94A3B8;strokeWidth=2;');
    }
    foreach (['Cabang', 'Sekolah', 'Pengguna', 'Pendaftaran', 'Pembayaran', 'Program Bimbel', 'Koneksi WhatsApp', 'Pengaturan', 'Audit Aktivitas'] as $i => $label) {
        $col = $i % 2;
        $row = intdiv($i, 2);
        $item = $p->vertex($label, 890 + ($col * 260), 460 + ($row * 115), 230, 58, boxStyle('#F6F8FC', '#075985'));
        $p->edge($master, $item, '', 'edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=none;strokeColor=#94A3B8;strokeWidth=2;');
    }
});

addPage($pages, '03 - Proses Bisnis', function (DrawioPage $p): void {
    title($p, 'Proses Bisnis End-to-End', 'Akuisisi calon siswa sampai monitoring kinerja');
    $stages = [
        ['1. Akuisisi', 'Identitas<br>Sumber lead<br>Sekolah<br>Cabang', '#EAF4FB'],
        ['2. Tindak Lanjut', 'Jadwal follow-up<br>Metode kontak<br>Status kontak<br>Catatan hasil', '#EAFBF5'],
        ['3. Konversi', 'Pilih program<br>Tanggal daftar<br>Status siswa', '#FFF7E6'],
        ['4. Transaksi', 'Nominal<br>Metode bayar<br>Verifikasi<br>Referensi', '#F5EEFF'],
        ['5. Monitoring', 'Dashboard<br>Target kinerja<br>Laporan<br>Audit', '#FFEFF2'],
    ];
    $previous = null;
    foreach ($stages as $i => [$heading, $content, $fill]) {
        $id = $p->vertex("<b>{$heading}</b><br><br>{$content}", 40 + ($i * 310), 260, 270, 400, boxStyle($fill));
        if ($previous) $p->edge($previous, $id, 'status/data');
        $previous = $id;
    }
    $p->vertex('<b>Kontrol</b><br>Validasi input | Role dan cabang | Notifikasi | Audit aktivitas | Query terfilter', 255, 760, 1050, 90, boxStyle('#F6F8FC', '#075985'));
});

addPage($pages, '04 - Flowmap', function (DrawioPage $p): void {
    title($p, 'Flowmap Proses Akuisisi hingga Monitoring', 'Pembagian aktivitas menurut aktor');
    $lanes = [];
    foreach (['CALON SISWA', 'STAFF', 'ADMIN CABANG', 'SISTEM', 'DIREKSI'] as $i => $label) {
        $lanes[$i] = $p->vertex($label, 50, 130 + ($i * 180), 1500, 170, 'shape=swimlane;horizontal=0;startSize=180;html=1;fillColor='.(($i % 2) ? '#EFF6FF' : '#F8FAFC').';swimlaneFillColor='.(($i === 4) ? '#075985' : '#1476B8').';strokeColor=#CBD5E1;fontColor=#FFFFFF;fontStyle=1;fontSize=16;');
    }
    $steps = [
        [0, 260, '1. Sampaikan minat<br>dan identitas'], [1, 480, '2. Catat lead dan<br>penanggung jawab'],
        [3, 700, '3. Validasi, simpan,<br>dan catat audit'], [1, 900, '4. Lakukan follow-up<br>dan jadwalkan ulang'],
        [2, 1110, '5. Review konversi /<br>verifikasi pembayaran'], [3, 1320, '6. Perbarui KPI,<br>notifikasi, laporan'],
        [4, 1370, '7. Pantau performa<br>dan tindak lanjut'],
    ];
    $previous = null;
    foreach ($steps as [$lane, $x, $label]) {
        $id = $p->vertex($label, $x, 175 + ($lane * 180), 210, 78, boxStyle('#FFFFFF'));
        if ($previous) $p->edge($previous, $id);
        $previous = $id;
    }
});

addPage($pages, '05 - Flowchart', function (DrawioPage $p): void {
    title($p, 'Flowchart Lead hingga Pembayaran', 'Keputusan pendaftaran dan verifikasi pembayaran');
    $start = $p->vertex('Mulai: lead diterima', 585, 120, 430, 70, processStyle());
    $validate = $p->vertex('Validasi identitas, cabang,<br>sekolah, dan minat program', 585, 240, 430, 85, processStyle());
    $followup = $p->vertex('Tetapkan penanggung jawab<br>dan lakukan follow-up', 585, 380, 430, 85, processStyle());
    $ready = $p->vertex('Lead siap<br>mendaftar?', 650, 540, 300, 150, 'shape=rhombus;whiteSpace=wrap;html=1;fillColor=#FFF7E6;strokeColor=#F59E0B;strokeWidth=2;fontSize=17;fontStyle=1;');
    $registration = $p->vertex('Pilih program dan<br>buat pendaftaran', 585, 760, 430, 85, processStyle());
    $valid = $p->vertex('Pembayaran valid dan<br>berwenang diverifikasi?', 650, 920, 300, 150, 'shape=rhombus;whiteSpace=wrap;html=1;fillColor=#FFF7E6;strokeColor=#F59E0B;strokeWidth=2;fontSize=17;fontStyle=1;');
    $update = $p->vertex('Perbarui status siswa, omzet, target,<br>notifikasi, dan audit', 585, 1140, 430, 85, processStyle());
    $finish = $p->vertex('Selesai', 585, 1280, 430, 70, processStyle());
    foreach ([[$start,$validate],[$validate,$followup],[$followup,$ready],[$ready,$registration],[$registration,$valid],[$valid,$update],[$update,$finish]] as [$a,$b]) $p->edge($a,$b);
    $p->edge($ready, $followup, 'Tidak: follow-up ulang', 'edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=block;strokeColor=#F43F5E;strokeWidth=2;fontSize=13;labelBackgroundColor=#FFFFFF;exitX=0;exitY=0.5;entryX=0;entryY=0.5;');
    $p->edge($valid, $registration, 'Tidak: koreksi', 'edgeStyle=orthogonalEdgeStyle;rounded=0;html=1;endArrow=block;strokeColor=#F43F5E;strokeWidth=2;fontSize=13;labelBackgroundColor=#FFFFFF;exitX=1;exitY=0.5;entryX=1;entryY=0.5;');
});

addPage($pages, '06 - DFD Level 0', function (DrawioPage $p): void {
    title($p, 'DFD Level 0 - Diagram Konteks', 'Batas sistem dan pertukaran data dengan entitas eksternal');
    $system = $p->vertex('<b>0</b><br><br>SISTEM CRM SIVMI', 600, 280, 400, 400, 'ellipse;whiteSpace=wrap;html=1;aspect=fixed;fillColor=#EAF4FB;strokeColor=#1476B8;strokeWidth=3;fontSize=24;fontStyle=1;fontColor=#14357A;');
    $student = $p->vertex('<b>Calon Siswa / Orang Tua</b><br>Identitas, minat<br>Konfirmasi dan status', 70, 160, 360, 150, boxStyle('#F6F8FC'));
    $staff = $p->vertex('<b>Staff / Admin Cabang</b><br>Lead, follow-up, transaksi<br>Tugas dan notifikasi', 70, 690, 360, 150, boxStyle('#F6F8FC'));
    $superadmin = $p->vertex('<b>Superadmin</b><br>Master data dan konfigurasi<br>Status sistem', 1170, 160, 360, 150, boxStyle('#F6F8FC'));
    $director = $p->vertex('<b>Direksi</b><br>Filter dan permintaan laporan<br>KPI dan laporan', 1170, 690, 360, 150, boxStyle('#F6F8FC'));
    foreach ([[$student,$system,'data calon siswa'],[$system,$student,'status layanan'],[$staff,$system,'data operasional'],[$system,$staff,'tugas/notifikasi'],[$superadmin,$system,'master dan aturan'],[$system,$superadmin,'hasil konfigurasi'],[$director,$system,'permintaan analisis'],[$system,$director,'KPI/laporan']] as [$a,$b,$label]) $p->edge($a,$b,$label);
});

addPage($pages, '07 - DFD Level 1', function (DrawioPage $p): void {
    title($p, 'DFD Level 1 - Dekomposisi Proses CRM', 'Lima proses utama dan kelompok data store');
    $ops = $p->vertex('<b>Pengguna Operasional</b><br>Staff<br>Admin Cabang', 40, 190, 260, 130, boxStyle('#F6F8FC'));
    $management = $p->vertex('<b>Manajemen</b><br>Superadmin<br>Direksi', 40, 780, 260, 130, boxStyle('#F6F8FC'));
    $processes = [];
    $stores = [];
    $labels = [
        ['1.0 Kelola Lead', 'validasi, penugasan, status', 'D1 Leads dan Master'],
        ['2.0 Kelola Follow-up', 'kontak, catatan, reminder', 'D2 Riwayat Follow-up'],
        ['3.0 Kelola Pendaftaran', 'konversi lead ke siswa', 'D3 Pendaftaran'],
        ['4.0 Kelola Pembayaran', 'catat dan verifikasi transaksi', 'D4 Pembayaran'],
        ['5.0 Monitoring', 'KPI, target, laporan, audit', 'D5 Target, Notifikasi, Audit'],
    ];
    foreach ($labels as $i => [$heading,$detail,$store]) {
        $y = 130 + ($i * 190);
        $processes[$i] = $p->vertex("<b>{$heading}</b><br>{$detail}", 440, $y, 390, 105, processStyle());
        $stores[$i] = $p->vertex($store, 1080, $y, 430, 105, dataStoreStyle());
        $p->edge($processes[$i], $stores[$i], 'simpan/baca');
        if ($i > 0) $p->edge($processes[$i - 1], $processes[$i], 'status');
    }
    $p->edge($ops, $processes[0], 'input/otorisasi');
    $p->edge($ops, $processes[1], 'aktivitas kontak');
    $p->edge($management, $processes[3], 'otorisasi');
    $p->edge($management, $processes[4], 'filter/permintaan');
});

addPage($pages, '08 - Peta Entitas', function (DrawioPage $p): void {
    title($p, 'Peta Entitas Inti SIVMI CRM', 'Pengelompokan domain data');
    $groups = [
        ['MASTER', 'Cabang<br>Sekolah<br>Pengguna<br>Program Bimbel', '#EAF4FB'],
        ['CRM', 'Leads<br>Follow-up<br>Pendaftaran<br>Pembayaran', '#EAFBF5'],
        ['KINERJA', 'Target Cabang<br>Target Pengguna<br>Laporan<br>Dashboard', '#FFF7E6'],
        ['KONTROL', 'Pengaturan<br>Notifikasi<br>Audit Aktivitas<br>Job Batch', '#F5EEFF'],
    ];
    $previous = null;
    foreach ($groups as $i => [$heading,$items,$fill]) {
        $id = $p->vertex("<b>{$heading}</b><br><br>{$items}", 90 + ($i * 380), 210, 330, 500, boxStyle($fill));
        if ($previous) $p->edge($previous, $id, 'mendukung');
        $previous = $id;
    }
});

addPage($pages, '09 - ERD Operasional', function (DrawioPage $p): void {
    title($p, 'ERD Operasional CRM SIVMI', 'PK, FK, relasi, dan kardinalitas berdasarkan migration serta model Eloquent');
    $cabang = $p->vertex('cabang<br><br>PK cabang_id<br>kode_cabang<br>nama<br>aktif', 40, 150, 280, 190, entityStyle());
    $users = $p->vertex('users<br><br>PK id<br>FK cabang_id (nullable)<br>role<br>aktif', 410, 120, 330, 220, entityStyle());
    $sekolah = $p->vertex('sekolah<br><br>PK sekolah_id<br>npsn (nullable, unique)<br>nama_sekolah<br>kluster_sekolah', 40, 650, 310, 230, entityStyle());
    $program = $p->vertex('program_bimbel<br><br>PK program_bimbel_id<br>nama_program<br>biaya_pendidikan<br>aktif', 410, 900, 340, 220, entityStyle());
    $leads = $p->vertex('leads<br><br>PK lead_id<br>FK sekolah_id (nullable)<br>FK minat_program_id (nullable)<br>FK cabang_id<br>FK diinput_oleh_user_id<br>FK diserahkan_ke_user_id (nullable)<br>status_lead<br>tanggal_masuk', 780, 420, 370, 340, entityStyle());
    $followup = $p->vertex('followup_riwayat<br><br>PK followup_riwayat_id<br>FK lead_id<br>FK user_id<br>tanggal_followup<br>status_kontak', 1240, 130, 360, 250, entityStyle());
    $registration = $p->vertex('pendaftaran<br><br>PK pendaftaran_id<br>FK lead_id (unique)<br>FK program_bimbel_id<br>FK cabang_id<br>status_pendaftaran', 1240, 530, 360, 260, entityStyle());
    $payment = $p->vertex('pembayaran<br><br>PK pembayaran_id<br>FK pendaftaran_id<br>tanggal_pembayaran<br>nominal<br>status_pembayaran', 1240, 900, 360, 250, entityStyle());
    foreach ([
        [$cabang,$users,'1 : 0..N'],[$cabang,$leads,'1 : 0..N'],[$cabang,$registration,'1 : 0..N'],
        [$sekolah,$leads,'1 : 0..N'],[$program,$leads,'1 : 0..N'],[$users,$leads,'1 : 0..N'],
        [$leads,$followup,'1 : 0..N'],[$users,$followup,'1 : 0..N'],[$leads,$registration,'1 : 0..1'],
        [$program,$registration,'1 : 0..N'],[$registration,$payment,'1 : 0..N'],
    ] as [$a,$b,$label]) $p->edge($a,$b,$label);
});

addPage($pages, '10 - ERD Kinerja dan Kontrol', function (DrawioPage $p): void {
    title($p, 'ERD Kinerja, Pengaturan, dan Audit', 'Target cabang/pengguna serta kontrol sistem');
    $cabang = $p->vertex('cabang<br><br>PK cabang_id<br>nama<br>aktif', 40, 170, 300, 180, entityStyle());
    $users = $p->vertex('users<br><br>PK id<br>FK cabang_id<br>role<br>aktif', 40, 650, 300, 210, entityStyle());
    $targetBranch = $p->vertex('target_kinerja_cabang<br><br>PK target_kinerja_cabang_id<br>FK cabang_id<br>FK created_by (nullable)<br>tahun + bulan (unique/cabang)<br>target_omzet<br>target_siswa', 500, 130, 460, 290, entityStyle());
    $targetUser = $p->vertex('target_kinerja_user<br><br>PK target_kinerja_user_id<br>FK target_kinerja_cabang_id<br>FK user_id<br>target_omzet<br>target_siswa', 1160, 150, 420, 250, entityStyle());
    $settings = $p->vertex('crm_settings<br><br>PK key<br>FK updated_by (nullable)<br>value<br>type<br>group', 500, 650, 460, 250, entityStyle());
    $activity = $p->vertex('activity_logs<br><br>PK activity_log_id<br>FK user_id (nullable)<br>FK cabang_id (nullable)<br>subject_type + subject_id<br>event<br>properties', 1130, 620, 450, 290, entityStyle());
    $notifications = $p->vertex('notifications<br><br>PK id (UUID)<br>notifiable_type + notifiable_id<br>data<br>read_at', 1130, 980, 450, 210, entityStyle('#F5EEFF'));
    foreach ([
        [$cabang,$targetBranch,'1 : 0..N'],[$targetBranch,$targetUser,'1 : 0..N'],[$users,$targetUser,'1 : 0..N'],
        [$users,$settings,'1 : 0..N'],[$users,$activity,'1 : 0..N'],[$cabang,$activity,'1 : 0..N'],[$users,$notifications,'1 : 0..N'],
    ] as [$a,$b,$label]) $p->edge($a,$b,$label);
    $p->vertex('<b>Catatan kardinalitas</b><br>0..1 = relasi opsional tunggal<br>0..N = nol atau banyak record anak<br>Relasi subject dan notifiable bersifat polymorphic', 430, 1010, 500, 140, boxStyle('#FFF7E6', '#F59E0B'));
});

addPage($pages, '11 - Arsitektur Integrasi WAHA', function (DrawioPage $p): void {
    title($p, 'Arsitektur Integrasi WhatsApp WAHA', 'Konfigurasi hybrid: Laravel dan MySQL di Laragon, WAHA GOWS di Docker');
    $phone = $p->vertex('<b>WhatsApp Pengguna</b><br>Nomor admin/staff<br>Perangkat tertaut', 50, 250, 300, 180, boxStyle('#EAFBF5', '#10B981'));
    $waha = $p->vertex('<b>WAHA 2026.8.1 CORE</b><br>Engine GOWS<br>Session per pengguna<br>API + webhook HMAC', 450, 220, 380, 240, boxStyle('#EAF4FB', '#1476B8'));
    $laravel = $p->vertex('<b>SIVMI CRM / Laravel</b><br>Webhook controller<br>Event persistence<br>Queue job<br>Lead matcher', 930, 220, 400, 240, boxStyle('#FFF7E6', '#F59E0B'));
    $mysql = $p->vertex('<b>MySQL</b><br>user_whatsapp_accounts<br>waha_webhook_events<br>whatsapp_conversations<br>whatsapp_messages<br>leads', 930, 650, 400, 260, dataStoreStyle());
    $worker = $p->vertex('<b>Queue Worker</b><br>ProcessWahaWebhook<br>retry + backoff<br>wajib selalu aktif', 450, 690, 380, 190, boxStyle('#F5EEFF', '#A855F7'));
    $browser = $p->vertex('<b>Filament Admin</b><br>Koneksi WhatsApp<br>QR terlindungi<br>Inbox dan resolusi lead', 50, 680, 300, 210, boxStyle('#F6F8FC', '#075985'));
    $p->edge($phone, $waha, 'protokol WhatsApp');
    $p->edge($waha, $laravel, 'POST webhook + HMAC');
    $p->edge($laravel, $mysql, 'simpan event');
    $p->edge($mysql, $worker, 'job database');
    $p->edge($worker, $mysql, 'conversation, message, lead');
    $p->edge($browser, $laravel, 'RBAC dan branch scope');
    $p->edge($laravel, $waha, 'API X-Api-Key');
    $p->vertex('<b>Batas keamanan</b><br>API key WAHA terpisah dari HMAC secret | session dimiliki satu user | chat dibatasi role dan cabang | port WAHA tidak diekspos ke internet pada production', 190, 990, 1180, 100, boxStyle('#FFEFF2', '#F43F5E'));
});

addPage($pages, '12 - Alur Pesan Masuk WAHA', function (DrawioPage $p): void {
    title($p, 'Alur Pesan Masuk WhatsApp hingga Lead', 'Urutan penerimaan, verifikasi, queue, normalisasi GOWS, dan deduplikasi');
    $steps = [
        ['1. Pesan diterima', 'WAHA GOWS menerima chat personal', '#EAF4FB'],
        ['2. Webhook HMAC', 'POST /api/webhooks/waha<br>signature SHA-512 diverifikasi', '#EAFBF5'],
        ['3. Simpan event', 'Hash payload mencegah event duplikat', '#FFF7E6'],
        ['4. Queue worker', 'ProcessWahaWebhook dengan retry/backoff', '#F5EEFF'],
        ['5. Normalisasi', '@lid memakai SenderAlt<br>suffix perangkat dihapus<br>PushName dibaca', '#FFEFF2'],
        ['6. Cocokkan lead', '0 cocok: lead Cold baru<br>1 cocok: tautkan<br>>1/lintas cabang: konflik', '#EAF4FB'],
        ['7. Tampilkan inbox', 'Conversation, message, unread, status match', '#EAFBF5'],
    ];
    $previous = null;
    foreach ($steps as $i => [$heading, $detail, $fill]) {
        $col = $i % 4;
        $row = intdiv($i, 4);
        $x = 45 + ($col * 380);
        $y = 180 + ($row * 430);
        $id = $p->vertex("<b>{$heading}</b><br><br>{$detail}", $x, $y, 330, 230, boxStyle($fill));
        if ($previous) $p->edge($previous, $id, $i === 4 ? 'asinkron' : 'data/status');
        $previous = $id;
    }
    $p->vertex('<b>Event yang diabaikan</b><br>group @g.us | newsletter | status@broadcast | event selain message/message.any/session.status', 610, 1010, 760, 90, boxStyle('#F6F8FC', '#64748B'));
});

addPage($pages, '13 - State Machine Session WAHA', function (DrawioPage $p): void {
    title($p, 'State Machine Session WAHA', 'Pemulihan otomatis QR dan status operasional session');
    $disconnected = $p->vertex('<b>DISCONNECTED</b><br>Belum dimulai', 70, 250, 300, 130, boxStyle('#F6F8FC', '#64748B'));
    $starting = $p->vertex('<b>STARTING</b><br>Engine menyiapkan session', 480, 250, 300, 130, boxStyle('#FFF7E6', '#F59E0B'));
    $qr = $p->vertex('<b>SCAN_QR_CODE</b><br>QR siap dipindai', 890, 250, 300, 130, boxStyle('#EAF4FB', '#1476B8'));
    $working = $p->vertex('<b>WORKING</b><br>gRPC dan stream READY', 480, 650, 300, 130, boxStyle('#EAFBF5', '#10B981'));
    $failed = $p->vertex('<b>FAILED / STOPPED</b><br>Error jaringan, timeout,<br>atau session berhenti', 1180, 650, 330, 150, boxStyle('#FFEFF2', '#F43F5E'));
    $p->edge($disconnected, $starting, 'Mulai');
    $p->edge($starting, $qr, 'session siap autentikasi');
    $p->edge($qr, $working, 'QR berhasil dipindai');
    $p->edge($working, $failed, 'disconnect/error');
    $p->edge($failed, $starting, 'restart otomatis');
    $p->edge($starting, $failed, 'timeout/gagal');
    $p->vertex('<b>Perilaku CRM</b><br>Halaman QR memeriksa status lebih dahulu, me-restart FAILED/STOPPED, menunggu SCAN_QR_CODE, lalu mengirim PNG tanpa cache. Jika belum siap, CRM menampilkan state pemulihan 503 dan tidak membocorkan error internal.', 220, 930, 1180, 120, boxStyle('#F5EEFF', '#A855F7'));
});

addPage($pages, '14 - ERD Integrasi WhatsApp', function (DrawioPage $p): void {
    title($p, 'ERD Integrasi WhatsApp dan Pencocokan Lead', 'Relasi, kepemilikan session, event webhook, conversation, dan message');
    $users = $p->vertex('users<br><br>PK id<br>FK cabang_id<br>role<br>aktif', 50, 130, 300, 210, entityStyle());
    $branch = $p->vertex('cabang<br><br>PK cabang_id<br>nama<br>aktif', 50, 600, 300, 180, entityStyle());
    $account = $p->vertex('user_whatsapp_accounts<br><br>PK id<br>FK user_id (unique)<br>FK cabang_id<br>phone_e164<br>session_name (unique)<br>status<br>aktif', 470, 120, 410, 300, entityStyle());
    $event = $p->vertex('waha_webhook_events<br><br>PK id<br>payload_hash (unique)<br>event_name<br>session_name<br>status + attempts<br>payload', 470, 680, 410, 280, entityStyle('#FFF7E6'));
    $conversation = $p->vertex('whatsapp_conversations<br><br>PK id<br>FK whatsapp_account_id<br>FK lead_id (nullable)<br>chat_id<br>contact_phone_e164<br>match_status<br>unread_count', 1010, 120, 430, 310, entityStyle('#EAFBF5'));
    $message = $p->vertex('whatsapp_messages<br><br>PK id<br>FK conversation_id<br>FK whatsapp_account_id<br>FK lead_id (nullable)<br>external_message_id<br>direction + body<br>sent_at', 1010, 650, 430, 300, entityStyle('#F5EEFF'));
    $lead = $p->vertex('leads<br><br>PK lead_id<br>no_wa_normalized<br>kanal_masuk<br>status_lead<br>whatsapp_first_seen_at<br>whatsapp_last_seen_at', 1360, 360, 260, 280, entityStyle('#FFEFF2'));
    foreach ([
        [$users,$account,'1 : 0..1'],[$branch,$account,'1 : 0..N'],[$account,$conversation,'1 : 0..N'],
        [$account,$message,'1 : 0..N'],[$conversation,$message,'1 : 0..N'],[$lead,$conversation,'1 : 0..N'],
        [$lead,$message,'1 : 0..N'],[$account,$event,'session_name (logis)'],
    ] as [$a,$b,$label]) $p->edge($a,$b,$label);
    $p->vertex('<b>Aturan</b><br>Satu user satu session aktif | payload_hash idempotent | external_message_id unik per akun | lead lintas cabang tidak ditautkan otomatis', 500, 1040, 920, 90, boxStyle('#F6F8FC', '#075985'));
});

addPage($pages, '15 - DFD Integrasi WhatsApp', function (DrawioPage $p): void {
    title($p, 'DFD Level 1 - Integrasi WhatsApp', 'Dekomposisi proses session, webhook, pencocokan, inbox, dan monitoring');
    $sender = $p->vertex('<b>Pengirim WhatsApp</b><br>Pesan dan identitas', 40, 160, 300, 120, boxStyle('#F6F8FC'));
    $operator = $p->vertex('<b>Admin / Staff</b><br>Session, QR, inbox', 40, 760, 300, 120, boxStyle('#F6F8FC'));
    $waha = $p->vertex('<b>WAHA GOWS</b><br>API dan event webhook', 1260, 160, 300, 120, boxStyle('#EAF4FB'));
    $p1 = $p->vertex('<b>6.1 Kelola Session</b><br>provision, start/restart, QR, sync', 440, 130, 400, 110, processStyle());
    $p2 = $p->vertex('<b>6.2 Terima Webhook</b><br>verifikasi HMAC dan idempotensi', 440, 350, 400, 110, processStyle());
    $p3 = $p->vertex('<b>6.3 Proses Pesan</b><br>queue, normalisasi GOWS/LID', 440, 570, 400, 110, processStyle());
    $p4 = $p->vertex('<b>6.4 Cocokkan Lead</b><br>baru, matched, conflict, unresolved', 440, 790, 400, 110, processStyle());
    $d1 = $p->vertex('D6.1 Akun dan Session', 1020, 350, 360, 90, dataStoreStyle());
    $d2 = $p->vertex('D6.2 Webhook Events', 1020, 500, 360, 90, dataStoreStyle());
    $d3 = $p->vertex('D6.3 Conversation dan Message', 1020, 650, 360, 90, dataStoreStyle());
    $d4 = $p->vertex('D1 Leads', 1020, 800, 360, 90, dataStoreStyle());
    $p->edge($operator,$p1,'perintah session'); $p->edge($p1,$waha,'API X-Api-Key');
    $p->edge($sender,$waha,'pesan'); $p->edge($waha,$p2,'webhook HMAC');
    $p->edge($p2,$d2,'event'); $p->edge($d2,$p3,'job'); $p->edge($p3,$d3,'simpan chat');
    $p->edge($p3,$p4,'nomor + identitas'); $p->edge($p4,$d4,'baca/buat/tautkan');
    $p->edge($p4,$d3,'status match'); $p->edge($d3,$operator,'inbox terfilter'); $p->edge($p1,$d1,'status akun');
});

addPage($pages, '16 - Deployment Hybrid WAHA', function (DrawioPage $p): void {
    title($p, 'Topologi Deployment Hybrid Laragon + Docker', 'Alur jaringan lokal dan komponen runtime yang wajib hidup');
    $browser = $p->vertex('<b>Browser</b><br>sivmi.crm.test', 50, 180, 280, 120, boxStyle('#F6F8FC'));
    $laragon = $p->vertex('<b>Laragon</b><br>Apache/Nginx + PHP 8.4<br>Laravel + Filament', 430, 150, 390, 180, boxStyle('#EAF4FB'));
    $mysql = $p->vertex('<b>MySQL Laragon</b><br>127.0.0.1:3306<br>database sivmi.crm', 430, 520, 390, 160, dataStoreStyle());
    $worker = $p->vertex('<b>Queue Worker</b><br>php artisan queue:work<br>harus dimonitor', 430, 820, 390, 150, boxStyle('#F5EEFF', '#A855F7'));
    $docker = $p->vertex('<b>Docker Desktop</b><br>WAHA GOWS<br>localhost:3000<br>volume waha_sessions', 1030, 150, 420, 210, boxStyle('#EAFBF5', '#10B981'));
    $whatsapp = $p->vertex('<b>WhatsApp Network</b><br>WebSocket dan perangkat tertaut', 1030, 560, 420, 150, boxStyle('#FFF7E6', '#F59E0B'));
    $p->edge($browser,$laragon,'HTTP lokal'); $p->edge($laragon,$mysql,'Eloquent');
    $p->edge($laragon,$docker,'127.0.0.1:3000 API'); $p->edge($docker,$laragon,'host-gateway webhook');
    $p->edge($docker,$whatsapp,'GOWS'); $p->edge($mysql,$worker,'database queue'); $p->edge($worker,$mysql,'hasil proses');
    $p->vertex('<b>Variabel penting</b><br>WAHA_BASE_URL=http://127.0.0.1:3000 | WAHA_WEBHOOK_URL=http://sivmi.crm.test/api/webhooks/waha | API key dan HMAC secret harus sama pada pasangan konfigurasi masing-masing', 170, 1030, 1280, 90, boxStyle('#FFEFF2', '#F43F5E'));
});

$xml = '<?xml version="1.0" encoding="UTF-8"?><mxfile host="Electron" modified="2026-08-24T15:00:00.000Z" agent="Codex SIVMI diagram builder" version="29.6.6" type="device">'.implode('', $pages).'</mxfile>';

$directory = dirname($output);
if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
    throw new RuntimeException("Tidak dapat membuat direktori: {$directory}");
}

if (file_put_contents($output, $xml) === false) {
    throw new RuntimeException("Tidak dapat menulis file: {$output}");
}

echo realpath($output).PHP_EOL;
