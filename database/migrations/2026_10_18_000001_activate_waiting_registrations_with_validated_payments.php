<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('pendaftaran')
            ->where('status_pendaftaran', 'Menunggu')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('pembayaran')
                ->whereColumn('pembayaran.pendaftaran_id', 'pendaftaran.pendaftaran_id')
                ->whereIn('pembayaran.status_pembayaran', ['Lunas', 'Terverifikasi']))
            ->update(['status_pendaftaran' => 'Aktif', 'updated_at' => $now]);

        DB::table('leads')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('pendaftaran')
                ->whereColumn('pendaftaran.lead_id', 'leads.lead_id')
                ->where('pendaftaran.status_pendaftaran', 'Aktif'))
            ->where('status_lead', '!=', 'Daftar')
            ->update(['status_lead' => 'Daftar', 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Statuses are a data correction and must not be reverted after users continue working.
    }
};
