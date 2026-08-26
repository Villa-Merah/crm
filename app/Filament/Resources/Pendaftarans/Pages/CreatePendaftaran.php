<?php

namespace App\Filament\Resources\Pendaftarans\Pages;

use App\Filament\Resources\Pendaftarans\PendaftaranResource;
use App\Models\Lead;
use Filament\Resources\Pages\CreateRecord;

class CreatePendaftaran extends CreateRecord
{
    protected static string $resource = PendaftaranResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $lead = Lead::query()->visibleTo(auth()->user())->findOrFail($data['lead_id']);
        $data['cabang_id'] = $lead->cabang_id;

        return $data;
    }
}
