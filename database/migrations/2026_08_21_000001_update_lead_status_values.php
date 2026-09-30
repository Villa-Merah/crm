<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceStatuses([
            'Baru' => 'Cold',
            'Dihubungi' => 'Warm',
            'Prospek' => 'Hot',
            'Terdaftar' => 'Daftar',
        ]);
    }

    public function down(): void
    {
        $this->replaceStatuses([
            'Cold' => 'Baru',
            'Warm' => 'Dihubungi',
            'Hot' => 'Prospek',
            'Daftar' => 'Terdaftar',
        ]);
    }

    /** @param array<string, string> $statuses */
    private function replaceStatuses(array $statuses): void
    {
        foreach ($statuses as $from => $to) {
            DB::table('leads')->where('status_lead', $from)->update(['status_lead' => $to]);
        }
    }
};
