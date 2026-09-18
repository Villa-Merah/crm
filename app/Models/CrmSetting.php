<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CrmSetting extends Model
{
    protected $table = 'crm_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    private const CACHE_KEY = 'sivmi.crm.settings';

    public const DEFAULTS = [
        'app_name' => 'SIVMI CRM',
        'organization_name' => 'SIVMI',
        'login_background_image' => null,
        'dashboard_tagline' => 'Pantau pertumbuhan leads, pencapaian siswa, dan kinerja tim dalam satu ruang kerja.',
        'support_email' => null,
        'support_phone' => null,
        'staff_report_export' => false,
        'school_class_levels' => ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII', 'Alumni/Gap Year'],
        'program_categories' => ['SR', 'AR', 'KG', 'W'],
        'program_classes' => ['Platinum', 'Gold', 'Silver', 'Bronze'],
        'program_types' => ['Reguler', 'Intensif'],
        'program_learning_methods' => ['Tatap Muka', 'Online', 'Hybrid'],
        'program_levels' => ['SD', 'SMP', 'SMA', 'Gap Year', 'Umum'],
        'program_class_levels' => ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'],
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $settings = static::allValues();

        return $settings[$key] ?? static::DEFAULTS[$key] ?? $default;
    }

    public static function getBoolean(string $key, bool $default = false): bool
    {
        return filter_var(static::getValue($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public static function getArray(string $key, array $default = []): array
    {
        $value = static::getValue($key, $default);

        return is_array($value) ? array_values($value) : $default;
    }

    public static function setMany(array $values, ?int $userId): void
    {
        $allowed = array_intersect_key($values, static::DEFAULTS);
        $groups = [
            'app_name' => 'identity', 'organization_name' => 'identity', 'login_background_image' => 'identity', 'dashboard_tagline' => 'dashboard',
            'support_email' => 'contact', 'support_phone' => 'contact', 'staff_report_export' => 'report',
            'school_class_levels' => 'lead',
            'program_categories' => 'program', 'program_classes' => 'program', 'program_types' => 'program',
            'program_learning_methods' => 'program', 'program_levels' => 'program', 'program_class_levels' => 'program',
        ];

        DB::transaction(function () use ($allowed, $groups, $userId): void {
            foreach ($allowed as $key => $value) {
                $type = is_bool($value) ? 'boolean' : (is_array($value) ? 'json' : 'string');
                static::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => match ($type) {
                        'boolean' => $value ? '1' : '0',
                        'json' => json_encode(array_values($value), JSON_UNESCAPED_UNICODE),
                        default => $value,
                    }, 'type' => $type, 'group' => $groups[$key], 'updated_by' => $userId],
                );
            }
        });

        Cache::forget(static::CACHE_KEY);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    private static function allValues(): array
    {
        try {
            if (! Schema::hasTable('crm_settings')) {
                return static::DEFAULTS;
            }

            return Cache::rememberForever(static::CACHE_KEY, function (): array {
                $rows = static::query()->get(['key', 'value', 'type']);

                return array_replace(static::DEFAULTS, $rows->mapWithKeys(fn (self $setting) => [
                    $setting->key => match ($setting->type) {
                        'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                        'json' => json_decode((string) $setting->value, true) ?: (static::DEFAULTS[$setting->key] ?? []),
                        default => $setting->value,
                    },
                ])->all());
            });
        } catch (Throwable) {
            return static::DEFAULTS;
        }
    }
}
