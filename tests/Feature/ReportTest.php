<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Laporan;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\Pembayaran;
use App\Models\Pendaftaran;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\TargetKinerjaCabang;
use App\Models\TargetKinerjaUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private static int $waPhoneSequence = 10000000;

    public function test_report_renders_real_metrics_and_pdf_export(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $this->createTransaction($branch, $staff, 3750000);
        $target = TargetKinerjaCabang::create(['cabang_id' => $branch->getKey(), 'tahun' => now()->year, 'bulan' => now()->month, 'status' => 'Aktif', 'target_omzet' => 10000000, 'target_siswa' => 5]);
        TargetKinerjaUser::create(['target_kinerja_cabang_id' => $target->getKey(), 'user_id' => $staff->id, 'target_omzet' => 5000000, 'target_siswa' => 3]);

        $this->actingAs($superadmin)->get('/admin/laporan')
            ->assertOk()
            ->assertSee('Laporan kinerja SIVMI')
            ->assertSee('Rp 3,8 jt')
            ->assertSee('Bandung')
            ->assertSee('Unduh laporan PDF')
            ->assertSee('Leads berdasarkan program')
            ->assertSee('Klasifikasi program')
            ->assertSee('Top 5 sekolah')
            ->assertSee('Peringkat kinerja pengguna')
            ->assertSee('Sekolah BDG')
            ->assertSee('Program Laporan')
            ->assertSee($staff->name)
            ->assertSee('33,3%')
            ->assertSee('75,0%')
            ->assertSee('Progress target cabang');

        Livewire::actingAs($superadmin)->test(Laporan::class)
            ->call('exportPdf')
            ->assertFileDownloaded('laporan-sivmi-'.now()->format('Y-m').'.pdf', null, 'application/pdf');
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

    public function test_annual_report_aggregates_all_months_targets_and_csv_for_selected_year(): void
    {
        $year = now()->year;
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);

        $this->createTransaction($branch, $staff, 1500000, Carbon::create($year, 1, 15));
        $this->createTransaction($branch, $staff, 2000000, Carbon::create($year, 10, 10));
        $this->createTransaction($branch, $staff, 9000000, Carbon::create($year - 1, 12, 20));

        TargetKinerjaCabang::create(['cabang_id' => $branch->getKey(), 'tahun' => $year, 'bulan' => 1, 'status' => 'Selesai', 'target_omzet' => 2000000, 'target_siswa' => 2]);
        TargetKinerjaCabang::create(['cabang_id' => $branch->getKey(), 'tahun' => $year, 'bulan' => 10, 'status' => 'Aktif', 'target_omzet' => 3000000, 'target_siswa' => 3]);
        TargetKinerjaCabang::create(['cabang_id' => $branch->getKey(), 'tahun' => $year - 1, 'bulan' => 12, 'status' => 'Selesai', 'target_omzet' => 9000000, 'target_siswa' => 9]);

        $this->actingAs($superadmin)
            ->get('/admin/laporan?tahun='.$year.'&bulan=')
            ->assertOk()
            ->assertSee('Semua bulan')
            ->assertSee('Tahun '.$year)
            ->assertSee('Pergerakan bulanan')
            ->assertSee('group flex h-full min-w-0 flex-1 items-end justify-center gap-px', false)
            ->assertSee('Rp 3,5 jt')
            ->assertSee('Rp 5,0 jt')
            ->assertDontSee('Rp 9,0 jt');

        Livewire::actingAs($superadmin)->test(Laporan::class)
            ->set('tahun', $year)
            ->set('bulan', null)
            ->assertSet('bulan', null)
            ->assertSee('Pergerakan bulanan')
            ->call('exportPdf')
            ->assertFileDownloaded('laporan-sivmi-'.$year.'-tahunan.pdf');
    }

    public function test_staff_annual_report_keeps_personal_and_branch_scope(): void
    {
        $year = now()->year;
        $bandung = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $jaksel = Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel Rahasia', 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $otherStaff = User::factory()->create(['cabang_id' => $jaksel->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);

        $this->createTransaction($bandung, $staff, 2500000, Carbon::create($year, 2, 10));
        $this->createTransaction($jaksel, $otherStaff, 8000000, Carbon::create($year, 3, 10));

        $bandungTarget = TargetKinerjaCabang::create(['cabang_id' => $bandung->getKey(), 'tahun' => $year, 'bulan' => 2, 'status' => 'Aktif', 'target_omzet' => 4000000, 'target_siswa' => 4]);
        TargetKinerjaUser::create(['target_kinerja_cabang_id' => $bandungTarget->getKey(), 'user_id' => $staff->id, 'target_omzet' => 3000000, 'target_siswa' => 3]);
        $jakselTarget = TargetKinerjaCabang::create(['cabang_id' => $jaksel->getKey(), 'tahun' => $year, 'bulan' => 3, 'status' => 'Aktif', 'target_omzet' => 10000000, 'target_siswa' => 10]);
        TargetKinerjaUser::create(['target_kinerja_cabang_id' => $jakselTarget->getKey(), 'user_id' => $otherStaff->id, 'target_omzet' => 10000000, 'target_siswa' => 10]);

        $this->actingAs($staff)
            ->get('/admin/laporan?tahun='.$year.'&bulan=&cabang='.$jaksel->getKey().'&pengguna='.$otherStaff->id)
            ->assertOk()
            ->assertSee('Tahun '.$year)
            ->assertSee('Bandung')
            ->assertSee('Rp 2,5 jt')
            ->assertSee('Rp 3,0 jt')
            ->assertDontSee('JakSel Rahasia')
            ->assertDontSee('Rp 8,0 jt')
            ->assertDontSee('Rp 10,0 jt');
    }

    public function test_direksi_can_read_all_branches_and_empty_state_is_available(): void
    {
        Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);

        $this->actingAs($direksi)->get('/admin/laporan')
            ->assertOk()->assertSee('Bandung')->assertSee('JakSel')->assertSee('Belum ada data laporan');
    }

    private function createTransaction(Cabang $branch, User $staff, int $nominal, Carbon|string|null $date = null): void
    {
        $transactionDate = $date ? Carbon::parse($date) : now();
        $program = ProgramBimbel::firstOrCreate(
            ['nama_program' => 'Program Laporan'],
            ['kategori_program' => 'SR', 'jenjang_program' => 'SMA', 'biaya_pendidikan' => 5000000, 'aktif' => true],
        );
        $school = Sekolah::firstOrCreate(
            ['nama_sekolah' => 'Sekolah '.$branch->kode_cabang],
            ['kota' => 'Bandung', 'aktif' => true],
        );
        $phone = '081'.str_pad((string) self::$waPhoneSequence++, 9, '0', STR_PAD_LEFT);
        $lead = Lead::create(['nama' => 'Siswa '.$branch->kode_cabang, 'no_wa' => $phone, 'sekolah_id' => $school->getKey(), 'minat_program_id' => $program->getKey(), 'cabang_id' => $branch->getKey(), 'status_lead' => 'Daftar', 'diinput_oleh_user_id' => $staff->id, 'diserahkan_ke_user_id' => $staff->id, 'tanggal_masuk' => $transactionDate->toDateString()]);
        $registration = Pendaftaran::create(['lead_id' => $lead->getKey(), 'program_bimbel_id' => $program->getKey(), 'cabang_id' => $branch->getKey(), 'tanggal_daftar' => $transactionDate->toDateString(), 'status_pendaftaran' => 'Aktif']);
        Pembayaran::create(['pendaftaran_id' => $registration->getKey(), 'tanggal_pembayaran' => $transactionDate, 'nominal' => $nominal, 'metode_pembayaran' => 'Transfer Bank', 'status_pembayaran' => 'Terverifikasi']);
    }
}
