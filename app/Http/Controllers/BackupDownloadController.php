<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Services\Backup\DatabaseBackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(string $filename, DatabaseBackupService $backups): BinaryFileResponse
    {
        abort_unless(auth()->user()?->hasRole(UserRole::Superadmin), 403);
        $path = $backups->resolveStoredPath($filename);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'event' => 'downloaded',
            'description' => 'Backup database diunduh',
            'properties' => ['filename' => basename($path)],
            'ip_address' => request()->ip(),
            'user_agent' => str(request()->userAgent())->limit(500)->toString(),
            'created_at' => now(),
        ]);

        return response()->download($path, basename($path), ['Content-Type' => 'application/sql']);
    }
}
