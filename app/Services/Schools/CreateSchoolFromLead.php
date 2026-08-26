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
     *     prefix: string,
     *     nama_sekolah_baru?: string|null,
     *     npsn?: string|null,
     *     kota: string,
     *     provinsi?: string|null
     * } $data
     */
    public function create(array $data): Sekolah
    {
        $prefix = SchoolNamePrefix::from($data['prefix']);
        $namePart = $this->normalizeNamePart((string) ($data['nama_sekolah_baru'] ?? ''));

        if ($prefix->requiresSchoolName() && $namePart === '') {
            throw ValidationException::withMessages([
                'nama_sekolah_baru' => 'Isi nama atau nomor sekolah setelah format sekolah.',
            ]);
        }

        $fullName = $prefix->requiresSchoolName()
            ? "{$prefix->value} {$namePart}"
            : $prefix->value;
        $city = Str::title(Str::squish($data['kota']));
        $existing = Sekolah::query()
            ->whereRaw('UPPER(TRIM(nama_sekolah)) = ?', [$fullName])
            ->whereRaw('UPPER(TRIM(kota)) = ?', [Str::upper($city)])
            ->first();

        if ($existing) {
            if (! $existing->aktif) {
                $existing->update(['aktif' => true]);
            }

            return $existing;
        }

        return Sekolah::create([
            'nama_sekolah' => $fullName,
            'npsn' => filled($data['npsn'] ?? null) ? trim((string) $data['npsn']) : null,
            'status_sekolah' => $prefix->schoolStatus(),
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
}
