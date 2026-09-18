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
        $demoPassword = Hash::make('admin123');
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
        $superadmin = User::create(['name' => 'Nadia Pratama', 'email' => 'admin@sivmi.test', 'password' => $demoPassword, 'role' => UserRole::Superadmin, 'aktif' => true]);
        User::create(['name' => 'Raka Direksi', 'email' => 'direksi@sivmi.test', 'password' => $demoPassword, 'role' => UserRole::Direksi, 'aktif' => true]);
        $users = collect();
        foreach ($cabangs as $branch) {
            $code = strtolower($branch->kode_cabang);
            $users->push(User::create(['cabang_id' => $branch->cabang_id, 'name' => 'Admin '.$branch->nama, 'email' => "admin.{$code}@sivmi.test", 'password' => $demoPassword, 'role' => UserRole::AdminCabang, 'aktif' => true]));
            foreach (range(1, 2) as $i) {
                $users->push(User::create(['cabang_id' => $branch->cabang_id, 'name' => $faker->name(), 'email' => "staff.{$code}.{$i}@sivmi.test", 'password' => $demoPassword, 'role' => UserRole::Staff, 'aktif' => true]));
            }
        }
        $schoolRegions = [
            'Jakarta' => ['provinsi' => 'DKI Jakarta', 'names' => ['SMAN 1 Jakarta', 'SMAS Harapan Jakarta', 'SMKN 2 Jakarta']],
            'Bandung' => ['provinsi' => 'Jawa Barat', 'names' => ['SMAN 3 Bandung', 'SMAS Pelita Bandung', 'SMKN 5 Bandung']],
            'Tangerang' => ['provinsi' => 'Banten', 'names' => ['SMAN 4 Tangerang', 'SMAS Citra Tangerang', 'SMKN 1 Tangerang']],
            'Bekasi' => ['provinsi' => 'Jawa Barat', 'names' => ['SMAN 2 Bekasi', 'SMAS Bina Bekasi', 'SMKN 3 Bekasi']],
            'Depok' => ['provinsi' => 'Jawa Barat', 'names' => ['SMAN 1 Depok', 'SMAS Nusantara Depok', 'SMKN 2 Depok']],
            'Luar Pulau Jawa' => ['provinsi' => 'Sumatera Utara', 'names' => ['SMAN 1 Medan', 'SMAS Cendekia Makassar', 'SMKN 1 Denpasar']],
        ];
        $schoolIdsByRegion = [];
        $schoolNumber = 1;
        foreach ($schoolRegions as $region => $schoolRegion) {
            foreach ($schoolRegion['names'] as $schoolName) {
                $schoolIdsByRegion[$region][] = DB::table('sekolah')->insertGetId([
                    'npsn' => str_pad((string) (10000000 + $schoolNumber), 8, '0', STR_PAD_LEFT),
                    'nama_sekolah' => $schoolName,
                    'status_sekolah' => str_starts_with($schoolName, 'SMAN') || str_starts_with($schoolName, 'SMKN') ? 'Negeri' : 'Swasta',
                    'alamat' => $region.', Indonesia',
                    'kecamatan' => $region,
                    'kota' => $region,
                    'provinsi' => $schoolRegion['provinsi'],
                    'kluster_sekolah' => $faker->randomElement(['Grade A', 'Grade B', 'Grade C', 'Grade D', 'Grade E']),
                    'aktif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $schoolNumber++;
            }
        }
        $programs = [
            ['SR', 'Gold', 'Intensif', 'Tatap Muka', 'SMA', 'XII', 250000, 4750000, 5, 48, 'Modul belajar, try out, dan konsultasi akademik.'],
            ['AR', 'Gold', 'Intensif', 'Tatap Muka', 'SMA', 'XII', 250000, 4750000, 5, 48, 'Modul belajar, try out, dan konsultasi akademik.'],
            ['SR', 'Silver', 'Reguler', 'Tatap Muka', 'SMA', 'X', 200000, 3200000, 0, 36, 'Modul belajar dan evaluasi bulanan.'],
            ['SR', 'SMP', 'Reguler', 'Tatap Muka', 'SMP', 'IX', 200000, 3200000, 0, 36, 'Modul belajar dan evaluasi bulanan.'],
            ['SR', 'Anak', 'Reguler', 'Tatap Muka', 'SD', 'VI', 150000, 2250000, 0, 24, 'Kelas penguatan dasar dan latihan mingguan.'],
            ['SR', 'Gold', 'Intensif', 'Online', 'SMA', 'XII', 250000, 4250000, 5, 48, 'Modul digital, try out, dan konsultasi akademik daring.'],
        ];
        $programNames = ['SR GOLD', 'AR GOLD', 'SR SILVER', 'SR SMP', 'SR ANAK', 'SR GOLD ONLINE'];
        $programsByName = [];
        foreach ($programs as $program) {
            [$kategori, $kelas, $tipe, $metode, $jenjang, $tingkat, $biayaDaftar, $biayaPendidikan, $diskon, $pertemuan, $fasilitas] = $program;
            $programIndex = count($programsByName);
            $programId = DB::table('program_bimbel')->insertGetId([
                'kategori_program' => $kategori, 'program_kelas' => $kelas, 'tipe_program' => $tipe,
                'metode_belajar' => $metode, 'jenjang_program' => $jenjang, 'tingkat_kelas' => $tingkat,
                'nama_program' => $programNames[$programIndex],
                'biaya_pendaftaran' => $biayaDaftar, 'biaya_pendidikan' => $biayaPendidikan,
                'diskon_pendidikan' => $diskon, 'jumlah_pertemuan' => $pertemuan,
                'fasilitas_program' => $fasilitas, 'aktif' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $programsByName[$programNames[$programIndex]] = ['id' => $programId, 'biaya' => $biayaPendidikan];
        }
        $programSequence = array_merge(
            array_fill(0, 40, 'SR GOLD'),
            array_fill(0, 20, 'AR GOLD'),
            array_fill(0, 15, 'SR SILVER'),
            array_fill(0, 10, 'SR SMP'),
            array_fill(0, 5, 'SR ANAK'),
            array_fill(0, 10, 'SR GOLD ONLINE'),
        );
        $regionSequence = array_keys($schoolRegions);
        foreach (range(1, 100) as $i) {
            $region = $regionSequence[($i - 1) % count($regionSequence)];
            $schoolIds = $schoolIdsByRegion[$region];
            $branch = $cabangs->where('kota', in_array($region, ['Jakarta', 'Bandung'], true) ? $region : 'Bandung')->first() ?? $cabangs->first();
            $owner = $users->where('cabang_id', $branch->cabang_id)->random();
            $date = $faker->dateTimeBetween('-5 months', 'now');
            $programName = $programSequence[$i - 1];
            $program = $programsByName[$programName];
            $leadId = DB::table('leads')->insertGetId(['nama' => $faker->name(), 'no_wa' => $faker->phoneNumber(), 'kota_asal' => $region, 'sekolah_id' => $schoolIds[($i - 1) % count($schoolIds)], 'minat_program_id' => $program['id'], 'sumber_lead_id' => $faker->randomElement($sourceIds), 'cabang_id' => $branch->cabang_id, 'status_lead' => $faker->randomElement(array_keys(LeadStatus::options())), 'diinput_oleh_user_id' => $owner->id, 'diserahkan_ke_user_id' => $owner->id, 'tanggal_masuk' => $date->format('Y-m-d'), 'created_at' => $date, 'updated_at' => $date]);
            if ($faker->boolean(70)) {
                DB::table('followup_riwayat')->insert(['lead_id' => $leadId, 'user_id' => $owner->id, 'tanggal_followup' => $date, 'metode_followup' => $faker->randomElement(['WhatsApp', 'Telepon', 'Tatap Muka']), 'status_kontak' => $faker->randomElement(['Terhubung', 'Tidak Terhubung']), 'tanggal_followup_berikutnya' => $faker->boolean(35) ? $faker->dateTimeBetween('now', '+14 days') : null, 'catatan' => 'Tindak lanjut minat program.', 'created_at' => $date, 'updated_at' => $date]);
            }
            if ($faker->boolean(42)) {
                $regId = DB::table('pendaftaran')->insertGetId(['lead_id' => $leadId, 'program_bimbel_id' => $program['id'], 'cabang_id' => $branch->cabang_id, 'tanggal_daftar' => $date->format('Y-m-d'), 'kelas_angkatan' => 'Angkatan '.$date->format('Y'), 'status_pendaftaran' => 'Aktif', 'catatan' => 'Pendaftaran demo untuk pengujian dashboard.', 'created_at' => $date, 'updated_at' => $date]);
                DB::table('pembayaran')->insert(['pendaftaran_id' => $regId, 'tanggal_pembayaran' => $date, 'nominal' => $program['biaya'], 'metode_pembayaran' => $faker->randomElement(['Transfer Bank', 'Tunai', 'QRIS']), 'status_pembayaran' => $faker->randomElement(['Lunas', 'Terverifikasi']), 'nomor_referensi' => 'DEMO-'.$regId, 'catatan' => 'Transaksi demo lokal.', 'created_at' => $date, 'updated_at' => $date]);
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
