<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Cabangs\CabangResource;
use App\Filament\Resources\Sekolahs\SekolahResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Cabang;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_manage_all_master_data_pages(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        $this->actingAs($superadmin)->get('/admin/cabang')->assertOk()->assertSee('Cabang');
        $this->get('/admin/cabang/create')->assertOk()->assertSee('Informasi cabang');
        $this->get('/admin/sekolah/create')->assertOk()->assertSee('Identitas sekolah');
        $this->get('/admin/pengguna/create')->assertOk()->assertSee('Profil pengguna');
    }

    public function test_admin_cabang_only_manages_staff_from_own_branch(): void
    {
        [$bandung, $jaksel] = $this->branches();
        $admin = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $ownStaff = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $otherStaff = User::factory()->create(['cabang_id' => $jaksel->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);

        $this->actingAs($admin);

        $this->assertEqualsCanonicalizing([$admin->id, $ownStaff->id], UserResource::getEloquentQuery()->pluck('id')->all());
        $this->assertTrue(UserResource::canCreate());
        $this->assertTrue(UserResource::canEdit($ownStaff));
        $this->assertFalse(UserResource::canEdit($admin));
        $this->assertFalse(UserResource::canEdit($otherStaff));
        $this->get('/admin/pengguna/create')->assertOk();
        $this->get('/admin/cabang/create')->assertForbidden();

        Livewire::actingAs($admin)->test(CreateUser::class)
            ->set('data', [
                'name' => 'Staff Baru',
                'email' => 'staff.baru@sivmi.test',
                'password' => 'password123',
                'role' => UserRole::Superadmin->value,
                'cabang_id' => $jaksel->cabang_id,
                'aktif' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['role', 'cabang_id']);

        Livewire::actingAs($admin)->test(CreateUser::class)
            ->set('data', [
                'name' => 'Staff Baru',
                'email' => 'staff.baru@sivmi.test',
                'password' => 'password123',
                'role' => UserRole::Staff->value,
                'cabang_id' => $bandung->cabang_id,
                'aktif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'staff.baru@sivmi.test')->firstOrFail();
        $this->assertTrue($created->hasRole(UserRole::Staff));
        $this->assertSame($bandung->cabang_id, $created->cabang_id);
    }

    public function test_staff_and_direksi_have_read_only_master_data_access(): void
    {
        [$bandung] = $this->branches();
        Sekolah::create(['nama_sekolah' => 'SMA Contoh', 'kota' => 'Bandung', 'status_sekolah' => 'Negeri', 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $bandung->cabang_id, 'role' => UserRole::Staff, 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);

        $this->actingAs($staff)->get('/admin/cabang')->assertOk()->assertSee('Bandung');
        $this->get('/admin/sekolah')->assertOk()->assertSee('SMA Contoh');
        $this->get('/admin/sekolah/create')->assertForbidden();
        $this->get('/admin/pengguna')->assertForbidden();

        $this->actingAs($direksi)->get('/admin/cabang')->assertOk();
        $this->get('/admin/sekolah')->assertOk();
        $this->get('/admin/cabang/create')->assertForbidden();
        $this->get('/admin/sekolah/create')->assertForbidden();
        $this->get('/admin/pengguna')->assertForbidden();

        $this->assertFalse(CabangResource::canCreate());
        $this->assertFalse(SekolahResource::canCreate());
    }

    private function branches(): array
    {
        return [
            Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'kota' => 'Bandung', 'aktif' => true]),
            Cabang::create(['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'kota' => 'Jakarta Selatan', 'aktif' => true]),
        ];
    }
}
