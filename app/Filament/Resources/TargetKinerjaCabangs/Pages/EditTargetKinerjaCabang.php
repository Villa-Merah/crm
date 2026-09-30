<?php

namespace App\Filament\Resources\TargetKinerjaCabangs\Pages;

use App\Filament\Resources\TargetKinerjaCabangs\TargetKinerjaCabangResource;
use App\Models\TargetKinerjaCabang;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditTargetKinerjaCabang extends EditRecord
{
    protected static string $resource = TargetKinerjaCabangResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $viewer = auth()->user();
        $data['cabang_id'] = $this->record->cabang_id;
        $exists = TargetKinerjaCabang::query()->whereKeyNot($this->record->getKey())->where('cabang_id', $data['cabang_id'])->where('tahun', $data['tahun'])->where('bulan', $data['bulan'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['data.bulan' => 'Target untuk cabang dan periode ini sudah tersedia.']);
        }

        return $data;
    }
}
