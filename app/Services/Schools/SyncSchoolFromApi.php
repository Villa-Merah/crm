<?php

namespace App\Services\Schools;

use App\Models\Sekolah;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SyncSchoolFromApi
{
    /** @param array<string, mixed> $data */
    public function sync(array $data): Sekolah
    {
        $npsn = Str::upper(trim((string) ($data['npsn'] ?? '')));

        if (preg_match('/^[A-Z0-9]{8}$/', $npsn) !== 1) {
            throw ValidationException::withMessages([
                'npsn' => 'NPSN dari API tidak valid.',
            ]);
        }

        $attributes = Arr::only($data, [
            'nama_sekolah',
            'status_sekolah',
            'bentuk_pendidikan',
            'akreditasi',
            'alamat',
            'kecamatan',
            'kota',
            'provinsi',
            'kode_wilayah',
        ]);

        return Sekolah::updateOrCreate(
            ['npsn' => $npsn],
            [...$attributes, 'aktif' => true, 'sumber_data' => 'api-sekolah', 'api_last_synced_at' => now()],
        );
    }
}
