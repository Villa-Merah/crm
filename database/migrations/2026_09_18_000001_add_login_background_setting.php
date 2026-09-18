<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('crm_settings')->insertOrIgnore([
            'key' => 'login_background_image',
            'value' => null,
            'type' => 'string',
            'group' => 'identity',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('crm_settings')->where('key', 'login_background_image')->delete();
    }
};
