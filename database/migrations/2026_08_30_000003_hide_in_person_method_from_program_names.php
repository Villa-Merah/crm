<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildNames(hideInPersonMethod: true);
    }

    public function down(): void
    {
        $this->rebuildNames(hideInPersonMethod: false);
    }

    private function rebuildNames(bool $hideInPersonMethod): void
    {
        DB::table('program_bimbel')
            ->select(['program_bimbel_id', 'kategori_program', 'program_kelas', 'metode_belajar'])
            ->orderBy('program_bimbel_id')
            ->chunkById(100, function ($programs) use ($hideInPersonMethod): void {
                foreach ($programs as $program) {
                    $method = trim((string) $program->metode_belajar);
                    $components = collect([
                        $program->kategori_program,
                        $program->program_kelas,
                        $hideInPersonMethod && mb_strtolower($method) === 'tatap muka'
                            ? null
                            : $method,
                    ])->filter(fn ($value) => filled($value));

                    DB::table('program_bimbel')
                        ->where('program_bimbel_id', $program->program_bimbel_id)
                        ->update(['nama_program' => mb_strtoupper($components->implode(' '))]);
                }
            }, 'program_bimbel_id');
    }
};
