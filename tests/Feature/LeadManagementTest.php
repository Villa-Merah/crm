<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Cabang;
use App\Models\FollowupRiwayat;
use App\Models\Lead;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_and_followup_pages_render_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        $this->actingAs($admin)->get('/admin/leads')->assertOk()->assertSee('Leads');
        $this->actingAs($admin)->get('/admin/leads/create')
            ->assertOk()
            ->assertSee('Data calon siswa')
            ->assertSee('Kota asal')
            ->assertSee('Kelas')
            ->assertSee('Cold')
            ->assertSee('Warm')
            ->assertSee('Hot')
            ->assertSee('Daftar')
            ->assertSee('Tidak Berminat')
            ->assertSee('Sumber leads')
            ->assertSee('Instagram')
            ->assertSee('Kunjungan')
            ->assertSee('Tiktok');
        $this->actingAs($admin)->get('/admin/follow-up')->assertOk()->assertSee('Follow-up');
    }

    public function test_staff_only_sees_assigned_leads_from_own_branch(): void
    {
        $branchA = Cabang::create(['kode_cabang' => 'A', 'nama' => 'Cabang A', 'aktif' => true]);
        $branchB = Cabang::create(['kode_cabang' => 'B', 'nama' => 'Cabang B', 'aktif' => true]);
        $marketing = User::factory()->create(['cabang_id' => $branchA->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $other = User::factory()->create(['cabang_id' => $branchB->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);

        $visible = Lead::create($this->leadData($branchA, $marketing, 'Lead terlihat'));
        $hidden = Lead::create($this->leadData($branchB, $other, 'Lead tersembunyi'));
        FollowupRiwayat::create(['lead_id' => $visible->lead_id, 'user_id' => $marketing->id, 'tanggal_followup' => now(), 'metode_followup' => 'WhatsApp', 'status_kontak' => 'Terhubung']);
        FollowupRiwayat::create(['lead_id' => $hidden->lead_id, 'user_id' => $other->id, 'tanggal_followup' => now(), 'metode_followup' => 'Telepon', 'status_kontak' => 'Terhubung']);

        $this->actingAs($marketing);

        $this->assertSame([$visible->lead_id], LeadResource::getEloquentQuery()->pluck('lead_id')->all());
        $this->assertSame([$visible->lead_id], FollowupRiwayatResource::getEloquentQuery()->pluck('lead_id')->all());
    }

    public function test_admin_cabang_only_sees_operational_data_from_own_branch(): void
    {
        $branchA = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $branchB = Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]);
        $admin = User::factory()->create(['cabang_id' => $branchA->cabang_id, 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $other = User::factory()->create(['cabang_id' => $branchB->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $visible = Lead::create($this->leadData($branchA, $admin, 'Lead Bandung'));
        Lead::create($this->leadData($branchB, $other, 'Lead Jakarta'));

        $this->actingAs($admin);

        $this->assertSame([$visible->lead_id], LeadResource::getEloquentQuery()->pluck('lead_id')->all());
        $this->assertTrue(LeadResource::canCreate());
        $this->assertTrue(LeadResource::canEdit($visible));
    }

    public function test_direksi_can_read_all_branches_but_cannot_mutate_operational_data(): void
    {
        $branchA = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $branchB = Cabang::create(['kode_cabang' => 'JKP', 'nama' => 'JakPus', 'aktif' => true]);
        $staffA = User::factory()->create(['cabang_id' => $branchA->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $staffB = User::factory()->create(['cabang_id' => $branchB->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $leadA = Lead::create($this->leadData($branchA, $staffA, 'Lead Bandung'));
        $leadB = Lead::create($this->leadData($branchB, $staffB, 'Lead JakPus'));
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'cabang_id' => null, 'aktif' => true]);

        $this->actingAs($direksi);

        $this->assertEqualsCanonicalizing([$leadA->lead_id, $leadB->lead_id], LeadResource::getEloquentQuery()->pluck('lead_id')->all());
        $this->assertFalse(LeadResource::canCreate());
        $this->assertFalse(LeadResource::canEdit($leadA));
        $this->assertFalse(FollowupRiwayatResource::canCreate());
        $this->get('/admin/leads/create')->assertForbidden();
    }

    public function test_lead_status_options_and_automatic_transitions_are_consistent(): void
    {
        $this->assertSame([
            'Iklan' => 'Iklan',
            'Instagram' => 'Instagram',
            'Kunjungan' => 'Kunjungan',
            'Saudara' => 'Saudara',
            'Facebook' => 'Facebook',
            'Tiktok' => 'Tiktok',
        ], LeadSource::options());

        $this->assertSame([
            'Cold' => 'Cold',
            'Warm' => 'Warm',
            'Hot' => 'Hot',
            'Daftar' => 'Daftar',
            'Tidak Berminat' => 'Tidak Berminat',
        ], LeadStatus::options());

        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $lead = Lead::create($this->leadData($branch, $staff, 'Lead berproses'));

        FollowupRiwayat::create([
            'lead_id' => $lead->lead_id,
            'user_id' => $staff->id,
            'tanggal_followup' => now(),
            'metode_followup' => 'WhatsApp',
            'status_kontak' => 'Terhubung',
        ]);

        $this->assertSame(LeadStatus::Warm->value, $lead->refresh()->status_lead);

        $program = ProgramBimbel::create([
            'nama_program' => 'Program Intensif',
            'jenjang_program' => 'SMA',
            'biaya_pendidikan' => 5000000,
            'aktif' => true,
        ]);

        Pendaftaran::create([
            'lead_id' => $lead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Aktif',
        ]);

        $this->assertSame(LeadStatus::Daftar->value, $lead->refresh()->status_lead);
    }

    private function leadData(Cabang $branch, User $user, string $name): array
    {
        return ['nama' => $name, 'no_wa' => '08123456789', 'cabang_id' => $branch->cabang_id, 'status_lead' => 'Cold', 'diinput_oleh_user_id' => $user->id, 'diserahkan_ke_user_id' => $user->id, 'tanggal_masuk' => today()];
    }
}
