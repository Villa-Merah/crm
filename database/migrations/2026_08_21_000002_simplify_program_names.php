<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildNames(['kategori_program', 'program_kelas', 'metode_belajar']);
    }

    public function down(): void
    {
        $this->rebuildNames([
            'kategori_program',
            'program_kelas',
            'tipe_program',
            'metode_belajar',
            'tingkat_kelas',
        ]);
    }

    /** @param array<int, string> $columns */
    private function rebuildNames(array $columns): void
    {
        DB::table('program_bimbel')
            ->select(['program_bimbel_id', ...$columns])
            ->orderBy('program_bimbel_id')
            ->chunkById(100, function ($programs) use ($columns): void {
                foreach ($programs as $program) {
                    $name = collect($columns)
                        ->map(fn (string $column) => $program->{$column})
                        ->filter(fn ($value) => filled($value))
                        ->implode(' ');

                    DB::table('program_bimbel')
                        ->where('program_bimbel_id', $program->program_bimbel_id)
                        ->update(['nama_program' => mb_strtoupper($name)]);
                }
            }, 'program_bimbel_id');
    }
};
