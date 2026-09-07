<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invoiceTotal = (float) ($this->programBimbel?->biaya_pendidikan ?? 0);
        $paidTotal = (float) ($this->paid_total ?? 0);
        $remainingBalance = max($invoiceTotal - $paidTotal, 0);

        return [
            'id' => $this->pendaftaran_id,
            'nama' => $this->lead?->nama,
            'no_wa' => $this->lead?->no_wa,
            'kota_asal' => $this->lead?->kota_asal,
            'kelas' => $this->kelas_angkatan ?: $this->lead?->kelas_sekolah,
            'status_pendaftaran' => $this->status_pendaftaran,
            'tanggal_daftar' => $this->tanggal_daftar?->format('Y-m-d'),
            'sekolah' => $this->lead?->sekolah ? [
                'id' => $this->lead->sekolah->sekolah_id,
                'nama' => $this->lead->sekolah->nama_sekolah,
            ] : null,
            'program' => $this->programBimbel ? [
                'id' => $this->programBimbel->program_bimbel_id,
                'nama' => $this->programBimbel->nama_program,
                'biaya' => $invoiceTotal,
            ] : null,
            'cabang' => $this->cabang ? [
                'id' => $this->cabang->cabang_id,
                'kode' => $this->cabang->kode_cabang,
                'nama' => $this->cabang->nama,
            ] : null,
            'pembayaran' => [
                'total_terverifikasi' => $paidTotal,
                'sisa_tagihan' => $remainingBalance,
                'status' => $remainingBalance <= 0 ? 'verified' : 'unpaid',
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
