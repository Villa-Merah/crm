<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sekolah')->where('kluster_sekolah', 'Prioritas')->update(['kluster_sekolah' => 'Grade A']);
        DB::table('sekolah')->where('kluster_sekolah', 'Reguler')->update(['kluster_sekolah' => 'Grade B']);
        DB::table('sekolah')->where('kluster_sekolah', 'Pengembangan')->update(['kluster_sekolah' => 'Grade C']);
    }

    public function down(): void
    {
        DB::table('sekolah')->where('kluster_sekolah', 'Grade A')->update(['kluster_sekolah' => 'Prioritas']);
        DB::table('sekolah')->where('kluster_sekolah', 'Grade B')->update(['kluster_sekolah' => 'Reguler']);
        DB::table('sekolah')->where('kluster_sekolah', 'Grade C')->update(['kluster_sekolah' => 'Pengembangan']);
    }
};
