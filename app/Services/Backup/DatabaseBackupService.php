<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function supported(): bool
    {
        return in_array(config('database.default'), ['mysql', 'mariadb'], true);
    }

    public function diagnostics(): array
    {
        if (! $this->supported()) {
            return ['ready' => false, 'message' => 'Backup SQL tersedia untuk koneksi MySQL atau MariaDB.'];
        }

        $dumpReady = $this->binaryAvailable($this->binary('dump'));
        $restoreReady = $this->binaryAvailable($this->binary('restore'));
        $databaseReady = $this->databaseAvailable();

        return [
            'ready' => $dumpReady && $restoreReady && $databaseReady,
            'dump_ready' => $dumpReady,
            'restore_ready' => $restoreReady,
            'database_ready' => $databaseReady,
            'message' => $dumpReady && $restoreReady && $databaseReady
                ? 'Perangkat backup dan restore siap digunakan.'
                : (! $databaseReady
                    ? 'Database tidak dapat dihubungi dari proses aplikasi.'
                    : 'Binary mysqldump/mysql belum tersedia pada server aplikasi.'),
        ];
    }

    public function create(string $reason = 'manual'): array
    {
        $this->assertSupported();
        $this->ensureDirectory();

        $reason = Str::slug($reason) ?: 'manual';
        $filename = sprintf('sivmi-crm-%s-%s-%s.sql', now()->format('Ymd-His'), $reason, Str::lower(Str::random(6)));
        $relativePath = $this->directory().'/'.$filename;
        $absolutePath = Storage::disk($this->disk())->path($relativePath);
        $connection = $this->connection();

        $command = [
            $this->binary('dump'),
            ...$this->connectionArguments($connection),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--add-drop-table',
            '--result-file='.$absolutePath,
            (string) $connection['database'],
        ];

        $process = new Process($command, base_path(), $this->processEnvironment($connection));
        $process->setTimeout($this->timeout());
        $process->run();

        if (! $process->isSuccessful() || ! is_file($absolutePath) || filesize($absolutePath) === 0) {
            @unlink($absolutePath);
            throw new RuntimeException('Backup database gagal: '.$this->cleanError($process->getErrorOutput()));
        }

        $this->prune();

        return $this->metadata($relativePath);
    }

    public function restoreStored(string $filename): array
    {
        return $this->restore($this->resolveStoredPath($filename));
    }

    public function restoreUploaded(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('File restore tidak ditemukan.');
        }

        return $this->restore($path);
    }

    public function backups(): array
    {
        $this->ensureDirectory();

        return collect(Storage::disk($this->disk())->files($this->directory()))
            ->filter(fn (string $path): bool => str_ends_with(strtolower($path), '.sql'))
            ->map(fn (string $path): array => $this->metadata($path))
            ->sortByDesc('timestamp')
            ->values()
            ->all();
    }

    public function resolveStoredPath(string $filename): string
    {
        $filename = basename($filename);
        if (! preg_match('/\A[a-zA-Z0-9._-]+\.sql\z/', $filename)) {
            throw new RuntimeException('Nama file backup tidak valid.');
        }

        $relativePath = $this->directory().'/'.$filename;
        if (! Storage::disk($this->disk())->exists($relativePath)) {
            throw new RuntimeException('File backup tidak ditemukan.');
        }

        return Storage::disk($this->disk())->path($relativePath);
    }

    private function restore(string $path): array
    {
        $this->assertSupported();
        $this->assertSqlBackup($path);

        $safetyBackup = $this->create('pre-restore');
        $connection = $this->connection();
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('File restore tidak dapat dibaca.');
        }

        try {
            $process = new Process([
                $this->binary('restore'),
                ...$this->connectionArguments($connection),
                '--default-character-set=utf8mb4',
                (string) $connection['database'],
            ], base_path(), $this->processEnvironment($connection));
            $process->setInput($stream);
            $process->setTimeout($this->timeout());
            $process->run();
        } finally {
            fclose($stream);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Restore database gagal: '.$this->cleanError($process->getErrorOutput()));
        }

        return $safetyBackup;
    }

    private function assertSqlBackup(string $path): void
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'sql') {
            throw new RuntimeException('Restore hanya menerima file dengan ekstensi .sql.');
        }

        $stream = fopen($path, 'rb');
        $sample = $stream === false ? '' : (string) fread($stream, 65536);
        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($sample === '' || ! preg_match('/MySQL dump|MariaDB dump|CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE/i', $sample)) {
            throw new RuntimeException('Isi file tidak dikenali sebagai backup SQL MySQL/MariaDB.');
        }
    }

    private function prune(): void
    {
        $retention = max(1, (int) config('backup.retention', 20));
        foreach (array_slice($this->backups(), $retention) as $backup) {
            Storage::disk($this->disk())->delete($backup['path']);
        }
    }

    private function metadata(string $relativePath): array
    {
        $disk = Storage::disk($this->disk());
        $timestamp = $disk->lastModified($relativePath);

        return [
            'name' => basename($relativePath),
            'path' => $relativePath,
            'size' => $disk->size($relativePath),
            'size_human' => $this->humanSize($disk->size($relativePath)),
            'timestamp' => $timestamp,
            'created_at' => Carbon::createFromTimestamp($timestamp)->timezone(config('app.timezone')),
        ];
    }

    private function connection(): array
    {
        return config('database.connections.'.config('database.default'));
    }

    private function connectionArguments(array $connection): array
    {
        $arguments = [];
        if (filled($connection['unix_socket'] ?? null)) {
            $arguments[] = '--socket='.$connection['unix_socket'];
        } else {
            $arguments[] = '--host='.(string) ($connection['host'] ?? '127.0.0.1');
            $arguments[] = '--port='.(string) ($connection['port'] ?? 3306);
        }
        $arguments[] = '--user='.(string) ($connection['username'] ?? 'root');

        return $arguments;
    }

    private function processEnvironment(array $connection): array
    {
        return ['MYSQL_PWD' => (string) ($connection['password'] ?? '')];
    }

    private function binaryAvailable(string $binary): bool
    {
        try {
            $process = new Process([$binary, '--version']);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function databaseAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function binary(string $type): string
    {
        $configured = (string) config($type === 'dump' ? 'backup.dump_binary' : 'backup.restore_binary');
        if ($configured === '') {
            $configured = $type === 'dump' ? 'mysqldump' : 'mysql';
        }

        if (is_file($configured) || PHP_OS_FAMILY !== 'Windows' || str_contains($configured, '/') || str_contains($configured, '\\')) {
            return $configured;
        }

        $laragonRoot = dirname(base_path(), 2);
        $matches = glob($laragonRoot.'/bin/mysql/*/bin/'.$configured.'.exe') ?: [];
        natsort($matches);

        return $matches === [] ? $configured : (string) end($matches);
    }

    private function assertSupported(): void
    {
        if (! $this->supported()) {
            throw new RuntimeException('Backup dan restore hanya didukung untuk MySQL/MariaDB.');
        }
    }

    private function ensureDirectory(): void
    {
        Storage::disk($this->disk())->makeDirectory($this->directory());
    }

    private function disk(): string
    {
        return (string) config('backup.disk', 'local');
    }

    private function directory(): string
    {
        return trim((string) config('backup.directory', 'backups/database'), '/');
    }

    private function timeout(): int
    {
        return max(60, (int) config('backup.timeout', 900));
    }

    private function cleanError(string $error): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $error)) ?: 'perintah database tidak berhasil', 500);
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }
        if ($bytes < 1073741824) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        return number_format($bytes / 1073741824, 1, ',', '.').' GB';
    }
}
