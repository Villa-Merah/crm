<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabang', function (Blueprint $table) {
            $table->text('alamat')->nullable()->after('nama');
            $table->string('no_telepon', 20)->nullable()->after('kota');
            $table->string('email')->nullable()->after('no_telepon');
        });

        Schema::table('sekolah', function (Blueprint $table) {
            $table->string('npsn', 20)->nullable()->unique()->after('sekolah_id');
            $table->string('status_sekolah', 30)->default('Lainnya')->after('nama_sekolah');
            $table->text('alamat')->nullable()->after('status_sekolah');
            $table->string('kecamatan', 100)->nullable()->after('alamat');
            $table->string('provinsi', 100)->nullable()->after('kota');
            $table->string('kluster_sekolah', 50)->nullable()->after('provinsi');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropUnique(['npsn']);
            $table->dropColumn(['npsn', 'status_sekolah', 'alamat', 'kecamatan', 'provinsi', 'kluster_sekolah']);
        });

        Schema::table('cabang', function (Blueprint $table) {
            $table->dropColumn(['alamat', 'no_telepon', 'email']);
        });
    }
};
