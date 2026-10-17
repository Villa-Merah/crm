<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Cabangs\CabangResource;
use App\Filament\Resources\Sekolahs\Pages\ListSekolahs;
use App\Filament\Resources\Sekolahs\SekolahResource;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Cabang;
use App\Models\Lead;
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

    public function test_school_delete_is_only_allowed_when_not_used_by_a_lead(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $schoolFree = Sekolah::create(['nama_sekolah' => 'Sekolah Bebas']);
        $schoolUsed = Sekolah::create(['nama_sekolah' => 'Sekolah Terpakai']);
        Lead::create([
            'nama' => 'Lead Sekolah',
            'no_wa' => '081234567898',
            'sekolah_id' => $schoolUsed->getKey(),
            'diinput_oleh_user_id' => $superadmin->getKey(),
        ]);

        $this->actingAs($superadmin);

        $this->assertTrue(SekolahResource::canDelete($schoolFree));
        $this->assertFalse(SekolahResource::canDelete($schoolUsed));
        $this->get('/admin/sekolah')->assertOk()->assertSee('Sekolah Bebas')->assertSee('Sekolah Terpakai');
    }

    public function test_superadmin_can_bulk_edit_and_delete_filtered_school_selection(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $first = Sekolah::create(['nama_sekolah' => 'Sekolah Satu']);
        $second = Sekolah::create(['nama_sekolah' => 'Sekolah Dua']);

        Livewire::actingAs($superadmin)
            ->test(ListSekolahs::class)
            ->assertTableBulkActionExists('ubahTerpilih')
            ->assertTableBulkActionExists('delete')
            ->callTableBulkAction('ubahTerpilih', [$first, $second], [
                'status_sekolah' => 'Swasta',
                'kluster_sekolah' => 'Grade B',
                'aktif' => '0',
                'kota' => 'Bandung',
            ]);

        $this->assertSame('Swasta', $first->refresh()->status_sekolah);
        $this->assertSame('Grade B', $first->kluster_sekolah);
        $this->assertFalse($first->aktif);
        $this->assertSame('Bandung', $second->refresh()->kota);

        Livewire::actingAs($superadmin)
            ->test(ListSekolahs::class)
            ->callTableBulkAction('delete', [$first]);

        $this->assertDatabaseMissing('sekolah', ['sekolah_id' => $first->getKey()]);
        $this->assertDatabaseHas('sekolah', ['sekolah_id' => $second->getKey()]);
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

    public function test_superadmin_can_create_global_keuangan_without_a_branch(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        Livewire::actingAs($superadmin)->test(CreateUser::class)
            ->set('data', [
                'name' => 'Keuangan Global',
                'email' => 'keuangan.tanpa.cabang@sivmi.test',
                'password' => 'password123',
                'role' => UserRole::Keuangan->value,
                'cabang_id' => null,
                'aktif' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $keuangan = User::where('email', 'keuangan.tanpa.cabang@sivmi.test')->firstOrFail();
        $this->assertTrue($keuangan->hasRole(UserRole::Keuangan));
        $this->assertNull($keuangan->cabang_id);
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
