<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\AuditAktivitas;
use App\Models\ActivityLog;
use App\Models\Cabang;
use App\Models\FollowupRiwayat;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_changes_are_audited_without_sensitive_fields(): void
    {
        $branch = $this->branch('BDG', 'Bandung');
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $this->actingAs($staff);
        $lead = Lead::create($this->leadData($branch, $staff, 'Lead Audit'));
        $lead->update(['status_lead' => 'Hot', 'no_wa' => '08999999999']);

        $created = ActivityLog::whereMorphedTo('subject', $lead)->where('event', 'created')->firstOrFail();
        $updated = ActivityLog::whereMorphedTo('subject', $lead)->where('event', 'updated')->latest('created_at')->firstOrFail();
        $this->assertSame($staff->id, $created->user_id);
        $this->assertSame($branch->getKey(), $created->cabang_id);
        $this->assertNotContains('no_wa', $created->properties['fields']);
        $this->assertContains('status_lead', $updated->properties['fields']);
        $this->assertNotContains('no_wa', $updated->properties['fields']);
    }

    public function test_lead_assignment_creates_filament_database_notification(): void
    {
        $branch = $this->branch('BDG', 'Bandung');
        $admin = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);

        $this->actingAs($admin);
        Lead::create($this->leadData($branch, $staff, 'Lead Ditugaskan'));

        $notification = $staff->notifications()->firstOrFail();
        $this->assertSame('filament', $notification->data['format']);
        $this->assertSame('Lead baru ditugaskan', $notification->data['title']);
        $this->assertSame(1, count($notification->data['actions']));
    }

    public function test_school_class_change_is_recorded_with_old_and_new_values(): void
    {
        $branch = $this->branch('BDG', 'Bandung');
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $this->actingAs($staff);
        $lead = Lead::create([...$this->leadData($branch, $staff, 'Lead Kelas'), 'kelas_sekolah' => 'XI']);

        $lead->update(['kelas_sekolah' => 'XII']);

        $history = ActivityLog::whereMorphedTo('subject', $lead)->where('event', 'class_changed')->sole();
        $this->assertSame('XI', $history->properties['old']);
        $this->assertSame('XII', $history->properties['new']);
        $this->assertSame('kelas_sekolah', $history->properties['field']);
    }

    public function test_reminder_command_is_idempotent_for_followups_and_payments(): void
    {
        $branch = $this->branch('BDG', 'Bandung');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $admin = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $lead = Lead::create($this->leadData($branch, $staff, 'Lead Terlambat'));
        FollowupRiwayat::create(['lead_id' => $lead->getKey(), 'user_id' => $staff->id, 'tanggal_followup' => now()->subDays(3), 'metode_followup' => 'WhatsApp', 'status_kontak' => 'Terhubung', 'tanggal_followup_berikutnya' => now()->subHour()]);
        $this->pendingPayment($branch, $staff, $lead);

        $this->artisan('crm:send-reminders')->assertSuccessful();
        $staffCount = $staff->notifications()->where('data->title', 'Follow-up melewati jadwal')->count();
        $adminCount = $admin->notifications()->where('data->title', 'Pembayaran perlu diperiksa')->count();
        $superCount = $superadmin->notifications()->where('data->title', 'Pembayaran perlu diperiksa')->count();
        $this->artisan('crm:send-reminders')->assertSuccessful();

        $this->assertSame(1, $staffCount);
        $this->assertSame(1, $adminCount);
        $this->assertSame(1, $superCount);
        $this->assertSame($staffCount, $staff->notifications()->where('data->title', 'Follow-up melewati jadwal')->count());
        $this->assertSame($adminCount, $admin->notifications()->where('data->title', 'Pembayaran perlu diperiksa')->count());
    }

    public function test_only_superadmin_can_access_audit_activity_page(): void
    {
        $bandung = $this->branch('BDG', 'Bandung');
        $jaksel = $this->branch('JKS', 'JakSel Rahasia');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $admin = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $other = User::factory()->create(['cabang_id' => $jaksel->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $keuangan = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Keuangan, 'aktif' => true]);
        ActivityLog::create(['user_id' => $staff->id, 'cabang_id' => $bandung->getKey(), 'event' => 'updated', 'description' => 'Aktivitas Bandung Aman', 'created_at' => now()]);
        ActivityLog::create(['user_id' => $other->id, 'cabang_id' => $jaksel->getKey(), 'event' => 'updated', 'description' => 'Aktivitas Jakarta Rahasia', 'created_at' => now()]);

        $this->actingAs($superadmin)->get('/admin/audit-aktivitas')
            ->assertOk()
            ->assertSee('Aktivitas Bandung Aman')
            ->assertSee('Aktivitas Jakarta Rahasia');
        $this->assertTrue(AuditAktivitas::shouldRegisterNavigation());

        foreach ([$admin, $staff, $direksi, $keuangan] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser);
            $this->assertFalse(AuditAktivitas::shouldRegisterNavigation());
            $this->get('/admin/audit-aktivitas')
                ->assertForbidden()
                ->assertSee('Akses dibatasi')
                ->assertSee('Kembali ke dashboard');
        }
    }

    private function branch(string $code, string $name): Cabang
    {
        return Cabang::create(['kode_cabang' => $code, 'nama' => $name, 'aktif' => true]);
    }

    private function leadData(Cabang $branch, User $staff, string $name): array
    {
        return ['nama' => $name, 'no_wa' => '08123456789', 'cabang_id' => $branch->getKey(), 'status_lead' => 'Cold', 'diinput_oleh_user_id' => $staff->id, 'diserahkan_ke_user_id' => $staff->id, 'tanggal_masuk' => today()];
    }

    private function pendingPayment(Cabang $branch, User $staff, Lead $lead): Pembayaran
    {
        $program = ProgramBimbel::create(['nama_program' => 'Program Audit', 'jenjang_program' => 'SMA', 'biaya_pendidikan' => 5000000, 'aktif' => true]);
        $registration = Pendaftaran::create(['lead_id' => $lead->getKey(), 'program_bimbel_id' => $program->getKey(), 'cabang_id' => $branch->getKey(), 'tanggal_daftar' => today(), 'status_pendaftaran' => 'Aktif']);

        return Pembayaran::create(['pendaftaran_id' => $registration->getKey(), 'tanggal_pembayaran' => now(), 'nominal' => 1000000, 'metode_pembayaran' => 'Transfer Bank', 'status_pembayaran' => 'Menunggu']);
    }
}
