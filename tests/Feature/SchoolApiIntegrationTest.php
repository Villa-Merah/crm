<?php

namespace Tests\Feature;

use App\Enums\SchoolGrade;
use App\Models\Sekolah;
use App\Services\Schools\SchoolApiClient;
use App\Services\Schools\SyncSchoolFromApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SchoolApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('services.school_api.enabled', true);
        config()->set('services.school_api.base_url', 'https://sekolah.example.test/sekolah');
        config()->set('services.school_api.timeout', 5);
        config()->set('services.school_api.cache_ttl', 3600);
        config()->set('services.school_api.result_limit', 20);
        Cache::flush();
    }

    public function test_it_searches_normalizes_and_caches_school_api_results(): void
    {
        Http::fake([
            'sekolah.example.test/*' => Http::response($this->apiResponse(), 200),
        ]);

        $client = app(SchoolApiClient::class);
        $first = $client->search('madani');
        $second = $client->search('MADANI');

        $this->assertSame($first, $second);
        $this->assertSame('P9984383', $first[0]['npsn']);
        $this->assertSame('PKBM MADANI', $first[0]['nama_sekolah']);
        $this->assertSame('Swasta', $first[0]['status_sekolah']);
        $this->assertSame('Kab. Mamuju', $first[0]['kota']);
        $this->assertSame('Sulawesi Barat', $first[0]['provinsi']);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['nama'] === 'MADANI' && $request['limit'] === 20);
    }

    public function test_it_uses_npsn_parameter_for_an_exact_npsn_search(): void
    {
        Http::fake([
            'sekolah.example.test/*' => Http::response($this->apiResponse(), 200),
        ]);

        $school = app(SchoolApiClient::class)->findByNpsn('p9984383');

        $this->assertNotNull($school);
        Http::assertSent(fn ($request): bool => $request['npsn'] === 'P9984383');
    }

    public function test_it_persists_api_data_locally_without_overwriting_internal_cluster(): void
    {
        $school = Sekolah::create([
            'npsn' => 'P9984383',
            'nama_sekolah' => 'NAMA LAMA',
            'status_sekolah' => 'Lainnya',
            'kluster_sekolah' => SchoolGrade::A->value,
            'aktif' => false,
        ]);

        $synced = app(SyncSchoolFromApi::class)->sync([
            'npsn' => 'P9984383',
            'nama_sekolah' => 'PKBM MADANI',
            'status_sekolah' => 'Swasta',
            'bentuk_pendidikan' => 'PKBM',
            'akreditasi' => 'B',
            'alamat' => 'Galung',
            'kecamatan' => 'Tapalang',
            'kota' => 'Kab. Mamuju',
            'provinsi' => 'Sulawesi Barat',
            'kode_wilayah' => '760202',
        ]);

        $this->assertTrue($school->is($synced));
        $this->assertSame('PKBM MADANI', $synced->nama_sekolah);
        $this->assertSame(SchoolGrade::A->value, $synced->kluster_sekolah);
        $this->assertSame('api-sekolah', $synced->sumber_data);
        $this->assertTrue($synced->aktif);
        $this->assertNotNull($synced->api_last_synced_at);
    }

    public function test_it_returns_an_empty_result_when_the_api_is_unavailable(): void
    {
        Http::fake([
            'sekolah.example.test/*' => Http::response(['success' => false], 503),
        ]);

        $this->assertSame([], app(SchoolApiClient::class)->search('sekolah gagal'));
    }

    /** @return array<string, mixed> */
    private function apiResponse(): array
    {
        return [
            'success' => true,
            'data' => [[
                'npsn' => 'P9984383',
                'nama' => 'PKBM Madani',
                'bentukPendidikan' => 'PKBM',
                'statusSatuanPendidikan' => 'SWASTA',
                'akreditasi' => 'B',
                'alamat' => [
                    'jalan' => 'GALUNG',
                    'nama_kecamatan' => 'TAPALANG',
                    'nama_kabupaten' => 'KAB. MAMUJU',
                    'nama_provinsi' => 'SULAWESI BARAT',
                    'kode_wilayah' => 760202,
                ],
            ]],
        ];
    }
}
