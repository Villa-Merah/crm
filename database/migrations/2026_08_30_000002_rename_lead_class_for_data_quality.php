<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->renameColumn('kelas', 'kelas_sekolah');
        });

        DB::table('leads')
            ->select('no_wa_normalized')
            ->whereNotNull('no_wa_normalized')
            ->groupBy('no_wa_normalized')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('no_wa_normalized')
            ->each(function (string $phone): void {
                $duplicateIds = DB::table('leads')
                    ->where('no_wa_normalized', $phone)
                    ->orderBy('lead_id')
                    ->pluck('lead_id')
                    ->skip(1);

                DB::table('leads')->whereIn('lead_id', $duplicateIds)->update(['no_wa_normalized' => null]);
            });

        Schema::table('leads', function (Blueprint $table): void {
            $table->unique('no_wa_normalized', 'leads_no_wa_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique('leads_no_wa_normalized_unique');
            $table->renameColumn('kelas_sekolah', 'kelas');
        });
    }
};
