<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$output = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'Diagram API Publik SIVMI CRM v1.0.drawio';

$document = new DOMDocument('1.0', 'UTF-8');
$document->formatOutput = true;

$mxfile = $document->createElement('mxfile');
$mxfile->setAttribute('host', 'app.diagrams.net');
$mxfile->setAttribute('agent', 'Codex');
$mxfile->setAttribute('version', '26.0.9');
$mxfile->setAttribute('pages', '4');
$document->appendChild($mxfile);

$context = createPage($document, 'public-api-01', '01 - Konteks API Publik', 'Konteks API Publik SIVMI CRM', 'Target architecture · API publik /api/v1 dipisahkan dari panel Filament dan webhook internal WAHA');
$r = graphRoot($context);
addLane($document, $r, 'p1-consumers', 45, 155, 330, 850, 'CONSUMER', '#EAF4FB');
addBox($document, $r, 'p1-mobile', 85, 250, 250, 150, 'Aplikasi Mobile', 'Pengguna resmi SIVMI<br>Bearer token', '#FFFFFF', '#1476B8');
addBox($document, $r, 'p1-partner', 85, 485, 250, 170, 'Sistem Mitra', 'Integrasi terdaftar<br>OAuth2 scoped token', '#FFFFFF', '#1476B8');
addBox($document, $r, 'p1-bi', 85, 740, 250, 150, 'BI / Reporting', 'Read-only scope<br>Data teragregasi', '#FFFFFF', '#1476B8');

addLane($document, $r, 'p1-public', 430, 155, 560, 850, 'PUBLIC API BOUNDARY · HTTPS', '#EAFBF5');
addBox($document, $r, 'p1-gateway', 500, 235, 420, 145, 'API Gateway / Laravel', '/api/v1<br>TLS · CORS allowlist · Rate limit<br>Correlation ID', '#FFFFFF', '#10B981');
addBox($document, $r, 'p1-auth', 500, 455, 190, 180, 'Autentikasi', 'Sanctum<br>atau OAuth2<br>Token scope', '#FFFFFF', '#A855F7');
addBox($document, $r, 'p1-policy', 730, 455, 190, 180, 'Otorisasi', 'RBAC<br>Scope cabang<br>Policy resource', '#FFFFFF', '#A855F7');
addBox($document, $r, 'p1-contract', 500, 720, 420, 185, 'Kontrak API v1', 'FormRequest · API Resource<br>Pagination · Filter allowlist<br>Idempotency-Key · JSON errors', '#FFFFFF', '#10B981');

addLane($document, $r, 'p1-app', 1045, 155, 560, 850, 'SIVMI APPLICATION & DATA', '#FFF7E8');
addBox($document, $r, 'p1-services', 1110, 235, 430, 175, 'Application Services', 'Lead · Follow-up · Pendaftaran<br>Pembayaran · Laporan · Master data', '#FFFFFF', '#F59E0B');
addBox($document, $r, 'p1-db', 1110, 500, 200, 180, 'MySQL', 'Transaksi<br>Indeks<br>Scope cabang', '#FFFFFF', '#075985');
addBox($document, $r, 'p1-queue', 1340, 500, 200, 180, 'Queue / Scheduler', 'Job async<br>Retry<br>Reminder', '#FFFFFF', '#075985');
addBox($document, $r, 'p1-waha', 1110, 770, 430, 145, 'Integrasi Internal WAHA', 'REST client outbound + webhook HMAC<br>Bukan bagian endpoint publik /api/v1', '#FFF1F2', '#F43F5E');

addEdge($document, $r, 'p1-e1', 'p1-mobile', 'p1-gateway', 'HTTPS + JSON');
addEdge($document, $r, 'p1-e2', 'p1-partner', 'p1-gateway', 'HTTPS + JSON');
addEdge($document, $r, 'p1-e3', 'p1-bi', 'p1-gateway', 'GET only');
addEdge($document, $r, 'p1-e4', 'p1-gateway', 'p1-auth', 'token');
addEdge($document, $r, 'p1-e5', 'p1-auth', 'p1-policy', 'identity');
addEdge($document, $r, 'p1-e6', 'p1-policy', 'p1-contract', 'authorized');
addEdge($document, $r, 'p1-e7', 'p1-contract', 'p1-services', 'DTO / command');
addEdge($document, $r, 'p1-e8', 'p1-services', 'p1-db', 'Eloquent');
addEdge($document, $r, 'p1-e9', 'p1-services', 'p1-queue', 'async event');
addEdge($document, $r, 'p1-e10', 'p1-services', 'p1-waha', 'internal only');
addNote($document, $r, 'p1-note', 440, 1035, 1150, 75, '<b>Status:</b> diagram ini adalah rancangan API publik. Source saat ini baru memiliki webhook WAHA pada routes/api.php; CRUD operasional masih melalui Filament/Livewire.');
$mxfile->appendChild($context);

$resources = createPage($document, 'public-api-02', '02 - Resource dan Endpoint v1', 'Peta Resource API Publik /api/v1', 'Endpoint kandidat · konsisten, versioned, paginated, dan selalu mengikuti policy serta scope cabang');
$r = graphRoot($resources);
addBox($document, $r, 'p2-root', 615, 155, 420, 125, 'API v1', 'https://api.sivmi.example/api/v1', '#EAF4FB', '#1476B8');
addResource($document, $r, 'p2-leads', 55, 355, 350, 275, 'Leads', [
    'GET /leads', 'POST /leads', 'GET /leads/{lead}', 'PATCH /leads/{lead}', 'POST /leads/{lead}/follow-ups',
], '#1476B8');
addResource($document, $r, 'p2-registration', 445, 355, 350, 275, 'Pendaftaran', [
    'GET /registrations', 'POST /registrations', 'GET /registrations/{id}', 'PATCH /registrations/{id}',
], '#10B981');
addResource($document, $r, 'p2-payment', 835, 355, 350, 275, 'Pembayaran', [
    'GET /payments', 'POST /payments', 'GET /payments/{id}', 'PATCH /payments/{id}',
], '#F59E0B');
addResource($document, $r, 'p2-report', 1225, 355, 350, 275, 'Laporan & KPI', [
    'GET /reports/summary', 'GET /reports/leads', 'GET /reports/revenue', 'GET /reports/programs',
], '#A855F7');
addResource($document, $r, 'p2-master', 250, 745, 480, 220, 'Master Data', [
    'GET /branches', 'GET /schools', 'GET /programs', 'GET /users (terbatas)',
], '#075985');
addResource($document, $r, 'p2-webhook', 900, 745, 480, 220, 'Webhook Terpisah', [
    'POST /webhooks/waha', 'HMAC SHA-512', '202 Accepted', 'Idempotensi payload_hash',
], '#F43F5E');
foreach (['p2-leads', 'p2-registration', 'p2-payment', 'p2-report', 'p2-master'] as $index => $target) {
    addEdge($document, $r, 'p2-e'.($index + 1), 'p2-root', $target, 'JSON');
}
addNote($document, $r, 'p2-note', 430, 1020, 790, 80, '<b>Envelope:</b> data + meta + links. Error: error.code, error.message, error.fields, request_id. Semua list memakai pagination dan filter allowlist.');
$mxfile->appendChild($resources);

$flow = createPage($document, 'public-api-03', '03 - Alur Request API Publik', 'Alur Request, Otorisasi, dan Respons', 'Happy path dan cabang kegagalan yang harus konsisten pada seluruh endpoint publik');
$r = graphRoot($flow);
addBox($document, $r, 'p3-request', 55, 230, 220, 150, '1. Request', 'HTTPS<br>Bearer token<br>Accept: application/json', '#EAF4FB', '#1476B8');
addBox($document, $r, 'p3-edge', 335, 230, 220, 150, '2. Edge Controls', 'TLS<br>CORS<br>Rate limit<br>Request ID', '#EAF4FB', '#1476B8');
addDecision($document, $r, 'p3-auth', 635, 205, 220, 200, '3. Token valid?');
addDecision($document, $r, 'p3-policy', 935, 205, 220, 200, '4. Role, scope, dan cabang valid?');
addDecision($document, $r, 'p3-valid', 1235, 205, 220, 200, '5. Payload valid?');
addBox($document, $r, 'p3-service', 1235, 520, 220, 160, '6. Service', 'Business rules<br>DB transaction<br>Domain event', '#EAFBF5', '#10B981');
addBox($document, $r, 'p3-resource', 935, 520, 220, 160, '7. API Resource', 'DTO aman<br>Field allowlist<br>Pagination/meta', '#EAFBF5', '#10B981');
addBox($document, $r, 'p3-response', 635, 520, 220, 160, '8. Response', '200 / 201 / 202<br>JSON<br>request_id', '#EAFBF5', '#10B981');
addBox($document, $r, 'p3-log', 335, 520, 220, 160, '9. Observability', 'Structured log<br>Metric latency/error<br>Audit event', '#F8FAFC', '#64748B');
addBox($document, $r, 'p3-client', 55, 520, 220, 160, '10. Consumer', 'Gunakan data/meta<br>Retry hanya aman<br>Hormati Retry-After', '#F8FAFC', '#64748B');
addEdge($document, $r, 'p3-e1', 'p3-request', 'p3-edge', '');
addEdge($document, $r, 'p3-e2', 'p3-edge', 'p3-auth', '');
addEdge($document, $r, 'p3-e3', 'p3-auth', 'p3-policy', 'ya');
addEdge($document, $r, 'p3-e4', 'p3-policy', 'p3-valid', 'ya');
addEdge($document, $r, 'p3-e5', 'p3-valid', 'p3-service', 'ya');
addEdge($document, $r, 'p3-e6', 'p3-service', 'p3-resource', 'commit');
addEdge($document, $r, 'p3-e7', 'p3-resource', 'p3-response', '');
addEdge($document, $r, 'p3-e8', 'p3-response', 'p3-log', 'telemetry');
addEdge($document, $r, 'p3-e9', 'p3-log', 'p3-client', '');
addError($document, $r, 'p3-401', 620, 825, 250, 130, '401', 'Token hilang/tidak valid');
addError($document, $r, 'p3-403', 900, 825, 250, 130, '403', 'Role/scope/cabang ditolak');
addError($document, $r, 'p3-422', 1180, 825, 250, 130, '422', 'Validasi payload gagal');
addEdge($document, $r, 'p3-er1', 'p3-auth', 'p3-401', 'tidak');
addEdge($document, $r, 'p3-er2', 'p3-policy', 'p3-403', 'tidak');
addEdge($document, $r, 'p3-er3', 'p3-valid', 'p3-422', 'tidak');
addNote($document, $r, 'p3-note', 260, 1020, 1130, 80, '<b>Error tambahan:</b> 409 konflik/idempotensi, 429 rate limit, 500 error internal, 503 dependency tidak tersedia. Detail sensitif tidak dikirim ke consumer.');
$mxfile->appendChild($flow);

$security = createPage($document, 'public-api-04', '04 - Keamanan dan Tata Kelola', 'Keamanan, Idempotensi, dan Tata Kelola API Publik', 'Defense in depth untuk consumer, kontrak, data cabang, operasi mutasi, dan integrasi internal');
$r = graphRoot($security);
addBox($document, $r, 'p4-identity', 70, 190, 310, 250, 'Identitas & Token', 'Sanctum untuk first-party<br>OAuth2 untuk mitra<br>Scope minimum<br>Expiry + rotasi + revoke', '#EAF4FB', '#1476B8');
addBox($document, $r, 'p4-authz', 455, 190, 310, 250, 'RBAC & Cabang', 'Superadmin · Direksi<br>Admin Cabang · Staff<br>Policy per resource<br>Scope cabang server-side', '#FAF5FF', '#A855F7');
addBox($document, $r, 'p4-data', 840, 190, 310, 250, 'Proteksi Data', 'Field allowlist<br>Masking data sensitif<br>Pagination wajib<br>Filter/sort allowlist', '#EAFBF5', '#10B981');
addBox($document, $r, 'p4-mutation', 1225, 190, 310, 250, 'Mutasi Aman', 'Idempotency-Key<br>Unique constraint<br>Optimistic locking<br>DB transaction', '#FFF7E8', '#F59E0B');
addBox($document, $r, 'p4-contract', 70, 600, 310, 250, 'Kontrak & Versi', '/api/v1<br>OpenAPI 3.1<br>Deprecation policy<br>Schema compatibility test', '#F8FAFC', '#64748B');
addBox($document, $r, 'p4-ops', 455, 600, 310, 250, 'Operasional', 'Rate limit per token<br>Structured logs<br>Metrics + alert<br>Health check', '#F8FAFC', '#64748B');
addBox($document, $r, 'p4-secret', 840, 600, 310, 250, 'Secret Management', 'Tidak di repository<br>Secret manager/env<br>Jangan log token/body<br>Audit rotasi', '#FFF1F2', '#F43F5E');
addBox($document, $r, 'p4-internal', 1225, 600, 310, 250, 'Boundary Internal', 'WAHA API key internal<br>Webhook HMAC SHA-512<br>Queue retry terukur<br>Tidak diekspos sebagai API v1', '#FFF1F2', '#F43F5E');
addNote($document, $r, 'p4-note', 170, 980, 1290, 100, '<b>Gate rilis API publik:</b> OpenAPI disetujui, policy dan test lintas cabang lulus, rate limit aktif, secret non-default, audit log tersedia, serta backup/rollback telah diuji.');
$mxfile->appendChild($security);

$document->save($output);
echo $output.PHP_EOL;

function createPage(DOMDocument $document, string $id, string $name, string $title, string $subtitle): DOMElement
{
    $diagram = $document->createElement('diagram');
    $diagram->setAttribute('id', $id);
    $diagram->setAttribute('name', $name);
    $model = $document->createElement('mxGraphModel');
    foreach (['dx' => '1600', 'dy' => '900', 'grid' => '0', 'gridSize' => '10', 'guides' => '1', 'tooltips' => '1', 'connect' => '1', 'arrows' => '1', 'fold' => '1', 'page' => '1', 'pageScale' => '1', 'pageWidth' => '1654', 'pageHeight' => '1169', 'math' => '0', 'shadow' => '0'] as $key => $value) {
        $model->setAttribute($key, $value);
    }
    $root = $document->createElement('root');
    $root->appendChild(cell($document, '0'));
    $root->appendChild(cell($document, '1', ['parent' => '0']));
    $root->appendChild(vertex($document, $id.'-title', 40, 25, 1570, 50, $title, 'text;html=1;strokeColor=none;fillColor=none;fontSize=30;fontStyle=1;fontColor=#075985;align=center;verticalAlign=middle;'));
    $root->appendChild(vertex($document, $id.'-subtitle', 40, 82, 1570, 50, $subtitle, 'text;html=1;strokeColor=none;fillColor=none;fontSize=18;fontColor=#64748B;align=center;verticalAlign=middle;whiteSpace=wrap;'));
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
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, '<b>'.$title.'</b><br><br>'.$body, "rounded=1;whiteSpace=wrap;html=1;arcSize=14;fillColor={$fill};strokeColor={$stroke};strokeWidth=2;fontSize=18;fontColor=#0F172A;spacing=12;align=center;verticalAlign=middle;"));
}

function addResource(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $title, array $endpoints, string $stroke): void
{
    addBox($document, $root, $id, $x, $y, $width, $height, $title, implode('<br>', $endpoints), '#FFFFFF', $stroke);
}

function addLane(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $title, string $fill): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $title, "swimlane;html=1;horizontal=1;startSize=48;rounded=1;arcSize=8;fillColor={$fill};swimlaneFillColor=#FFFFFF;strokeColor=#CBD5E1;strokeWidth=2;fontSize=18;fontStyle=1;fontColor=#0F172A;align=left;spacingLeft=18;"));
}

function addDecision(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $value): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, '<b>'.$value.'</b>', 'rhombus;whiteSpace=wrap;html=1;fillColor=#FFF7E8;strokeColor=#F59E0B;strokeWidth=2;fontSize=17;fontColor=#0F172A;align=center;verticalAlign=middle;'));
}

function addError(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $code, string $body): void
{
    addBox($document, $root, $id, $x, $y, $width, $height, $code, $body, '#FFF1F2', '#F43F5E');
}

function addNote(DOMDocument $document, DOMElement $root, string $id, int $x, int $y, int $width, int $height, string $value): void
{
    $root->appendChild(vertex($document, $id, $x, $y, $width, $height, $value, 'rounded=1;whiteSpace=wrap;html=1;arcSize=10;fillColor=#F8FAFC;strokeColor=#CBD5E1;strokeWidth=2;fontSize=17;fontColor=#334155;spacing=12;align=center;verticalAlign=middle;'));
}

function addEdge(DOMDocument $document, DOMElement $root, string $id, string $source, string $target, string $label): void
{
    $edge = cell($document, $id, ['value' => $label, 'edge' => '1', 'parent' => '1', 'source' => $source, 'target' => $target, 'style' => 'edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;endArrow=block;endFill=1;strokeWidth=2;strokeColor=#64748B;fontSize=15;labelBackgroundColor=#FFFFFF;']);
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
