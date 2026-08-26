<?php

namespace App\Filament\Resources\ProgramBimbels\Pages;

use App\Filament\Resources\ProgramBimbels\ProgramBimbelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProgramBimbels extends ListRecords
{
    protected static string $resource = ProgramBimbelResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Tambah program')]; }
}
