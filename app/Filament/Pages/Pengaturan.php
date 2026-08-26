<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\CrmSetting;
use App\Models\ActivityLog;
use App\Models\ProgramBimbel;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Pengaturan extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Pengaturan';
    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';
    protected static ?int $navigationSort = 7;
    protected static ?string $title = 'Pengaturan';
    protected string $view = 'filament.pages.pengaturan';

    public string $appName = '';
    public string $organizationName = '';
    public string $dashboardTagline = '';
    public ?string $supportEmail = null;
    public ?string $supportPhone = null;
    public bool $staffReportExport = false;
    public string $programCategories = '';
    public string $programClasses = '';
    public string $programTypes = '';
    public string $programLearningMethods = '';
    public string $programLevels = '';
    public string $programClassLevels = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->appName = (string) CrmSetting::getValue('app_name');
        $this->organizationName = (string) CrmSetting::getValue('organization_name');
        $this->dashboardTagline = (string) CrmSetting::getValue('dashboard_tagline');
        $this->supportEmail = CrmSetting::getValue('support_email');
        $this->supportPhone = CrmSetting::getValue('support_phone');
        $this->staffReportExport = CrmSetting::getBoolean('staff_report_export');
        $this->programCategories = implode("\n", CrmSetting::getArray('program_categories'));
        $this->programClasses = implode("\n", CrmSetting::getArray('program_classes'));
        $this->programTypes = implode("\n", CrmSetting::getArray('program_types'));
        $this->programLearningMethods = implode("\n", CrmSetting::getArray('program_learning_methods'));
        $this->programLevels = implode("\n", CrmSetting::getArray('program_levels'));
        $this->programClassLevels = implode("\n", CrmSetting::getArray('program_class_levels'));
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->validate([
            'appName' => ['required', 'string', 'max:60'],
            'organizationName' => ['required', 'string', 'max:100'],
            'dashboardTagline' => ['required', 'string', 'max:180'],
            'supportEmail' => ['nullable', 'email', 'max:255'],
            'supportPhone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\-\s]*$/'],
            'staffReportExport' => ['boolean'],
            'programCategories' => ['required', 'string', 'max:1500'],
            'programClasses' => ['required', 'string', 'max:1500'],
            'programTypes' => ['required', 'string', 'max:1500'],
            'programLearningMethods' => ['required', 'string', 'max:1500'],
            'programLevels' => ['required', 'string', 'max:1500'],
            'programClassLevels' => ['required', 'string', 'max:1500'],
        ], ['supportPhone.regex' => 'Nomor dukungan hanya boleh berisi angka dan simbol telepon.']);

        $programSettings = [
            'programCategories' => ['key' => 'program_categories', 'column' => 'kategori_program'],
            'programClasses' => ['key' => 'program_classes', 'column' => 'program_kelas'],
            'programTypes' => ['key' => 'program_types', 'column' => 'tipe_program'],
            'programLearningMethods' => ['key' => 'program_learning_methods', 'column' => 'metode_belajar'],
            'programLevels' => ['key' => 'program_levels', 'column' => 'jenjang_program'],
            'programClassLevels' => ['key' => 'program_class_levels', 'column' => 'tingkat_kelas'],
        ];
        $normalizedProgramSettings = [];

        foreach ($programSettings as $property => $setting) {
            $values = $this->normalizeOptions($data[$property]);
            if ($values === []) {
                $this->addError($property, 'Minimal satu opsi harus tersedia.');
                return;
            }
            if (count($values) > 30) {
                $this->addError($property, 'Maksimal 30 opsi dapat disimpan.');
                return;
            }
            if (collect($values)->contains(fn (string $option): bool => mb_strlen($option) > 60)) {
                $this->addError($property, 'Setiap opsi maksimal 60 karakter.');
                return;
            }

            $usedValues = ProgramBimbel::query()->whereNotNull($setting['column'])->distinct()->pluck($setting['column'])->filter()->all();
            $missingValues = array_values(array_diff($usedValues, $values));
            if ($missingValues !== []) {
                $this->addError($property, 'Opsi masih dipakai oleh program: '.implode(', ', $missingValues).'.');
                return;
            }

            $normalizedProgramSettings[$setting['key']] = $values;
        }

        CrmSetting::setMany([
            'app_name' => trim($data['appName']),
            'organization_name' => trim($data['organizationName']),
            'dashboard_tagline' => trim($data['dashboardTagline']),
            'support_email' => filled($data['supportEmail']) ? trim($data['supportEmail']) : null,
            'support_phone' => filled($data['supportPhone']) ? trim($data['supportPhone']) : null,
            'staff_report_export' => $data['staffReportExport'],
            ...$normalizedProgramSettings,
        ], auth()->id());

        ActivityLog::create(['user_id' => auth()->id(), 'event' => 'updated', 'description' => 'Pengaturan aplikasi diperbarui', 'properties' => ['fields' => array_merge(['app_name', 'organization_name', 'dashboard_tagline', 'support_email', 'support_phone', 'staff_report_export'], array_column($programSettings, 'key'))], 'ip_address' => request()->ip(), 'user_agent' => str(request()->userAgent())->limit(500)->toString(), 'created_at' => now()]);

        Notification::make()->title('Pengaturan berhasil disimpan')->body('Perubahan langsung diterapkan pada panel SIVMI CRM.')->success()->send();
    }

    private function normalizeOptions(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn (string $option): string => trim($option))
            ->filter()
            ->unique(fn (string $option): string => mb_strtolower($option))
            ->values()
            ->all();
    }
}
