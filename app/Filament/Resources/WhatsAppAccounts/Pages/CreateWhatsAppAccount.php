<?php

namespace App\Filament\Resources\WhatsAppAccounts\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\WhatsAppAccounts\WhatsAppAccountResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateWhatsAppAccount extends CreateRecord
{
    protected static string $resource = WhatsAppAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $viewer = auth()->user();
        $user = User::query()->whereKey($data['user_id'])->where('aktif', true)
            ->whereIn('role', [UserRole::AdminCabang->value, UserRole::Staff->value])
            ->when(! $viewer->hasGlobalBranchAccess(), fn ($query) => $query->where('cabang_id', $viewer->cabang_id))->first();
        if (! $user || ! $user->cabang_id) {
            throw ValidationException::withMessages(['user_id' => 'Pengguna tidak valid atau belum memiliki cabang.']);
        }
        if (blank($data['phone_e164'])) {
            throw ValidationException::withMessages(['phone_e164' => 'Nomor WhatsApp tidak valid.']);
        }

        $data['cabang_id'] = $user->cabang_id;
        $data['session_name'] = 'sivmi-user-'.$user->getKey();
        $data['status'] = 'Disconnected';

        return $data;
    }
}
