<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SsoUserService
{
    /** @param array{id: string, name: string, email: string} $identity */
    public function resolve(array $identity): User
    {
        return DB::transaction(function () use ($identity): User {
            $user = User::query()->where('sso_id', $identity['id'])->lockForUpdate()->first();
            $user ??= User::query()->where('email', $identity['email'])->lockForUpdate()->first();

            if (! $user || ! $user->aktif || UserRole::tryFrom((string) $user->getRawOriginal('role')) === null) {
                throw ValidationException::withMessages(['sso' => 'Akun CRM belum tersedia atau tidak aktif. Hubungi superadmin untuk mengatur akun, role, dan cabang.']);
            }
            if ($user->sso_id !== null && $user->sso_id !== $identity['id']) {
                throw ValidationException::withMessages(['sso' => 'Akun CRM terhubung ke identitas SSO lain. Hubungi superadmin.']);
            }

            $user->sso_id = $identity['id'];
            $user->save();

            return $user;
        });
    }
}
