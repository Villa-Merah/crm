<?php

namespace App\Filament\Exports;

use App\Models\Lead;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class LeadExporter extends Exporter
{
    protected static ?string $model = Lead::class;

    public static function getColumns(): array
    {
        $safe = fn ($state): string => static::sanitize((string) $state);

        return [
            ExportColumn::make('lead_id')->label('lead_id'),
            ExportColumn::make('nama')->label('nama')->formatStateUsing($safe),
            ExportColumn::make('no_wa')->label('no_wa')->formatStateUsing($safe),
            ExportColumn::make('kota_asal')->label('kota_asal')->formatStateUsing($safe),
            ExportColumn::make('kelas_sekolah')->label('kelas_sekolah')->formatStateUsing($safe),
            ExportColumn::make('sekolah_npsn')->label('sekolah_npsn')->state(fn (Lead $record) => $record->sekolah?->npsn)->formatStateUsing($safe),
            ExportColumn::make('sekolah_nama')->label('sekolah_nama')->state(fn (Lead $record) => $record->sekolah?->nama_sekolah)->formatStateUsing($safe),
            ExportColumn::make('program_bimbel')->label('program_bimbel')->state(fn (Lead $record) => $record->minatProgram?->nama_program)->formatStateUsing($safe),
            ExportColumn::make('sumber_lead')->label('sumber_lead')->formatStateUsing($safe),
            ExportColumn::make('status_lead')->label('status_lead')->formatStateUsing($safe),
            ExportColumn::make('cabang')->label('cabang')->state(fn (Lead $record) => $record->cabang?->nama)->formatStateUsing($safe),
            ExportColumn::make('pic_email')->label('pic_email')->state(fn (Lead $record) => $record->diserahkanKe?->email)->formatStateUsing($safe),
            ExportColumn::make('tanggal_masuk')->label('tanggal_masuk')->state(fn (Lead $record): string => $record->tanggal_masuk?->format('Y-m-d') ?? ''),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $user = auth()->user();

        return $query
            ->when($user, fn (Builder $query): Builder => $query->visibleTo($user))
            ->when(! $user, fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->with(['cabang', 'sekolah', 'minatProgram', 'diserahkanKe']);
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public function getFileName(Export $export): string
    {
        return 'data-leads-'.now()->format('Y-m-d-His');
    }

    public static function getCompletedNotificationTitle(Export $export): string
    {
        return 'Ekspor data leads selesai';
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = Number::format($export->successful_rows).' baris leads berhasil diekspor.';

        if ($failed = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failed).' baris gagal diekspor.';
        }

        return $body;
    }

    private static function sanitize(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/u', $value) ? "'{$value}" : $value;
    }
}
