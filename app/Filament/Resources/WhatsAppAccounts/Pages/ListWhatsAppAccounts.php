<?php

namespace App\Filament\Resources\WhatsAppAccounts\Pages;

use App\Filament\Resources\WhatsAppAccounts\WhatsAppAccountResource;
use App\Filament\Widgets\WhatsAppHealthOverview;
use App\Filament\Widgets\WhatsAppOperationsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppAccounts extends ListRecords
{
    protected static string $resource = WhatsAppAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah nomor')->visible(fn () => WhatsAppAccountResource::canCreate())];
    }

    protected function getHeaderWidgets(): array
    {
        return [WhatsAppHealthOverview::class];
    }

    protected function getFooterWidgets(): array
    {
        return [WhatsAppOperationsOverview::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }
}
