<?php

namespace App\Filament\Imports;

use App\Enums\LeadEntryChannel;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\CrmSetting;
use App\Models\Lead;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\SumberLead;
use App\Models\User;
use App\Support\PhoneNormalizer;
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
                ->rules(['nullable', 'string', 'max:100']),
            ImportColumn::make('kelas_sekolah')
                ->label('Kelas sekolah saat ini')
                ->guess(['kelas', 'kelas_sekolah', 'kelas sekolah'])
                ->rules(['nullable', 'string', 'max:60', 'in:'.implode(',', CrmSetting::getArray('school_class_levels'))]),
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
                ->rules(['nullable', 'string', 'max:100'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('status_lead')
                ->label('Status lead')
                ->rules(['nullable', 'in:'.implode(',', array_column(LeadStatus::cases(), 'value'))]),
            ImportColumn::make('cabang')
                ->label('Cabang')
                ->rules(['nullable', 'string', 'max:150'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('pic_email')
                ->label('Email PIC')
                ->rules(['nullable', 'email', 'max:255'])
                ->fillRecordUsing($ignore),
            ImportColumn::make('tanggal_masuk')
                ->label('Tanggal masuk')
                ->rules(['nullable', 'date_format:Y-m-d', 'before_or_equal:today']),
        ];
    }

    /** @return array<string, string> */
    public function getValidationMessages(): array
    {
        return [
            'nama.required' => 'Nama calon siswa wajib diisi.',
            'no_wa.required' => 'Nomor WhatsApp wajib diisi.',
            'no_wa.regex' => 'Format nomor WhatsApp tidak valid. Gunakan 8–20 digit dan boleh diawali tanda +.',
            'kota_asal.required' => 'Kota asal wajib diisi.',
            'kelas_sekolah.required' => 'Kelas sekolah saat ini wajib diisi.',
            'kelas_sekolah.in' => 'Kelas sekolah tidak tersedia pada pilihan tingkat kelas.',
            'sekolah_npsn.regex' => 'NPSN sekolah harus terdiri dari tepat 8 digit.',
            'sumber_lead.required' => 'Sumber leads wajib diisi.',
            'sumber_lead.max' => 'Sumber leads maksimal 100 karakter.',
            'status_lead.required' => 'Status lead wajib diisi.',
            'status_lead.in' => 'Status lead tidak valid.',
            'cabang.required' => 'Cabang wajib diisi.',
            'pic_email.required' => 'Email PIC wajib diisi.',
            'pic_email.email' => 'Format email PIC tidak valid.',
            'tanggal_masuk.required' => 'Tanggal masuk wajib diisi.',
            'tanggal_masuk.date_format' => 'Tanggal masuk harus menggunakan format YYYY-MM-DD.',
            'tanggal_masuk.before_or_equal' => 'Tanggal masuk tidak boleh melewati hari ini.',
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

        $phone = app(PhoneNormalizer::class)->normalize($this->data['no_wa'] ?? null);
        $duplicatePhone = $phone
            ? Lead::query()
                ->where('no_wa_normalized', $phone)
                ->when($this->record->exists, fn ($query) => $query->whereKeyNot($this->record->getKey()))
                ->exists()
            : false;

        if ($duplicatePhone) {
            $errors['no_wa'] = 'Nomor WhatsApp sudah digunakan oleh lead lain.';
        }

        $branchName = trim((string) ($this->data['cabang'] ?? ''));
        $branch = $branchName !== ''
            ? Cabang::query()->where('aktif', true)->where('nama', $branchName)->first()
            : ($user->cabang_id ? Cabang::query()->where('aktif', true)->find($user->cabang_id) : null);

        if ($branchName !== '' && ! $branch) {
            $errors['cabang'] = "Cabang '{$branchName}' tidak ditemukan atau tidak aktif.";
        } elseif (! $user->hasGlobalBranchAccess() && $branch->getKey() !== $user->cabang_id) {
            $errors['cabang'] = 'Impor lead hanya diperbolehkan untuk cabang pengguna.';
        }

        $school = $this->resolveSchool($errors);
        $program = $this->resolveProgram($errors);
        $source = $this->resolveSource($errors);
        $pic = blank($this->data['pic_email'] ?? null) && $user->hasRole(UserRole::Staff)
            ? $user
            : $this->resolvePic($branch, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->record->cabang_id = $branch?->getKey();
        $this->record->sekolah_id = $school?->getKey();
        $this->record->minat_program_id = $program?->getKey();
        $this->record->sumber_lead_id = $source?->getKey();
        $this->record->diserahkan_ke_user_id = $pic?->getKey();

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
            ? Sekolah::query()->where('aktif', true)->where('npsn', $npsn)->first()
            : Sekolah::query()->where('aktif', true)->where('nama_sekolah', $name)->first();

        if (! $school) {
            $errors[$npsn !== '' ? 'sekolah_npsn' : 'sekolah_nama'] = 'Sekolah tidak ditemukan atau tidak aktif. Periksa Master Data Sekolah.';

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
    private function resolveSource(array &$errors): ?SumberLead
    {
        $name = trim((string) ($this->data['sumber_lead'] ?? ''));

        if ($name === '') {
            return null;
        }

        $source = SumberLead::findActiveByNameOrCode($name);

        if (! $source) {
            $errors['sumber_lead'] = 'Sumber leads tidak ditemukan atau tidak aktif. Periksa master sumber leads.';
        }

        return $source;
    }

    /** @param array<string, string> $errors */
    private function resolvePic(?Cabang $branch, array &$errors): ?User
    {
        $email = trim((string) ($this->data['pic_email'] ?? ''));

        if ($email === '') {
            return null;
        }

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
