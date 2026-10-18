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
use Illuminate\Validation\ValidationException;
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

    public function test_target_resource_keeps_staff_scope_while_dashboard_uses_global_targets(): void
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
        $this->get('/admin/dashboard')->assertOk()->assertSee('Rp 250,0 jt')->assertSee('0 / 70 siswa');
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

    public function test_active_target_requires_positive_values_and_completed_target_is_locked(): void
    {
        [$bandung] = $this->branches();

        try {
            TargetKinerjaCabang::create([
                'cabang_id' => $bandung->getKey(), 'tahun' => 2027, 'bulan' => 2,
                'status' => 'Aktif', 'target_omzet' => 0, 'target_siswa' => 0,
            ]);
            $this->fail('Target aktif bernilai nol seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('target_omzet', $exception->errors());
            $this->assertArrayHasKey('target_siswa', $exception->errors());
        }

        $target = $this->target($bandung);
        $target->update(['status' => 'Selesai']);

        $this->expectException(ValidationException::class);
        $target->update(['target_omzet' => 130000000]);
    }

    public function test_allocation_must_match_target_branch_and_cannot_exceed_branch_target(): void
    {
        [$bandung, $jaksel] = $this->branches();
        $target = $this->target($bandung);
        $staffBandung = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $staffJaksel = User::factory()->create(['cabang_id' => $jaksel->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);

        TargetKinerjaUser::create([
            'target_kinerja_cabang_id' => $target->getKey(), 'user_id' => $staffBandung->id,
            'target_omzet' => 100000000, 'target_siswa' => 30,
        ]);

        try {
            TargetKinerjaUser::create([
                'target_kinerja_cabang_id' => $target->getKey(), 'user_id' => $staffJaksel->id,
                'target_omzet' => 10000000, 'target_siswa' => 2,
            ]);
            $this->fail('Alokasi lintas cabang seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user_id', $exception->errors());
        }

        $otherBandung = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $this->expectException(ValidationException::class);
        TargetKinerjaUser::create([
            'target_kinerja_cabang_id' => $target->getKey(), 'user_id' => $otherBandung->id,
            'target_omzet' => 30000000, 'target_siswa' => 6,
        ]);
    }

    public function test_target_cannot_be_reduced_below_existing_allocations(): void
    {
        [$bandung] = $this->branches();
        $target = $this->target($bandung);
        $staff = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        TargetKinerjaUser::create([
            'target_kinerja_cabang_id' => $target->getKey(), 'user_id' => $staff->id,
            'target_omzet' => 100000000, 'target_siswa' => 30,
        ]);

        $this->expectException(ValidationException::class);
        $target->update(['target_omzet' => 90000000]);
    }

    public function test_completed_target_remains_visible_as_historical_target(): void
    {
        [$bandung] = $this->branches();
        $target = $this->target($bandung);
        $target->update(['status' => 'Selesai']);
        $admin = User::factory()->create(['cabang_id' => $bandung->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);

        $this->actingAs($admin)
            ->get('/admin/dashboard?tahun='.now()->year.'&bulan='.now()->month)
            ->assertOk()
            ->assertSee('Rp 125,0 jt')
            ->assertSee('0 / 35 siswa');

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
