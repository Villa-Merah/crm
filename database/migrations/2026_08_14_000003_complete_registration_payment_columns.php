<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->string('kelas_angkatan', 100)->nullable()->after('tanggal_daftar');
            $table->text('catatan')->nullable()->after('status_pendaftaran');
        });

        Schema::table('pembayaran', function (Blueprint $table) {
            $table->string('metode_pembayaran', 30)->default('Lainnya')->after('nominal');
            $table->string('nomor_referensi', 100)->nullable()->after('status_pembayaran');
            $table->text('catatan')->nullable()->after('nomor_referensi');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran', function (Blueprint $table) {
            $table->dropColumn(['metode_pembayaran', 'nomor_referensi', 'catatan']);
        });

        Schema::table('pendaftaran', function (Blueprint $table) {
            $table->dropColumn(['kelas_angkatan', 'catatan']);
        });
    }
};
