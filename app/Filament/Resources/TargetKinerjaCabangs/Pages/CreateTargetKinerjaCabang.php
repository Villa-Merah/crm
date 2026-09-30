<?php

namespace App\Filament\Resources\TargetKinerjaCabangs\Pages;

use App\Filament\Resources\TargetKinerjaCabangs\TargetKinerjaCabangResource;
use App\Models\TargetKinerjaCabang;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateTargetKinerjaCabang extends CreateRecord
{
    protected static string $resource = TargetKinerjaCabangResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $viewer = auth()->user();
        if (! $viewer->hasGlobalBranchAccess()) {
            $data['cabang_id'] = $viewer->cabang_id;
        }
        $data['created_by'] = $viewer->id;
        $this->ensureUniquePeriod($data);

        return $data;
    }

    private function ensureUniquePeriod(array $data): void
    {
        if (TargetKinerjaCabang::query()->where('cabang_id', $data['cabang_id'])->where('tahun', $data['tahun'])->where('bulan', $data['bulan'])->exists()) {
            throw ValidationException::withMessages(['data.bulan' => 'Target untuk cabang dan periode ini sudah tersedia.']);
        }
    }
}
