<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\ApiClient;
use App\Models\Cabang;
use App\Models\Lead;
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
        $payload = [
            'nama' => 'Lead dari API',
            'no_wa' => '081234567890',
            'kota_asal' => 'Bandung',
            'status_lead' => 'Cold',
            'sumber_lead' => 'Instagram',
            'tanggal_masuk' => '2026-08-25',
        ];

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'lead-create-20260825-001')
            ->postJson('/api/v1/leads', $payload)
            ->assertCreated()
            ->assertJsonPath('data.nama', 'Lead dari API')
            ->assertJsonPath('data.kota_asal', 'Bandung')
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

        $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/leads', [
            'nama' => 'Payload Pertama', 'no_wa' => '081234567890',
        ])->assertCreated();

        $this->withToken($token)->withHeaders($headers)->postJson('/api/v1/leads', [
            'nama' => 'Payload Berbeda', 'no_wa' => '081234567890',
        ])->assertConflict()->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_scoped_client_cannot_write_to_another_branch(): void
    {
        [$bandung, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$jaksel] = $this->branchAndUser('JKS', 'JakSel');
        [$token] = $this->apiClient($admin, $bandung, ['leads:write']);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'lead-cross-branch-001')
            ->postJson('/api/v1/leads', [
                'nama' => 'Cross Branch',
                'no_wa' => '081234567890',
                'cabang_id' => $jaksel->getKey(),
            ])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'branch_scope_violation');
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
