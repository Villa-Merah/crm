<?php

namespace Database\Seeders;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Cabang;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Seeder demo SIVMI dilewati pada environment production.');

            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (['target_kinerja_user', 'target_kinerja_cabang', 'pembayaran', 'pendaftaran', 'followup_riwayat', 'leads', 'sumber_leads', 'program_bimbel', 'sekolah', 'users', 'cabang'] as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $faker = fake('id_ID');
        $now = now();
        foreach (LeadSource::cases() as $index => $source) {
            DB::table('sumber_leads')->insert([
                'kode' => mb_strtolower($source->value),
                'nama' => $source->value,
                'warna' => LeadSource::color($source->value),
                'urutan' => ($index + 1) * 10,
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        $sourceIds = DB::table('sumber_leads')->pluck('sumber_lead_id')->all();
        $cabangs = collect([
            ['kode_cabang' => 'BDG', 'nama' => 'Bandung', 'kota' => 'Bandung', 'alamat' => 'Kota Bandung, Jawa Barat', 'no_telepon' => '0227000001', 'email' => 'bandung@sivmi.test'],
            ['kode_cabang' => 'JKS', 'nama' => 'JakSel', 'kota' => 'Jakarta Selatan', 'alamat' => 'Jakarta Selatan, DKI Jakarta', 'no_telepon' => '0217000002', 'email' => 'jaksel@sivmi.test'],
            ['kode_cabang' => 'JKP', 'nama' => 'JakPus', 'kota' => 'Jakarta Pusat', 'alamat' => 'Jakarta Pusat, DKI Jakarta', 'no_telepon' => '0217000003', 'email' => 'jakpus@sivmi.test'],
        ])->map(fn ($data) => Cabang::create($data));
        $superadmin = User::create(['name' => 'Nadia Pratama', 'email' => 'admin@sivmi.test', 'password' => Hash::make('password'), 'role' => UserRole::Superadmin, 'aktif' => true]);
        User::create(['name' => 'Raka Direksi', 'email' => 'direksi@sivmi.test', 'password' => Hash::make('password'), 'role' => UserRole::Direksi, 'aktif' => true]);
        $users = collect();
        foreach ($cabangs as $branch) {
            $code = strtolower($branch->kode_cabang);
            $users->push(User::create(['cabang_id' => $branch->cabang_id, 'name' => 'Admin '.$branch->nama, 'email' => "admin.{$code}@sivmi.test", 'password' => Hash::make('password'), 'role' => UserRole::AdminCabang, 'aktif' => true]));
            foreach (range(1, 2) as $i) {
                $users->push(User::create(['cabang_id' => $branch->cabang_id, 'name' => $faker->name(), 'email' => "staff.{$code}.{$i}@sivmi.test", 'password' => Hash::make('password'), 'role' => UserRole::Staff, 'aktif' => true]));
            }
        }
        foreach (range(1, 12) as $i) {
            DB::table('sekolah')->insert(['npsn' => str_pad((string) (10000000 + $i), 8, '0', STR_PAD_LEFT), 'nama_sekolah' => 'Sekolah '.$faker->company(), 'status_sekolah' => $faker->randomElement(['Negeri', 'Swasta']), 'alamat' => $faker->streetAddress(), 'kecamatan' => $faker->citySuffix(), 'kota' => $faker->city(), 'provinsi' => $faker->state(), 'kluster_sekolah' => $faker->randomElement(['Grade A', 'Grade B', 'Grade C', 'Grade D', 'Grade E']), 'aktif' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $programs = [
            ['SR', 'Gold', 'Intensif', 'Tatap Muka', 'SMA', 'XII', 250000, 4750000, 5, 48, 'Modul belajar, try out, dan konsultasi akademik.'],
            ['AR', 'Silver', 'Reguler', 'Hybrid', 'SMP', 'IX', 200000, 3200000, 0, 36, 'Modul belajar dan evaluasi bulanan.'],
            ['KG', 'Platinum', 'Intensif', 'Hybrid', 'Gap Year', null, 300000, 6500000, 10, 60, 'Modul intensif, try out, dan konsultasi jurusan.'],
            ['W', 'Bronze', 'Reguler', 'Online', 'SD', 'VI', 150000, 2250000, 0, 24, 'Kelas daring dan latihan mingguan.'],
        ];
        foreach ($programs as $program) {
            [$kategori, $kelas, $tipe, $metode, $jenjang, $tingkat, $biayaDaftar, $biayaPendidikan, $diskon, $pertemuan, $fasilitas] = $program;
            DB::table('program_bimbel')->insert([
                'kategori_program' => $kategori, 'program_kelas' => $kelas, 'tipe_program' => $tipe,
                'metode_belajar' => $metode, 'jenjang_program' => $jenjang, 'tingkat_kelas' => $tingkat,
                'nama_program' => mb_strtoupper(collect([
                    $kategori,
                    $kelas,
                    mb_strtolower(trim($metode)) === 'tatap muka' ? null : $metode,
                ])->filter()->implode(' ')),
                'biaya_pendaftaran' => $biayaDaftar, 'biaya_pendidikan' => $biayaPendidikan,
                'diskon_pendidikan' => $diskon, 'jumlah_pertemuan' => $pertemuan,
                'fasilitas_program' => $fasilitas, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach (range(1, 140) as $i) {
            $branch = $cabangs->random();
            $owner = $users->where('cabang_id', $branch->cabang_id)->random();
            $date = $faker->dateTimeBetween('-5 months', 'now');
            $leadId = DB::table('leads')->insertGetId(['nama' => $faker->name(), 'no_wa' => $faker->phoneNumber(), 'kota_asal' => $faker->city(), 'sekolah_id' => random_int(1, 12), 'minat_program_id' => random_int(1, 4), 'sumber_lead_id' => $faker->randomElement($sourceIds), 'cabang_id' => $branch->cabang_id, 'status_lead' => $faker->randomElement(array_keys(LeadStatus::options())), 'diinput_oleh_user_id' => $owner->id, 'diserahkan_ke_user_id' => $owner->id, 'tanggal_masuk' => $date->format('Y-m-d'), 'created_at' => $date, 'updated_at' => $date]);
            if ($faker->boolean(70)) {
                DB::table('followup_riwayat')->insert(['lead_id' => $leadId, 'user_id' => $owner->id, 'tanggal_followup' => $date, 'metode_followup' => $faker->randomElement(['WhatsApp', 'Telepon', 'Tatap Muka']), 'status_kontak' => $faker->randomElement(['Terhubung', 'Tidak Terhubung']), 'tanggal_followup_berikutnya' => $faker->boolean(35) ? $faker->dateTimeBetween('now', '+14 days') : null, 'catatan' => 'Tindak lanjut minat program.', 'created_at' => $date, 'updated_at' => $date]);
            }
            if ($faker->boolean(42)) {
                $regId = DB::table('pendaftaran')->insertGetId(['lead_id' => $leadId, 'program_bimbel_id' => random_int(1, 4), 'cabang_id' => $branch->cabang_id, 'tanggal_daftar' => $date->format('Y-m-d'), 'kelas_angkatan' => 'Angkatan '.$date->format('Y'), 'status_pendaftaran' => 'Aktif', 'catatan' => 'Pendaftaran demo untuk pengujian dashboard.', 'created_at' => $date, 'updated_at' => $date]);
                DB::table('pembayaran')->insert(['pendaftaran_id' => $regId, 'tanggal_pembayaran' => $date, 'nominal' => $faker->randomElement([2250000, 3200000, 4750000, 6500000]), 'metode_pembayaran' => $faker->randomElement(['Transfer Bank', 'Tunai', 'QRIS']), 'status_pembayaran' => $faker->randomElement(['Lunas', 'Terverifikasi']), 'nomor_referensi' => 'DEMO-'.$regId, 'catatan' => 'Transaksi demo lokal.', 'created_at' => $date, 'updated_at' => $date]);
            }
        }
        foreach ($cabangs as $branch) {
            $branchUsers = $users->where('cabang_id', $branch->cabang_id)->values();
            foreach (range(1, 12) as $month) {
                $targetId = DB::table('target_kinerja_cabang')->insertGetId([
                    'cabang_id' => $branch->cabang_id, 'tahun' => now()->year, 'bulan' => $month,
                    'status' => 'Aktif', 'target_omzet' => 125000000, 'target_siswa' => 35,
                    'created_by' => $superadmin->id, 'catatan' => 'Target operasional demo lokal.',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                foreach ($branchUsers as $index => $branchUser) {
                    DB::table('target_kinerja_user')->insert([
                        'target_kinerja_cabang_id' => $targetId, 'user_id' => $branchUser->id,
                        'target_omzet' => $index === 0 ? 45000000 : 40000000,
                        'target_siswa' => $index === 0 ? 13 : 11,
                        'catatan' => 'Alokasi target demo lokal.', 'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }
}
