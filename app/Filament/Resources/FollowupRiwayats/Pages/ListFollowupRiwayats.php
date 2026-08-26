<?php

namespace App\Filament\Resources\FollowupRiwayats\Pages;

use App\Filament\Resources\FollowupRiwayats\FollowupRiwayatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFollowupRiwayats extends ListRecords
{
    protected static string $resource = FollowupRiwayatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
