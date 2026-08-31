<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SumberLead extends Model
{
    protected $table = 'sumber_leads';

    protected $primaryKey = 'sumber_lead_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function leads()
    {
        return $this->hasMany(Lead::class, 'sumber_lead_id', 'sumber_lead_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /** @return array<int|string, string> */
    public static function options(): array
    {
        return static::query()
            ->active()
            ->orderBy('urutan')
            ->orderBy('nama')
            ->pluck('nama', 'sumber_lead_id')
            ->all();
    }

    public static function findActiveByNameOrCode(?string $value): ?self
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return static::query()
            ->active()
            ->where(fn (Builder $query): Builder => $query
                ->whereRaw('LOWER(nama) = ?', [mb_strtolower($value)])
                ->orWhereRaw('LOWER(kode) = ?', [mb_strtolower($value)]))
            ->first();
    }
}
