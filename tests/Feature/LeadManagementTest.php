<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Models\Cabang;
use App\Models\FollowupRiwayat;
use App\Models\Lead;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\SumberLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_source_is_stored_as_a_foreign_key_with_legacy_name_compatibility(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $source = SumberLead::query()->where('nama', 'Instagram')->sole();

        $lead = Lead::create([
            'nama' => 'Lead Instagram',
            'no_wa' => '081234567898',
            'sumber_lead' => 'Instagram',
            'diinput_oleh_user_id' => $admin->getKey(),
        ]);

        $this->assertFalse(Schema::hasColumn('leads', 'sumber_lead'));
        $this->assertSame($source->getKey(), $lead->sumber_lead_id);
        $this->assertSame('Instagram', $lead->sumber_lead);
        $this->assertDatabaseHas('leads', [
            'lead_id' => $lead->getKey(),
            'sumber_lead_id' => $source->getKey(),
        ]);
    }

    public function test_lead_can_be_created_with_only_name_and_whatsapp_number(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'cabang_id' => null, 'aktif' => true]);

        $lead = Lead::create([
            'nama' => 'Lead Minimal',
            'no_wa' => '081234567899',
            'diinput_oleh_user_id' => $admin->getKey(),
        ]);

        $this->assertNull($lead->cabang_id);
        $this->assertNull($lead->sekolah_id);
        $this->assertNull($lead->minat_program_id);
        $this->assertNull($lead->diserahkan_ke_user_id);
        $this->assertNull($lead->sumber_lead);
        $this->assertSame(LeadStatus::Cold->value, $lead->status_lead);
        $this->assertTrue($lead->tanggal_masuk->isToday());
    }

    public function test_lead_and_followup_pages_render_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::AdminCabang, 'aktif' => true]);

        $this->actingAs($admin)->get('/admin/leads')->assertOk()->assertSee('Leads');
        $this->actingAs($admin)->get('/admin/leads/create')
            ->assertOk()
            ->assertSee('Data calon siswa')
            ->assertSee('Kota asal')
            ->assertSee('Kelas sekolah saat ini')
            ->assertSee('Cold')
            ->assertSee('Warm')
            ->assertSee('Hot')
            ->assertDontSee('Daftar')
            ->assertSee('Tidak Berminat')
            ->assertSee('Sumber leads')
            ->assertSee('Instagram')
            ->assertSee('Kunjungan')
            ->assertSee('Tiktok');
        $this->actingAs($admin)->get('/admin/follow-up')->assertOk()->assertSee('Follow-up');
    }

    public function test_superadmin_cannot_mutate_leads_or_followups(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $lead = Lead::create([
            'nama' => 'Lead Read Only',
            'no_wa' => '081234567890',
            'diinput_oleh_user_id' => $superadmin->getKey(),
        ]);

        $this->actingAs($superadmin);
        $this->assertFalse($superadmin->canManageOperationalData());
        $this->assertFalse(LeadResource::canCreate());
        $this->assertFalse(LeadResource::canEdit($lead));
        $this->assertFalse(LeadResource::canDelete($lead));
        $this->assertFalse(FollowupRiwayatResource::canCreate());
        $this->assertFalse(FollowupRiwayatResource::canEdit($lead));
        $this->assertFalse(FollowupRiwayatResource::canDelete($lead));
        $this->get('/admin/leads/create')->assertForbidden();
        $this->get('/admin/follow-up/create')->assertForbidden();

        Livewire::actingAs($superadmin)
            ->test(ListLeads::class)
            ->assertTableBulkActionHidden('ubahTerpilih')
            ->assertTableBulkActionHidden('delete');
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

    public function test_only_lead_creator_or_assigned_pic_can_delete_unregistered_leads(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $creator = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $pic = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $other = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);

        $creatorLead = Lead::create($this->leadData($branch, $creator, 'Lead dibuat creator'));
        $creatorLead->update(['diserahkan_ke_user_id' => $other->id]);

        $picLead = Lead::create($this->leadData($branch, $other, 'Lead ditugaskan ke PIC'));
        $picLead->update(['diserahkan_ke_user_id' => $pic->id]);

        $otherLead = Lead::create($this->leadData($branch, $other, 'Lead milik user lain'));

        $registeredLead = Lead::create($this->leadData($branch, $creator, 'Lead sudah terdaftar'));
        $registeredLead->update(['diserahkan_ke_user_id' => $pic->id]);
        $program = ProgramBimbel::create([
            'nama_program' => 'Program Lead Terdaftar',
            'jenjang_program' => 'SMA',
            'biaya_pendidikan' => 5000000,
            'aktif' => true,
        ]);
        Pendaftaran::create([
            'lead_id' => $registeredLead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Aktif',
        ]);

        $this->actingAs($creator);
        $this->assertTrue(LeadResource::canDelete($creatorLead));
        $this->assertFalse(LeadResource::canDelete($picLead));
        $this->assertFalse(LeadResource::canDelete($otherLead));
        $this->assertFalse(LeadResource::canDelete($registeredLead));

        Livewire::actingAs($creator)->test(ListLeads::class)
            ->assertTableActionVisible('delete', $creatorLead)
            ->assertTableActionHidden('delete', $registeredLead)
            ->callTableAction('delete', $creatorLead);
        $this->assertDatabaseMissing('leads', ['lead_id' => $creatorLead->lead_id]);

        $this->actingAs($pic);
        $this->assertTrue(LeadResource::canDelete($picLead));
        $this->assertFalse(LeadResource::canDelete($registeredLead));
        $this->assertFalse(LeadResource::canDelete($otherLead));

        Livewire::actingAs($pic)->test(ListLeads::class)
            ->assertTableActionVisible('delete', $picLead)
            ->assertTableActionHidden('delete', $registeredLead)
            ->callTableAction('delete', $picLead);
        $this->assertDatabaseMissing('leads', ['lead_id' => $picLead->lead_id]);
        $this->assertDatabaseHas('leads', ['lead_id' => $registeredLead->lead_id]);

        $admin = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $adminLead = Lead::create($this->leadData($branch, $admin, 'Lead dibuat admin'));

        Livewire::actingAs($admin)->test(ListLeads::class)
            ->assertTableBulkActionVisible('delete')
            ->callTableBulkAction('delete', [$adminLead, $otherLead, $registeredLead]);

        $this->assertDatabaseMissing('leads', ['lead_id' => $adminLead->lead_id]);
        $this->assertDatabaseHas('leads', ['lead_id' => $otherLead->lead_id]);
        $this->assertDatabaseHas('leads', ['lead_id' => $registeredLead->lead_id]);
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
        Livewire::actingAs($direksi)
            ->test(ListLeads::class)
            ->assertTableBulkActionHidden('ubahTerpilih')
            ->assertTableBulkActionHidden('delete');
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

    public function test_lead_cannot_be_manually_marked_as_registered_without_an_active_registration(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $lead = Lead::create($this->leadData($branch, $staff, 'Lead Belum Terdaftar'));

        try {
            $lead->update(['status_lead' => LeadStatus::Daftar->value]);
            $this->fail('Lead tanpa pendaftaran aktif tidak boleh diubah menjadi Daftar.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status_lead', $exception->errors());
        }

        $this->assertSame(LeadStatus::Cold->value, $lead->refresh()->status_lead);
    }

    private function leadData(Cabang $branch, User $user, string $name): array
    {
        return ['nama' => $name, 'no_wa' => '08'.str_pad((string) sprintf('%u', crc32($name)), 10, '0', STR_PAD_LEFT), 'cabang_id' => $branch->cabang_id, 'status_lead' => 'Cold', 'diinput_oleh_user_id' => $user->id, 'diserahkan_ke_user_id' => $user->id, 'tanggal_masuk' => today()];
    }
}
