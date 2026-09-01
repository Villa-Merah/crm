<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_active_user_can_view_dashboard_shell(): void
    {
        $user = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        $this->actingAs($user)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Selamat datang')
            ->assertSee('Konversi lead')
            ->assertSee('Pipeline leads')
            ->assertSee('Performa sumber leads')
            ->assertSee('Progress target')
            ->assertSee('Tindakan tertunda')
            ->assertSee('SIVMI CRM')
            ->assertSee('MASTER DATA')
            ->assertSee('Memuat data dashboard')
            ->assertSee('sivmi.sidebar.collapsed')
            ->assertSee("localStorage.setItem('theme', 'light')", false)
            ->assertDontSee('const loadDarkMode')
            ->assertSeeInOrder(['Dashboard', 'Leads', 'Follow-up', 'Target Kinerja', 'Laporan', 'Cabang', 'Sekolah', 'Pengguna', 'Pendaftaran', 'Pembayaran', 'Program Bimbel', 'Pengaturan', 'Audit Aktivitas']);
    }

    public function test_authenticated_admin_home_redirects_once_to_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        $this->actingAs($user)->get('/admin')
            ->assertRedirect('/admin/dashboard');

        $this->actingAs($user)->get('/admin/dashboard')->assertOk();
    }

    public function test_staff_dashboard_rejects_tampered_global_filters(): void
    {
        $bandung = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $jaksel = Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $otherStaff = User::factory()->create(['cabang_id' => $jaksel->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        Lead::create($this->leadData($bandung, $staff, 'Lead milik staff'));
        Lead::create($this->leadData($jaksel, $otherStaff, 'Lead cabang lain'));

        $this->actingAs($staff)->get('/admin/dashboard?cabang='.$jaksel->cabang_id.'&pengguna='.$otherStaff->id)
            ->assertOk()
            ->assertSee('Lead milik staff')
            ->assertDontSee('Lead cabang lain');
    }

    public function test_dashboard_period_filter_includes_first_day_and_excludes_other_months(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        Lead::create($this->leadData($branch, $staff, 'Lead hari pertama', now()->startOfMonth()));
        Lead::create($this->leadData($branch, $staff, 'Lead bulan lalu', now()->subMonthNoOverflow()->startOfMonth()));

        $this->actingAs($admin)->get('/admin/dashboard?cabang='.$branch->cabang_id.'&tahun='.now()->year.'&bulan='.now()->month)
            ->assertOk()
            ->assertSee('Lead hari pertama')
            ->assertDontSee('Lead bulan lalu');
    }

    public function test_all_months_filter_includes_the_entire_selected_year(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        Lead::create($this->leadData($branch, $staff, 'Lead Januari', now()->setMonth(1)->startOfMonth()));
        Lead::create($this->leadData($branch, $staff, 'Lead Desember', now()->setMonth(12)->startOfMonth()));
        Lead::create($this->leadData($branch, $staff, 'Lead tahun lalu', now()->subYear()->startOfYear()));

        $this->actingAs($admin)->get('/admin/dashboard?cabang='.$branch->cabang_id.'&tahun='.now()->year.'&bulan=')
            ->assertOk()
            ->assertSee('Semua bulan')
            ->assertSee('Pergerakan bulanan')
            ->assertSee('group flex h-full min-w-0 flex-1', false)
            ->assertSee('Lead Januari')
            ->assertSee('Lead Desember')
            ->assertDontSee('Lead tahun lalu');
    }

    public function test_program_lead_kpi_uses_filtered_database_aggregates(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $gold = ProgramBimbel::create(['nama_program' => 'sementara', 'kategori_program' => 'SR', 'program_kelas' => 'Gold', 'tipe_program' => 'Intensif', 'metode_belajar' => 'Online', 'jenjang_program' => 'SMA', 'biaya_pendidikan' => 1000000]);
        $silver = ProgramBimbel::create(['nama_program' => 'sementara', 'kategori_program' => 'W', 'program_kelas' => 'Silver', 'tipe_program' => 'Reguler', 'metode_belajar' => 'Tatap Muka', 'jenjang_program' => 'SMP', 'biaya_pendidikan' => 750000]);
        $schoolBandung = Sekolah::create(['nama_sekolah' => 'SMA Prestasi Bandung', 'kota' => 'Bandung', 'aktif' => true]);
        $schoolCimahi = Sekolah::create(['nama_sekolah' => 'SMP Harapan Cimahi', 'kota' => 'Cimahi', 'aktif' => true]);

        Lead::create([...$this->leadData($branch, $staff, 'Lead Gold 1'), 'minat_program_id' => $gold->program_bimbel_id, 'sekolah_id' => $schoolBandung->sekolah_id, 'sumber_lead' => 'Instagram']);
        Lead::create([...$this->leadData($branch, $staff, 'Lead Gold 2'), 'minat_program_id' => $gold->program_bimbel_id, 'sekolah_id' => $schoolBandung->sekolah_id, 'sumber_lead' => 'Instagram', 'status_lead' => 'Warm']);
        Lead::create([...$this->leadData($branch, $staff, 'Lead Silver'), 'minat_program_id' => $silver->program_bimbel_id, 'sekolah_id' => $schoolCimahi->sekolah_id, 'sumber_lead' => 'Kunjungan', 'status_lead' => 'Hot']);

        $this->actingAs($admin)->get('/admin/dashboard?cabang='.$branch->cabang_id.'&tahun='.now()->year.'&bulan='.now()->month)
            ->assertOk()
            ->assertSee('KPI leads berdasarkan program')
            ->assertSee('Breakdown klasifikasi program')
            ->assertSee('Kategori')
            ->assertSee('Tipe program')
            ->assertSee('Metode belajar')
            ->assertSee('Paket program')
            ->assertSee('Jenjang')
            ->assertSee('SR GOLD ONLINE')
            ->assertSee('W SILVER')
            ->assertSee('Intensif')
            ->assertSee('Reguler')
            ->assertSee('SMA')
            ->assertSee('SMP')
            ->assertSee('Instagram')
            ->assertSee('Kunjungan')
            ->assertSee('Cold')
            ->assertSee('Warm')
            ->assertSee('Hot')
            ->assertSee('Top 5 sekolah')
            ->assertSee('Top 5 wilayah/kota')
            ->assertSee('Peta persebaran leads')
            ->assertSee('Cakupan seluruh leads')
            ->assertSee('/vendor/leaflet/leaflet.css?v=1.9.4', false)
            ->assertSee('/vendor/leaflet/leaflet.js?v=1.9.4', false)
            ->assertSee('data-cfasync="false"', false)
            ->assertSee('https://tile.openstreetmap.org/{z}/{x}/{y}.png', false)
            ->assertDontSee('unpkg.com/leaflet', false)
            ->assertSee('SMA Prestasi Bandung')
            ->assertSee('SMP Harapan Cimahi')
            ->assertSee('Bandung')
            ->assertSee('Cimahi')
            ->assertSee('67%')
            ->assertSee('33%');
    }

    public function test_location_map_prefers_lead_city_and_falls_back_to_school_city(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]);
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $school = Sekolah::create(['nama_sekolah' => 'Sekolah Peta Bandung', 'kota' => 'Bandung', 'aktif' => true]);

        Lead::create([...$this->leadData($branch, $staff, 'Lead asal Jakarta'), 'kota_asal' => 'Jakarta Selatan', 'sekolah_id' => $school->sekolah_id]);
        Lead::create([...$this->leadData($branch, $staff, 'Lead fallback sekolah'), 'sekolah_id' => $school->sekolah_id]);
        Lead::create($this->leadData($branch, $staff, 'Lead tanpa lokasi'));

        $this->actingAs($admin)->get('/admin/dashboard?cabang='.$branch->cabang_id.'&tahun='.now()->year.'&bulan='.now()->month)
            ->assertOk()
            ->assertSee('Peta persebaran leads')
            ->assertSee('Jakarta Selatan')
            ->assertSee('Bandung')
            ->assertSee('2 terpetakan')
            ->assertSee('66.7%')
            ->assertSee('1 leads belum memiliki kota asal maupun kota sekolah.');
    }

    public function test_leaflet_distribution_is_available_locally(): void
    {
        $this->assertFileExists(public_path('vendor/leaflet/leaflet.css'));
        $this->assertFileExists(public_path('vendor/leaflet/leaflet.js'));
        $this->assertFileExists(public_path('vendor/leaflet/images/marker-icon.png'));
        $this->assertFileExists(public_path('vendor/leaflet/LICENSE'));
    }

    private function leadData(Cabang $branch, User $user, string $name, $date = null): array
    {
        return ['nama' => $name, 'no_wa' => '08'.str_pad((string) sprintf('%u', crc32($name)), 10, '0', STR_PAD_LEFT), 'cabang_id' => $branch->cabang_id, 'status_lead' => 'Cold', 'diinput_oleh_user_id' => $user->id, 'diserahkan_ke_user_id' => $user->id, 'tanggal_masuk' => $date ?? today()];
    }
}
