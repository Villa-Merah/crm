<?php

namespace App\Filament\Exports;

use App\Models\Sekolah;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class SekolahExporter extends Exporter
{
    protected static ?string $model = Sekolah::class;

    public static function getColumns(): array
    {
        $safe = fn ($state): string => static::sanitize((string) $state);
        return [
            ExportColumn::make('npsn')->label('npsn')->formatStateUsing($safe),
            ExportColumn::make('nama_sekolah')->label('nama_sekolah')->formatStateUsing($safe),
            ExportColumn::make('status_sekolah')->label('status_sekolah')->formatStateUsing($safe),
            ExportColumn::make('alamat')->label('alamat')->formatStateUsing($safe),
            ExportColumn::make('kecamatan')->label('kecamatan')->formatStateUsing($safe),
            ExportColumn::make('kota')->label('kota')->formatStateUsing($safe),
            ExportColumn::make('provinsi')->label('provinsi')->formatStateUsing($safe),
            ExportColumn::make('kluster_sekolah')->label('kluster_sekolah')->formatStateUsing($safe),
            ExportColumn::make('aktif')->label('aktif')->state(fn (Sekolah $record): string => $record->aktif ? '1' : '0'),
        ];
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public function getFileName(Export $export): string
    {
        return 'data-sekolah-'.now()->format('Y-m-d-His');
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Ekspor data sekolah selesai';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = Number::format($export->successful_rows).' baris sekolah berhasil diekspor.';
        if ($failed = $export->getFailedRowsCount()) $body .= ' '.Number::format($failed).' baris gagal diekspor.';
        return $body;
    }

    private static function sanitize(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'{$value}" : $value;
    }
}
