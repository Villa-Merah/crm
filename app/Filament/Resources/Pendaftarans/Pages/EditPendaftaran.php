<?php

namespace App\Filament\Resources\Pendaftarans\Pages;

use App\Filament\Resources\Pendaftarans\PendaftaranResource;
use Filament\Resources\Pages\EditRecord;

class EditPendaftaran extends EditRecord
{
    protected static string $resource = PendaftaranResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['lead_id'] = $this->record->lead_id;
        $data['cabang_id'] = $this->record->cabang_id;

        return $data;
    }
}
