<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cabang', function (Blueprint $table) {
            $table->id('cabang_id');
            $table->string('kode_cabang', 20)->unique();
            $table->string('nama', 150);
            $table->string('kota', 100)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('cabang_id')->nullable()->after('id')->constrained('cabang', 'cabang_id')->nullOnDelete();
            $table->string('role', 30)->default('staff')->after('password');
            $table->boolean('aktif')->default(true)->after('role');
        });
        Schema::create('sekolah', function (Blueprint $table) {
            $table->id('sekolah_id');
            $table->string('nama_sekolah', 200);
            $table->string('kota', 100);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
        Schema::create('program_bimbel', function (Blueprint $table) {
            $table->id('program_bimbel_id');
            $table->string('nama_program', 150);
            $table->string('jenjang_program', 30);
            $table->decimal('biaya_pendidikan', 15, 2);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
        Schema::create('leads', function (Blueprint $table) {
            $table->id('lead_id');
            $table->string('nama', 150);
            $table->string('no_wa', 20);
            $table->foreignId('sekolah_id')->nullable()->constrained('sekolah', 'sekolah_id')->nullOnDelete();
            $table->foreignId('minat_program_id')->nullable()->constrained('program_bimbel', 'program_bimbel_id')->nullOnDelete();
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id');
            $table->string('status_lead', 50)->index();
            $table->foreignId('diinput_oleh_user_id')->constrained('users');
            $table->foreignId('diserahkan_ke_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('tanggal_masuk')->index();
            $table->timestamps();
            $table->index(['cabang_id', 'tanggal_masuk']);
        });
        Schema::create('followup_riwayat', function (Blueprint $table) {
            $table->id('followup_riwayat_id');
            $table->foreignId('lead_id')->constrained('leads', 'lead_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('tanggal_followup')->index();
            $table->string('metode_followup', 50);
            $table->string('status_kontak', 50);
            $table->dateTime('tanggal_followup_berikutnya')->nullable()->index();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
        Schema::create('pendaftaran', function (Blueprint $table) {
            $table->id('pendaftaran_id');
            $table->foreignId('lead_id')->unique()->constrained('leads', 'lead_id');
            $table->foreignId('program_bimbel_id')->constrained('program_bimbel', 'program_bimbel_id');
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id');
            $table->date('tanggal_daftar')->index();
            $table->string('status_pendaftaran', 30)->index();
            $table->timestamps();
            $table->index(['cabang_id', 'tanggal_daftar']);
        });
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('pembayaran_id');
            $table->foreignId('pendaftaran_id')->constrained('pendaftaran', 'pendaftaran_id')->cascadeOnDelete();
            $table->dateTime('tanggal_pembayaran')->index();
            $table->decimal('nominal', 15, 2);
            $table->string('status_pembayaran', 30)->index();
            $table->timestamps();
        });
        Schema::create('target_kinerja_cabang', function (Blueprint $table) {
            $table->id('target_kinerja_cabang_id');
            $table->foreignId('cabang_id')->constrained('cabang', 'cabang_id');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->decimal('target_omzet', 15, 2);
            $table->unsignedInteger('target_siswa');
            $table->timestamps();
            $table->unique(['cabang_id', 'tahun', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_kinerja_cabang');
        Schema::dropIfExists('pembayaran');
        Schema::dropIfExists('pendaftaran');
        Schema::dropIfExists('followup_riwayat');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('program_bimbel');
        Schema::dropIfExists('sekolah');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['cabang_id']);
            $table->dropColumn(['cabang_id', 'role', 'aktif']);
        });
        Schema::dropIfExists('cabang');
    }
};
