<?php

namespace App\Services\Schools;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SchoolApiClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array
    {
        $query = Str::squish($query);

        if (! config('services.school_api.enabled') || mb_strlen($query) < 3) {
            return [];
        }

        $normalizedQuery = Str::upper($query);
        $cacheKey = 'school-api:search:'.hash('sha256', $normalizedQuery);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey, []);
        }

        $parameter = preg_match('/^[A-Z0-9]{8}$/', $normalizedQuery) === 1 ? 'npsn' : 'nama';

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('services.school_api.timeout', 5))
                ->retry(2, 250, throw: false)
                ->get((string) config('services.school_api.base_url'), [
                    $parameter => $normalizedQuery,
                    'limit' => (int) config('services.school_api.result_limit', 20),
                ]);

            if (! $response->successful() || $response->json('success') !== true) {
                Log::warning('Pencarian API sekolah gagal.', [
                    'status' => $response->status(),
                    'parameter' => $parameter,
                ]);

                return [];
            }

            $schools = collect($response->json('data', []))
                ->filter(fn ($school): bool => is_array($school))
                ->map(fn (array $school): array => $this->normalize($school))
                ->filter(fn (array $school): bool => filled($school['npsn']) && filled($school['nama_sekolah']))
                ->values()
                ->all();

            Cache::put($cacheKey, $schools, now()->addSeconds((int) config('services.school_api.cache_ttl', 86400)));

            return $schools;
        } catch (Throwable $exception) {
            Log::warning('API sekolah tidak dapat dihubungi.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /** @return array<string, string> */
    public function searchOptions(string $query): array
    {
        return collect($this->search($query))
            ->mapWithKeys(fn (array $school): array => [
                $school['npsn'] => $this->formatLabel($school),
            ])
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function findByNpsn(string $npsn): ?array
    {
        $npsn = Str::upper(trim($npsn));

        return collect($this->search($npsn))
            ->first(fn (array $school): bool => $school['npsn'] === $npsn);
    }

    /** @param array<string, mixed> $school */
    public function formatLabel(array $school): string
    {
        return collect([
            $school['nama_sekolah'] ?? null,
            $school['npsn'] ?? null,
            $school['kota'] ?? null,
        ])->filter()->implode(' — ');
    }

    /**
     * @param  array<string, mixed>  $school
     * @return array<string, mixed>
     */
    private function normalize(array $school): array
    {
        $address = is_array($school['alamat'] ?? null) ? $school['alamat'] : [];

        return [
            'npsn' => Str::upper(trim((string) ($school['npsn'] ?? ''))),
            'nama_sekolah' => Str::upper(Str::squish((string) ($school['nama'] ?? ''))),
            'status_sekolah' => $this->normalizeStatus((string) ($school['statusSatuanPendidikan'] ?? '')),
            'bentuk_pendidikan' => Str::upper(Str::squish((string) ($school['bentukPendidikan'] ?? ''))) ?: null,
            'akreditasi' => Str::upper(Str::squish((string) ($school['akreditasi'] ?? ''))) ?: null,
            'alamat' => Str::squish((string) ($address['jalan'] ?? '')) ?: null,
            'kecamatan' => $this->title($address['nama_kecamatan'] ?? null),
            'kota' => $this->title($address['nama_kabupaten'] ?? null),
            'provinsi' => $this->title($address['nama_provinsi'] ?? null),
            'kode_wilayah' => filled($address['kode_wilayah'] ?? null) ? (string) $address['kode_wilayah'] : null,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match (Str::upper(Str::squish($status))) {
            'NEGERI' => 'Negeri',
            'SWASTA' => 'Swasta',
            default => 'Lainnya',
        };
    }

    private function title(mixed $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : Str::title(Str::lower($value));
    }
}
