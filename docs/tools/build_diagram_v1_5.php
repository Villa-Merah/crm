<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'Diagram Gabungan SIVMI CRM v1.4.drawio';
$output = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'Diagram Gabungan SIVMI CRM v1.5.drawio';

$document = new DOMDocument('1.0', 'UTF-8');
$document->preserveWhiteSpace = true;
$document->formatOutput = true;

if (! $document->load($source)) {
    throw new RuntimeException('Diagram sumber tidak dapat dibaca.');
}

$mxfile = $document->documentElement;
$mxfile->setAttribute('pages', '23');
$xpath = new DOMXPath($document);

updateValue($xpath, 'sivmi-17', 'subtitle', 'Pesan teks/gambar/video, deduplikasi lead, unread badge, serta notifikasi mengikuti role dan cabang');
updateValue($xpath, 'sivmi-17', 'd17-contact', '<b>Calon siswa</b><br><br>Mengirim teks, gambar, atau video melalui WhatsApp');
updateValue($xpath, 'sivmi-17', 'd17-waha', '<b>WAHA GOWS</b><br><br>Session per admin/staff<br>Event message/ack<br>URL media + API key');
updateValue($xpath, 'sivmi-17', 'd17-webhook', '<b>Webhook + Queue</b><br><br>Verifikasi HMAC<br>Idempotensi event<br>Normalisasi JID/LID<br>Unduh media<br>Retry terukur');
updateValue($xpath, 'sivmi-17', 'd17-inbox', '<b>Inbox WhatsApp</b><br><br>Bubble teks/media<br>Preview gambar/video<br>Caption + unread badge');
updateValue($xpath, 'sivmi-17', 'd17-db', '<b>Penyimpanan</b><br><br>conversation + message<br>message_type / has_media<br>media_path privat<br>raw_payload metadata');

updateValue($xpath, 'sivmi-18', 'd18-test', '<b>4. Automated Test</b><br><br>74 test lulus<br>461 assertion<br>Media chat teruji');
updateValue($xpath, 'sivmi-18', 'd18-smoke', '<b>5. Smoke &amp; UAT</b><br><br>/up + login/dashboard<br>RBAC/cabang<br>WAHA chat/notifikasi<br>Gambar/video GOWS');
updateValue($xpath, 'sivmi-18', 'd18-ready', '<b>Keputusan rilis</b><br><br><font color="#10B981"><b>READY</b></font> jika build, migration, 74 test/461 assertion, health check, login, RBAC, data cabang, queue/scheduler, serta WAHA teks dan media valid.<br><br><font color="#F43F5E"><b>STOP</b></font> jika secret default, migration gagal, container tidak healthy, test regresi, media terbuka tanpa otorisasi, webhook tanpa HMAC, atau backup/rollback belum siap.');

$mediaArchitecture = createPage($document, 'sivmi-19', '19 - Arsitektur Chat Media', 'Arsitektur Chat Gambar dan Video', 'Integrasi Filament/Livewire, layanan aplikasi, WAHA GOWS, database, dan penyimpanan privat');
$mediaRoot = graphRoot($mediaArchitecture);
addBox($document, $mediaRoot, 'd19-user', 55, 200, 220, 180, 'Admin / Staff', 'Pilih file<br>Preview<br>Caption<br>Kirim', '#EAF4FB', '#1476B8');
addBox($document, $mediaRoot, 'd19-ui', 340, 150, 240, 280, 'Filament + Livewire', 'WithFileUploads<br>Progress upload<br>Validasi form<br>Bubble media responsif', '#EAF4FB', '#1476B8');
addBox($document, $mediaRoot, 'd19-service', 650, 130, 270, 320, 'WhatsAppChatService', 'Otorisasi percakapan<br>Deteksi MIME<br>sendImage/sendVideo<br>Audit aktivitas<br>Catat kegagalan', '#FFF7E8', '#F59E0B');
addBox($document, $mediaRoot, 'd19-waha', 1000, 150, 240, 280, 'WAHA GOWS', 'POST /api/sendImage<br>POST /api/sendVideo<br>Base64 + caption<br>ACK pesan', '#EAFBF5', '#10B981');
addBox($document, $mediaRoot, 'd19-wa', 1310, 200, 220, 180, 'WhatsApp', 'Penerima teks<br>Gambar<br>Video<br>Status pesan', '#EAFBF5', '#10B981');
addBox($document, $mediaRoot, 'd19-db', 360, 650, 330, 220, 'MySQL', 'whatsapp_messages<br>message_type<br>has_media<br>media_path<br>raw_payload', '#FAF5FF', '#A855F7');
addBox($document, $mediaRoot, 'd19-storage', 860, 650, 330, 220, 'Disk Privat Laravel', 'whatsapp-media/YYYY/MM<br>Nama UUID<br>Tidak memakai public storage<br>Akses via controller', '#F8FAFC', '#64748B');
addEdge($document, $mediaRoot, 'd19-e1', 'd19-user', 'd19-ui', 'upload');
addEdge($document, $mediaRoot, 'd19-e2', 'd19-ui', 'd19-service', 'file sementara');
addEdge($document, $mediaRoot, 'd19-e3', 'd19-service', 'd19-waha', 'REST API');
addEdge($document, $mediaRoot, 'd19-e4', 'd19-waha', 'd19-wa', 'WhatsApp');
addEdge($document, $mediaRoot, 'd19-e5', 'd19-service', 'd19-db', 'metadata/status');
addEdge($document, $mediaRoot, 'd19-e6', 'd19-service', 'd19-storage', 'file biner');
$mxfile->appendChild($mediaArchitecture);

$mediaFlow = createPage($document, 'sivmi-20', '20 - Alur Media Masuk dan Keluar', 'Flow Media WhatsApp Dua Arah', 'Alur outbound dari CRM dan inbound dari webhook dipisahkan agar retry serta kegagalan dapat ditangani');
$flowRoot = graphRoot($mediaFlow);
addLane($document, $flowRoot, 'd20-out-lane', 45, 155, 1535, 390, 'OUTBOUND · CRM → WhatsApp', '#EAF4FB');
addBox($document, $flowRoot, 'd20-o1', 95, 270, 210, 150, '1. Pilih lampiran', 'JPG/PNG/WebP<br>MP4/MOV<br>Maks. 10 MB', '#FFFFFF', '#1476B8');
addBox($document, $flowRoot, 'd20-o2', 365, 270, 210, 150, '2. Validasi', 'MIME<br>Ukuran<br>Caption ≤ 4.000', '#FFFFFF', '#1476B8');
addBox($document, $flowRoot, 'd20-o3', 635, 270, 210, 150, '3. Kirim WAHA', 'Image/video endpoint<br>Base64<br>Session Working', '#FFFFFF', '#10B981');
addBox($document, $flowRoot, 'd20-o4', 905, 270, 210, 150, '4. Simpan', 'File privat<br>Metadata DB<br>Audit aktivitas', '#FFFFFF', '#A855F7');
addBox($document, $flowRoot, 'd20-o5', 1175, 270, 290, 150, '5. Tampilkan bubble', 'Thumbnail atau video player<br>ACK: terkirim/diterima/dibaca', '#FFFFFF', '#F59E0B');
addEdge($document, $flowRoot, 'd20-oe1', 'd20-o1', 'd20-o2', '');
addEdge($document, $flowRoot, 'd20-oe2', 'd20-o2', 'd20-o3', 'valid');
addEdge($document, $flowRoot, 'd20-oe3', 'd20-o3', 'd20-o4', 'berhasil');
addEdge($document, $flowRoot, 'd20-oe4', 'd20-o4', 'd20-o5', '');
addLane($document, $flowRoot, 'd20-in-lane', 45, 585, 1535, 430, 'INBOUND · WhatsApp → CRM', '#EAFBF5');
addBox($document, $flowRoot, 'd20-i1', 95, 730, 210, 150, '1. Webhook', 'message/message.any<br>HMAC valid<br>Idempoten', '#FFFFFF', '#10B981');
addBox($document, $flowRoot, 'd20-i2', 365, 730, 210, 150, '2. Unduh media', 'media.url<br>X-Api-Key<br>WAHA internal', '#FFFFFF', '#10B981');
addBox($document, $flowRoot, 'd20-i3', 635, 730, 210, 150, '3. Validasi', 'Whitelist MIME<br>Nama aman<br>UUID path', '#FFFFFF', '#F59E0B');
addBox($document, $flowRoot, 'd20-i4', 905, 730, 210, 150, '4. Persistensi', 'Disk privat<br>Message + conversation<br>Unread count', '#FFFFFF', '#A855F7');
addBox($document, $flowRoot, 'd20-i5', 1175, 730, 290, 150, '5. Inbox + notifikasi', 'Lead matching<br>Scope PIC/cabang<br>Preview media', '#FFFFFF', '#1476B8');
addEdge($document, $flowRoot, 'd20-ie1', 'd20-i1', 'd20-i2', 'hasMedia');
addEdge($document, $flowRoot, 'd20-ie2', 'd20-i2', 'd20-i3', '');
addEdge($document, $flowRoot, 'd20-ie3', 'd20-i3', 'd20-i4', 'valid');
addEdge($document, $flowRoot, 'd20-ie4', 'd20-i4', 'd20-i5', '');
$mxfile->appendChild($mediaFlow);

$mediaSecurity = createPage($document, 'sivmi-21', '21 - Keamanan Media WhatsApp', 'Kontrol Keamanan Media Chat', 'Defense in depth untuk upload, penyimpanan, akses, webhook, dan observabilitas');
$securityRoot = graphRoot($mediaSecurity);
addBox($document, $securityRoot, 'd21-input', 80, 190, 280, 230, '1. Validasi Input', 'Whitelist JPG/PNG/WebP/MP4/MOV<br>Maksimum 10 MB<br>Caption dibatasi<br>MIME server-side', '#EAF4FB', '#1476B8');
addBox($document, $securityRoot, 'd21-storage', 460, 190, 280, 230, '2. Penyimpanan Privat', 'Disk local privat<br>Folder per tahun/bulan<br>Nama UUID<br>Tidak ada public symlink', '#EAFBF5', '#10B981');
addBox($document, $securityRoot, 'd21-access', 840, 190, 280, 230, '3. Otorisasi Akses', 'Route wajib auth<br>Conversation visibleTo(user)<br>Staff hanya akun sendiri<br>Admin dibatasi cabang', '#FAF5FF', '#A855F7');
addBox($document, $securityRoot, 'd21-response', 1220, 190, 280, 230, '4. Respons Aman', 'Content-Type terkontrol<br>inline disposition<br>nosniff<br>private cache', '#FFF7E8', '#F59E0B');
addBox($document, $securityRoot, 'd21-webhook', 270, 610, 350, 240, '5. Keamanan Integrasi', 'X-Api-Key ke WAHA<br>Webhook HMAC SHA-512<br>URL diubah ke origin WAHA internal<br>Retry queue terukur', '#FFF1F2', '#F43F5E');
addBox($document, $securityRoot, 'd21-audit', 930, 610, 350, 240, '6. Audit dan Kegagalan', 'ActivityLog pengiriman media<br>Status Failed tersimpan<br>Notifikasi kegagalan<br>Media yatim dibersihkan saat error', '#F8FAFC', '#64748B');
addEdge($document, $securityRoot, 'd21-e1', 'd21-input', 'd21-storage', 'valid');
addEdge($document, $securityRoot, 'd21-e2', 'd21-storage', 'd21-access', 'request');
addEdge($document, $securityRoot, 'd21-e3', 'd21-access', 'd21-response', 'authorized');
addEdge($document, $securityRoot, 'd21-e4', 'd21-webhook', 'd21-storage', 'media inbound');
addEdge($document, $securityRoot, 'd21-e5', 'd21-access', 'd21-audit', 'event/error');
addNote($document, $securityRoot, 'd21-note', 260, 950, 1030, 105, '<b>Prinsip:</b> metadata percakapan boleh terlihat hanya jika file medianya juga lolos scope role, cabang, dan kepemilikan akun WhatsApp.');
$mxfile->appendChild($mediaSecurity);

$schoolFlow = createPage($document, 'sivmi-22', '22 - Input Sekolah dan Alumni', 'Alur Input Sekolah dari Form Lead', 'Pengguna dapat memilih sekolah aktif atau menambah sekolah baru dengan format nama yang terkontrol');
$schoolRoot = graphRoot($schoolFlow);
addBox($document, $schoolRoot, 'd22-start', 65, 220, 230, 170, 'Form Lead', 'Cari sekolah<br>Searchable + preload', '#EAF4FB', '#1476B8');
addBox($document, $schoolRoot, 'd22-exists', 365, 220, 230, 170, 'Sekolah tersedia?', 'Ya → pilih record<br>Tidak → tombol +', '#FFF7E8', '#F59E0B');
addBox($document, $schoolRoot, 'd22-prefix', 665, 130, 280, 290, 'Pilih Format', 'SMAN · Negeri<br>SMAS · Swasta<br>SMKN · Negeri<br>SMKS · Swasta<br>MAN · Negeri<br>Alumni/Gapyear', '#FAF5FF', '#A855F7');
addBox($document, $schoolRoot, 'd22-school', 1040, 125, 270, 300, 'Sekolah Formal', 'Nama/nomor sekolah wajib<br>Awalan otomatis<br>NPSN opsional 8 digit<br>Kota wajib<br>Provinsi opsional', '#EAFBF5', '#10B981');
addBox($document, $schoolRoot, 'd22-alumni', 680, 610, 250, 210, 'Alumni/Gapyear', 'Nama sekolah tidak diminta<br>NPSN disembunyikan<br>Status: Lainnya<br>Kota tetap dicatat', '#EAF4FB', '#1476B8');
addBox($document, $schoolRoot, 'd22-normalize', 1040, 590, 270, 250, 'Normalisasi', 'Uppercase<br>Spasi dirapikan<br>Prefix tidak diduplikasi<br>Status sekolah otomatis', '#F8FAFC', '#64748B');
addBox($document, $schoolRoot, 'd22-save', 1380, 610, 250, 210, 'Simpan dan Hubungkan', 'CreateSchoolFromLead<br>Record sekolah aktif<br>ID langsung dipilih pada lead', '#EAFBF5', '#10B981');
addEdge($document, $schoolRoot, 'd22-e1', 'd22-start', 'd22-exists', '');
addEdge($document, $schoolRoot, 'd22-e2', 'd22-exists', 'd22-prefix', 'tambah baru');
addEdge($document, $schoolRoot, 'd22-e3', 'd22-prefix', 'd22-school', 'formal');
addEdge($document, $schoolRoot, 'd22-e4', 'd22-prefix', 'd22-alumni', 'alumni');
addEdge($document, $schoolRoot, 'd22-e5', 'd22-school', 'd22-normalize', '');
addEdge($document, $schoolRoot, 'd22-e6', 'd22-alumni', 'd22-normalize', '');
addEdge($document, $schoolRoot, 'd22-e7', 'd22-normalize', 'd22-save', 'valid');
$mxfile->appendChild($schoolFlow);

$classification = createPage($document, 'sivmi-23', '23 - Kamus Klasifikasi Utama', 'Kamus Data Operasional Terbaru', 'Nilai baku lead, program bimbel, sumber lead, serta kluster sekolah');
$classificationRoot = graphRoot($classification);
addBox($document, $classificationRoot, 'd23-status', 75, 190, 310, 300, 'Status Lead', 'Cold<br>Warm<br>Hot<br>Daftar<br>Tidak Berminat', '#EAF4FB', '#1476B8');
addBox($document, $classificationRoot, 'd23-source', 455, 190, 310, 300, 'Sumber Lead', 'Iklan<br>Instagram<br>Kunjungan<br>Saudara<br>Facebook<br>Tiktok', '#FFF7E8', '#F59E0B');
addBox($document, $classificationRoot, 'd23-program', 835, 160, 340, 360, 'Nama Program 3 Komponen', '<b>Kategori + Kelas program + Metode belajar</b><br><br>Contoh:<br>SR + GOLD + ONLINE<br>= SR GOLD ONLINE<br><br>Tipe, jenjang, dan tingkat kelas tetap atribut terpisah.', '#FAF5FF', '#A855F7');
addBox($document, $classificationRoot, 'd23-grade', 1245, 190, 310, 300, 'Kluster Sekolah', 'Grade A · pemasok terbanyak<br>Grade B · jarang + favorit<br>Grade C · belum pernah + favorit<br>Grade D · definisi bisnis menyusul<br>Grade E · definisi bisnis menyusul', '#EAFBF5', '#10B981');
addNote($document, $classificationRoot, 'd23-note', 180, 680, 1280, 220, '<b>Aturan konsistensi</b><br><br>Enum dan formula di atas menjadi acuan form, import/export CSV, filter, badge, laporan, KPI dashboard, seeder, test, dokumentasi, serta integrasi WhatsApp. Perubahan nilai wajib disertai migration data dan uji regresi.');
$mxfile->appendChild($classification);

$document->save($output);
echo $output.PHP_EOL;

function updateValue(DOMXPath $xpath, string $diagramId, string $cellId, string $value): void
{
    $cell = $xpath->query("//diagram[@id='{$diagramId}']//mxCell[@id='{$cellId}']")->item(0);
    if (! $cell instanceof DOMElement) {
        throw new RuntimeException("Cell {$diagramId}/{$cellId} tidak ditemukan.");
    }
    $cell->setAttribute('value', $value);
}

function createPage(DOMDocument $document, string $id, string $name, string $title, string $subtitle): DOMElement
{
    $diagram = $document->createElement('diagram');
    $diagram->setAttribute('id', $id);
    $diagram->setAttribute('name', $name);
    $model = $document->createElement('mxGraphModel');
    foreach (['dx' => '1895', 'dy' => '1138', 'grid' => '0', 'gridSize' => '10', 'guides' => '1', 'tooltips' => '1', 'connect' => '1', 'arrows' => '1', 'fold' => '1', 'page' => '1', 'pageScale' => '1', 'pageWidth' => '1654', 'pageHeight' => '1169', 'math' => '0', 'shadow' => '0'] as $key => $value) {
        $model->setAttribute($key, $value);
    }
    $root = $document->createElement('root');
    $root->appendChild(cell($document, '0'));
    $root->appendChild(cell($document, '1', ['parent' => '0']));
    $root->appendChild(vertex($document, 'title', 40, 25, 1500, 50, $title, 'text;html=1;strokeColor=none;fillColor=none;fontSize=30;fontStyle=1;fontColor=#14357A;align=center;verticalAlign=middle;'));
    $root->appendChild(vertex($document, 'subtitle', 40, 87, 1500, 45, $subtitle, 'text;html=1;strokeColor=none;fillColor=none;fontSize=20;fontColor=#64748B;align=center;verticalAlign=middle;whiteSpace=wrap;'));
    $model->appendChild($root);
    $diagram->appendChild($model);

    return $diagram;
}

function graphRoot(DOMElement $diagram): DOMElement
{
    return $diagram->getElementsByTagName('root')->item(0);
}

function addBox(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $title, string $body, string $fill, string $stroke): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, '<b>'.$title.'</b><br><br>'.$body, "rounded=1;whiteSpace=wrap;html=1;arcSize=14;fillColor={$fill};strokeColor={$stroke};strokeWidth=2;fontSize=20;fontColor=#334155;spacing=12;align=center;verticalAlign=middle;"));
}

function addLane(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $title, string $fill): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $title, "swimlane;html=1;horizontal=1;startSize=48;rounded=1;arcSize=8;fillColor={$fill};swimlaneFillColor=#FFFFFF;strokeColor=#CBD5E1;strokeWidth=2;fontSize=20;fontStyle=1;fontColor=#0F172A;align=left;spacingLeft=18;"));
}

function addNote(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $value): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $value, 'rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#F8FAFC;strokeColor=#CBD5E1;strokeWidth=2;fontSize=20;fontColor=#334155;spacing=14;align=center;verticalAlign=middle;'));
}

function addEdge(DOMDocument $document, DOMElement $root, string $id, string $source, string $target, string $label): void
{
    $edge = cell($document, $id, ['value' => $label, 'edge' => '1', 'parent' => '1', 'source' => $source, 'target' => $target, 'style' => 'edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;endArrow=block;endFill=1;strokeWidth=2;strokeColor=#64748B;fontSize=16;labelBackgroundColor=#FFFFFF;']);
    $geometry = $document->createElement('mxGeometry');
    $geometry->setAttribute('relative', '1');
    $geometry->setAttribute('as', 'geometry');
    $edge->appendChild($geometry);
    $root->appendChild($edge);
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
        $cell->setAttribute($key, $value);
    }

    return $cell;
}
