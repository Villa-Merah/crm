<?php

namespace App\Filament\Resources\Pembayarans\Pages;

use App\Filament\Resources\Pembayarans\PembayaranResource;
use App\Models\Pendaftaran;
use Filament\Resources\Pages\EditRecord;

class EditPembayaran extends EditRecord
{
    protected static string $resource = PembayaranResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($this->record->status_pembayaran, ['Lunas', 'Terverifikasi'], true)) {
            $data['pendaftaran_id'] = $this->record->pendaftaran_id;
        } else {
            Pendaftaran::query()->visibleTo(auth()->user())->findOrFail($data['pendaftaran_id']);
        }

        if (! auth()->user()->canVerifyPayments()) {
            $data['status_pembayaran'] = 'Menunggu';
        }

        return $data;
    }
}
