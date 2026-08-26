<?php

namespace App\Filament\Imports;

use App\Enums\UserRole;
use App\Enums\SchoolGrade;
use App\Models\Sekolah;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class SekolahImporter extends Importer
{
    protected static ?string $model = Sekolah::class;
    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('npsn')->label('NPSN')->rules(['nullable', 'regex:/^\d{8}$/']),
            ImportColumn::make('nama_sekolah')->label('Nama Sekolah')->requiredMappingForNewRecordsOnly()->rules(['required', 'string', 'max:200']),
            ImportColumn::make('status_sekolah')->label('Status Sekolah')->rules(['nullable', 'in:Negeri,Swasta,Lainnya']),
            ImportColumn::make('alamat')->label('Alamat')->rules(['nullable', 'string', 'max:3000']),
            ImportColumn::make('kecamatan')->label('Kecamatan')->rules(['nullable', 'string', 'max:100']),
            ImportColumn::make('kota')->label('Kota/Kabupaten')->requiredMappingForNewRecordsOnly()->rules(['required', 'string', 'max:100']),
            ImportColumn::make('provinsi')->label('Provinsi')->rules(['nullable', 'string', 'max:100']),
            ImportColumn::make('kluster_sekolah')->label('Kluster Sekolah')->rules(['nullable', 'in:'.implode(',', SchoolGrade::values())]),
            ImportColumn::make('aktif')->label('Aktif')->boolean()->rules(['nullable', 'boolean']),
        ];
    }

    public function resolveRecord(): Sekolah
    {
        $npsn = trim((string) ($this->data['npsn'] ?? ''));
        return $npsn === '' ? new Sekolah() : Sekolah::firstOrNew(['npsn' => $npsn]);
    }

    protected function beforeSave(): void
    {
        $user = $this->import->user;
        abort_unless($user->hasRole(UserRole::Superadmin) || $user->hasRole(UserRole::AdminCabang), 403);

        $npsn = trim((string) $this->record->npsn);
        $this->record->npsn = $npsn === '' ? null : $npsn;

        if (! $this->record->exists) {
            $this->record->status_sekolah ??= 'Lainnya';
            $this->record->aktif ??= true;
        }
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'Impor data sekolah selesai';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = Number::format($import->successful_rows).' baris sekolah berhasil diimpor.';
        if ($failed = $import->getFailedRowsCount()) $body .= ' '.Number::format($failed).' baris gagal dan tersedia untuk diperiksa.';
        return $body;
    }
}
