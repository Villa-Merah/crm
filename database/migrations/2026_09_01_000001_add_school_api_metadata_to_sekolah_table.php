<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table): void {
            $table->string('bentuk_pendidikan', 50)->nullable()->after('status_sekolah');
            $table->string('akreditasi', 20)->nullable()->after('bentuk_pendidikan');
            $table->string('kode_wilayah', 20)->nullable()->after('provinsi');
            $table->string('sumber_data', 30)->default('manual')->after('kluster_sekolah');
            $table->timestamp('api_last_synced_at')->nullable()->after('sumber_data');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table): void {
            $table->dropColumn([
                'bentuk_pendidikan',
                'akreditasi',
                'kode_wilayah',
                'sumber_data',
                'api_last_synced_at',
            ]);
        });
    }
};
