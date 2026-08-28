<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Services\Backup\DatabaseBackupService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class BackupRestore extends Page
{
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationLabel = 'Backup & Restore';

    protected static string|\UnitEnum|null $navigationGroup = 'MASTER DATA';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Backup & Restore';

    protected string $view = 'filament.pages.backup-restore';

    public $restoreUpload = null;

    public ?string $selectedBackup = null;

    public string $restoreConfirmation = '';

    public string $uploadConfirmation = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(UserRole::Superadmin) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function createBackup(DatabaseBackupService $service): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $backup = $service->create();
            $this->audit('created', 'Backup database dibuat', ['filename' => $backup['name'], 'size' => $backup['size']]);
            Notification::make()->title('Backup berhasil dibuat')->body($backup['name'].' · '.$backup['size_human'])->success()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('Backup gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    public function selectRestore(string $filename): void
    {
        abort_unless(static::canAccess(), 403);
        $this->selectedBackup = basename($filename);
        $this->restoreConfirmation = '';
    }

    public function cancelRestore(): void
    {
        $this->selectedBackup = null;
        $this->restoreConfirmation = '';
    }

    public function restoreSelected(DatabaseBackupService $service): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['selectedBackup' => ['required', 'string'], 'restoreConfirmation' => ['required', 'in:RESTORE']], [
            'restoreConfirmation.in' => 'Ketik RESTORE untuk mengonfirmasi pemulihan database.',
        ]);

        try {
            $filename = (string) $this->selectedBackup;
            $safetyBackup = $service->restoreStored($filename);
            $this->audit('restored', 'Database dipulihkan dari backup tersimpan', ['filename' => $filename, 'safety_backup' => $safetyBackup['name']]);
            $this->cancelRestore();
            Notification::make()->title('Restore berhasil')->body('Backup pengaman dibuat: '.$safetyBackup['name'])->success()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('Restore gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    public function restoreUploaded(DatabaseBackupService $service): void
    {
        abort_unless(static::canAccess(), 403);
        $max = max(1024, (int) config('backup.max_upload_kb', 102400));
        $this->validate([
            'restoreUpload' => ['required', 'file', 'extensions:sql', 'max:'.$max],
            'uploadConfirmation' => ['required', 'in:RESTORE'],
        ], [
            'restoreUpload.extensions' => 'File restore harus menggunakan ekstensi .sql.',
            'uploadConfirmation.in' => 'Ketik RESTORE untuk mengonfirmasi pemulihan database.',
        ]);

        try {
            $originalName = Str::limit($this->restoreUpload->getClientOriginalName(), 180, '');
            $safetyBackup = $service->restoreUploaded($this->restoreUpload->getRealPath());
            $this->audit('restored', 'Database dipulihkan dari unggahan SQL', ['filename' => $originalName, 'safety_backup' => $safetyBackup['name']]);
            $this->reset('restoreUpload', 'uploadConfirmation');
            Notification::make()->title('Restore unggahan berhasil')->body('Backup pengaman dibuat: '.$safetyBackup['name'])->success()->send();
        } catch (\Throwable $exception) {
            report($exception);
            Notification::make()->title('Restore gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    public function getBackupsProperty(): array
    {
        return app(DatabaseBackupService::class)->backups();
    }

    public function getDiagnosticsProperty(): array
    {
        return app(DatabaseBackupService::class)->diagnostics();
    }

    private function audit(string $event, string $description, array $properties): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'event' => $event,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => str(request()->userAgent())->limit(500)->toString(),
            'created_at' => now(),
        ]);
    }
}
