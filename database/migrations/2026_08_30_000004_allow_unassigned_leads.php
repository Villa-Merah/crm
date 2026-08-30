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
            $table->unsignedBigInteger('cabang_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $fallbackBranchId = DB::table('cabang')->orderBy('cabang_id')->value('cabang_id');

        if ($fallbackBranchId !== null) {
            DB::table('leads')->whereNull('cabang_id')->update(['cabang_id' => $fallbackBranchId]);
        }

        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedBigInteger('cabang_id')->nullable(false)->change();
        });
    }
};
