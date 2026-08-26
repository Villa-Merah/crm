<?php

namespace App\Filament\Resources\FollowupRiwayats\Pages;

use App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFollowupRiwayat extends CreateRecord
{
    protected static string $resource = FollowupRiwayatResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
