<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
$output = $projectRoot.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'ERD Relasi dan Kardinalitas SIVMI CRM v1.1.drawio';

$document = new DOMDocument('1.0', 'UTF-8');
$document->preserveWhiteSpace = true;
$document->formatOutput = true;

$mxfile = $document->createElement('mxfile');
$mxfile->setAttribute('host', 'Electron');
$mxfile->setAttribute('agent', 'Codex');
$mxfile->setAttribute('version', '26.0.16');
$mxfile->setAttribute('pages', '7');
$document->appendChild($mxfile);

// 01. ERD operasional end-to-end seperti format dokumentasi.
$page = page($document, 'erd-00', '01 - ERD Operasional End-to-End', 'ERD Operasional: Lead sampai Pembayaran', 'Kolom kunci ditampilkan; atribut lengkap tetap tersedia pada migration dan kamus data');
$root = graphRoot($page);
table($document, $root, 'p0-cabang', 45, 160, 215, 220, 'cabang', ['PK cabang_id', 'UQ kode_cabang', 'nama', 'IDX aktif']);
table($document, $root, 'p0-users', 310, 160, 230, 260, 'users', ['PK id', 'FK cabang_id (nullable)', 'UQ email', 'role', 'IDX aktif']);
table($document, $root, 'p0-sekolah', 590, 160, 230, 260, 'sekolah', ['PK sekolah_id', 'UQ npsn (nullable)', 'nama_sekolah', 'status / kluster', 'IDX aktif']);
table($document, $root, 'p0-program', 870, 160, 360, 300, 'program_bimbel', ['PK program_bimbel_id', 'kategori / kelas / metode', 'tipe / jenjang / tingkat', 'UQ nama_program', 'biaya / diskon', 'IDX aktif']);
table($document, $root, 'p0-leads', 180, 545, 330, 370, 'leads', ['PK lead_id', 'FK cabang_id', 'FK sekolah_id (nullable)', 'FK minat_program_id (nullable)', 'FK diinput_oleh / PIC (nullable)', 'IDX no_wa_normalized (nullable)', 'IDX status / sumber / kanal', 'IDX tanggal_masuk']);
table($document, $root, 'p0-followup', 570, 545, 285, 330, 'followup_riwayat', ['PK followup_riwayat_id', 'FK lead_id', 'FK user_id', 'IDX tanggal_followup', 'metode / status_kontak', 'IDX berikutnya (nullable)', 'catatan (nullable)']);
table($document, $root, 'p0-register', 930, 650, 275, 290, 'pendaftaran', ['PK pendaftaran_id', 'UQ lead_id (FK)', 'FK program_bimbel_id', 'FK cabang_id', 'IDX tanggal / status', 'kelas_angkatan (nullable)']);
table($document, $root, 'p0-payment', 1300, 650, 275, 270, 'pembayaran', ['PK pembayaran_id', 'FK pendaftaran_id', 'IDX tanggal_pembayaran', 'nominal / metode', 'IDX status', 'referensi (nullable)']);
relation($document, $root, 'p0-r1', 'p0-cabang', 'p0-users', '1 : 0..N');
relation($document, $root, 'p0-r2', 'p0-cabang', 'p0-leads', '1 : 0..N');
relation($document, $root, 'p0-r3', 'p0-users', 'p0-leads', '1 : 0..N');
relation($document, $root, 'p0-r4', 'p0-sekolah', 'p0-leads', '1 : 0..N');
relation($document, $root, 'p0-r5', 'p0-program', 'p0-leads', '1 : 0..N');
relation($document, $root, 'p0-r6', 'p0-leads', 'p0-followup', '1 : 0..N');
relation($document, $root, 'p0-r7', 'p0-users', 'p0-followup', '1 : 0..N');
relation($document, $root, 'p0-r8', 'p0-leads', 'p0-register', '1 : 0..1');
relation($document, $root, 'p0-r9', 'p0-program', 'p0-register', '1 : 0..N');
relation($document, $root, 'p0-r10', 'p0-cabang', 'p0-register', '1 : 0..N');
relation($document, $root, 'p0-r11', 'p0-register', 'p0-payment', '1 : 0..N');
legend($document, $root, 'p0-note', 280, 1010, 1100, 90, 'PK = primary key · FK = foreign key · UQ = unique constraint · IDX = indeks · tanda ? = nullable. Warna header: biru master, hijau CRM, dan oranye transaksi.');
$mxfile->appendChild($page);

// 02. Master data, pengguna, dan lead.
$page = page($document, 'erd-01', '02 - Detail Master, Pengguna, dan Lead', 'ERD Master Data, Pengguna, dan Lead', 'Relasi inti pembentukan lead, kepemilikan cabang, sekolah, program, dan PIC');
$root = graphRoot($page);
table($document, $root, 'p1-cabang', 45, 170, 245, 230, 'cabang', ['PK cabang_id', 'UQ kode_cabang', 'nama', 'kota', 'IDX aktif']);
table($document, $root, 'p1-users', 45, 555, 270, 275, 'users', ['PK id', 'FK cabang_id (nullable)', 'UQ email', 'role', 'IDX aktif']);
table($document, $root, 'p1-sekolah', 370, 130, 290, 320, 'sekolah', ['PK sekolah_id', 'UQ npsn (nullable)', 'nama_sekolah', 'status_sekolah', 'kota / provinsi', 'kluster_sekolah', 'IDX aktif']);
table($document, $root, 'p1-program', 370, 560, 300, 330, 'program_bimbel', ['PK program_bimbel_id', 'nama_program', 'kategori_program', 'program_kelas', 'metode_belajar', 'tipe / jenjang / tingkat', 'biaya / diskon', 'IDX aktif']);
table($document, $root, 'p1-leads', 790, 230, 360, 430, 'leads', ['PK lead_id', 'FK sekolah_id (nullable)', 'FK minat_program_id (nullable)', 'FK cabang_id', 'FK diinput_oleh_user_id', 'FK diserahkan_ke_user_id (nullable)', 'nama / no_wa', 'IDX no_wa_normalized (nullable)', 'IDX status / sumber / kanal', 'IDX tanggal_masuk', 'whatsapp_first_seen_at / last_seen_at']);
table($document, $root, 'p1-followup', 1280, 270, 310, 330, 'followup_riwayat', ['PK followup_riwayat_id', 'FK lead_id', 'FK user_id', 'IDX tanggal_followup', 'metode_followup', 'status_kontak', 'IDX tanggal_followup_berikutnya (nullable)', 'catatan (nullable)']);
relation($document, $root, 'p1-r1', 'p1-cabang', 'p1-users', '1  —  0..N');
relation($document, $root, 'p1-r2', 'p1-cabang', 'p1-leads', '1  —  0..N');
relation($document, $root, 'p1-r3', 'p1-sekolah', 'p1-leads', '1  —  0..N');
relation($document, $root, 'p1-r4', 'p1-program', 'p1-leads', '1  —  0..N');
relation($document, $root, 'p1-r5', 'p1-users', 'p1-leads', '1  —  0..N · input/PIC');
relation($document, $root, 'p1-r6', 'p1-leads', 'p1-followup', '1  —  0..N');
relation($document, $root, 'p1-r7', 'p1-users', 'p1-followup', '1  —  0..N');
legend($document, $root, 'p1-note', 260, 970, 1130, 110, 'FK sekolah, minat program, dan PIC penyerahan pada lead boleh kosong. Setiap follow-up wajib mempunyai tepat satu lead dan satu pengguna.');
$mxfile->appendChild($page);

// 02. Pendaftaran dan pembayaran.
$page = page($document, 'erd-02', '03 - Detail Pendaftaran dan Pembayaran', 'ERD Pendaftaran dan Pembayaran', 'Lead hanya dapat mempunyai satu pendaftaran; satu pendaftaran dapat menerima banyak pembayaran');
$root = graphRoot($page);
table($document, $root, 'p2-leads', 80, 220, 300, 330, 'leads', ['PK lead_id', 'FK cabang_id', 'nama', 'no_wa_normalized', 'status_lead', 'tanggal_masuk']);
table($document, $root, 'p2-program', 80, 650, 300, 260, 'program_bimbel', ['PK program_bimbel_id', 'nama_program', 'biaya_pendaftaran', 'biaya_pendidikan', 'diskon_pendidikan', 'aktif']);
table($document, $root, 'p2-cabang', 490, 650, 270, 230, 'cabang', ['PK cabang_id', 'UQ kode_cabang', 'nama', 'kota', 'aktif']);
table($document, $root, 'p2-register', 600, 200, 370, 390, 'pendaftaran', ['PK pendaftaran_id', 'UQ FK lead_id', 'FK program_bimbel_id', 'FK cabang_id', 'IDX tanggal_daftar', 'kelas_angkatan (nullable)', 'IDX status_pendaftaran', 'catatan (nullable)']);
table($document, $root, 'p2-payment', 1160, 230, 370, 390, 'pembayaran', ['PK pembayaran_id', 'FK pendaftaran_id', 'IDX tanggal_pembayaran', 'nominal', 'metode_pembayaran', 'IDX status_pembayaran', 'nomor_referensi (nullable)', 'catatan (nullable)']);
relation($document, $root, 'p2-r1', 'p2-leads', 'p2-register', '1  —  0..1');
relation($document, $root, 'p2-r2', 'p2-program', 'p2-register', '1  —  0..N');
relation($document, $root, 'p2-r3', 'p2-cabang', 'p2-register', '1  —  0..N');
relation($document, $root, 'p2-r4', 'p2-register', 'p2-payment', '1  —  0..N');
legend($document, $root, 'p2-note', 260, 970, 1130, 110, 'UNIQUE pada pendaftaran.lead_id menegakkan relasi lead 1 : 0..1 pendaftaran. Pembayaran dihapus berantai ketika pendaftaran dihapus.');
$mxfile->appendChild($page);

// 03. Kinerja, pengaturan, audit, dan notifikasi.
$page = page($document, 'erd-03', '04 - Kinerja, Pengaturan, dan Audit', 'ERD Kinerja, Pengaturan, Audit, dan Notifikasi', 'Target cabang/pengguna serta kontrol sistem dengan relasi normal dan polymorphic');
$root = graphRoot($page);
table($document, $root, 'p3-cabang', 45, 155, 245, 225, 'cabang', ['PK cabang_id', 'kode_cabang', 'nama', 'aktif']);
table($document, $root, 'p3-users', 45, 520, 260, 260, 'users', ['PK id', 'FK cabang_id (nullable)', 'email', 'role', 'aktif']);
table($document, $root, 'p3-target-branch', 390, 130, 350, 330, 'target_kinerja_cabang', ['PK target_kinerja_cabang_id', 'FK cabang_id', 'FK created_by (nullable)', 'UQ cabang_id + tahun + bulan', 'status', 'target_omzet', 'target_siswa', 'catatan']);
table($document, $root, 'p3-target-user', 855, 130, 350, 300, 'target_kinerja_user', ['PK target_kinerja_user_id', 'FK target_kinerja_cabang_id', 'FK user_id', 'UQ target_cabang + user', 'target_omzet', 'target_siswa', 'catatan']);
table($document, $root, 'p3-settings', 390, 610, 320, 280, 'crm_settings', ['PK key', 'FK updated_by (nullable)', 'value', 'type', 'group', 'updated_at']);
table($document, $root, 'p3-activity', 830, 560, 360, 350, 'activity_logs', ['PK activity_log_id', 'FK user_id (nullable)', 'FK cabang_id (nullable)', 'subject_type + subject_id (nullable)', 'event / description', 'properties', 'ip_address / user_agent', 'created_at']);
table($document, $root, 'p3-notifications', 1300, 260, 300, 300, 'notifications', ['PK id (UUID)', 'notifiable_type + notifiable_id', 'type', 'data', 'read_at', 'created_at']);
relation($document, $root, 'p3-r1', 'p3-cabang', 'p3-target-branch', '1  —  0..N');
relation($document, $root, 'p3-r2', 'p3-target-branch', 'p3-target-user', '1  —  0..N');
relation($document, $root, 'p3-r3', 'p3-users', 'p3-target-user', '1  —  0..N');
relation($document, $root, 'p3-r4', 'p3-users', 'p3-settings', '1  —  0..N');
relation($document, $root, 'p3-r5', 'p3-users', 'p3-activity', '1  —  0..N');
relation($document, $root, 'p3-r6', 'p3-cabang', 'p3-activity', '1  —  0..N');
relation($document, $root, 'p3-r7', 'p3-users', 'p3-notifications', '1  —  0..N · polymorphic', true);
legend($document, $root, 'p3-note', 250, 990, 1150, 100, 'Garis putus-putus menunjukkan relasi polymorphic/logis. activity_logs.subject dan notifications.notifiable tidak memakai foreign key langsung ke satu jenis tabel.');
$mxfile->appendChild($page);

// 04. Integrasi WhatsApp dan media.
$page = page($document, 'erd-04', '05 - Integrasi WhatsApp dan Media', 'ERD Integrasi WhatsApp WAHA/GOWS', 'Kepemilikan akun per pengguna, percakapan, pesan teks/media, pencocokan lead, dan event webhook');
$root = graphRoot($page);
table($document, $root, 'p4-users', 40, 135, 250, 230, 'users', ['PK id', 'FK cabang_id', 'email', 'role', 'aktif']);
table($document, $root, 'p4-cabang', 40, 525, 250, 220, 'cabang', ['PK cabang_id', 'kode_cabang', 'nama', 'aktif']);
table($document, $root, 'p4-account', 385, 170, 330, 360, 'user_whatsapp_accounts', ['PK id', 'UQ FK user_id', 'FK cabang_id', 'UQ phone_e164', 'UQ session_name', 'waha_me_id', 'status / aktif', 'connected_at / last_seen_at', 'last_error / metadata']);
table($document, $root, 'p4-leads', 385, 670, 330, 300, 'leads', ['PK lead_id', 'FK cabang_id', 'no_wa_normalized', 'kanal_masuk', 'whatsapp_first_seen_at', 'whatsapp_last_seen_at']);
table($document, $root, 'p4-conversation', 820, 125, 350, 380, 'whatsapp_conversations', ['PK id', 'FK whatsapp_account_id', 'FK lead_id (nullable)', 'UQ account_id + chat_id', 'contact_phone_e164 / contact_name', 'match_status', 'first_message_at / last_message_at', 'unread_count']);
table($document, $root, 'p4-message', 1260, 120, 350, 440, 'whatsapp_messages', ['PK id', 'FK conversation_id', 'FK whatsapp_account_id', 'FK lead_id (nullable)', 'UQ account_id + external_message_id', 'direction / message_type', 'body / has_media / media_path', 'sent_at / from_me', 'processing_status / raw_payload']);
table($document, $root, 'p4-event', 880, 700, 350, 300, 'waha_webhook_events', ['PK id', 'UQ payload_hash', 'event_name', 'session_name', 'status / attempts', 'payload / last_error', 'processed_at']);
relation($document, $root, 'p4-r1', 'p4-users', 'p4-account', '1  —  0..1');
relation($document, $root, 'p4-r2', 'p4-cabang', 'p4-account', '1  —  0..N');
relation($document, $root, 'p4-r3', 'p4-account', 'p4-conversation', '1  —  0..N');
relation($document, $root, 'p4-r4', 'p4-leads', 'p4-conversation', '1  —  0..N');
relation($document, $root, 'p4-r5', 'p4-conversation', 'p4-message', '1  —  0..N');
relation($document, $root, 'p4-r6', 'p4-account', 'p4-message', '1  —  0..N');
relation($document, $root, 'p4-r7', 'p4-leads', 'p4-message', '1  —  0..N');
relation($document, $root, 'p4-r8', 'p4-account', 'p4-event', 'session_name · logis', true);
legend($document, $root, 'p4-note', 45, 820, 270, 180, 'Kardinalitas opsional:<br>conversation.lead_id = 0..1<br>message.lead_id = 0..1<br><br>media_path menunjuk file pada disk privat Laravel.');
$mxfile->appendChild($page);

// 05. Import/export dan tabel runtime.
$page = page($document, 'erd-05', '06 - Import, Export, dan Runtime', 'ERD Import, Export, dan Runtime Laravel', 'Tabel pendukung pemrosesan CSV, queue, session, cache, dan pemulihan kegagalan');
$root = graphRoot($page);
table($document, $root, 'p5-users', 60, 350, 260, 230, 'users', ['PK id', 'email', 'role', 'aktif']);
table($document, $root, 'p5-imports', 430, 120, 320, 300, 'imports', ['PK id', 'FK user_id', 'file_name / file_path', 'importer', 'total_rows', 'successful_rows', 'completed_at']);
table($document, $root, 'p5-failed', 860, 120, 330, 290, 'failed_import_rows', ['PK id', 'FK import_id', 'data', 'validation_error']);
table($document, $root, 'p5-exports', 430, 540, 320, 300, 'exports', ['PK id', 'FK user_id', 'exporter', 'total_rows', 'processed_rows', 'file_disk / file_name', 'completed_at']);
table($document, $root, 'p5-jobs', 1260, 120, 290, 270, 'jobs / failed_jobs', ['PK id / uuid', 'queue', 'payload', 'attempts', 'available_at', 'failed_at']);
table($document, $root, 'p5-session', 870, 560, 300, 250, 'sessions', ['PK id', 'user_id (nullable)', 'ip_address', 'user_agent', 'payload', 'last_activity']);
table($document, $root, 'p5-cache', 1260, 560, 290, 250, 'cache / cache_locks', ['PK key', 'value / owner', 'expiration']);
relation($document, $root, 'p5-r1', 'p5-users', 'p5-imports', '1  —  0..N');
relation($document, $root, 'p5-r2', 'p5-imports', 'p5-failed', '1  —  0..N');
relation($document, $root, 'p5-r3', 'p5-users', 'p5-exports', '1  —  0..N');
legend($document, $root, 'p5-note', 300, 960, 1050, 120, 'jobs, failed_jobs, cache, dan cache_locks adalah tabel runtime. Relasi session.user_id bersifat logis/nullable pada migration awal dan tidak memakai constraint database.');
$mxfile->appendChild($page);

// 06. Matriks relasi dan legenda kardinalitas.
$page = page($document, 'erd-06', '07 - Matriks Relasi dan Kardinalitas', 'Matriks Relasi dan Kardinalitas', 'Ringkasan interpretasi relasi database SIVMI CRM');
$root = graphRoot($page);
matrix($document, $root, 'p6-left', 60, 160, 740, 720, [
    ['cabang', 'users', '1 : 0..N', 'users.cabang_id nullable'],
    ['cabang', 'leads', '1 : 0..N', 'lead wajib satu cabang'],
    ['sekolah', 'leads', '1 : 0..N', 'sekolah pada lead opsional'],
    ['program_bimbel', 'leads', '1 : 0..N', 'minat program opsional'],
    ['users', 'leads', '1 : 0..N', 'input wajib; PIC opsional'],
    ['leads', 'followup_riwayat', '1 : 0..N', 'cascade delete'],
    ['users', 'followup_riwayat', '1 : 0..N', 'pelaksana wajib'],
    ['leads', 'pendaftaran', '1 : 0..1', 'lead_id unique'],
    ['program_bimbel', 'pendaftaran', '1 : 0..N', 'program wajib'],
    ['cabang', 'pendaftaran', '1 : 0..N', 'cabang wajib'],
    ['pendaftaran', 'pembayaran', '1 : 0..N', 'cascade delete'],
]);
matrix($document, $root, 'p6-right', 855, 160, 740, 720, [
    ['cabang', 'target_kinerja_cabang', '1 : 0..N', 'unik per bulan/tahun'],
    ['target_kinerja_cabang', 'target_kinerja_user', '1 : 0..N', 'cascade delete'],
    ['users', 'target_kinerja_user', '1 : 0..N', 'unik per target cabang'],
    ['users', 'user_whatsapp_accounts', '1 : 0..1', 'user_id unique'],
    ['cabang', 'user_whatsapp_accounts', '1 : 0..N', 'akun wajib satu cabang'],
    ['user_whatsapp_accounts', 'whatsapp_conversations', '1 : 0..N', 'cascade delete'],
    ['leads', 'whatsapp_conversations', '1 : 0..N', 'FK pada conversation opsional'],
    ['whatsapp_conversations', 'whatsapp_messages', '1 : 0..N', 'cascade delete'],
    ['leads', 'whatsapp_messages', '1 : 0..N', 'FK pada message opsional'],
    ['users', 'imports / exports', '1 : 0..N', 'cascade delete'],
    ['imports', 'failed_import_rows', '1 : 0..N', 'cascade delete'],
]);
legend($document, $root, 'p6-legend', 210, 935, 1230, 140, '<b>Notasi:</b> 1 = tepat satu; 0..1 = opsional satu; 0..N = nol atau banyak. Garis penuh = foreign key database. Garis putus-putus = relasi logis atau polymorphic. UQ = unique constraint.');
$mxfile->appendChild($page);

if ($document->save($output) === false) {
    throw new RuntimeException('File ERD gagal disimpan.');
}

echo $output.PHP_EOL;

function page(DOMDocument $document, string $id, string $name, string $title, string $subtitle): DOMElement
{
    $diagram = $document->createElement('diagram');
    $diagram->setAttribute('id', $id);
    $diagram->setAttribute('name', $name);

    $model = $document->createElement('mxGraphModel');
    foreach (['dx' => '1895', 'dy' => '1138', 'grid' => '0', 'guides' => '1', 'tooltips' => '1', 'connect' => '1', 'arrows' => '1', 'fold' => '1', 'page' => '1', 'pageScale' => '1', 'pageWidth' => '1654', 'pageHeight' => '1169', 'math' => '0', 'shadow' => '0'] as $key => $value) {
        $model->setAttribute($key, $value);
    }

    $root = $document->createElement('root');
    $root->appendChild(cell($document, '0'));
    $root->appendChild(cell($document, '1', ['parent' => '0']));
    $root->appendChild(vertex($document, $id.'-title', 45, 25, 1560, 48, $title, 'text;html=1;strokeColor=none;fillColor=none;fontSize=28;fontStyle=1;fontColor=#075985;align=center;verticalAlign=middle;'));
    $root->appendChild(vertex($document, $id.'-subtitle', 80, 82, 1490, 40, $subtitle, 'text;html=1;strokeColor=none;fillColor=none;fontSize=17;fontColor=#64748B;align=center;verticalAlign=middle;whiteSpace=wrap;'));
    $model->appendChild($root);
    $diagram->appendChild($model);

    return $diagram;
}

function graphRoot(DOMElement $diagram): DOMElement
{
    $root = $diagram->getElementsByTagName('root')->item(0);
    if (! $root instanceof DOMElement) {
        throw new RuntimeException('Root diagram tidak ditemukan.');
    }

    return $root;
}

function table(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $name, array $fields): void
{
    $headerColor = tableColor($name);
    $container = vertex($document, $id, $x, $y, $width, $height, '', 'rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#FFFFFF;strokeColor='.$headerColor.';strokeWidth=2;shadow=1;');
    $root->appendChild($container);

    $header = vertex($document, $id.'-header', $x, $y, $width, 42, '<font color="#FFFFFF"><b>'.$name.'</b></font>', 'rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor='.$headerColor.';strokeColor='.$headerColor.';strokeWidth=2;fontSize=15;align=left;verticalAlign=middle;spacingLeft=12;pointerEvents=0;');
    $root->appendChild($header);

    $rowHeight = ($height - 42) / max(1, count($fields));
    foreach (array_values($fields) as $index => $field) {
        [$badge, $label] = splitFieldBadge($field);
        $rowY = (int) round($y + 42 + ($index * $rowHeight));
        $nextY = (int) round($y + 42 + (($index + 1) * $rowHeight));
        $actualHeight = max(22, $nextY - $rowY);
        $fill = $index % 2 === 0 ? '#FFFFFF' : '#F8FAFC';
        $value = badgeHtml($badge).$label;
        $root->appendChild(vertex($document, $id.'-row-'.$index, $x + 1, $rowY, $width - 2, $actualHeight, $value, 'whiteSpace=wrap;html=1;rounded=0;fillColor='.$fill.';strokeColor=#E2E8F0;strokeWidth=1;fontSize=13;fontColor=#334155;align=left;verticalAlign=middle;spacingLeft=9;pointerEvents=0;'));
    }
}

function tableColor(string $name): string
{
    if (in_array($name, ['leads', 'followup_riwayat', 'whatsapp_conversations', 'whatsapp_messages', 'user_whatsapp_accounts'], true)) {
        return '#10B981';
    }
    if (in_array($name, ['pendaftaran', 'pembayaran', 'imports', 'failed_import_rows', 'exports'], true)) {
        return '#F59E0B';
    }
    if (in_array($name, ['target_kinerja_cabang', 'target_kinerja_user', 'crm_settings', 'activity_logs', 'notifications', 'waha_webhook_events'], true)) {
        return '#A855F7';
    }

    return '#1476B8';
}

function splitFieldBadge(string $field): array
{
    foreach (['PK', 'FK', 'UQ', 'IDX'] as $badge) {
        if (str_starts_with($field, $badge.' ')) {
            $label = substr($field, strlen($badge) + 1);
            $label = str_replace(' (nullable)', '?', $label);

            return [$badge, $label];
        }
    }

    return ['', str_replace(' (nullable)', '?', $field)];
}

function badgeHtml(string $badge): string
{
    if ($badge === '') {
        return '<span style="display:inline-block;width:31px">&nbsp;</span>';
    }

    $colors = ['PK' => '#075985', 'FK' => '#A855F7', 'UQ' => '#F59E0B', 'IDX' => '#10B981'];
    $color = $colors[$badge];

    return '<span style="background-color:'.$color.';color:#FFFFFF;border-radius:4px;padding:2px 5px"><b>'.$badge.'</b></span>&nbsp;&nbsp;';
}

function relation(DOMDocument $document, DOMElement $root, string $id, string $source, string $target, string $label, bool $logical = false): void
{
    $style = 'edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;endArrow=block;endFill=1;strokeWidth=2;strokeColor=#64748B;fontSize=13;fontStyle=1;fontColor=#334155;labelBackgroundColor=#FFFFFF;';
    if ($logical) {
        $style .= 'dashed=1;dashPattern=8 6;';
    }
    $edge = cell($document, $id, ['value' => $label, 'edge' => '1', 'parent' => '1', 'source' => $source, 'target' => $target, 'style' => $style]);
    $geometry = $document->createElement('mxGeometry');
    $geometry->setAttribute('relative', '1');
    $geometry->setAttribute('as', 'geometry');
    $edge->appendChild($geometry);
    $root->appendChild($edge);
}

function matrix(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, array $rows): void
{
    $html = '<b>INDUK → ANAK | KARDINALITAS | CATATAN</b><br><br>';
    foreach ($rows as [$parent, $child, $cardinality, $note]) {
        $html .= '<b>'.$parent.'</b> → '.$child.'<br><font color="#075985"><b>'.$cardinality.'</b></font> · '.$note.'<br><br>';
    }
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $html, 'rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#FFFFFF;strokeColor=#CBD5E1;strokeWidth=2;fontSize=15;fontColor=#334155;align=left;verticalAlign=top;spacing=18;shadow=1;'));
}

function legend(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $value): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $value, 'rounded=1;whiteSpace=wrap;html=1;arcSize=8;fillColor=#F0F9FF;strokeColor=#7DD3FC;strokeWidth=2;fontSize=15;fontColor=#334155;align=center;verticalAlign=middle;spacing=12;'));
}

function vertex(DOMDocument $document, string $id, int $x, int $y, int $width, int $height, string $value, string $style): DOMElement
{
    $node = cell($document, $id, ['value' => $value, 'style' => $style, 'vertex' => '1', 'parent' => '1']);
    $geometry = $document->createElement('mxGeometry');
    foreach (['x' => (string) $x, 'y' => (string) $y, 'width' => (string) $width, 'height' => (string) $height, 'as' => 'geometry'] as $key => $value) {
        $geometry->setAttribute($key, $value);
    }
    $node->appendChild($geometry);

    return $node;
}

function cell(DOMDocument $document, string $id, array $attributes = []): DOMElement
{
    $cell = $document->createElement('mxCell');
    $cell->setAttribute('id', $id);
    foreach ($attributes as $key => $value) {
        $cell->setAttribute($key, (string) $value);
    }

    return $cell;
}
