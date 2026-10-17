<?php

namespace App\Filament\Resources\Pembayarans\Pages;

use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Models\Pendaftaran;
use Filament\Resources\Pages\CreateRecord;

class CreatePembayaran extends CreateRecord
{
    protected static string $resource = PembayaranResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Pendaftaran::query()->visibleTo(auth()->user())->findOrFail($data['pendaftaran_id']);
        if (! auth()->user()->canVerifyPayments()) {
            $data['status_pembayaran'] = 'Menunggu';
        }

        return $data;
    }
}
