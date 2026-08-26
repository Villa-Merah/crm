<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Diagram Gabungan SIVMI CRM v1.3.drawio';
$output = $root . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'Diagram Gabungan SIVMI CRM v1.4.drawio';

$document = new DOMDocument('1.0', 'UTF-8');
$document->preserveWhiteSpace = true;
$document->formatOutput = true;

if (! $document->load($source)) {
    throw new RuntimeException('Diagram sumber tidak dapat dibaca.');
}

$mxfile = $document->documentElement;
$mxfile->setAttribute('pages', '18');
$xpath = new DOMXPath($document);

foreach ($xpath->query("//diagram[@id='sivmi-11']//mxCell") as $cell) {
    $value = $cell->getAttribute('value');

    if (str_contains($value, 'Konfigurasi hybrid:')) {
        $cell->setAttribute('value', 'Konfigurasi utama full Docker: Nginx, Laravel, MySQL, Redis, queue, scheduler, dan WAHA GOWS berada pada satu jaringan Compose');
    }
}

$oldDeployment = $xpath->query("//diagram[@id='sivmi-16']")->item(0);
if ($oldDeployment instanceof DOMElement) {
    $replacement = createPage($document, 'sivmi-16', '16 - Deployment Full Docker', 'Topologi Development Full Docker', 'Baseline lokal utama tanpa service Laragon; akses host hanya melalui port yang dipublikasikan');
    $rootNode = graphRoot($replacement);

    addBox($document, $rootNode, 'd16-browser', 55, 205, 250, 190, 'Browser Windows', 'Akses CRM<br>localhost:8000/admin/login', '#EAF4FB', '#1476B8');
    addBox($document, $rootNode, 'd16-nginx', 390, 205, 250, 190, 'web / Nginx', 'Port host 8000 → container 80<br>FastCGI ke app:9000', '#EAF4FB', '#1476B8');
    addBox($document, $rootNode, 'd16-app', 725, 155, 280, 290, 'app / Laravel', 'PHP-FPM<br>Filament + Livewire<br>WAHA_BASE_URL=http://waha:3000', '#EAFBF5', '#10B981');
    addBox($document, $rootNode, 'd16-data', 1090, 115, 290, 180, 'mysql + redis', 'MySQL persisten<br>Redis cache/session/queue', '#FAF5FF', '#A855F7');
    addBox($document, $rootNode, 'd16-workers', 1090, 350, 290, 190, 'queue + scheduler', 'ProcessWahaWebhook<br>Reminder dan notifikasi<br>restart: unless-stopped', '#FFF7E8', '#F59E0B');
    addBox($document, $rootNode, 'd16-waha', 725, 560, 280, 210, 'waha / GOWS', 'Port host 3000 → container 3000<br>Webhook: http://web/api/webhooks/waha<br>Volume session persisten', '#EAFBF5', '#10B981');
    addBox($document, $rootNode, 'd16-host', 1090, 610, 290, 160, 'Host Docker Desktop', 'Source code bind mount<br>docker/.env<br>Linux containers', '#FFF1F2', '#F43F5E');

    addEdge($document, $rootNode, 'd16-e1', 'd16-browser', 'd16-nginx', '');
    addEdge($document, $rootNode, 'd16-e2', 'd16-nginx', 'd16-app', '');
    addEdge($document, $rootNode, 'd16-e3', 'd16-app', 'd16-data', '');
    addEdge($document, $rootNode, 'd16-e4', 'd16-app', 'd16-workers', '');
    addEdge($document, $rootNode, 'd16-e5', 'd16-app', 'd16-waha', 'REST API + X-Api-Key');
    addEdge($document, $rootNode, 'd16-e6', 'd16-waha', 'd16-nginx', 'POST webhook + HMAC');
    addEdge($document, $rootNode, 'd16-e7', 'd16-host', 'd16-waha', 'volume + config');

    addNote($document, $rootNode, 'd16-note', 160, 880, 1280, 150, '<b>Operasional:</b> jalankan .\\docker\\start-full.ps1, cek .\\docker\\status-full.ps1, uji .\\docker\\test-full.ps1, dan hentikan dengan .\\docker\\stop-full.ps1. Database Docker terpisah dari database Laragon.');
    $mxfile->replaceChild($replacement, $oldDeployment);
}

$chatPage = createPage($document, 'sivmi-17', '17 - Chat dan Notifikasi Sistem', 'Alur Chat WhatsApp dan Notifikasi SIVMI', 'Pesan inbound/outbound, deduplikasi lead, unread badge, dan notifikasi mengikuti role serta cabang');
$chatRoot = graphRoot($chatPage);
addBox($document, $chatRoot, 'd17-contact', 50, 180, 230, 180, 'Calon siswa', 'Mengirim pesan WhatsApp', '#EAF4FB', '#1476B8');
addBox($document, $chatRoot, 'd17-waha', 350, 180, 230, 180, 'WAHA GOWS', 'Session per admin/staff<br>Event message/ack', '#EAFBF5', '#10B981');
addBox($document, $chatRoot, 'd17-webhook', 650, 120, 250, 300, 'Webhook + Queue', 'Verifikasi HMAC<br>Idempotensi event<br>Normalisasi JID/LID/SenderAlt<br>Retry terukur', '#FFF7E8', '#F59E0B');
addBox($document, $chatRoot, 'd17-match', 970, 120, 250, 300, 'Pencocokan Lead', 'Nomor dinormalisasi<br>Cari lead cabang/PIC<br>Tandai WA masuk<br>Buat lead baru bila unik', '#FAF5FF', '#A855F7');
addBox($document, $chatRoot, 'd17-inbox', 1290, 180, 240, 180, 'Inbox WhatsApp', 'Percakapan + pesan<br>Unread badge<br>Balas chat', '#EAF4FB', '#1476B8');
addBox($document, $chatRoot, 'd17-db', 520, 600, 360, 230, 'Penyimpanan', 'user_whatsapp_accounts<br>waha_webhook_events<br>whatsapp_conversations<br>whatsapp_messages', '#F8FAFC', '#64748B');
addBox($document, $chatRoot, 'd17-notif', 1010, 600, 360, 230, 'Notifikasi Sistem', 'Database notification<br>Ikon lonceng Filament<br>Status dibaca<br>Scope role dan cabang', '#FFF1F2', '#F43F5E');
addEdge($document, $chatRoot, 'd17-e1', 'd17-contact', 'd17-waha', '');
addEdge($document, $chatRoot, 'd17-e2', 'd17-waha', 'd17-webhook', '');
addEdge($document, $chatRoot, 'd17-e3', 'd17-webhook', 'd17-match', '');
addEdge($document, $chatRoot, 'd17-e4', 'd17-match', 'd17-inbox', '');
addEdge($document, $chatRoot, 'd17-e5', 'd17-webhook', 'd17-db', 'simpan event/pesan');
addEdge($document, $chatRoot, 'd17-e6', 'd17-match', 'd17-notif', 'lead/pesan baru');
addEdge($document, $chatRoot, 'd17-e7', 'd17-inbox', 'd17-waha', '');
$mxfile->appendChild($chatPage);

$qaPage = createPage($document, 'sivmi-18', '18 - Quality Gate dan Kesiapan Deploy', 'Quality Gate Full Docker', 'Pipeline verifikasi sebelum rilis lokal, staging, atau VPS');
$qaRoot = graphRoot($qaPage);
addBox($document, $qaRoot, 'd18-source', 70, 220, 240, 190, '1. Source & Config', 'docker/.env tervalidasi<br>Secret tidak masuk repository<br>Compose config valid', '#EAF4FB', '#1476B8');
addBox($document, $qaRoot, 'd18-build', 380, 220, 240, 190, '2. Build', 'Image PHP-FPM<br>Vite/Tailwind production<br>Nginx config', '#EAFBF5', '#10B981');
addBox($document, $qaRoot, 'd18-migrate', 690, 220, 240, 190, '3. Database', 'MySQL healthy<br>Migration berhasil<br>Seeder hanya lokal', '#FFF7E8', '#F59E0B');
addBox($document, $qaRoot, 'd18-test', 1000, 220, 240, 190, '4. Automated Test', '72 test lulus<br>448 assertion<br>SQLite in-memory', '#FAF5FF', '#A855F7');
addBox($document, $qaRoot, 'd18-smoke', 1310, 220, 240, 190, '5. Smoke & UAT', '/up<br>Login/dashboard<br>RBAC/cabang<br>WAHA chat/notifikasi', '#FFF1F2', '#F43F5E');
addEdge($document, $qaRoot, 'd18-e1', 'd18-source', 'd18-build', 'lulus');
addEdge($document, $qaRoot, 'd18-e2', 'd18-build', 'd18-migrate', 'lulus');
addEdge($document, $qaRoot, 'd18-e3', 'd18-migrate', 'd18-test', 'lulus');
addEdge($document, $qaRoot, 'd18-e4', 'd18-test', 'd18-smoke', 'lulus');
addNote($document, $qaRoot, 'd18-ready', 250, 620, 1180, 230, '<b>Keputusan rilis</b><br><br><font color="#10B981"><b>READY</b></font> jika build, migration, 72 test/448 assertion, health check, login, RBAC, data cabang, queue/scheduler, dan WAHA semuanya valid.<br><br><font color="#F43F5E"><b>STOP</b></font> jika ada secret default, migration gagal, container tidak healthy, test regresi, webhook tanpa HMAC, atau backup/rollback belum siap.');
$mxfile->appendChild($qaPage);

$document->save($output);
echo $output . PHP_EOL;

function createPage(DOMDocument $document, string $id, string $name, string $title, string $subtitle): DOMElement
{
    $diagram = $document->createElement('diagram');
    $diagram->setAttribute('id', $id);
    $diagram->setAttribute('name', $name);

    $model = $document->createElement('mxGraphModel');
    foreach ([
        'dx' => '1895', 'dy' => '1138', 'grid' => '0', 'gridSize' => '10', 'guides' => '1',
        'tooltips' => '1', 'connect' => '1', 'arrows' => '1', 'fold' => '1', 'page' => '1',
        'pageScale' => '1', 'pageWidth' => '1654', 'pageHeight' => '1169', 'math' => '0', 'shadow' => '0',
    ] as $key => $value) {
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
    $value = '<b>' . $title . '</b><br><br>' . $body;
    $style = "rounded=1;whiteSpace=wrap;html=1;arcSize=14;fillColor={$fill};strokeColor={$stroke};strokeWidth=2;fontSize=20;fontColor=#334155;spacing=12;align=center;verticalAlign=middle;";
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $value, $style));
}

function addNote(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $value): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $value, 'rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#F8FAFC;strokeColor=#CBD5E1;strokeWidth=2;fontSize=20;fontColor=#334155;spacing=14;align=center;verticalAlign=middle;'));
}

function addEdge(DOMDocument $document, DOMElement $root, string $id, string $source, string $target, string $label): void
{
    $edge = cell($document, $id, [
        'value' => $label,
        'edge' => '1',
        'parent' => '1',
        'source' => $source,
        'target' => $target,
        'style' => 'edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;endArrow=block;endFill=1;strokeWidth=2;strokeColor=#64748B;fontSize=16;labelBackgroundColor=#FFFFFF;',
    ]);
    $geometry = $document->createElement('mxGeometry');
    $geometry->setAttribute('relative', '1');
    $geometry->setAttribute('as', 'geometry');
    $edge->appendChild($geometry);
    $root->appendChild($edge);
}

function vertex(DOMDocument $document, string $id, int $x, int $y, int $width, int $height, string $value, string $style): DOMElement
{
    $node = cell($document, $id, [
        'value' => $value,
        'style' => $style,
        'vertex' => '1',
        'parent' => '1',
    ]);
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
