<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Exports\LeadExporter;
use App\Filament\Imports\LeadImporter;
use App\Filament\Resources\Leads\LeadResource;
use App\Support\LeadCsvTemplate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('contohCsv')
                ->label('Contoh CSV')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(fn () => LeadCsvTemplate::download()),
            ImportAction::make('imporCsv')
                ->label('Impor CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->importer(LeadImporter::class)
                ->csvDelimiter(',')
                ->maxRows(5000)
                ->chunkSize(100)
                ->visible(fn (): bool => LeadResource::canCreate()),
            ExportAction::make('eksporCsv')
                ->label('Ekspor CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->exporter(LeadExporter::class)
                ->formats([ExportFormat::Csv])
                ->columnMapping(false),
            CreateAction::make()->label('Buat lead'),
        ];
    }
}
