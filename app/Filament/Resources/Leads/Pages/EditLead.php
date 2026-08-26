<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()->hasGlobalBranchAccess()) $data['cabang_id'] = auth()->user()->cabang_id;
        $data['no_wa'] = preg_replace('/[^0-9+]/', '', $data['no_wa']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->visible(fn () => \App\Filament\Resources\Leads\LeadResource::canDelete($this->record)),
        ];
    }
}
