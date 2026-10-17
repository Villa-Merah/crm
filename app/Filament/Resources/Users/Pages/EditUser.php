<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (auth()->user()->hasRole(UserRole::AdminCabang)) {
            $data['cabang_id'] = auth()->user()->cabang_id;
            $data['role'] = UserRole::Staff->value;
        }

        if ($this->record->is(auth()->user())) {
            $data['aktif'] = true;
            $data['role'] = $this->record->role->value;
        }

        if (in_array($data['role'], [UserRole::Superadmin->value, UserRole::Keuangan->value, UserRole::Direksi->value], true)) {
            $data['cabang_id'] = null;
        }

        return $data;
    }
}
