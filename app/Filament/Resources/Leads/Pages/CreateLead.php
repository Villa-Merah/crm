<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Leads\LeadResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLead extends CreateRecord
{
    protected static string $resource = LeadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['diinput_oleh_user_id'] = $user->id;
        if (! $user->hasGlobalBranchAccess()) $data['cabang_id'] = $user->cabang_id;
        if ($user->hasRole(UserRole::Staff)) $data['diserahkan_ke_user_id'] = $user->id;
        $data['no_wa'] = preg_replace('/[^0-9+]/', '', $data['no_wa']);

        return $data;
    }
}
