<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Laporan;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\TargetKinerjaCabang;
use App\Models\TargetKinerjaUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private static int $waPhoneSequence = 10000000;

    public function test_report_renders_real_metrics_and_csv_export(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $this->createTransaction($branch, $staff, 3750000);
        TargetKinerjaCabang::create(['cabang_id' => $branch->getKey(), 'tahun' => now()->year, 'bulan' => now()->month, 'status' => 'Aktif', 'target_omzet' => 10000000, 'target_siswa' => 5]);

        $this->actingAs($superadmin)->get('/admin/laporan')
            ->assertOk()->assertSee('Laporan kinerja SIVMI')->assertSee('Rp 3,8 jt')->assertSee('Bandung')->assertSee('Ekspor CSV');

        Livewire::actingAs($superadmin)->test(Laporan::class)
            ->call('exportCsv')
            ->assertFileDownloaded('laporan-sivmi-'.now()->format('Y-m').'.csv');
    }

    public function test_staff_report_ignores_tampered_branch_and_user_filters(): void
    {
        $bandung = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $jaksel = Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel Rahasia', 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $otherStaff = User::factory()->create(['cabang_id' => $jaksel->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $this->createTransaction($bandung, $staff, 1500000);
        $this->createTransaction($jaksel, $otherStaff, 9000000);
        $target = TargetKinerjaCabang::create(['cabang_id' => $bandung->getKey(), 'tahun' => now()->year, 'bulan' => now()->month, 'status' => 'Aktif', 'target_omzet' => 20000000, 'target_siswa' => 10]);
        TargetKinerjaUser::create(['target_kinerja_cabang_id' => $target->getKey(), 'user_id' => $staff->id, 'target_omzet' => 5000000, 'target_siswa' => 3]);

        $this->actingAs($staff)->get('/admin/laporan?cabang='.$jaksel->getKey().'&pengguna='.$otherStaff->id)
            ->assertOk()->assertSee('Bandung')->assertDontSee('JakSel Rahasia')->assertSee('Rp 1,5 jt')->assertDontSee('Rp 9,0 jt');
    }

    public function test_direksi_can_read_all_branches_and_empty_state_is_available(): void
    {
        Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);

        $this->actingAs($direksi)->get('/admin/laporan')
            ->assertOk()->assertSee('Bandung')->assertSee('JakSel')->assertSee('Belum ada data laporan');
    }

    private function createTransaction(Cabang $branch, User $staff, int $nominal): void
    {
        $program = ProgramBimbel::firstOrCreate(
            ['nama_program' => 'Program Laporan'],
            ['jenjang_program' => 'SMA', 'biaya_pendidikan' => 5000000, 'aktif' => true],
        );
        $phone = '081'.str_pad((string) self::$waPhoneSequence++, 9, '0', STR_PAD_LEFT);
        $lead = Lead::create(['nama' => 'Siswa '.$branch->kode_cabang, 'no_wa' => $phone, 'cabang_id' => $branch->getKey(), 'status_lead' => 'Daftar', 'diinput_oleh_user_id' => $staff->id, 'diserahkan_ke_user_id' => $staff->id, 'tanggal_masuk' => today()]);
        $registration = Pendaftaran::create(['lead_id' => $lead->getKey(), 'program_bimbel_id' => $program->getKey(), 'cabang_id' => $branch->getKey(), 'tanggal_daftar' => today(), 'status_pendaftaran' => 'Aktif']);
        Pembayaran::create(['pendaftaran_id' => $registration->getKey(), 'tanggal_pembayaran' => now(), 'nominal' => $nominal, 'metode_pembayaran' => 'Transfer Bank', 'status_pembayaran' => 'Terverifikasi']);
    }
}
