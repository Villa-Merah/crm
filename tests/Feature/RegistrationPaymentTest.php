<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Pembayarans\Pages\CreatePembayaran;
use App\Filament\Resources\Pembayarans\Pages\EditPembayaran;
use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Filament\Resources\Pendaftarans\Pages\CreatePendaftaran;
use App\Filament\Resources\Pendaftarans\Pages\ListPendaftarans;
use App\Filament\Resources\Pendaftarans\PendaftaranResource;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cabang_can_manage_registration_and_record_payment_while_superadmin_and_direksi_are_read_only(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $admin = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $registration = $this->registration($branch, $admin, $this->program(), 'Siswa Read Only');
        $payment = $this->payment($registration);
        $pendingPayment = Pembayaran::create([
            'pendaftaran_id' => $registration->getKey(),
            'tanggal_pembayaran' => now(),
            'nominal' => 250000,
            'metode_pembayaran' => 'Tunai',
            'status_pembayaran' => 'Menunggu',
        ]);

        $this->assertFalse($superadmin->canManageOperationalData());
        $this->assertTrue($superadmin->hasRole(UserRole::Superadmin));
        $superadminNavigation = $this->actingAs($superadmin)->get('/admin/dashboard');
        $superadminNavigation->assertOk();
        $superadminNavigation->assertSee('MASTER DATA');
        $superadminNavigation->assertSee('Pendaftaran');
        $superadminNavigation->assertSee('Pembayaran');
        $superadminNavigation->assertSee('Program Bimbel');
        $superadminNavigation->assertDontSee('Daftar Pendaftaran');
        $superadminNavigation->assertDontSee('Pendaftaran Baru');
        $superadminNavigation->assertDontSee('Daftar Pembayaran');
        $superadminNavigation->assertDontSee('Catat Pembayaran');
        $superadminNavigation->assertDontSee('Daftar Program');
        $superadminNavigation->assertDontSee('Program Baru');
        $superadminNavigation->assertDontSee('/admin/pendaftaran/create', false);
        $superadminNavigation->assertDontSee('/admin/pembayaran/create', false);

        $this->actingAs($superadmin)->get('/admin/pendaftaran')->assertOk()->assertSee('Pendaftaran');
        $this->get('/admin/pendaftaran/create')->assertForbidden();
        $this->get("/admin/pendaftaran/{$registration->getKey()}/edit")->assertForbidden();
        $this->get('/admin/pembayaran')->assertOk()->assertSee('Pembayaran');
        $this->get('/admin/pembayaran/create')->assertForbidden();
        $this->get("/admin/pembayaran/{$payment->getKey()}/edit")->assertForbidden();

        $this->actingAs($admin)->get('/admin/pendaftaran')->assertOk();
        $this->get('/admin/pembayaran')->assertOk();
        $this->assertTrue($admin->canManageRegistrations());
        $this->assertTrue($admin->canRecordPayments());
        $this->assertFalse($admin->canVerifyPayments());
        $this->get('/admin/pendaftaran/create')->assertOk();
        $this->get("/admin/pendaftaran/{$registration->getKey()}/edit")->assertOk();
        $this->get('/admin/pembayaran/create')->assertOk();
        $this->get("/admin/pembayaran/{$payment->getKey()}/edit")->assertForbidden();
        $this->get("/admin/pembayaran/{$pendingPayment->getKey()}/edit")->assertOk();

        Livewire::actingAs($admin)->test(CreatePembayaran::class)
            ->set('data', [
                'pendaftaran_id' => $registration->getKey(),
                'tanggal_pembayaran' => now()->toDateTimeString(),
                'nominal' => 100000,
                'metode_pembayaran' => 'Tunai',
                'status_pembayaran' => 'Terverifikasi',
            ])
            ->call('create')
            ->assertHasFormErrors(['status_pembayaran']);

        $this->assertDatabaseMissing('pembayaran', ['nominal' => 100000]);

        Livewire::actingAs($admin)->test(CreatePembayaran::class)
            ->set('data', [
                'pendaftaran_id' => $registration->getKey(),
                'tanggal_pembayaran' => now()->toDateTimeString(),
                'nominal' => 100000,
                'metode_pembayaran' => 'Tunai',
                'status_pembayaran' => 'Menunggu',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Menunggu', Pembayaran::query()->where('nominal', 100000)->value('status_pembayaran'));

        Livewire::actingAs($admin)->test(EditPembayaran::class, ['record' => $pendingPayment->getKey()])
            ->set('data.status_pembayaran', 'Terverifikasi')
            ->call('save')
            ->assertHasFormErrors(['status_pembayaran']);

        $this->assertSame('Menunggu', $pendingPayment->refresh()->status_pembayaran);

        $this->actingAs($direksi)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Pendaftaran')
            ->assertDontSee('Pendaftaran Baru')
            ->assertSee('Pembayaran')
            ->assertDontSee('Catat Pembayaran')
            ->assertSee('Program Bimbel')
            ->assertDontSee('Program Baru');
        $this->get('/admin/pendaftaran')->assertOk();
        $this->get('/admin/pembayaran')->assertOk();
        $this->get('/admin/pendaftaran/create')->assertForbidden();
        $this->get("/admin/pendaftaran/{$registration->getKey()}/edit")->assertForbidden();
        $this->get('/admin/pembayaran/create')->assertForbidden();
        $this->get("/admin/pembayaran/{$payment->getKey()}/edit")->assertForbidden();
    }

    public function test_keuangan_manages_payments_across_branches_and_cannot_manage_registration_or_sales(): void
    {
        [$bandung, $jaksel] = $this->branches();
        $keuangan = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Keuangan, 'aktif' => true]);
        $staffBandung = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $staffJaksel = User::factory()->create(['cabang_id' => $jaksel->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $registrationBandung = $this->registration($bandung, $staffBandung, $program, 'Siswa Bandung');
        $registrationJaksel = $this->registration($jaksel, $staffJaksel, $program, 'Siswa Jakarta');
        $paymentBandung = $this->payment($registrationBandung);
        $paymentJaksel = $this->payment($registrationJaksel);

        $this->actingAs($keuangan);

        $this->assertFalse($keuangan->canManageOperationalData());
        $this->assertFalse($keuangan->canViewSalesData());
        $this->assertFalse($keuangan->canManageRegistrations());
        $this->assertTrue($keuangan->hasGlobalFinanceScope());
        $this->assertFalse($keuangan->hasGlobalBranchAccess());
        $this->assertTrue($keuangan->canRecordPayments());
        $this->assertTrue($keuangan->canVerifyPayments());
        $this->assertEqualsCanonicalizing(
            [$registrationBandung->getKey(), $registrationJaksel->getKey()],
            PendaftaranResource::getEloquentQuery()->pluck('pendaftaran_id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$paymentBandung->getKey(), $paymentJaksel->getKey()],
            PembayaranResource::getEloquentQuery()->pluck('pembayaran_id')->all(),
        );

        $financeDashboard = $this->get('/admin/dashboard')->assertOk();
        $financeDashboard->assertDontSee('/admin/leads', false);
        $financeDashboard->assertDontSee('/admin/follow-up', false);
        $financeDashboard->assertSee('/admin/pendaftaran', false);
        $financeDashboard->assertSee('/admin/pembayaran/create', false);
        $this->get('/admin/pendaftaran')->assertOk();
        $this->get('/admin/pendaftaran/create')->assertForbidden();
        $this->get('/admin/leads')->assertForbidden();
        $this->get('/admin/follow-up')->assertForbidden();
        $this->get('/admin/cabang')->assertForbidden();
        $this->get('/admin/sekolah')->assertForbidden();
        $this->get('/admin/program-bimbel')->assertForbidden();
        $this->get('/admin/target-kinerja')->assertForbidden();
        $this->get('/admin/audit-aktivitas')->assertForbidden();
        $this->get('/admin/pembayaran')->assertOk();
        $this->get('/admin/pembayaran/create')->assertOk();

        Livewire::actingAs($keuangan)->test(CreatePembayaran::class)
            ->set('data', [
                'pendaftaran_id' => $registrationBandung->getKey(),
                'tanggal_pembayaran' => now()->format('Y-m-d H:i:s'),
                'nominal' => 500000,
                'metode_pembayaran' => 'Transfer Bank',
                'status_pembayaran' => 'Lunas',
            ])->call('create')->assertHasFormErrors(['status_pembayaran']);

        $this->assertDatabaseMissing('pembayaran', [
            'pendaftaran_id' => $registrationBandung->getKey(),
            'nominal' => 500000,
            'status_pembayaran' => 'Lunas',
        ]);

        Livewire::actingAs($keuangan)->test(CreatePembayaran::class)
            ->set('data', [
                'pendaftaran_id' => $registrationJaksel->getKey(),
                'tanggal_pembayaran' => now()->format('Y-m-d H:i:s'),
                'nominal' => 750000,
                'metode_pembayaran' => 'Transfer Bank',
                'status_pembayaran' => 'Terverifikasi',
            ])->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseHas('pembayaran', [
            'pendaftaran_id' => $registrationJaksel->getKey(),
            'nominal' => 750000,
            'status_pembayaran' => 'Terverifikasi',
        ]);
    }

    public function test_staff_scope_only_contains_owned_registration_and_payment(): void
    {
        [$bandung, $jaksel] = $this->branches();
        $staff = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $other = User::factory()->create(['cabang_id' => $jaksel->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $ownRegistration = $this->registration($bandung, $staff, $program, 'Siswa Bandung');
        $otherRegistration = $this->registration($jaksel, $other, $program, 'Siswa Jakarta');
        $ownPayment = $this->payment($ownRegistration);
        $this->payment($otherRegistration);

        $this->actingAs($staff);

        $this->assertSame([$ownRegistration->pendaftaran_id], PendaftaranResource::getEloquentQuery()->pluck('pendaftaran_id')->all());
        $this->assertSame([$ownPayment->pembayaran_id], PembayaranResource::getEloquentQuery()->pluck('pembayaran_id')->all());
    }

    public function test_registration_list_hides_verified_and_legacy_paid_records_without_deleting_them(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $admin = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $program = $this->program();
        $pendingRegistration = $this->registration($branch, $admin, $program, 'Siswa Menunggu');
        $verifiedRegistration = $this->registration($branch, $admin, $program, 'Siswa Terverifikasi');
        $legacyPaidRegistration = $this->registration($branch, $admin, $program, 'Siswa Lunas Lama');

        Pembayaran::create([
            'pendaftaran_id' => $pendingRegistration->getKey(),
            'tanggal_pembayaran' => now(),
            'nominal' => 500000,
            'metode_pembayaran' => 'Transfer Bank',
            'status_pembayaran' => 'Menunggu',
        ]);
        $this->payment($verifiedRegistration);
        Pembayaran::create([
            'pendaftaran_id' => $legacyPaidRegistration->getKey(),
            'tanggal_pembayaran' => now(),
            'nominal' => 1000000,
            'metode_pembayaran' => 'Tunai',
            'status_pembayaran' => 'Lunas',
        ]);

        Livewire::actingAs($admin)->test(ListPendaftarans::class)
            ->assertCanSeeTableRecords([$pendingRegistration])
            ->assertCanNotSeeTableRecords([$verifiedRegistration, $legacyPaidRegistration]);

        $this->assertDatabaseHas('pendaftaran', ['pendaftaran_id' => $verifiedRegistration->getKey()]);
        $this->assertDatabaseHas('pendaftaran', ['pendaftaran_id' => $legacyPaidRegistration->getKey()]);
    }

    public function test_staff_can_register_owned_lead_and_payment_starts_unverified(): void
    {
        [$bandung, $jaksel] = $this->branches();
        $staff = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $lead = Lead::create($this->leadData($bandung, $staff, 'Calon Siswa Baru'));

        Livewire::actingAs($staff)->test(CreatePendaftaran::class)
            ->set('data', [
                'lead_id' => $lead->lead_id,
                'program_bimbel_id' => $program->program_bimbel_id,
                'tanggal_daftar' => today()->toDateString(),
                'status_pendaftaran' => 'Aktif',
            ])->call('create')->assertHasFormErrors(['status_pendaftaran']);

        Livewire::actingAs($staff)->test(CreatePendaftaran::class)
            ->set('data', [
                'lead_id' => $lead->lead_id,
                'program_bimbel_id' => $program->program_bimbel_id,
                'tanggal_daftar' => today()->toDateString(),
                'kelas_angkatan' => 'Angkatan 2026',
                'status_pendaftaran' => 'Menunggu',
                'cabang_id' => $jaksel->cabang_id,
            ])->call('create')->assertHasNoFormErrors();

        $registration = Pendaftaran::where('lead_id', $lead->lead_id)->firstOrFail();
        $this->assertSame($bandung->cabang_id, $registration->cabang_id);
        $this->assertSame('Menunggu', $registration->status_pendaftaran);
        $this->assertSame('Hot', $lead->refresh()->status_lead);

        Livewire::actingAs($staff)->test(CreatePembayaran::class)
            ->set('data', [
                'pendaftaran_id' => $registration->pendaftaran_id,
                'tanggal_pembayaran' => now()->format('Y-m-d H:i:s'),
                'nominal' => 1500000,
                'metode_pembayaran' => 'Transfer Bank',
                'status_pembayaran' => 'Lunas',
            ])->call('create')->assertHasFormErrors(['status_pembayaran']);

        Livewire::actingAs($staff)->test(CreatePembayaran::class)
            ->set('data', [
                'pendaftaran_id' => $registration->pendaftaran_id,
                'tanggal_pembayaran' => now()->format('Y-m-d H:i:s'),
                'nominal' => 1500000,
                'metode_pembayaran' => 'Transfer Bank',
                'status_pembayaran' => 'Menunggu',
            ])->call('create')->assertHasNoFormErrors();

        $payment = Pembayaran::where('pendaftaran_id', $registration->pendaftaran_id)->firstOrFail();
        $this->assertSame('Menunggu', $payment->status_pembayaran);
        $this->assertTrue(PembayaranResource::canEdit($payment));
    }

    public function test_verified_payment_activates_its_registration_and_updates_lead_status(): void
    {
        [$branch] = $this->branches();
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $lead = Lead::create($this->leadData($branch, $staff, 'Siswa Aktivasi Otomatis'));
        $registration = Pendaftaran::create([
            'lead_id' => $lead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Menunggu',
        ]);

        $payment = Pembayaran::create([
            'pendaftaran_id' => $registration->pendaftaran_id,
            'tanggal_pembayaran' => now(),
            'nominal' => 1000000,
            'metode_pembayaran' => 'Transfer Bank',
            'status_pembayaran' => 'Menunggu',
        ]);
        $this->assertSame('Menunggu', $registration->refresh()->status_pendaftaran);

        $payment->update(['status_pembayaran' => 'Terverifikasi']);

        $this->assertSame('Aktif', $registration->refresh()->status_pendaftaran);
        $this->assertSame('Daftar', $lead->refresh()->status_lead);
    }

    public function test_creating_verified_payment_activates_a_waiting_registration(): void
    {
        [$branch] = $this->branches();
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $lead = Lead::create($this->leadData($branch, $staff, 'Siswa Dibayar Langsung'));
        $registration = Pendaftaran::create([
            'lead_id' => $lead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Menunggu',
        ]);

        Pembayaran::create([
            'pendaftaran_id' => $registration->pendaftaran_id,
            'tanggal_pembayaran' => now(),
            'nominal' => 1000000,
            'metode_pembayaran' => 'Transfer Bank',
            'status_pembayaran' => 'Terverifikasi',
        ]);

        $this->assertSame('Aktif', $registration->refresh()->status_pendaftaran);
        $this->assertSame('Daftar', $lead->refresh()->status_lead);
    }

    public function test_legacy_lunas_payment_activates_only_a_waiting_registration(): void
    {
        [$branch] = $this->branches();
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $waitingLead = Lead::create($this->leadData($branch, $staff, 'Siswa Lunas Menunggu'));
        $waitingRegistration = Pendaftaran::create([
            'lead_id' => $waitingLead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Menunggu',
        ]);
        $completedLead = Lead::create($this->leadData($branch, $staff, 'Siswa Lunas Selesai'));
        $completedRegistration = Pendaftaran::create([
            'lead_id' => $completedLead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Selesai',
        ]);

        Pembayaran::create([
            'pendaftaran_id' => $waitingRegistration->pendaftaran_id,
            'tanggal_pembayaran' => now(),
            'nominal' => 1000000,
            'metode_pembayaran' => 'Tunai',
            'status_pembayaran' => 'Lunas',
        ]);
        Pembayaran::create([
            'pendaftaran_id' => $completedRegistration->pendaftaran_id,
            'tanggal_pembayaran' => now(),
            'nominal' => 1000000,
            'metode_pembayaran' => 'Tunai',
            'status_pembayaran' => 'Lunas',
        ]);

        $this->assertSame('Aktif', $waitingRegistration->refresh()->status_pendaftaran);
        $this->assertSame('Daftar', $waitingLead->refresh()->status_lead);
        $this->assertSame('Selesai', $completedRegistration->refresh()->status_pendaftaran);
        $this->assertSame('Hot', $completedLead->refresh()->status_lead);
    }

    public function test_data_migration_activates_existing_waiting_registrations_with_validated_payments(): void
    {
        [$branch] = $this->branches();
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $verifiedLead = Lead::create($this->leadData($branch, $staff, 'Siswa Legacy Terverifikasi'));
        $verifiedRegistration = Pendaftaran::create([
            'lead_id' => $verifiedLead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Menunggu',
        ]);
        $cancelledLead = Lead::create($this->leadData($branch, $staff, 'Siswa Legacy Dibatalkan'));
        $cancelledRegistration = Pendaftaran::create([
            'lead_id' => $cancelledLead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Dibatalkan',
        ]);

        DB::table('pembayaran')->insert([
            [
                'pendaftaran_id' => $verifiedRegistration->pendaftaran_id,
                'tanggal_pembayaran' => now(),
                'nominal' => 1000000,
                'metode_pembayaran' => 'Transfer Bank',
                'status_pembayaran' => 'Terverifikasi',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pendaftaran_id' => $cancelledRegistration->pendaftaran_id,
                'tanggal_pembayaran' => now(),
                'nominal' => 1000000,
                'metode_pembayaran' => 'Transfer Bank',
                'status_pembayaran' => 'Lunas',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $migration = require database_path('migrations/2026_10_18_000001_activate_waiting_registrations_with_validated_payments.php');
        $migration->up();

        $this->assertSame('Aktif', $verifiedRegistration->refresh()->status_pendaftaran);
        $this->assertSame('Daftar', $verifiedLead->refresh()->status_lead);
        $this->assertSame('Dibatalkan', $cancelledRegistration->refresh()->status_pendaftaran);
        $this->assertSame('Hot', $cancelledLead->refresh()->status_lead);
    }

    public function test_finance_cannot_reassign_a_verified_payment_to_another_registration(): void
    {
        [$branch] = $this->branches();
        $finance = User::factory()->create(['role' => UserRole::Keuangan, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $program = $this->program();
        $originalRegistration = $this->registration($branch, $staff, $program, 'Siswa Relasi Asal');
        $otherLead = Lead::create($this->leadData($branch, $staff, 'Siswa Relasi Tujuan'));
        $otherRegistration = Pendaftaran::create([
            'lead_id' => $otherLead->lead_id,
            'program_bimbel_id' => $program->program_bimbel_id,
            'cabang_id' => $branch->cabang_id,
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Menunggu',
        ]);
        $payment = $this->payment($originalRegistration);

        Livewire::actingAs($finance)->test(EditPembayaran::class, ['record' => $payment->getKey()])
            ->set('data.pendaftaran_id', $otherRegistration->getKey())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($originalRegistration->getKey(), $payment->refresh()->pendaftaran_id);
        $this->assertSame('Menunggu', $otherRegistration->refresh()->status_pendaftaran);
        $this->assertSame('Hot', $otherLead->refresh()->status_lead);
    }

    private function branches(): array
    {
        return [
            Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]),
            Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]),
        ];
    }

    private function program(): ProgramBimbel
    {
        return ProgramBimbel::create(['nama_program' => 'Program Intensif', 'jenjang_program' => 'SMA', 'biaya_pendidikan' => 5000000, 'aktif' => true]);
    }

    private function registration(Cabang $branch, User $user, ProgramBimbel $program, string $name): Pendaftaran
    {
        $lead = Lead::create($this->leadData($branch, $user, $name));

        return Pendaftaran::create(['lead_id' => $lead->lead_id, 'program_bimbel_id' => $program->program_bimbel_id, 'cabang_id' => $branch->cabang_id, 'tanggal_daftar' => today(), 'status_pendaftaran' => 'Aktif']);
    }

    private function payment(Pendaftaran $registration): Pembayaran
    {
        return Pembayaran::create(['pendaftaran_id' => $registration->pendaftaran_id, 'tanggal_pembayaran' => now(), 'nominal' => 1000000, 'metode_pembayaran' => 'Tunai', 'status_pembayaran' => 'Terverifikasi']);
    }

    private function leadData(Cabang $branch, User $user, string $name): array
    {
        return ['nama' => $name, 'no_wa' => '08'.str_pad((string) sprintf('%u', crc32($name)), 10, '0', STR_PAD_LEFT), 'cabang_id' => $branch->cabang_id, 'status_lead' => 'Hot', 'diinput_oleh_user_id' => $user->id, 'diserahkan_ke_user_id' => $user->id, 'tanggal_masuk' => today()];
    }
}
