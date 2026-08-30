<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ProgramBimbels\ProgramBimbelResource;
use App\Filament\Resources\TargetKinerjaCabangs\Pages\CreateTargetKinerjaCabang;
use App\Filament\Resources\TargetKinerjaCabangs\TargetKinerjaCabangResource;
use App\Models\Cabang;
use App\Models\ProgramBimbel;
use App\Models\TargetKinerjaCabang;
use App\Models\TargetKinerjaUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgramTargetTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_name_is_generated_and_only_superadmin_can_manage_catalog(): void
    {
        $program = ProgramBimbel::create([
            'kategori_program' => 'SR', 'program_kelas' => 'Gold', 'tipe_program' => 'Intensif',
            'metode_belajar' => 'Hybrid', 'jenjang_program' => 'SMA', 'tingkat_kelas' => 'XII',
            'biaya_pendaftaran' => 250000, 'biaya_pendidikan' => 4750000,
            'diskon_pendidikan' => 10, 'jumlah_pertemuan' => 48, 'aktif' => true,
        ]);
        $this->assertSame('SR GOLD HYBRID', $program->nama_program);
        $this->assertSame(4275000.0, $program->biaya_setelah_diskon);

        $program->update(['tipe_program' => 'Reguler', 'jenjang_program' => 'SMP', 'tingkat_kelas' => 'IX']);
        $this->assertSame('SR GOLD HYBRID', $program->nama_program);

        $program->update(['metode_belajar' => 'Online']);
        $this->assertSame('SR GOLD ONLINE', $program->nama_program);

        $program->update(['metode_belajar' => 'Tatap Muka']);
        $this->assertSame('SR GOLD', $program->nama_program);

        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $this->actingAs($superadmin)->get('/admin/program-bimbel')->assertOk()->assertSee($program->nama_program);
        $this->get('/admin/program-bimbel/create')->assertOk()->assertSee('Klasifikasi program');
        $this->assertTrue(ProgramBimbelResource::canCreate());

        $this->actingAs($direksi)->get('/admin/program-bimbel')->assertOk();
        $this->get('/admin/program-bimbel/create')->assertForbidden();
        $this->assertFalse(ProgramBimbelResource::canCreate());
    }

    public function test_admin_cabang_target_is_forced_to_own_branch_and_period_is_unique(): void
    {
        [$bandung, $jaksel] = $this->branches();
        $admin = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::AdminCabang, 'aktif' => true]);

        Livewire::actingAs($admin)->test(CreateTargetKinerjaCabang::class)->set('data', [
            'cabang_id' => $jaksel->cabang_id, 'tahun' => 2027, 'bulan' => 1, 'status' => 'Aktif',
            'target_omzet' => 100000000, 'target_siswa' => 30,
        ])->call('create')->assertHasFormErrors(['cabang_id']);

        Livewire::actingAs($admin)->test(CreateTargetKinerjaCabang::class)->set('data', [
            'cabang_id' => $bandung->cabang_id, 'tahun' => 2027, 'bulan' => 1, 'status' => 'Aktif',
            'target_omzet' => 100000000, 'target_siswa' => 30,
        ])->call('create')->assertHasNoFormErrors();

        $target = TargetKinerjaCabang::firstOrFail();
        $this->assertSame($bandung->cabang_id, $target->cabang_id);
        $this->assertSame($admin->id, $target->created_by);

        Livewire::actingAs($admin)->test(CreateTargetKinerjaCabang::class)->set('data', [
            'cabang_id' => $bandung->cabang_id, 'tahun' => 2027, 'bulan' => 1, 'status' => 'Aktif',
            'target_omzet' => 120000000, 'target_siswa' => 35,
        ])->call('create')->assertHasFormErrors(['bulan']);
    }

    public function test_target_scope_and_dashboard_use_staff_allocation(): void
    {
        [$bandung, $jaksel] = $this->branches();
        $staff = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $other = User::factory()->create(['cabang_id' => $jaksel->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $bandungTarget = $this->target($bandung);
        $jakselTarget = $this->target($jaksel);
        TargetKinerjaUser::create(['target_kinerja_cabang_id' => $bandungTarget->getKey(), 'user_id' => $staff->id, 'target_omzet' => 45000000, 'target_siswa' => 13]);
        TargetKinerjaUser::create(['target_kinerja_cabang_id' => $jakselTarget->getKey(), 'user_id' => $other->id, 'target_omzet' => 50000000, 'target_siswa' => 15]);

        $this->actingAs($staff);
        $this->assertSame([$bandungTarget->getKey()], TargetKinerjaCabangResource::getEloquentQuery()->pluck('target_kinerja_cabang_id')->all());
        $this->get('/admin/target-kinerja')->assertOk()->assertSee('Target omzet saya')->assertDontSee('JakSel');
        $this->get('/admin/target-kinerja/create')->assertForbidden();
        $this->get('/admin/dashboard')->assertOk()->assertSee('Rp 45,0 jt')->assertSee('0 / 13 siswa');
    }

    public function test_direksi_can_read_targets_but_cannot_change_them(): void
    {
        [$bandung] = $this->branches();
        $target = $this->target($bandung);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);

        $this->actingAs($direksi)->get('/admin/target-kinerja')->assertOk()->assertSee('Bandung');
        $this->get('/admin/target-kinerja/create')->assertForbidden();
        $this->get('/admin/target-kinerja/'.$target->getKey().'/edit')->assertForbidden();
    }

    private function branches(): array
    {
        return [
            Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]),
            Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'aktif' => true]),
        ];
    }

    private function target(Cabang $branch): TargetKinerjaCabang
    {
        return TargetKinerjaCabang::create(['cabang_id' => $branch->getKey(), 'tahun' => now()->year, 'bulan' => now()->month, 'status' => 'Aktif', 'target_omzet' => 125000000, 'target_siswa' => 35]);
    }
}
