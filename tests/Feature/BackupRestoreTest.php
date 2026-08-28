<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\BackupRestore;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_superadmin_can_access_backup_page(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $admin = User::factory()->create(['role' => UserRole::AdminCabang, 'aktif' => true]);
        $staff = User::factory()->create(['role' => UserRole::Staff, 'aktif' => true]);
        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);

        $this->actingAs($superadmin)->get('/admin/backup-restore')->assertOk()->assertSee('Backup & Restore Database', false);
        foreach ([$admin, $staff, $direksi] as $user) {
            $this->actingAs($user)->get('/admin/backup-restore')->assertForbidden();
        }
    }

    public function test_superadmin_can_create_backup_and_activity_is_audited(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $service = Mockery::mock(DatabaseBackupService::class);
        $service->shouldReceive('diagnostics')->andReturn(['ready' => true, 'message' => 'Siap']);
        $service->shouldReceive('backups')->andReturn([]);
        $service->shouldReceive('create')->once()->andReturn([
            'name' => 'sivmi-crm-test.sql',
            'size' => 2048,
            'size_human' => '2,0 KB',
        ]);
        $this->app->instance(DatabaseBackupService::class, $service);

        Livewire::actingAs($superadmin)->test(BackupRestore::class)->call('createBackup')->assertHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $superadmin->id,
            'event' => 'created',
            'description' => 'Backup database dibuat',
        ]);
    }

    public function test_restore_requires_explicit_confirmation(): void
    {
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $service = Mockery::mock(DatabaseBackupService::class);
        $service->shouldReceive('diagnostics')->andReturn(['ready' => true, 'message' => 'Siap']);
        $service->shouldReceive('backups')->andReturn([]);
        $service->shouldNotReceive('restoreStored');
        $this->app->instance(DatabaseBackupService::class, $service);

        Livewire::actingAs($superadmin)->test(BackupRestore::class)
            ->set('selectedBackup', 'sivmi-crm-test.sql')
            ->set('restoreConfirmation', 'restore')
            ->call('restoreSelected')
            ->assertHasErrors(['restoreConfirmation']);
    }

    public function test_backup_download_is_private_and_rejects_other_roles(): void
    {
        Storage::fake('local');
        config()->set('backup.disk', 'local');
        config()->set('backup.directory', 'backups/database');
        Storage::disk('local')->put('backups/database/sivmi-crm-test.sql', '-- MySQL dump'.PHP_EOL.'CREATE TABLE test (id INT);');

        $superadmin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $admin = User::factory()->create(['role' => UserRole::AdminCabang, 'aktif' => true]);

        $this->actingAs($admin)->get('/admin/backups/sivmi-crm-test.sql/download')->assertForbidden();
        $this->actingAs($superadmin)->get('/admin/backups/sivmi-crm-test.sql/download')
            ->assertOk()
            ->assertDownload('sivmi-crm-test.sql');
    }
}
