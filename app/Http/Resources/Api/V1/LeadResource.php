<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->lead_id,
            'nama' => $this->nama,
            'no_wa' => $this->no_wa,
            'kota_asal' => $this->kota_asal,
            'kelas' => $this->kelas,
            'status' => $this->status_lead,
            'sumber' => $this->sumber_lead,
            'tanggal_masuk' => $this->tanggal_masuk?->format('Y-m-d'),
            'cabang' => $this->whenLoaded('cabang', fn (): array => [
                'id' => $this->cabang?->cabang_id,
                'kode' => $this->cabang?->kode_cabang,
                'nama' => $this->cabang?->nama,
            ]),
            'sekolah' => $this->whenLoaded('sekolah', fn (): ?array => $this->sekolah ? [
                'id' => $this->sekolah->sekolah_id,
                'nama' => $this->sekolah->nama_sekolah,
            ] : null),
            'program' => $this->whenLoaded('minatProgram', fn (): ?array => $this->minatProgram ? [
                'id' => $this->minatProgram->program_bimbel_id,
                'nama' => $this->minatProgram->nama_program,
            ] : null),
            'pic' => $this->whenLoaded('diserahkanKe', fn (): ?array => $this->diserahkanKe ? [
                'id' => $this->diserahkanKe->id,
                'nama' => $this->diserahkanKe->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
