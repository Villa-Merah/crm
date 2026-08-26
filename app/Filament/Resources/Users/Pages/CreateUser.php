<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()->hasRole(UserRole::AdminCabang)) {
            $data['cabang_id'] = auth()->user()->cabang_id;
            $data['role'] = UserRole::Staff->value;
        }

        if (in_array($data['role'], [UserRole::Superadmin->value, UserRole::Direksi->value], true)) {
            $data['cabang_id'] = null;
        }

        return $data;
    }
}
