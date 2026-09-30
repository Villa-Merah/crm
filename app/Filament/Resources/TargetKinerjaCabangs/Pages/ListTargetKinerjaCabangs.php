<?php

namespace App\Filament\Resources\TargetKinerjaCabangs\Pages;

use App\Filament\Resources\TargetKinerjaCabangs\TargetKinerjaCabangResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTargetKinerjaCabangs extends ListRecords
{
    protected static string $resource = TargetKinerjaCabangResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah target')];
    }
}
