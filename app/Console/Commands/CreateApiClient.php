<?php

namespace App\Console\Commands;

use App\Models\ApiClient;
use App\Models\Cabang;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateApiClient extends Command
{
    protected $signature = 'api-client:create
        {name : Nama integrasi/API client}
        {--user= : Email pengguna audit yang aktif}
        {--branch= : Kode cabang; kosong untuk akses global yang diizinkan role}
        {--abilities=leads:read,leads:write : Ability dipisahkan koma}';

    protected $description = 'Membuat API client dan menampilkan raw token satu kali';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->option('user'))->where('aktif', true)->first();
        if (! $user) {
            $this->error('Pengguna audit aktif tidak ditemukan. Gunakan --user=email.');

            return self::FAILURE;
        }

        $branch = null;
        if ($branchCode = $this->option('branch')) {
            $branch = Cabang::query()->where('kode_cabang', $branchCode)->where('aktif', true)->first();
            if (! $branch) {
                $this->error('Cabang aktif tidak ditemukan.');

                return self::FAILURE;
            }
        } elseif (! $user->hasGlobalBranchAccess()) {
            $branch = $user->cabang;
        }

        if (! $branch && ! $user->hasGlobalBranchAccess()) {
            $this->error('Pengguna non-global wajib memiliki cabang.');

            return self::FAILURE;
        }

        $abilities = collect(explode(',', (string) $this->option('abilities')))
            ->map(fn (string $ability): string => trim($ability))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $allowed = ['*', 'leads:read', 'leads:write'];
        if ($abilities === [] || array_diff($abilities, $allowed) !== []) {
            $this->error('Ability tidak valid. Pilihan: *, leads:read, leads:write.');

            return self::FAILURE;
        }

        if (in_array('leads:write', $abilities, true) && ! $user->canManageOperationalData()) {
            $this->error('Role pengguna audit tidak diizinkan memiliki ability leads:write.');

            return self::FAILURE;
        }

        if ($branch && ! $user->hasGlobalBranchAccess() && (int) $branch->getKey() !== (int) $user->cabang_id) {
            $this->error('Pengguna non-global tidak dapat membuat client untuk cabang lain.');

            return self::FAILURE;
        }

        $token = 'sivmi_'.Str::random(48);
        $client = ApiClient::create([
            'name' => $this->argument('name'),
            'key_prefix' => substr($token, 0, 14),
            'key_hash' => hash('sha256', $token),
            'abilities' => $abilities,
            'user_id' => $user->getKey(),
            'cabang_id' => $branch?->getKey(),
            'active' => true,
        ]);

        $this->info("API client #{$client->getKey()} berhasil dibuat.");
        $this->warn('Simpan token berikut sekarang; nilai ini tidak dapat ditampilkan kembali:');
        $this->line($token);

        return self::SUCCESS;
    }
}
