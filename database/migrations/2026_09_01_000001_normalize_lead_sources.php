<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var array<int, array{kode: string, nama: string, warna: string, urutan: int}> */
    private array $sources = [
        ['kode' => 'iklan', 'nama' => 'Iklan', 'warna' => 'warning', 'urutan' => 10],
        ['kode' => 'instagram', 'nama' => 'Instagram', 'warna' => 'danger', 'urutan' => 20],
        ['kode' => 'kunjungan', 'nama' => 'Kunjungan', 'warna' => 'success', 'urutan' => 30],
        ['kode' => 'saudara', 'nama' => 'Saudara', 'warna' => 'info', 'urutan' => 40],
        ['kode' => 'facebook', 'nama' => 'Facebook', 'warna' => 'primary', 'urutan' => 50],
        ['kode' => 'tiktok', 'nama' => 'Tiktok', 'warna' => 'gray', 'urutan' => 60],
    ];

    public function up(): void
    {
        Schema::create('sumber_leads', function (Blueprint $table): void {
            $table->id('sumber_lead_id');
            $table->string('kode', 50)->unique();
            $table->string('nama', 100)->unique();
            $table->string('warna', 30)->default('gray');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('sumber_leads')->insert(array_map(
            fn (array $source): array => [...$source, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now],
            $this->sources,
        ));

        // Pertahankan nilai lama di luar pilihan standar sebagai master baru.
        DB::table('leads')
            ->whereNotNull('sumber_lead')
            ->where('sumber_lead', '<>', '')
            ->distinct()
            ->pluck('sumber_lead')
            ->each(function (string $legacyName) use ($now): void {
                $name = trim($legacyName);
                $exists = DB::table('sumber_leads')
                    ->whereRaw('LOWER(nama) = ?', [mb_strtolower($name)])
                    ->exists();

                if ($name === '' || $exists) {
                    return;
                }

                $baseCode = mb_substr(Str::slug($name), 0, 42) ?: 'sumber';
                $code = $baseCode;
                $suffix = 2;

                while (DB::table('sumber_leads')->where('kode', $code)->exists()) {
                    $code = mb_substr($baseCode, 0, 42).'-'.$suffix++;
                }

                DB::table('sumber_leads')->insert([
                    'kode' => $code,
                    'nama' => mb_substr($name, 0, 100),
                    'warna' => 'gray',
                    'urutan' => 1000,
                    'aktif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        Schema::table('leads', function (Blueprint $table): void {
            $table->foreignId('sumber_lead_id')
                ->nullable()
                ->after('sumber_lead')
                ->constrained('sumber_leads', 'sumber_lead_id')
                ->nullOnDelete();
        });

        DB::table('sumber_leads')->orderBy('sumber_lead_id')->each(function (object $source): void {
            DB::table('leads')
                ->whereRaw('LOWER(sumber_lead) = ?', [mb_strtolower($source->nama)])
                ->update(['sumber_lead_id' => $source->sumber_lead_id]);
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['sumber_lead']);
            $table->dropColumn('sumber_lead');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('sumber_lead', 30)->nullable()->index();
        });

        DB::table('sumber_leads')->orderBy('sumber_lead_id')->each(function (object $source): void {
            DB::table('leads')
                ->where('sumber_lead_id', $source->sumber_lead_id)
                ->update(['sumber_lead' => $source->nama]);
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sumber_lead_id');
        });

        Schema::dropIfExists('sumber_leads');
    }
};
