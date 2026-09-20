<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Laporan;
use App\Filament\Pages\Pengaturan;
use App\Models\Cabang;
use App\Models\CrmSetting;
use App\Models\ProgramBimbel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_can_open_settings(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $admin = User::factory()->create(['role' => UserRole::AdminCabang, 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $staff = User::factory()->create(['role' => UserRole::Staff, 'aktif' => true]);

        $this->actingAs($superadmin)->get('/admin/pengaturan')->assertOk()->assertSee('Pengaturan SIVMI CRM')->assertSee('Pengaturan Program Bimbel');
        foreach ([$admin, $direksi, $staff] as $user) {
            $this->actingAs($user)->get('/admin/pengaturan')->assertForbidden();
        }
    }

    public function test_superadmin_can_update_identity_and_dashboard_tagline(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        Livewire::actingAs($superadmin)->test(Pengaturan::class)
            ->set('appName', 'CRM Akademik SIVMI')
            ->set('organizationName', 'SIVMI Indonesia')
            ->set('dashboardTagline', 'Tagline dashboard yang sudah diperbarui.')
            ->set('supportEmail', 'dukungan@sivmi.test')
            ->set('supportPhone', '+62 812-0000-0000')
            ->set('staffReportExport', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('CRM Akademik SIVMI', CrmSetting::getValue('app_name'));
        $this->assertTrue(CrmSetting::getBoolean('staff_report_export'));
        $this->assertSame($superadmin->id, CrmSetting::whereKey('app_name')->value('updated_by'));
        $this->actingAs($superadmin)->get('/admin/dashboard')->assertOk()->assertSee('Tagline dashboard yang sudah diperbarui.')->assertSee('CRM Akademik SIVMI');
    }

    public function test_settings_validation_rejects_invalid_contact(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        Livewire::actingAs($superadmin)->test(Pengaturan::class)
            ->set('supportEmail', 'bukan-email')
            ->set('supportPhone', 'nomor tidak valid')
            ->call('save')
            ->assertHasErrors(['supportEmail', 'supportPhone']);
    }

    public function test_superadmin_can_configure_program_options(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);

        Livewire::actingAs($superadmin)->test(Pengaturan::class)
            ->set('programClasses', "Diamond\nGold")
            ->set('programLearningMethods', "Tatap Muka\nOnline\nHybrid\nPrivat")
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['Diamond', 'Gold'], CrmSetting::getArray('program_classes'));
        $this->assertSame('json', CrmSetting::whereKey('program_classes')->value('type'));
        $this->actingAs($superadmin)->get('/admin/program-bimbel/create')->assertOk()->assertSee('Diamond')->assertSee('Privat');
    }

    public function test_superadmin_login_background_upload_is_persisted_immediately(): void
    {
        Storage::fake('public');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        CrmSetting::setMany(['login_background_image' => 'login/old-bg.png'], $superadmin->id);

        $component = Livewire::actingAs($superadmin)->test(Pengaturan::class)
            ->set('loginBackgroundImage', UploadedFile::fake()->createWithContent(
                'new-login-bg.png',
                file_get_contents(public_path('login.png')),
            ));

        $this->assertNotNull($component->loginBackgroundImagePath);
        $this->assertStringStartsWith('login/', $component->loginBackgroundImagePath);
        $this->assertNotSame('login/old-bg.png', $component->loginBackgroundImagePath);
        $this->assertSame($component->loginBackgroundImagePath, CrmSetting::getValue('login_background_image'));
        $this->assertNotNull(CrmSetting::getValue('login_background_image'));
        $this->assertStringStartsWith('login/', CrmSetting::getValue('login_background_image'));
        Storage::disk('public')->assertExists($component->loginBackgroundImagePath);

        $loginBackgroundMarkup = view('filament.partials.login-background')->render();
        $this->assertStringContainsString('/storage/'.$component->loginBackgroundImagePath, $loginBackgroundMarkup);
        $this->assertStringContainsString('?v=', $loginBackgroundMarkup);

        $component->call('save')->assertHasNoErrors();
        $this->assertSame($component->loginBackgroundImagePath, CrmSetting::getValue('login_background_image'));
    }

    public function test_in_use_program_option_cannot_be_removed(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        ProgramBimbel::create([
            'nama_program' => 'Program Aktif',
            'kategori_program' => 'SR',
            'program_kelas' => 'Platinum',
            'tipe_program' => 'Reguler',
            'metode_belajar' => 'Tatap Muka',
            'jenjang_program' => 'SMA',
            'biaya_pendidikan' => 1000000,
            'aktif' => true,
        ]);

        Livewire::actingAs($superadmin)->test(Pengaturan::class)
            ->set('programClasses', "Gold\nSilver")
            ->call('save')
            ->assertHasErrors(['programClasses']);

        $this->assertContains('Platinum', CrmSetting::getArray('program_classes'));
    }

    public function test_staff_export_follows_setting_and_direct_call_is_protected(): void
    {
        $branch = Cabang::create(['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'aktif' => true]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $staff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);

        $this->actingAs($staff)->get('/admin/laporan')->assertOk()->assertDontSee('Ekspor CSV');
        Livewire::actingAs($staff)->test(Laporan::class)->call('exportCsv')->assertForbidden();

        CrmSetting::setMany(['staff_report_export' => true], $superadmin->id);
        $this->actingAs($staff)->get('/admin/laporan')->assertOk()->assertSee('Ekspor CSV');
        Livewire::actingAs($staff)->test(Laporan::class)->call('exportCsv')->assertFileDownloaded('laporan-sivmi-'.now()->format('Y-m').'.csv');
    }
}
