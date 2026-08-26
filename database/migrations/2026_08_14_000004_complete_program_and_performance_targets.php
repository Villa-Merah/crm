<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_bimbel', function (Blueprint $table) {
            $table->string('kategori_program', 10)->default('SR')->after('program_bimbel_id');
            $table->string('program_kelas', 20)->default('Gold')->after('kategori_program');
            $table->string('tipe_program', 20)->default('Reguler')->after('program_kelas');
            $table->string('metode_belajar', 20)->default('Tatap Muka')->after('tipe_program');
            $table->string('tingkat_kelas', 10)->nullable()->after('jenjang_program');
            $table->decimal('biaya_pendaftaran', 15, 2)->default(0)->after('tingkat_kelas');
            $table->decimal('diskon_pendidikan', 5, 2)->default(0)->after('biaya_pendidikan');
            $table->unsignedSmallInteger('jumlah_pertemuan')->default(0)->after('diskon_pendidikan');
            $table->text('fasilitas_program')->nullable()->after('jumlah_pertemuan');
        });

        Schema::table('target_kinerja_cabang', function (Blueprint $table) {
            $table->string('status', 30)->default('Draft')->after('bulan');
            $table->foreignId('created_by')->nullable()->after('target_siswa')->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable()->after('created_by');
        });

        Schema::create('target_kinerja_user', function (Blueprint $table) {
            $table->id('target_kinerja_user_id');
            $table->foreignId('target_kinerja_cabang_id')->constrained('target_kinerja_cabang', 'target_kinerja_cabang_id')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('target_omzet', 15, 2)->default(0);
            $table->unsignedInteger('target_siswa')->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['target_kinerja_cabang_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_kinerja_user');

        Schema::table('target_kinerja_cabang', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['status', 'created_by', 'catatan']);
        });

        Schema::table('program_bimbel', function (Blueprint $table) {
            $table->dropColumn([
                'kategori_program', 'program_kelas', 'tipe_program', 'metode_belajar',
                'tingkat_kelas', 'biaya_pendaftaran', 'diskon_pendidikan',
                'jumlah_pertemuan', 'fasilitas_program',
            ]);
        });
    }
};
