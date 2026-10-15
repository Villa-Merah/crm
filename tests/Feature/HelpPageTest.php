<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CrmSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_page_requires_authentication(): void
    {
        $this->get('/admin/bantuan')->assertRedirect('/admin/login');
    }

    public function test_every_active_role_can_open_help_page_with_role_guidance(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'aktif' => true]);

            $this->actingAs($user)
                ->get('/admin/bantuan')
                ->assertOk()
                ->assertSee('Pusat bantuan SIVMI CRM')
                ->assertSee('Alur proses utama')
                ->assertSee($role->label());
        }
    }

    public function test_help_page_displays_configured_support_contact(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        CrmSetting::setMany([
            'support_email' => 'dukungan@sivmi.test',
            'support_phone' => '+62 812-3456-7890',
        ], $superadmin->id);

        $this->actingAs($superadmin)
            ->get('/admin/bantuan')
            ->assertOk()
            ->assertSee('dukungan@sivmi.test')
            ->assertSee('+62 812-3456-7890');
    }

    public function test_staff_help_page_shows_operational_shortcuts(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'aktif' => true]);

        $this->actingAs($staff)
            ->get('/admin/bantuan')
            ->assertOk()
            ->assertSee('Inbox WhatsApp')
            ->assertSee('Pendaftaran')
            ->assertSee('Pembayaran')
            ->assertSee('Staff hanya mengubah lead miliknya');
    }
}
