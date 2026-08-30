<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadCsvTemplate
{
    public const HEADERS = [
        'lead_id',
        'nama',
        'no_wa',
        'kota_asal',
        'kelas',
        'sekolah_npsn',
        'sekolah_nama',
        'program_bimbel',
        'sumber_lead',
        'status_lead',
        'cabang',
        'pic_email',
        'tanggal_masuk',
    ];

    public const EXAMPLES = [
        ['', 'Alya Putri', '+6281234567890', 'Bandung', 'XII', '', '', 'SR GOLD TATAP MUKA', 'Instagram', 'Warm', 'Bandung', 'staff.bdg.1@sivmi.test', '2026-08-21'],
        ['', 'Bima Pratama', '+6281234567891', 'Jakarta Selatan', 'IX', '', '', '', 'Kunjungan', 'Cold', 'JakSel', 'staff.jks.1@sivmi.test', '2026-08-21'],
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
        }, 'contoh-import-leads.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
