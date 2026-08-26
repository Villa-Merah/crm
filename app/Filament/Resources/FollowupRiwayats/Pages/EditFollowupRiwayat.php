<?php

namespace App\Filament\Resources\FollowupRiwayats\Pages;

use App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFollowupRiwayat extends EditRecord
{
    protected static string $resource = FollowupRiwayatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->visible(fn () => \App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource::canDelete($this->record)),
        ];
    }
}
