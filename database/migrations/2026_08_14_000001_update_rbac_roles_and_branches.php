<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'superadmin']);
        DB::table('users')->where('role', 'kepala_cabang')->update(['role' => 'admin_cabang']);
        DB::table('users')->where('role', 'marketing')->update(['role' => 'staff']);

        $this->renameBranch('SBY-PST', 'BDG', 'Bandung', 'Bandung');
        $this->renameBranch('SBY-TMR', 'JKS', 'JakSel', 'Jakarta Selatan');
        $this->renameBranch('GSK-KOT', 'JKP', 'JakPus', 'Jakarta Pusat');
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'superadmin')->update(['role' => 'super_admin']);
        DB::table('users')->where('role', 'admin_cabang')->update(['role' => 'kepala_cabang']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'marketing']);

        $this->renameBranch('BDG', 'SBY-PST', 'SIVMI Surabaya Pusat', 'Surabaya');
        $this->renameBranch('JKS', 'SBY-TMR', 'SIVMI Surabaya Timur', 'Surabaya');
        $this->renameBranch('JKP', 'GSK-KOT', 'SIVMI Gresik Kota', 'Gresik');
    }

    private function renameBranch(string $from, string $to, string $name, string $city): void
    {
        DB::table('cabang')->where('kode_cabang', $from)->update([
            'kode_cabang' => $to,
            'nama' => $name,
            'kota' => $city,
            'updated_at' => now(),
        ]);
    }
};
