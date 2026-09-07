<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\ApiClient;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStudentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('public_api.enabled', true);
        config()->set('public_api.rate_limit', 1000);
    }

    public function test_api_rejects_student_request_without_valid_token(): void
    {
        $this->getJson('/api/v1/students')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertHeader('X-Request-ID');
    }

    public function test_read_client_only_receives_students_from_its_branch_with_payment_summary(): void
    {
        [$bandung, $admin] = $this->branchAndUser('BDG', 'Bandung');
        [$jakarta, $otherAdmin] = $this->branchAndUser('JKS', 'Jakarta');
        $token = $this->apiToken($admin, $bandung, ['students:read']);
        $bandungRegistration = $this->registration($bandung, $admin, 'Siswa Bandung');
        $this->registration($jakarta, $otherAdmin, 'Siswa Jakarta');

        Pembayaran::create([
            'pendaftaran_id' => $bandungRegistration->getKey(),
            'tanggal_pembayaran' => '2026-09-01 10:00:00',
            'nominal' => 2500000,
            'metode_pembayaran' => 'Transfer Bank',
            'status_pembayaran' => 'Terverifikasi',
        ]);
        Pembayaran::create([
            'pendaftaran_id' => $bandungRegistration->getKey(),
            'tanggal_pembayaran' => '2026-09-02 10:00:00',
            'nominal' => 500000,
            'metode_pembayaran' => 'Transfer Bank',
            'status_pembayaran' => 'Menunggu',
        ]);

        $this->withToken($token)->getJson('/api/v1/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Siswa Bandung')
            ->assertJsonPath('data.0.program.biaya', 8200000)
            ->assertJsonPath('data.0.pembayaran.total_terverifikasi', 2500000)
            ->assertJsonPath('data.0.pembayaran.sisa_tagihan', 5700000)
            ->assertJsonPath('data.0.pembayaran.status', 'unpaid')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_student_endpoint_requires_matching_ability(): void
    {
        [$branch, $admin] = $this->branchAndUser('BDG', 'Bandung');
        $token = $this->apiToken($admin, $branch, ['leads:read']);

        $this->withToken($token)->getJson('/api/v1/students')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    private function branchAndUser(string $code, string $name): array
    {
        $branch = Cabang::create([
            'kode_cabang' => $code,
            'nama' => $name,
            'kota' => $name,
            'aktif' => true,
        ]);
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

    private function apiToken(User $user, Cabang $branch, array $abilities): string
    {
        $token = 'sivmi_'.str_repeat('s', 48).$user->getKey();
        ApiClient::create([
            'name' => 'Data Siswa Test',
            'key_prefix' => substr($token, 0, 14),
            'key_hash' => hash('sha256', $token),
            'abilities' => $abilities,
            'user_id' => $user->getKey(),
            'cabang_id' => $branch->getKey(),
            'active' => true,
        ]);

        return $token;
    }

    private function registration(Cabang $branch, User $user, string $name): Pendaftaran
    {
        $school = Sekolah::create([
            'nama_sekolah' => "SMA {$branch->kode_cabang}",
            'kota' => $branch->kota,
            'aktif' => true,
        ]);
        $program = ProgramBimbel::create([
            'nama_program' => 'PROGRAM INTENSIF',
            'jenjang_program' => 'SMA',
            'biaya_pendidikan' => 8200000,
            'aktif' => true,
        ]);
        $lead = Lead::create([
            'nama' => $name,
            'no_wa' => '0812'.str_pad((string) $branch->getKey(), 8, '0', STR_PAD_LEFT),
            'kota_asal' => $branch->kota,
            'kelas_sekolah' => 'XII',
            'sekolah_id' => $school->getKey(),
            'minat_program_id' => $program->getKey(),
            'cabang_id' => $branch->getKey(),
            'status_lead' => LeadStatus::Daftar->value,
            'diinput_oleh_user_id' => $user->getKey(),
            'tanggal_masuk' => '2026-08-25',
        ]);

        return Pendaftaran::create([
            'lead_id' => $lead->getKey(),
            'program_bimbel_id' => $program->getKey(),
            'cabang_id' => $branch->getKey(),
            'tanggal_daftar' => '2026-09-01',
            'kelas_angkatan' => 'Angkatan 2026',
            'status_pendaftaran' => 'Aktif',
        ]);
    }
}
