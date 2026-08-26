<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Pendaftarans\Pages\CreatePendaftaran;
use App\Filament\Resources\Pendaftarans\PendaftaranResource;
use App\Filament\Resources\Pembayarans\Pages\CreatePembayaran;
use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_pages_render_and_direksi_is_read_only(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);

        $this->assertTrue($superadmin->canManageOperationalData());
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

        $this->actingAs($superadmin)->get('/admin/pendaftaran')->assertOk()->assertSee('Pendaftaran');
        $this->get('/admin/pendaftaran/create')->assertOk()->assertSee('Data pendaftaran');
        $this->get('/admin/pembayaran')->assertOk()->assertSee('Pembayaran');
        $this->get('/admin/pembayaran/create')->assertOk()->assertSee('Data pembayaran');

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
        $this->get('/admin/pembayaran/create')->assertForbidden();
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
                'kelas_angkatan' => 'Angkatan 2026',
                'status_pendaftaran' => 'Aktif',
                'cabang_id' => $jaksel->cabang_id,
            ])->call('create')->assertHasNoFormErrors();

        $registration = Pendaftaran::where('lead_id', $lead->lead_id)->firstOrFail();
        $this->assertSame($bandung->cabang_id, $registration->cabang_id);
        $this->assertSame('Daftar', $lead->refresh()->status_lead);

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
        return ['nama' => $name, 'no_wa' => '08123456789', 'cabang_id' => $branch->cabang_id, 'status_lead' => 'Hot', 'diinput_oleh_user_id' => $user->id, 'diserahkan_ke_user_id' => $user->id, 'tanggal_masuk' => today()];
    }
}
