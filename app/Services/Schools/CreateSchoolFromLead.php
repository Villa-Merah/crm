<?php

namespace App\Services\Schools;

use App\Enums\SchoolNamePrefix;
use App\Models\Sekolah;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateSchoolFromLead
{
    /**
     * @param array{
     *     prefix?: string|null,
     *     nama_sekolah_baru: string,
     *     npsn?: string|null,
     *     kota?: string|null,
     *     provinsi?: string|null
     * } $data
     */
    public function create(array $data): Sekolah
    {
        $prefix = filled($data['prefix'] ?? null)
            ? SchoolNamePrefix::from((string) $data['prefix'])
            : null;
        $rawName = Str::upper(Str::squish((string) ($data['nama_sekolah_baru'] ?? '')));

        if ($rawName === '' && (! $prefix || $prefix->requiresSchoolName())) {
            throw ValidationException::withMessages([
                'nama_sekolah_baru' => 'Nama sekolah wajib diisi.',
            ]);
        }

        $fullName = $prefix
            ? ($prefix->requiresSchoolName() ? "{$prefix->value} {$this->normalizeNamePart($rawName)}" : $prefix->value)
            : $rawName;
        $city = filled($data['kota'] ?? null)
            ? Str::title(Str::squish((string) $data['kota']))
            : null;
        $npsn = filled($data['npsn'] ?? null)
            ? Str::upper(trim((string) $data['npsn']))
            : null;
        $existing = $npsn
            ? Sekolah::query()->where('npsn', $npsn)->first()
            : Sekolah::query()
                ->whereRaw('UPPER(TRIM(nama_sekolah)) = ?', [$fullName])
                ->when(
                    $city,
                    fn ($query) => $query->whereRaw('UPPER(TRIM(kota)) = ?', [Str::upper($city)]),
                    fn ($query) => $query->whereNull('kota'),
                )
                ->first();

        if ($existing) {
            if (! $existing->aktif) {
                $existing->update(['aktif' => true]);
            }

            return $existing;
        }

        return Sekolah::create([
            'nama_sekolah' => $fullName,
            'npsn' => $npsn,
            'status_sekolah' => $prefix?->schoolStatus() ?? $this->inferSchoolStatus($fullName),
            'kota' => $city,
            'provinsi' => filled($data['provinsi'] ?? null) ? Str::title(Str::squish((string) $data['provinsi'])) : null,
            'aktif' => true,
        ]);
    }

    private function normalizeNamePart(string $name): string
    {
        $normalized = Str::upper(Str::squish($name));

        return trim((string) preg_replace('/^(SMAN|SMAS|SMKN|SMKS|MAN)\s+/iu', '', $normalized));
    }

    private function inferSchoolStatus(string $name): string
    {
        return match (true) {
            preg_match('/^(SMAN|SMKN|MAN)\b/u', $name) === 1 => 'Negeri',
            preg_match('/^(SMAS|SMKS)\b/u', $name) === 1 => 'Swasta',
            default => 'Lainnya',
        };
    }
}
