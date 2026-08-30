<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table): void {
            $table->string('kota', 100)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('sekolah')->whereNull('kota')->update(['kota' => 'Belum diisi']);

        Schema::table('sekolah', function (Blueprint $table): void {
            $table->string('kota', 100)->nullable(false)->change();
        });
    }
};
