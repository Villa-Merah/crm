<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->string('group', 50)->default('general')->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('crm_settings')->insert([
            ['key' => 'app_name', 'value' => 'SIVMI CRM', 'type' => 'string', 'group' => 'identity', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'organization_name', 'value' => 'SIVMI', 'type' => 'string', 'group' => 'identity', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'dashboard_tagline', 'value' => 'Pantau pertumbuhan leads, pencapaian siswa, dan kinerja tim dalam satu ruang kerja.', 'type' => 'string', 'group' => 'dashboard', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'support_email', 'value' => null, 'type' => 'string', 'group' => 'contact', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'support_phone', 'value' => null, 'type' => 'string', 'group' => 'contact', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'staff_report_export', 'value' => '0', 'type' => 'boolean', 'group' => 'report', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_settings');
    }
};
