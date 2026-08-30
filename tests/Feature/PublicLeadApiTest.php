<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\ApiClient;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLeadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('public_api.enabled', true);
        config()->set('public_api.rate_limit', 1000);
    }

    public function test_api_rejects_request_without_valid_token(): void
    {
        $this->getJson('/api/v1/leads')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertHeader('X-Request-ID');
    }

    public function test_read_client_only_receives_leads_from_its_branch(): void
    {
        [$bandung, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$jaksel, $other] = $this->branchAndUser('JKS', 'JakSel');
        [$token] = $this->apiClient($admin, $bandung, ['leads:read']);
        $this->lead($bandung, $admin, 'Lead Bandung');
        $this->lead($jaksel, $other, 'Lead Jakarta');

        $this->withToken($token)->getJson('/api/v1/leads')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Lead Bandung')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_client_requires_matching_ability(): void
    {
        [$branch, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$token] = $this->apiClient($admin, $branch, ['leads:read']);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'lead-write-denied-001')
            ->postJson('/api/v1/leads', ['nama' => 'Ditolak', 'no_wa' => '081234567890'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_write_client_creates_lead_and_replays_same_idempotent_response(): void
    {
        [$branch, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$token] = $this->apiClient($admin, $branch, ['leads:read', 'leads:write']);
        $payload = $this->validLeadPayload([
            'nama' => 'Lead dari API',
            'no_wa' => '081234567890',
            'kota_asal' => 'Bandung',
            'kelas' => 'XII',
            'status_lead' => 'Cold',
            'sumber_lead' => 'Instagram',
            'tanggal_masuk' => '2026-08-25',
        ]);

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'lead-create-20260825-001')
            ->postJson('/api/v1/leads', $payload)
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Lead dari API')
            ->assertJsonPath('data.kota_asal', 'Bandung')
            ->assertJsonPath('data.kelas_sekolah', 'XII')
            ->assertJsonPath('data.cabang.kode', 'BDG');

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'lead-create-20260825-001')
            ->postJson('/api/v1/leads', $payload)
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Lead::query()->where('nama', 'Lead dari API')->count());
    }

    public function test_same_idempotency_key_with_different_payload_returns_conflict(): void
    {
        [$branch, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$token] = $this->apiClient($admin, $branch, ['leads:write']);
        $headers = ['Idempotency-Key' => 'lead-conflict-20260825-001'];

        $payload = $this->validLeadPayload(['nama' => 'Payload Pertama', 'no_wa' => '081234567890']);

        $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/leads', $payload)->assertCreated();

        $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/leads', [...$payload, 'nama' => 'Payload Berbeda'])
            ->assertConflict()->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_scoped_client_cannot_write_to_another_branch(): void
    {
        [$bandung, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$jaksel] = $this->branchAndUser('JKS', 'JakSel');
        [$token] = $this->apiClient($admin, $bandung, ['leads:write']);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'lead-cross-branch-001')
            ->postJson('/api/v1/leads', $this->validLeadPayload([
                'nama' => 'Cross Branch',
                'no_wa' => '081234567890',
                'cabang_id' => $jaksel->getKey(),
            ]))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'branch_scope_violation');
    }

    public function test_api_rejects_duplicate_phone_and_accepts_minimal_lead(): void
    {
        [$branch, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$token] = $this->apiClient($admin, $branch, ['leads:write']);
        $payload = $this->validLeadPayload(['no_wa' => '081234567890']);

        $this->withToken($token)->withHeader('Idempotency-Key', 'lead-quality-001')->postJson('/api/v1/leads', $payload)->assertCreated();

        $this->withToken($token)->withHeader('Idempotency-Key', 'lead-quality-002')->postJson('/api/v1/leads', [
            ...$payload,
            'nama' => 'Duplikat',
            'no_wa' => '+62 812-3456-7890',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.no_wa.0', 'Nomor WhatsApp sudah digunakan oleh lead lain. Periksa data lead yang sudah ada.');

        $this->withToken($token)->withHeader('Idempotency-Key', 'lead-quality-003')->postJson('/api/v1/leads', [
            'nama' => 'Lead Minimal',
            'no_wa' => '081234567899',
        ])->assertCreated()
            ->assertJsonPath('data.nama', 'Lead Minimal')
            ->assertJsonPath('data.kota_asal', null)
            ->assertJsonPath('data.kelas_sekolah', null)
            ->assertJsonPath('data.status', 'Cold');
    }

    private function branchAndUser(string $code, string $name): array
    {
        $branch = Cabang::create(['kode_cabang' => $code, 'nama' => $name, 'kota' => $name, 'aktif' => true]);
        $user = User::create([
            'name' => "Admin {$name}",
            'email' => strtolower($code).'@sivmi.test',
            'password' => 'password',
            'role' => UserRole::AdminCabang->value,
            'cabang_id' => $branch->getKey(),
            'aktif' => true,
        ]);

        return [$branch, $user];
    }

    private function apiClient(User $user, Cabang $branch, array $abilities): array
    {
        $token = 'sivmi_'.str_repeat('a', 48).$user->getKey().count($abilities);
        $client = ApiClient::create([
            'name' => 'Test Client',
            'key_prefix' => substr($token, 0, 14),
            'key_hash' => hash('sha256', $token),
            'abilities' => $abilities,
            'user_id' => $user->getKey(),
            'cabang_id' => $branch->getKey(),
            'active' => true,
        ]);

        return [$token, $client];
    }

    private function validLeadPayload(array $overrides = []): array
    {
        $school = Sekolah::firstOrCreate(
            ['npsn' => '20200001'],
            ['nama_sekolah' => 'SMA Contoh', 'kota' => 'Bandung', 'aktif' => true],
        );
        $program = ProgramBimbel::firstOrCreate(
            ['nama_program' => 'SR GOLD ONLINE'],
            ['jenjang_program' => 'SMA', 'biaya_pendidikan' => 1000000, 'aktif' => true],
        );

        return array_replace([
            'nama' => 'Lead API',
            'no_wa' => '081234567890',
            'kota_asal' => 'Bandung',
            'kelas_sekolah' => 'XII',
            'sekolah_id' => $school->getKey(),
            'minat_program_id' => $program->getKey(),
            'sumber_lead' => 'Instagram',
            'status_lead' => 'Cold',
            'tanggal_masuk' => '2026-08-25',
        ], $overrides);
    }

    private function lead(Cabang $branch, User $user, string $name): Lead
    {
        return Lead::create([
            'nama' => $name,
            'no_wa' => '0812'.str_pad((string) $branch->getKey(), 8, '0', STR_PAD_LEFT),
            'cabang_id' => $branch->getKey(),
            'status_lead' => LeadStatus::Cold->value,
            'diinput_oleh_user_id' => $user->getKey(),
            'tanggal_masuk' => '2026-08-25',
        ]);
    }
}
