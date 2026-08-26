<?php

declare(strict_types=1);

const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

$docsDir = dirname(__DIR__);
$sourceDrawio = $docsDir . DIRECTORY_SEPARATOR . 'Diagram Gabungan SIVMI CRM v1.1.drawio';
$targetDrawio = $docsDir . DIRECTORY_SEPARATOR . 'Diagram Gabungan SIVMI CRM v1.2.drawio';
$sourceDocx = $docsDir . DIRECTORY_SEPARATOR . 'Dokumentasi dan Pengembangan Sistem CRM SIVMI v1.1 - Sementara.docx';
$targetDocx = $docsDir . DIRECTORY_SEPARATOR . 'Dokumentasi dan Pengembangan Sistem CRM SIVMI v1.2 - Sementara.docx';

foreach ([$sourceDrawio, $sourceDocx] as $source) {
    if (! is_file($source)) {
        throw new RuntimeException("Berkas sumber tidak ditemukan: {$source}");
    }
}

updateDrawio($sourceDrawio, $targetDrawio);
copy($sourceDocx, $targetDocx) || throw new RuntimeException('Gagal menyalin DOCX sumber.');
updateDocx($targetDocx);
embedDiagramImages($targetDocx, $docsDir . DIRECTORY_SEPARATOR . '_diagram_v1_2');

echo "Created:\n- {$targetDrawio}\n- {$targetDocx}\n";

function updateDrawio(string $source, string $target): void
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;

    if (! $dom->load($source)) {
        throw new RuntimeException('XML draw.io tidak valid.');
    }

    $dom->documentElement?->setAttribute('modified', '2026-08-24T12:00:00.000Z');

    $pages = [];
    foreach ($dom->getElementsByTagName('diagram') as $diagram) {
        $pages[$diagram->getAttribute('name')] = $diagram;
    }

    $changes = [
        '01 - Arsitektur Sistem' => [
            'v9' => 'DATA&lt;br&gt;Eloquent ORM / Query scope / Migration&lt;br&gt;CSV Sekolah &amp; Leads',
        ],
        '04 - Flowmap' => [
            'v10' => '2. Catat lead, sumber, dan penanggung jawab',
        ],
        '05 - Flowchart' => [
            'v5' => 'Validasi identitas, sumber, cabang, sekolah, dan minat program',
        ],
        '06 - DFD Level 0' => [
            'v6' => 'Staff / Admin Cabang&lt;br&gt;Lead + sumber, follow-up, transaksi',
        ],
        '07 - DFD Level 1' => [
            'v6' => '1.0 Kelola Lead&lt;br&gt;validasi sumber, penugasan, status',
            'v7' => 'D1 Leads, Sekolah, Program',
        ],
        '09 - ERD Operasional' => [
            'v7' => 'PROGRAM_BIMBELS&lt;br&gt;&lt;br&gt;PK id&lt;br&gt;kategori_program&lt;br&gt;program_kelas&lt;br&gt;metode_belajar&lt;br&gt;nama = 3 komponen&lt;br&gt;biaya, aktif',
            'v8' => 'LEADS&lt;br&gt;&lt;br&gt;PK id&lt;br&gt;FK branch_id&lt;br&gt;FK school_id?&lt;br&gt;FK program_id?&lt;br&gt;FK assigned_to&lt;br&gt;status (5 opsi)&lt;br&gt;sumber_lead (6 opsi)&lt;br&gt;tanggal_masuk',
        ],
    ];

    foreach ($changes as $pageName => $cells) {
        if (! isset($pages[$pageName])) {
            throw new RuntimeException("Halaman draw.io tidak ditemukan: {$pageName}");
        }

        $xpath = new DOMXPath($dom);
        foreach ($cells as $id => $value) {
            $nodes = $xpath->query('.//mxCell[@id="' . $id . '"]', $pages[$pageName]);
            if ($nodes === false || $nodes->length !== 1) {
                throw new RuntimeException("Cell {$pageName}/{$id} tidak ditemukan atau tidak unik.");
            }
            $nodes->item(0)->setAttribute('value', html_entity_decode($value, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }
    }

    if (isset($pages['09 - ERD Operasional'])) {
        $xpath = new DOMXPath($dom);
        foreach (['v4', 'v5', 'v6', 'v7', 'v8', 'v9', 'v10', 'v11'] as $id) {
            $nodes = $xpath->query('.//mxCell[@id="' . $id . '"]', $pages['09 - ERD Operasional']);
            if ($nodes === false || $nodes->length !== 1) {
                throw new RuntimeException("Cell ERD Operasional/{$id} tidak ditemukan atau tidak unik.");
            }
            $cell = $nodes->item(0);
            $cell->setAttribute('style', 'rounded=1;whiteSpace=wrap;html=1;fillColor=#EAF4FB;strokeColor=#1476B8;strokeWidth=2;fontColor=#0F172A;fontSize=15;align=center;verticalAlign=middle;spacing=8;');
            $value = $cell->getAttribute('value');
            $cell->setAttribute('value', preg_replace('/^([^<]+)<br><br>/', '<b>$1</b><br><br>', $value) ?? $value);
        }
    }

    if ($dom->save($target) === false) {
        throw new RuntimeException('Gagal menyimpan draw.io v1.2.');
    }
}

function updateDocx(string $path): void
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Gagal membuka DOCX sebagai arsip ZIP.');
    }

    $documentXml = $zip->getFromName('word/document.xml');
    if ($documentXml === false) {
        throw new RuntimeException('word/document.xml tidak ditemukan.');
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->preserveWhiteSpace = true;
    if (! $dom->loadXML($documentXml)) {
        throw new RuntimeException('XML dokumen Word tidak valid.');
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', W_NS);

    replaceTextNodes($xpath, [
        'SIVMI CRM v1.1' => 'SIVMI CRM v1.2',
        'Versi sementara 1.1' => 'Versi sementara 1.2',
        '20 Agustus 2026' => '24 Agustus 2026',
        'Import dan Export Sekolah' => 'Import dan Export Sekolah dan Leads',
        '8.5 Import Sekolah CSV' => '8.5 Import CSV',
        '8.6 Export Sekolah CSV' => '8.6 Export CSV',
        'Impor dan ekspor sekolah melalui CSV' => 'Impor dan ekspor sekolah serta leads melalui CSV',
        '40 test' => '49 test',
        '267 assertion' => '317 assertion',
    ]);

    replaceParagraphContaining($xpath, 'Kolom wajib:', 'Kolom impor sekolah: nama_sekolah, status, kota_kabupaten, kluster, dan is_active. NPSN bersifat opsional.');
    replaceParagraphContaining($xpath, 'Baris dengan NPSN', 'Sekolah diperbarui berdasarkan NPSN bila tersedia; tanpa NPSN, kecocokan memakai nama sekolah dan kota/kabupaten.');
    replaceParagraphContaining($xpath, 'Nilai kluster', 'Kluster sekolah dibatasi enum Grade A, Grade B, Grade C, Grade D, dan Grade E.');
    replaceParagraphContaining($xpath, 'Berkas diproses', 'CSV sekolah dan leads diproses bertahap, dibatasi maksimal 5.000 baris, dan setiap baris divalidasi sebelum disimpan.');
    replaceParagraphContaining($xpath, 'Ekspor mengikuti', 'Ekspor sekolah dan leads mengikuti ruang lingkup role, cabang pengguna, filter tabel, pencarian, dan pengurutan aktif.');
    replaceParagraphContaining($xpath, 'Formula injection', 'Sel CSV yang diawali karakter formula spreadsheet dinetralkan untuk mencegah formula injection pada ekspor sekolah dan leads.');
    replaceParagraphContaining($xpath, 'Sekolah tanpa NPSN selalu dibuat', 'Sekolah tanpa NPSN dicocokkan menggunakan kombinasi nama sekolah dan kota/kabupaten sebelum record baru dibuat.');
    replaceParagraphContaining($xpath, '7.2sponsivitas', '7.2 Responsivitas');
    replaceParagraphContaining($xpath, '11.1tatus dan Batasan Saat Ini', '11.1 Status dan Batasan Saat Ini');
    replaceParagraphContaining($xpath, 'List, create, view, edit, follow-up relation', 'List, create, view, edit, follow-up relation, import/export CSV');
    replaceParagraphContaining($xpath, 'php artisan test', 'php artisan test  |  php artisan migrate:status  |  php artisan route:list');
    removeParagraphExact($xpath, 'php artisan migrate:status');
    removeParagraphExact($xpath, 'php artisan route:list');
    removeParagraphExact($xpath, 'php artisan view:clear');
    removeParagraphExact($xpath, 'php artisan view:cache');

    $heading57 = findParagraphExact($xpath, '5.7 Import dan Export Sekolah dan Leads');
    if ($heading57 instanceof DOMElement) {
        insertParagraphsAfter($heading57, [
            ['Normal', 'Data leads dapat diimpor dan diekspor melalui CSV dari halaman Leads. Template contoh disediakan pada aksi impor.'],
            ['List Bullet', 'Kolom leads: lead_id (opsional), nama_lengkap, nomor_whatsapp, sekolah, program, status, sumber_lead, cabang, PIC, dan tanggal_masuk.'],
            ['List Bullet', 'Status yang diterima: Cold, Warm, Hot, Daftar, dan Tidak Berminat.'],
            ['List Bullet', 'Sumber lead yang diterima: Iklan, Instagram, Kunjungan, Saudara, Facebook, dan Tiktok.'],
            ['List Bullet', 'Lead baru mewajibkan sumber_lead; data historis tetap kompatibel karena kolom basis data bersifat nullable.'],
            ['List Bullet', 'Pembaruan dapat memakai lead_id dan tetap menerapkan validasi kepemilikan cabang serta pembatasan role.'],
        ]);
    }

    $heading85 = findParagraphExact($xpath, '8.5 Import CSV');
    if ($heading85 instanceof DOMElement) {
        insertParagraphsAfter($heading85, [
            ['Normal', 'Sekolah: buka Master Data > Sekolah, pilih Import CSV, unduh contoh, isi data, lalu unggah dan periksa ringkasan hasil.'],
            ['Normal', 'Leads: buka Leads, pilih Import CSV, unduh contoh, isi status dan sumber lead sesuai enum, lalu unggah. Baris yang gagal ditampilkan agar dapat diperbaiki.'],
        ]);
    }

    $heading86 = findParagraphExact($xpath, '8.6 Export CSV');
    if ($heading86 instanceof DOMElement) {
        insertParagraphsAfter($heading86, [
            ['Normal', 'Gunakan Export CSV pada halaman Sekolah atau Leads. Hasil ekspor hanya memuat data yang boleh diakses pengguna serta mengikuti filter tabel aktif.'],
        ]);
    }

    appendTableRowAfterCellText($xpath, 'SchoolCsvTest', ['LeadCsvTest', 'Import/export leads, enum status/sumber, RBAC cabang, keamanan CSV']);
    appendTableRowAfterCellText($xpath, 'Sekolah CSV', ['Leads CSV', 'Contoh, impor, validasi status/sumber, kegagalan, dan ekspor berfungsi', 'Automated test tersedia']);
    appendTableRowAfterCellText($xpath, 'Metode pembayaran', ['Status lead', 'Cold, Warm, Hot, Daftar, Tidak Berminat']);
    appendTableRowAfterCellText($xpath, 'Status lead', ['Sumber lead', 'Iklan, Instagram, Kunjungan, Saudara, Facebook, Tiktok']);
    appendTableRowAfterCellText($xpath, 'Sumber lead', ['Nama program', 'Kategori + Kelas Program + Metode Belajar; contoh SR GOLD ONLINE']);

    $babX = findLastParagraphStartingWith($xpath, 'BAB X:');
    if ($babX instanceof DOMElement) {
        insertParagraphsBefore($babX, [
            ['Heading 2', '9.6 Docker dan Laragon'],
            ['Normal', 'Aplikasi dapat dijalankan melalui Docker Compose dengan layanan app, nginx, MySQL, Redis, node, queue, dan scheduler. Konfigurasi tersedia di folder docker.'],
            ['List Bullet', 'Bangun dan jalankan container dari root proyek: docker compose --env-file docker/.env -f docker/compose.yml up -d --build.'],
            ['List Bullet', 'Jalankan migrasi/seeder di container app: docker compose --env-file docker/.env -f docker/compose.yml exec app php artisan migrate --seed.'],
            ['List Bullet', 'Untuk kembali ke Laragon, hentikan container tanpa menghapus volume, aktifkan Apache dan MySQL Laragon, lalu pulihkan konfigurasi .env untuk host 127.0.0.1 dan port MySQL Laragon.'],
            ['List Bullet', 'Hindari docker compose down -v kecuali volume database Docker memang akan dihapus. Pastikan port web dan database tidak bentrok dengan Laragon.'],
        ]);
    }

    $body = $xpath->query('//w:body')->item(0);
    if (! $body instanceof DOMElement) {
        throw new RuntimeException('Body dokumen tidak ditemukan.');
    }

    $zip->addFromString('word/document.xml', $dom->saveXML());

    $coreXml = $zip->getFromName('docProps/core.xml');
    if ($coreXml !== false) {
        $coreXml = str_replace(
            ['SIVMI CRM v1.1', '2026-08-20'],
            ['SIVMI CRM v1.2', '2026-08-24'],
            $coreXml,
        );
        $zip->addFromString('docProps/core.xml', $coreXml);
    }

    $zip->close();
}

function embedDiagramImages(string $docxPath, string $imageDirectory): void
{
    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        throw new RuntimeException('Gagal membuka DOCX untuk menanam diagram.');
    }

    $cover = $imageDirectory . DIRECTORY_SEPARATOR . 'cover-v1_2.png';
    if (! is_file($cover) || ! $zip->addFile($cover, 'word/media/image1.png')) {
        throw new RuntimeException('Gagal menanam gambar cover v1.2.');
    }

    $exports = [
        1 => 'diagram-01.png',
        2 => 'diagram-03.png',
        3 => 'diagram-04.png',
        4 => 'diagram-05.png',
        5 => 'diagram-06.png',
        6 => 'diagram-07.png',
        7 => 'diagram-08.png',
        8 => 'diagram-09.png',
        9 => 'erd-operasional-v1_2.png',
    ];

    foreach ($exports as $page => $filename) {
        $source = $imageDirectory . DIRECTORY_SEPARATOR . $filename;
        $target = 'word/media/image' . ($page + 1) . '.png';
        if (! is_file($source)) {
            throw new RuntimeException("Gambar diagram tidak ditemukan: {$source}");
        }
        if (! $zip->addFile($source, $target)) {
            throw new RuntimeException("Gagal menanam {$source} ke {$target}");
        }
    }

    $zip->close();
}

function replaceTextNodes(DOMXPath $xpath, array $replacements): void
{
    foreach ($xpath->query('//w:t') as $node) {
        $value = $node->nodeValue ?? '';
        $updated = str_replace(array_keys($replacements), array_values($replacements), $value);
        if ($updated !== $value) {
            $node->nodeValue = $updated;
        }
    }
}

function paragraphText(DOMElement $paragraph): string
{
    $text = '';
    foreach ($paragraph->getElementsByTagNameNS(W_NS, 't') as $node) {
        $text .= $node->nodeValue;
    }
    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function findParagraphExact(DOMXPath $xpath, string $text): ?DOMElement
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        if ($paragraph instanceof DOMElement && paragraphText($paragraph) === $text) {
            return $paragraph;
        }
    }
    return null;
}

function findParagraphStartingWith(DOMXPath $xpath, string $prefix): ?DOMElement
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        if ($paragraph instanceof DOMElement && str_starts_with(paragraphText($paragraph), $prefix)) {
            return $paragraph;
        }
    }
    return null;
}

function findLastParagraphStartingWith(DOMXPath $xpath, string $prefix): ?DOMElement
{
    $match = null;
    foreach ($xpath->query('//w:p') as $paragraph) {
        if ($paragraph instanceof DOMElement && str_starts_with(paragraphText($paragraph), $prefix)) {
            $match = $paragraph;
        }
    }
    return $match;
}

function appendTableRowAfterCellText(DOMXPath $xpath, string $needle, array $values): void
{
    foreach ($xpath->query('//w:tr') as $row) {
        if (! $row instanceof DOMElement || ! str_contains(paragraphText($row), $needle)) {
            continue;
        }

        $clone = $row->cloneNode(true);
        if (! $clone instanceof DOMElement) {
            return;
        }

        $cells = $clone->getElementsByTagNameNS(W_NS, 'tc');
        foreach ($values as $index => $value) {
            $cell = $cells->item($index);
            if (! $cell instanceof DOMElement) {
                continue;
            }
            $paragraph = $cell->getElementsByTagNameNS(W_NS, 'p')->item(0);
            if ($paragraph instanceof DOMElement) {
                setParagraphText($paragraph, $value);
            }
        }

        $row->parentNode?->insertBefore($clone, $row->nextSibling);
        return;
    }
}

function replaceParagraphContaining(DOMXPath $xpath, string $needle, string $replacement): void
{
    foreach ($xpath->query('//w:p') as $paragraph) {
        if ($paragraph instanceof DOMElement && str_contains(paragraphText($paragraph), $needle)) {
            setParagraphText($paragraph, $replacement);
            return;
        }
    }
}

function removeParagraphExact(DOMXPath $xpath, string $text): void
{
    $paragraph = findParagraphExact($xpath, $text);
    $paragraph?->parentNode?->removeChild($paragraph);
}

function setParagraphText(DOMElement $paragraph, string $text): void
{
    $texts = $paragraph->getElementsByTagNameNS(W_NS, 't');
    if ($texts->length === 0) {
        $run = $paragraph->ownerDocument->createElementNS(W_NS, 'w:r');
        $textNode = $paragraph->ownerDocument->createElementNS(W_NS, 'w:t');
        $run->appendChild($textNode);
        $paragraph->appendChild($run);
        $texts = $paragraph->getElementsByTagNameNS(W_NS, 't');
    }
    $texts->item(0)->nodeValue = $text;
    for ($i = 1; $i < $texts->length; $i++) {
        $texts->item($i)->nodeValue = '';
    }
}

function createParagraph(DOMDocument $dom, string $style, string $text): DOMElement
{
    $p = $dom->createElementNS(W_NS, 'w:p');
    $pPr = $dom->createElementNS(W_NS, 'w:pPr');
    $pStyle = $dom->createElementNS(W_NS, 'w:pStyle');
    $pStyle->setAttributeNS(W_NS, 'w:val', str_replace(' ', '', $style));
    $pPr->appendChild($pStyle);
    $p->appendChild($pPr);

    $run = $dom->createElementNS(W_NS, 'w:r');
    $t = $dom->createElementNS(W_NS, 'w:t');
    $t->setAttribute('xml:space', 'preserve');
    $t->nodeValue = $text;
    $run->appendChild($t);
    $p->appendChild($run);
    return $p;
}

function insertParagraphsAfter(DOMElement $anchor, array $paragraphs): void
{
    $parent = $anchor->parentNode;
    if (! $parent) {
        return;
    }
    $reference = $anchor->nextSibling;
    foreach ($paragraphs as [$style, $text]) {
        $node = createParagraph($anchor->ownerDocument, $style, $text);
        $parent->insertBefore($node, $reference);
    }
}

function insertParagraphsBefore(DOMElement $anchor, array $paragraphs): void
{
    $parent = $anchor->parentNode;
    if (! $parent) {
        return;
    }
    foreach ($paragraphs as [$style, $text]) {
        $parent->insertBefore(createParagraph($anchor->ownerDocument, $style, $text), $anchor);
    }
}
