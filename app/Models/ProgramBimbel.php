<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramBimbel extends Model
{
    protected $table = 'program_bimbel';

    protected $primaryKey = 'program_bimbel_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'biaya_pendaftaran' => 'decimal:2',
            'biaya_pendidikan' => 'decimal:2',
            'diskon_pendidikan' => 'decimal:2',
            'jumlah_pertemuan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $program): void {
            $components = collect([
                $program->kategori_program,
                $program->program_kelas,
                $program->metode_belajar,
            ])->filter();

            if ($components->count() === 3) {
                $program->nama_program = mb_strtoupper($components->implode(' '));
            }
        });
    }

    public function getBiayaSetelahDiskonAttribute(): float
    {
        return (float) $this->biaya_pendidikan * (1 - ((float) $this->diskon_pendidikan / 100));
    }

    public function pendaftaran()
    {
        return $this->hasMany(Pendaftaran::class, 'program_bimbel_id', 'program_bimbel_id');
    }
}
