<?php

namespace App\Filament\Resources\Sekolahs\Pages;

use App\Filament\Exports\SekolahExporter;
use App\Filament\Imports\SekolahImporter;
use App\Filament\Resources\Sekolahs\SekolahResource;
use App\Support\SekolahCsvTemplate;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListSekolahs extends ListRecords
{
    protected static string $resource = SekolahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('contohCsv')->label('Contoh CSV')->icon('heroicon-o-document-arrow-down')->color('gray')->action(fn () => SekolahCsvTemplate::download()),
            ImportAction::make('imporCsv')->label('Impor CSV')->icon('heroicon-o-arrow-up-tray')->importer(SekolahImporter::class)->csvDelimiter(',')->maxRows(5000)->chunkSize(100)->visible(fn (): bool => SekolahResource::canCreate()),
            ExportAction::make('eksporCsv')->label('Ekspor CSV')->icon('heroicon-o-arrow-down-tray')->exporter(SekolahExporter::class)->formats([ExportFormat::Csv])->columnMapping(false),
            CreateAction::make()->label('Buat sekolah'),
        ];
    }
}
