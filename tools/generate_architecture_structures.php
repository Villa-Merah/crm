<?php

declare(strict_types=1);

/**
 * Membuat tiga proyeksi struktur source SIVMI CRM:
 * 1. arsitektur bisnis (domain/proses),
 * 2. arsitektur aplikasi (layer/komponen), dan
 * 3. arsitektur teknologi (runtime/infrastruktur).
 *
 * File source tidak dipindahkan. Setiap keluaran hanya memetakan lokasi fisik
 * aktual ke struktur logis yang sesuai dengan arsitektur sistem.
 */

$root = dirname(__DIR__);

$outputs = [
    'business' => $root . DIRECTORY_SEPARATOR . 'STRUKTUR-ARSITEKTUR-BISNIS-SIVMI.txt',
    'application' => $root . DIRECTORY_SEPARATOR . 'STRUKTUR-ARSITEKTUR-APLIKASI-SIVMI.txt',
    'technology' => $root . DIRECTORY_SEPARATOR . 'STRUKTUR-ARSITEKTUR-TEKNOLOGI-SIVMI.txt',
];

$architectures = [
    'business' => [
        'title' => 'STRUKTUR BERDASARKAN ARSITEKTUR BISNIS SIVMI CRM',
        'description' => 'Pemetaan source menurut kapabilitas, proses bisnis, aktor, dan kontrol CRM.',
        'groups' => [
            '01 Akuisisi Lead dan Sekolah' => [
                'app/Enums/LeadEntryChannel.php', 'app/Enums/LeadSource.php',
                'app/Enums/LeadStatus.php', 'app/Enums/SchoolGrade.php',
                'app/Enums/SchoolNamePrefix.php',
                'app/Filament/Resources/Leads', 'app/Filament/Resources/Sekolahs',
                'app/Filament/Imports/LeadImporter.php', 'app/Filament/Imports/SekolahImporter.php',
                'app/Filament/Exports/LeadExporter.php', 'app/Filament/Exports/SekolahExporter.php',
                'app/Services/Schools', 'app/Support/LeadCsvTemplate.php',
                'app/Support/SekolahCsvTemplate.php', 'app/Support/PhoneNormalizer.php',
                'app/Models/Lead.php', 'app/Models/Sekolah.php',
            ],
            '02 Engagement dan Follow-up' => [
                'app/Filament/Resources/FollowupRiwayats',
                'app/Models/FollowupRiwayat.php',
                'app/Console/Commands/SendCrmReminders.php',
                'app/Support/CrmNotifier.php',
            ],
            '03 Percakapan dan Inbox WhatsApp' => [
                'app/Filament/Resources/WhatsAppConversations',
                'app/Filament/Resources/WhatsAppAccounts',
                'app/Services/WhatsApp', 'app/Jobs/ProcessWahaWebhook.php',
                'app/Http/Controllers/Webhooks', 'app/Http/Controllers/WhatsAppMediaController.php',
                'app/Http/Controllers/WhatsAppQrController.php',
                'app/Http/Middleware/VerifyWahaWebhookHmac.php',
                'app/Models/UserWhatsAppAccount.php', 'app/Models/WahaWebhookEvent.php',
                'app/Models/WhatsAppConversation.php', 'app/Models/WhatsAppMessage.php',
                'resources/views/filament/resources/whatsapp-conversations',
                'resources/views/integrations/waha',
            ],
            '04 Konversi dan Pendaftaran Siswa' => [
                'app/Filament/Resources/Pendaftarans', 'app/Models/Pendaftaran.php',
                'app/Filament/Resources/ProgramBimbels', 'app/Models/ProgramBimbel.php',
            ],
            '05 Pembayaran dan Pengakuan Omzet' => [
                'app/Filament/Resources/Pembayarans', 'app/Models/Pembayaran.php',
            ],
            '06 Kinerja, Dashboard, dan Laporan' => [
                'app/Filament/Pages/Dashboard.php', 'app/Filament/Pages/Laporan.php',
                'app/Filament/Resources/TargetKinerjaCabangs',
                'app/Models/TargetKinerjaCabang.php', 'app/Models/TargetKinerjaUser.php',
                'resources/views/filament/pages/dashboard.blade.php',
                'resources/views/filament/pages/laporan.blade.php',
            ],
            '07 Organisasi, Pengguna, dan RBAC' => [
                'app/Enums/UserRole.php', 'app/Filament/Resources/Cabangs',
                'app/Filament/Resources/Users', 'app/Filament/Pages/Auth',
                'app/Models/Cabang.php', 'app/Models/User.php',
                'app/Providers/Filament/AdminPanelProvider.php',
            ],
            '08 Pengaturan, Audit, dan Notifikasi' => [
                'app/Filament/Pages/Pengaturan.php', 'app/Filament/Pages/AuditAktivitas.php',
                'app/Models/CrmSetting.php', 'app/Models/ActivityLog.php',
                'app/Observers', 'app/Support/CrmNotifier.php',
                'resources/views/filament/pages/pengaturan.blade.php',
                'resources/views/filament/pages/audit-aktivitas.blade.php',
            ],
            '09 Pertukaran Data melalui REST API' => [
                'app/Console/Commands/CreateApiClient.php',
                'app/Http/Controllers/Api', 'app/Http/Middleware/AuthenticatePublicApi.php',
                'app/Http/Middleware/EnsureApiIdempotency.php', 'app/Http/Requests/Api',
                'app/Http/Resources/Api', 'app/Models/ApiClient.php',
                'app/Models/ApiIdempotencyKey.php', 'app/Support/ApiResponse.php',
                'config/public_api.php', 'routes/api.php',
            ],
            '10 Data, Aturan Bisnis, dan Verifikasi' => [
                'database/migrations', 'database/seeders', 'database/factories',
                'tests/Feature', 'tests/Unit', 'tests/TestCase.php',
            ],
        ],
    ],
    'application' => [
        'title' => 'STRUKTUR BERDASARKAN ARSITEKTUR APLIKASI SIVMI CRM',
        'description' => 'Pemetaan source modular monolith menurut layer dan tanggung jawab aplikasi.',
        'groups' => [
            '01 Presentation - Filament, Livewire, Blade, dan Tailwind' => [
                'app/Filament', 'resources/views', 'resources/css', 'resources/js',
                'public/index.php', 'public/.htaccess', 'public/robots.txt',
            ],
            '02 Application - Use Case dan Orkestrasi' => [
                'app/Console/Commands', 'app/Http/Controllers', 'app/Http/Requests',
                'app/Http/Resources', 'app/Jobs', 'app/Observers',
            ],
            '03 Domain - Enum, Service, dan Aturan Bisnis' => [
                'app/Enums', 'app/Services', 'app/Support',
            ],
            '04 Persistence - Model dan Skema Data' => [
                'app/Models', 'database/migrations', 'database/seeders',
                'database/factories',
            ],
            '05 Integration - REST API dan WAHA' => [
                'app/Http/Controllers/Api', 'app/Http/Controllers/Webhooks',
                'app/Http/Controllers/WhatsAppMediaController.php',
                'app/Http/Controllers/WhatsAppQrController.php',
                'app/Http/Middleware/AuthenticatePublicApi.php',
                'app/Http/Middleware/EnsureApiIdempotency.php',
                'app/Http/Middleware/VerifyWahaWebhookHmac.php',
                'app/Services/WhatsApp', 'app/Jobs/ProcessWahaWebhook.php',
                'routes/api.php', 'config/public_api.php', 'config/services.php',
            ],
            '06 Cross-Cutting - Provider, Auth, Config, Route, dan Logging' => [
                'app/Providers', 'bootstrap/app.php', 'bootstrap/providers.php',
                'config', 'routes',
            ],
            '07 Quality Assurance' => [
                'tests', 'phpunit.xml',
            ],
            '08 Build dan Package Management' => [
                'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
                'vite.config.js', '.npmrc',
            ],
        ],
    ],
    'technology' => [
        'title' => 'STRUKTUR BERDASARKAN ARSITEKTUR TEKNOLOGI SIVMI CRM',
        'description' => 'Pemetaan file menurut node runtime, state, jaringan, deployment, dan operasi.',
        'groups' => [
            '01 Client dan Front-End Build' => [
                'resources/css', 'resources/js', 'resources/views',
                'package.json', 'package-lock.json', 'vite.config.js', '.npmrc',
            ],
            '02 Web Server dan Public Entry Point' => [
                'docker/nginx', 'public/index.php', 'public/.htaccess',
                'public/favicon.ico', 'public/robots.txt',
            ],
            '03 PHP dan Laravel Runtime' => [
                'docker/php', 'artisan', 'bootstrap', 'composer.json', 'composer.lock',
                'config/app.php', 'config/auth.php', 'config/filesystems.php',
                'config/logging.php', 'config/mail.php',
            ],
            '04 MySQL dan Persistensi' => [
                'config/database.php', 'database/migrations', 'database/seeders',
                'database/factories',
            ],
            '05 Redis, Session, Cache, dan Queue' => [
                'config/cache.php', 'config/session.php', 'config/queue.php',
                'app/Jobs', 'app/Console/Commands/SendCrmReminders.php',
                'app/Console/Commands/SyncWahaSessions.php',
            ],
            '06 WAHA GOWS dan Webhook' => [
                'app/Services/WhatsApp', 'app/Jobs/ProcessWahaWebhook.php',
                'app/Http/Controllers/Webhooks', 'app/Http/Controllers/WhatsAppQrController.php',
                'app/Http/Controllers/WhatsAppMediaController.php',
                'app/Http/Middleware/VerifyWahaWebhookHmac.php',
                'config/services.php', 'routes/api.php',
            ],
            '07 Docker Compose dan Jaringan Layanan' => [
                'docker/compose.yml', 'docker/compose.production.yml',
                'docker/nginx/default.conf', 'docker/nginx/Dockerfile',
                'docker/php/Dockerfile', 'docker/php/entrypoint.sh',
                'docker/php/php.ini', '.dockerignore',
            ],
            '08 Environment dan Secret Template' => [
                '.env.example', 'docker/.env.example', 'docker/.env.production.example',
                '.gitignore', '.gitattributes', '.editorconfig',
            ],
            '09 Storage dan File Media' => [
                'config/filesystems.php', 'storage/app/private/.gitignore',
                'storage/app/public/.gitignore', 'public/storage',
            ],
            '10 Operasi, Health Check, dan Diagnostik' => [
                'docker/start-full.ps1', 'docker/stop-full.ps1',
                'docker/status-full.ps1', 'docker/logs-full.ps1',
                'docker/test-full.ps1', 'routes/console.php',
                'config/logging.php',
            ],
            '11 Testing dan Release Gate' => [
                'tests', 'phpunit.xml', 'composer.json', 'package.json',
            ],
        ],
    ],
];

function normalizePath(string $path): string
{
    return str_replace('\\', '/', trim($path, '/\\'));
}

function collectFiles(string $root, array $specifications): array
{
    $files = [];

    foreach ($specifications as $specification) {
        $relative = normalizePath($specification);
        $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

        if (is_file($absolute) || is_link($absolute)) {
            $files[$relative] = true;
            continue;
        }

        if (! is_dir($absolute)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if (! $entry->isFile() && ! $entry->isLink()) {
                continue;
            }

            $entryRelative = normalizePath(substr($entry->getPathname(), strlen($root)));
            $extension = strtolower(pathinfo($entryRelative, PATHINFO_EXTENSION));

            if (in_array($extension, ['md', 'doc', 'docx', 'pdf', 'drawio'], true)) {
                continue;
            }

            if (str_contains($entryRelative, '/__pycache__/')) {
                continue;
            }

            $files[$entryRelative] = true;
        }
    }

    $result = array_keys($files);
    natcasesort($result);

    return array_values($result);
}

function insertTreePath(array &$tree, string $path): void
{
    $cursor = &$tree;
    foreach (explode('/', normalizePath($path)) as $part) {
        if (! isset($cursor[$part])) {
            $cursor[$part] = [];
        }
        $cursor = &$cursor[$part];
    }
}

function renderTree(array $tree, string $prefix = ''): array
{
    uksort($tree, 'strnatcasecmp');
    $keys = array_keys($tree);
    $lines = [];

    foreach ($keys as $index => $key) {
        $children = $tree[$key];
        $last = $index === count($keys) - 1;
        $directory = $children !== [];
        $lines[] = $prefix . ($last ? '└── ' : '├── ') . $key . ($directory ? '/' : '');

        if ($directory) {
            $lines = array_merge($lines, renderTree(
                $children,
                $prefix . ($last ? '    ' : '│   '),
            ));
        }
    }

    return $lines;
}

function buildArchitectureFile(string $root, array $definition): array
{
    $lines = [
        $definition['title'],
        str_repeat('=', strlen($definition['title'])),
        '',
        $definition['description'],
        '',
        'Catatan:',
        '- Struktur ini adalah proyeksi logis; file fisik tidak dipindahkan atau digandakan.',
        '- Satu file dapat muncul pada beberapa kelompok jika memiliki fungsi lintas arsitektur.',
        '- Dokumentasi, draw.io, dependency pihak ketiga, cache, dan log tidak dicantumkan.',
        '',
    ];

    $total = 0;
    foreach ($definition['groups'] as $group => $specifications) {
        $files = collectFiles($root, $specifications);
        $tree = [];
        foreach ($files as $file) {
            insertTreePath($tree, $file);
        }

        $lines[] = $group . '/';
        $rendered = renderTree($tree);
        $lines = array_merge($lines, $rendered ?: ['└── [belum ada file aktual]']);
        $lines[] = '';
        $total += count($files);
    }

    $lines[] = 'Total referensi file pada seluruh kelompok: ' . $total;

    return $lines;
}

foreach ($architectures as $key => $definition) {
    $lines = buildArchitectureFile($root, $definition);
    file_put_contents($outputs[$key], implode(PHP_EOL, $lines) . PHP_EOL);
    echo 'Created: ' . $outputs[$key] . PHP_EOL;
}

