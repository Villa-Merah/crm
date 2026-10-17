<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Pembayarans\Pages\CreatePembayaran;
use App\Filament\Resources\Pendaftarans\Pages\ListPendaftarans;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentStatusVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_cannot_select_legacy_paid_status_for_a_new_payment(): void
    {
        $branch = $this->branch();
        $finance = User::factory()->create([
            'cabang_id' => $branch->getKey(),
            'role' => UserRole::Keuangan,
            'aktif' => true,
        ]);
        $registration = $this->registration($branch, $finance, 'Siswa Pembayaran Baru');

        Livewire::actingAs($finance)->test(CreatePembayaran::class)
            ->set('data', [
                'pendaftaran_id' => $registration->getKey(),
                'tanggal_pembayaran' => now()->format('Y-m-d H:i:s'),
                'nominal' => 500000,
                'metode_pembayaran' => 'Transfer Bank',
                'status_pembayaran' => 'Lunas',
            ])
            ->call('create')
            ->assertHasFormErrors(['status_pembayaran']);

        $this->assertDatabaseMissing('pembayaran', [
            'pendaftaran_id' => $registration->getKey(),
            'status_pembayaran' => 'Lunas',
        ]);
    }

    public function test_registration_list_hides_verified_and_legacy_paid_records_without_deleting_them(): void
    {
        $branch = $this->branch();
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $owner = User::factory()->create([
            'cabang_id' => $branch->getKey(),
            'role' => UserRole::Staff,
            'aktif' => true,
        ]);
        $pending = $this->registration($branch, $owner, 'Siswa Menunggu');
        $verified = $this->registration($branch, $owner, 'Siswa Terverifikasi');
        $legacyPaid = $this->registration($branch, $owner, 'Siswa Lunas Lama');

        $this->payment($pending, 'Menunggu');
        $this->payment($verified, 'Terverifikasi');
        $this->payment($legacyPaid, 'Lunas');

        Livewire::actingAs($superadmin)->test(ListPendaftarans::class)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$verified, $legacyPaid]);

        $this->assertDatabaseHas('pendaftaran', ['pendaftaran_id' => $verified->getKey()]);
        $this->assertDatabaseHas('pendaftaran', ['pendaftaran_id' => $legacyPaid->getKey()]);
    }

    private function branch(): Cabang
    {
        return Cabang::create([
            'kode_cabang' => 'BDG',
            'nama' => 'Bandung',
            'aktif' => true,
        ]);
    }

    private function registration(Cabang $branch, User $owner, string $name): Pendaftaran
    {
        $program = ProgramBimbel::firstOrCreate(
            ['nama_program' => 'Program Intensif'],
            ['jenjang_program' => 'SMA', 'biaya_pendidikan' => 5000000, 'aktif' => true],
        );
        $lead = Lead::create([
            'nama' => $name,
            'no_wa' => '08'.str_pad((string) sprintf('%u', crc32($name)), 10, '0', STR_PAD_LEFT),
            'cabang_id' => $branch->getKey(),
            'status_lead' => 'Hot',
            'diinput_oleh_user_id' => $owner->getKey(),
            'diserahkan_ke_user_id' => $owner->getKey(),
            'tanggal_masuk' => today(),
        ]);

        return Pendaftaran::create([
            'lead_id' => $lead->getKey(),
            'program_bimbel_id' => $program->getKey(),
            'cabang_id' => $branch->getKey(),
            'tanggal_daftar' => today(),
            'status_pendaftaran' => 'Aktif',
        ]);
    }

    private function payment(Pendaftaran $registration, string $status): Pembayaran
    {
        return Pembayaran::create([
            'pendaftaran_id' => $registration->getKey(),
            'tanggal_pembayaran' => now(),
            'nominal' => 1000000,
            'metode_pembayaran' => 'Tunai',
            'status_pembayaran' => $status,
        ]);
    }
}
