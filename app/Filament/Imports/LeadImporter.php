<?php

namespace App\Filament\Imports;

use App\Enums\LeadEntryChannel;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Illuminate\Validation\ValidationException;

class LeadImporter extends Importer
{
    protected static ?string $model = Lead::class;

    protected static bool $shouldPreventFormulaInjection = true;

    public static function getColumns(): array
    {
        $ignore = fn (): null => null;

        return [
            ImportColumn::make('lead_id')
                ->label('ID Lead (opsional untuk pembaruan)')
                ->integer()
                ->rules(['nullable', 'integer', 'min:1'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('nama')
                ->label('Nama calon siswa')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'string', 'max:150']),
            ImportColumn::make('no_wa')
                ->label('Nomor WhatsApp')
                ->requiredMappingForNewRecordsOnly()
                ->castStateUsing(function ($state): ?string {
                    if ($state === null) {
                        return null;
                    }

                    return preg_replace("/^'(?=\+)/", '', (string) $state);
                })
                ->rules(['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s().-]{7,19}$/']),
            ImportColumn::make('kota_asal')
                ->label('Kota asal')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'string', 'max:100']),
            ImportColumn::make('sekolah_npsn')
                ->label('NPSN sekolah')
                ->rules(['nullable', 'regex:/^\d{8}$/'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('sekolah_nama')
                ->label('Nama sekolah')
                ->rules(['nullable', 'string', 'max:200'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('program_bimbel')
                ->label('Program bimbel')
                ->rules(['nullable', 'string', 'max:150'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('sumber_lead')
                ->label('Sumber leads')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['nullable', 'in:'.implode(',', array_column(LeadSource::cases(), 'value'))]),
            ImportColumn::make('status_lead')
                ->label('Status lead')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'in:'.implode(',', array_column(LeadStatus::cases(), 'value'))]),
            ImportColumn::make('cabang')
                ->label('Cabang')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'string', 'max:150'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('pic_email')
                ->label('Email PIC')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'email', 'max:255'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('tanggal_masuk')
                ->label('Tanggal masuk')
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required', 'date_format:Y-m-d', 'before_or_equal:today']),
        ];
    }

    public function resolveRecord(): Lead
    {
        $user = $this->import->user;
        abort_unless($user?->canManageOperationalData(), 403);

        $leadId = $this->data['lead_id'] ?? null;

        if (blank($leadId)) {
            return new Lead;
        }

        $lead = Lead::query()->visibleTo($user)->find($leadId);

        if (! $lead) {
            throw ValidationException::withMessages([
                'lead_id' => 'Lead tidak ditemukan atau tidak dapat diakses oleh pengguna ini.',
            ]);
        }

        return $lead;
    }

    protected function beforeSave(): void
    {
        $user = $this->import->user;
        abort_unless($user?->canManageOperationalData(), 403);

        $errors = [];

        if (! $this->record->exists && blank($this->data['sumber_lead'] ?? null)) {
            $errors['sumber_lead'] = 'Sumber leads wajib diisi untuk lead baru.';
        }

        $branchName = trim((string) ($this->data['cabang'] ?? ''));
        $branch = Cabang::query()->where('aktif', true)->where('nama', $branchName)->first();

        if (! $branch) {
            $errors['cabang'] = "Cabang '{$branchName}' tidak ditemukan atau tidak aktif.";
        } elseif (! $user->hasGlobalBranchAccess() && $branch->getKey() !== $user->cabang_id) {
            $errors['cabang'] = 'Impor lead hanya diperbolehkan untuk cabang pengguna.';
        }

        $school = $this->resolveSchool($errors);
        $program = $this->resolveProgram($errors);
        $pic = $this->resolvePic($branch, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->record->cabang_id = $branch->getKey();
        $this->record->sekolah_id = $school?->getKey();
        $this->record->minat_program_id = $program?->getKey();
        $this->record->diserahkan_ke_user_id = $pic->getKey();

        if (! $this->record->exists) {
            $this->record->diinput_oleh_user_id = $user->getKey();
            $this->record->kanal_masuk = LeadEntryChannel::Csv;
        }
    }

    /** @param array<string, string> $errors */
    private function resolveSchool(array &$errors): ?Sekolah
    {
        $npsn = trim((string) ($this->data['sekolah_npsn'] ?? ''));
        $name = trim((string) ($this->data['sekolah_nama'] ?? ''));

        if ($npsn === '' && $name === '') {
            return null;
        }

        $school = $npsn !== ''
            ? Sekolah::query()->where('npsn', $npsn)->first()
            : Sekolah::query()->where('nama_sekolah', $name)->first();

        if (! $school) {
            $errors[$npsn !== '' ? 'sekolah_npsn' : 'sekolah_nama'] = 'Sekolah tidak ditemukan. Tambahkan sekolah terlebih dahulu pada Master Data.';

            return null;
        }

        if ($name !== '' && mb_strtolower($school->nama_sekolah) !== mb_strtolower($name)) {
            $errors['sekolah_nama'] = 'Nama sekolah tidak sesuai dengan NPSN yang diberikan.';
        }

        return $school;
    }

    /** @param array<string, string> $errors */
    private function resolveProgram(array &$errors): ?ProgramBimbel
    {
        $name = trim((string) ($this->data['program_bimbel'] ?? ''));

        if ($name === '') {
            return null;
        }

        $program = ProgramBimbel::query()->where('aktif', true)->where('nama_program', $name)->first();

        if (! $program) {
            $errors['program_bimbel'] = 'Program bimbel tidak ditemukan atau tidak aktif.';
        }

        return $program;
    }

    /** @param array<string, string> $errors */
    private function resolvePic(?Cabang $branch, array &$errors): ?User
    {
        $email = trim((string) ($this->data['pic_email'] ?? ''));
        $pic = User::query()
            ->where('email', $email)
            ->where('aktif', true)
            ->whereIn('role', [UserRole::Staff->value, UserRole::AdminCabang->value])
            ->first();

        if (! $pic) {
            $errors['pic_email'] = 'PIC tidak ditemukan, tidak aktif, atau tidak memiliki peran operasional.';

            return null;
        }

        if ($branch && $pic->cabang_id !== $branch->getKey()) {
            $errors['pic_email'] = 'PIC harus berasal dari cabang yang sama dengan lead.';
        }

        return $pic;
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    public static function getCompletedNotificationTitle(Import $import): string
    {
        return 'Impor data leads selesai';
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = Number::format($import->successful_rows).' baris leads berhasil diimpor.';

        if ($failed = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failed).' baris gagal dan tersedia untuk diperiksa.';
        }

        return $body;
    }
}
