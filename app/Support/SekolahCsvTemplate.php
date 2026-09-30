<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class SekolahCsvTemplate
{
    public const HEADERS = ['npsn', 'nama_sekolah', 'status_sekolah', 'alamat', 'kecamatan', 'kota', 'provinsi', 'kluster_sekolah', 'aktif'];

    public const EXAMPLES = [
        ['20200001', 'SMA Contoh Nusantara', 'Negeri', 'Jl. Pendidikan No. 1', 'Coblong', 'Bandung', 'Jawa Barat', 'Grade A', '1'],
        ['', 'SMP Contoh Mandiri', 'Swasta', 'Jl. Belajar No. 2', 'Kebayoran Baru', 'Jakarta Selatan', 'DKI Jakarta', 'Grade B', '1'],
    ];

    public static function download(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, self::HEADERS, ',', '"', '\\');
            foreach (self::EXAMPLES as $row) {
                fputcsv($stream, $row, ',', '"', '\\');
            }
            fclose($stream);
        }, 'contoh-import-sekolah.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
