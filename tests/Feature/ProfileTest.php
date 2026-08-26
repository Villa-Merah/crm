<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WhatsAppAccountStatus;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\Cabang;
use App\Models\User;
use App\Models\UserWhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_and_operational_context(): void
    {
        $branch = Cabang::create([
            'kode_cabang' => 'JKS',
            'nama' => 'JakSel',
            'kota' => 'Jakarta Selatan',
            'aktif' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Admin JakSel',
            'email' => 'admin.jaksel@sivmi.test',
            'role' => UserRole::AdminCabang,
            'cabang_id' => $branch->getKey(),
            'aktif' => true,
        ]);
        UserWhatsAppAccount::create([
            'user_id' => $user->getKey(),
            'cabang_id' => $branch->getKey(),
            'phone_e164' => '628123456789',
            'session_name' => 'sivmi-user-'.$user->getKey(),
            'status' => WhatsAppAccountStatus::Working,
            'aktif' => true,
            'last_seen_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('Data pribadi')
            ->assertSee('Admin Cabang')
            ->assertSee('JakSel')
            ->assertSee('+628123456789')
            ->assertSee('Terhubung')
            ->assertSee('Keamanan akun');
    }

    public function test_user_can_update_own_name_and_email_without_changing_access(): void
    {
        $branch = Cabang::create([
            'kode_cabang' => 'BDG',
            'nama' => 'Bandung',
            'kota' => 'Bandung',
            'aktif' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'nama.lama@sivmi.test',
            'password' => 'password123',
            'role' => UserRole::Staff,
            'cabang_id' => $branch->getKey(),
            'aktif' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => 'Nama Baru',
                'email' => 'nama.baru@sivmi.test',
                'currentPassword' => 'password123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('nama.baru@sivmi.test', $user->email);
        $this->assertSame(UserRole::Staff, $user->role);
        $this->assertSame($branch->getKey(), $user->cabang_id);
    }

    public function test_user_can_change_password_with_current_password_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
            'role' => UserRole::Direksi,
            'aktif' => true,
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'KataSandiBaru123!',
                'passwordConfirmation' => 'KataSandiBaru123!',
                'currentPassword' => 'password123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('KataSandiBaru123!', $user->fresh()->password));
    }
}
