<?php

declare(strict_types=1);

/**
 * Membuat manifest struktur source SIVMI CRM tanpa dokumentasi, draw.io,
 * dependency pihak ketiga, cache, log, dan artefak runtime berukuran besar.
 */
$root = dirname(__DIR__);
$output = $root.DIRECTORY_SEPARATOR.'STRUKTUR-FOLDER-DAN-FILE-SIVMI.txt';

$excludedDirectories = [
    '.git', '.tmp', '.codex-pydeps', '.codex-deps', 'docs', 'node_modules', 'vendor',
    'storage/app/doc-qa', 'storage/app/doc-tools',
    'storage/doc-qa', 'storage/doc-tools',
    'storage/framework/cache', 'storage/framework/sessions',
    'storage/framework/testing', 'storage/framework/views',
    'storage/logs',
    'public/build', 'public/css/filament', 'public/fonts/filament',
    'public/js/filament',
    'tools/__pycache__',
];

$excludedFiles = [
    'STRUKTUR-FOLDER-DAN-FILE-SIVMI.txt',
    'STRUKTUR-ARSITEKTUR-BISNIS-SIVMI.txt',
    'STRUKTUR-ARSITEKTUR-APLIKASI-SIVMI.txt',
    'STRUKTUR-ARSITEKTUR-TEKNOLOGI-SIVMI.txt',
    '.phpunit.result.cache',
    'get())', 'toArray())',
    'public/hot',
];

$excludedExtensions = ['drawio', 'doc', 'docx', 'pdf', 'md'];

$placeholderDirectories = [
    'node_modules' => 'dependency npm; isi tidak dicantumkan',
    'vendor' => 'dependency Composer; isi tidak dicantumkan',
    'public/build' => 'hasil build Vite; isi tidak dicantumkan',
    'public/css/filament' => 'asset vendor Filament; isi tidak dicantumkan',
    'public/fonts/filament' => 'asset vendor Filament; isi tidak dicantumkan',
    'public/js/filament' => 'asset vendor Filament; isi tidak dicantumkan',
    'storage/framework' => 'cache, session, testing, dan compiled view runtime',
    'storage/logs' => 'log runtime; isi tidak dicantumkan',
];

function normalizedRelative(string $root, string $path): string
{
    $relative = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');

    return $relative;
}

function pathIsExcluded(string $relative, array $excludedDirectories): bool
{
    foreach ($excludedDirectories as $excluded) {
        if ($relative === $excluded || str_starts_with($relative, $excluded.'/')) {
            return true;
        }
    }

    return false;
}

function directoryTree(
    string $root,
    string $directory,
    string $prefix,
    array $excludedDirectories,
    array $excludedFiles,
    array $excludedExtensions,
    array $placeholderDirectories,
): array {
    $entries = [];
    $iterator = new DirectoryIterator($directory);

    foreach ($iterator as $entry) {
        if ($entry->isDot()) {
            continue;
        }

        $path = $entry->getPathname();
        $relative = normalizedRelative($root, $path);
        $extension = strtolower($entry->getExtension());

        if (in_array($relative, $excludedFiles, true)) {
            continue;
        }

        if ($entry->isFile() && in_array($extension, $excludedExtensions, true)) {
            continue;
        }

        if ($entry->isDir() && pathIsExcluded($relative, $excludedDirectories)) {
            if (isset($placeholderDirectories[$relative])) {
                $entries[] = [
                    'name' => $entry->getFilename().'/  ['.$placeholderDirectories[$relative].']',
                    'directory' => false,
                    'placeholder' => true,
                ];
            }

            continue;
        }

        $entries[] = [
            'name' => $entry->getFilename().($entry->isDir() ? '/' : ''),
            'path' => $path,
            'relative' => $relative,
            'directory' => $entry->isDir(),
            'placeholder' => false,
        ];
    }

    usort($entries, static function (array $left, array $right): int {
        if ($left['directory'] !== $right['directory']) {
            return $left['directory'] ? -1 : 1;
        }

        return strnatcasecmp($left['name'], $right['name']);
    });

    $lines = [];
    $count = count($entries);

    foreach ($entries as $index => $entry) {
        $last = $index === $count - 1;
        $lines[] = $prefix.($last ? '└── ' : '├── ').$entry['name'];

        if ($entry['directory'] && ! $entry['placeholder']) {
            $lines = array_merge($lines, directoryTree(
                $root,
                $entry['path'],
                $prefix.($last ? '    ' : '│   '),
                $excludedDirectories,
                $excludedFiles,
                $excludedExtensions,
                $placeholderDirectories,
            ));
        }
    }

    return $lines;
}

$tree = directoryTree(
    $root,
    $root,
    '',
    $excludedDirectories,
    $excludedFiles,
    $excludedExtensions,
    $placeholderDirectories,
);

$header = [
    'STRUKTUR FOLDER DAN FILE SIVMI CRM',
    '====================================',
    '',
    'Cakupan:',
    '- Source aplikasi Laravel, Filament, Livewire, Tailwind, REST API, dan WAHA.',
    '- Konfigurasi, migration, seeder, test, Docker, resource, route, dan entry point.',
    '- Baseline struktur 8 September 2026 mencakup role Keuangan dan REST API data siswa.',
    '',
    'Tidak dicantumkan:',
    '- Folder docs dan seluruh file dokumentasi (.md, .doc, .docx, .pdf).',
    '- Seluruh file .drawio.',
    '- Isi vendor, node_modules, asset vendor/build, cache, log, dan runtime.',
    '- Secret atau isi konfigurasi .env (nama file tetap terlihat dalam struktur).',
    '',
    'sivmi.crm/',
];

file_put_contents($output, implode(PHP_EOL, array_merge($header, $tree)).PHP_EOL);

echo "Created: {$output}".PHP_EOL;
echo 'Tree lines: '.count($tree).PHP_EOL;
