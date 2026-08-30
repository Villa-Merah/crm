<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Exports\LeadExporter;
use App\Filament\Imports\LeadImporter;
use App\Models\Cabang;
use App\Models\Lead;
use App\Models\ProgramBimbel;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\LeadCsvTemplate;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LeadCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cabang_can_import_lead_using_human_readable_references(): void
    {
        [$branch, $admin, $pic, $school, $program] = $this->fixtures();
        $importer = new LeadImporter($this->importFor($admin), $this->columnMap(), []);

        $importer($this->row([
            'sekolah_npsn' => $school->npsn,
            'sekolah_nama' => $school->nama_sekolah,
            'program_bimbel' => $program->nama_program,
            'cabang' => $branch->nama,
            'pic_email' => $pic->email,
        ]));

        $lead = Lead::sole();
        $this->assertSame('Alya Putri', $lead->nama);
        $this->assertSame('Bandung', $lead->kota_asal);
        $this->assertSame('XII', $lead->kelas);
        $this->assertSame($branch->getKey(), $lead->cabang_id);
        $this->assertSame($school->getKey(), $lead->sekolah_id);
        $this->assertSame($program->getKey(), $lead->minat_program_id);
        $this->assertSame($pic->getKey(), $lead->diserahkan_ke_user_id);
        $this->assertSame($admin->getKey(), $lead->diinput_oleh_user_id);
        $this->assertSame('Instagram', $lead->sumber_lead);
        $this->assertSame('Warm', $lead->status_lead);
    }

    public function test_import_requires_a_valid_source_for_new_lead(): void
    {
        [$branch, $admin, $pic] = $this->fixtures();
        $importer = new LeadImporter($this->importFor($admin), $this->columnMap(), []);

        try {
            $importer($this->row([
                'sumber_lead' => '',
                'cabang' => $branch->nama,
                'pic_email' => $pic->email,
            ]));
            $this->fail('Sumber leads kosong seharusnya ditolak untuk lead baru.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sumber_lead', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $importer($this->row(['sumber_lead' => 'Marketplace']));
    }

    public function test_import_validation_errors_are_clear_in_indonesian(): void
    {
        [, $admin] = $this->fixtures();
        $importer = new LeadImporter($this->importFor($admin), $this->columnMap(), []);

        try {
            $importer($this->row([
                'no_wa' => 'nomor-salah',
                'kota_asal' => '',
            ]));
            $this->fail('Baris dengan nomor WhatsApp dan kota asal tidak valid seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Format nomor WhatsApp tidak valid. Gunakan 8–20 digit dan boleh diawali tanda +.',
                $exception->errors()['no_wa'][0],
            );
            $this->assertSame('Kota asal wajib diisi.', $exception->errors()['kota_asal'][0]);
        }
    }

    public function test_import_rejects_cross_branch_pic_and_read_only_user(): void
    {
        [$branch, $admin] = $this->fixtures();
        $otherBranch = Cabang::create(['kode_cabang' => 'JAKSEL', 'nama' => 'JakSel', 'kota' => 'Jakarta', 'aktif' => true]);
        $otherPic = User::factory()->create(['cabang_id' => $otherBranch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $importer = new LeadImporter($this->importFor($admin), $this->columnMap(), []);

        try {
            $importer($this->row(['cabang' => $branch->nama, 'pic_email' => $otherPic->email]));
            $this->fail('PIC lintas cabang seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pic_email', $exception->errors());
        }

        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $readonlyImporter = new LeadImporter($this->importFor($direksi), $this->columnMap(), []);

        $this->expectException(HttpException::class);
        $readonlyImporter($this->row());
    }

    public function test_staff_cannot_update_a_lead_outside_their_visible_scope(): void
    {
        [$branch, $admin, $staff] = $this->fixtures();
        $otherStaff = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $lead = Lead::create([
            'nama' => 'Lead Milik Orang Lain',
            'no_wa' => '081234567890',
            'cabang_id' => $branch->getKey(),
            'status_lead' => 'Cold',
            'diinput_oleh_user_id' => $admin->getKey(),
            'diserahkan_ke_user_id' => $otherStaff->getKey(),
            'tanggal_masuk' => '2026-08-20',
        ]);
        $importer = new LeadImporter($this->importFor($staff), $this->columnMap(), []);

        $this->expectException(ValidationException::class);
        $importer($this->row(['lead_id' => (string) $lead->getKey(), 'cabang' => $branch->nama, 'pic_email' => $staff->email]));
    }

    public function test_exporter_outputs_round_trip_columns_and_neutralizes_formulas(): void
    {
        [$branch, $admin, $pic, $school, $program] = $this->fixtures();
        $lead = Lead::create([
            'nama' => '=HYPERLINK("https://example.test")',
            'no_wa' => '+6281234567890',
            'kota_asal' => '  Bandung  ',
            'kelas' => 'XII',
            'sekolah_id' => $school->getKey(),
            'minat_program_id' => $program->getKey(),
            'cabang_id' => $branch->getKey(),
            'sumber_lead' => 'Facebook',
            'status_lead' => 'Hot',
            'diinput_oleh_user_id' => $admin->getKey(),
            'diserahkan_ke_user_id' => $pic->getKey(),
            'tanggal_masuk' => '2026-08-21',
        ]);
        $export = Export::create(['file_disk' => 'local', 'file_name' => 'leads', 'exporter' => LeadExporter::class, 'total_rows' => 1, 'user_id' => $admin->id]);
        $map = array_combine(LeadCsvTemplate::HEADERS, LeadCsvTemplate::HEADERS);
        $data = (new LeadExporter($export, $map, []))($lead->load(['sekolah', 'minatProgram', 'cabang', 'diserahkanKe']));

        $this->assertSame((string) $lead->getKey(), $data[0]);
        $this->assertStringStartsWith("'=HYPERLINK", $data[1]);
        $this->assertSame("'+6281234567890", $data[2]);
        $this->assertSame('Bandung', $data[3]);
        $this->assertSame('XII', $data[4]);
        $this->assertSame($school->npsn, $data[5]);
        $this->assertSame('Facebook', $data[8]);
        $this->assertSame($branch->nama, $data[10]);
        $this->assertSame($pic->email, $data[11]);
        $this->assertSame('2026-08-21', $data[12]);
    }

    public function test_export_query_is_scoped_to_the_authenticated_users_branch(): void
    {
        [$branch, $admin, $pic] = $this->fixtures();
        $otherBranch = Cabang::create(['kode_cabang' => 'JAKPUS', 'nama' => 'JakPus', 'kota' => 'Jakarta', 'aktif' => true]);
        $otherPic = User::factory()->create(['cabang_id' => $otherBranch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);

        foreach ([[$branch, $pic], [$otherBranch, $otherPic]] as [$leadBranch, $leadPic]) {
            Lead::create([
                'nama' => 'Lead '.$leadBranch->nama,
                'no_wa' => '081234567890',
                'cabang_id' => $leadBranch->getKey(),
                'status_lead' => 'Cold',
                'diinput_oleh_user_id' => $leadPic->getKey(),
                'diserahkan_ke_user_id' => $leadPic->getKey(),
                'tanggal_masuk' => '2026-08-21',
            ]);
        }

        $this->actingAs($admin);
        $exportedNames = LeadExporter::modifyQuery(Lead::query())->pluck('nama');

        $this->assertSame(['Lead Bandung'], $exportedNames->all());
    }

    public function test_example_csv_and_page_actions_follow_permissions(): void
    {
        $response = LeadCsvTemplate::download();
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString('lead_id,nama,no_wa,kota_asal,kelas,sekolah_npsn', $content);
        $this->assertStringContainsString('program_bimbel,sumber_lead,status_lead', $content);
        $this->assertStringContainsString('Alya Putri', $content);

        [, $admin] = $this->fixtures();
        $this->actingAs($admin)->get('/admin/leads')->assertOk()->assertSee('Contoh CSV')->assertSee('Impor CSV')->assertSee('Ekspor CSV');

        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $this->actingAs($direksi)->get('/admin/leads')->assertOk()->assertSee('Contoh CSV')->assertDontSee('Impor CSV')->assertSee('Ekspor CSV');
    }

    /** @return array{Cabang, User, User, Sekolah, ProgramBimbel} */
    private function fixtures(): array
    {
        $branch = Cabang::firstOrCreate(
            ['kode_cabang' => 'BDG'],
            ['nama' => 'Bandung', 'kota' => 'Bandung', 'aktif' => true],
        );
        $admin = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::AdminCabang, 'aktif' => true]);
        $pic = User::factory()->create(['cabang_id' => $branch->getKey(), 'role' => UserRole::Staff, 'aktif' => true]);
        $school = Sekolah::firstOrCreate(
            ['npsn' => '20200001'],
            ['nama_sekolah' => 'SMA Contoh Nusantara', 'kota' => 'Bandung', 'aktif' => true],
        );
        $program = ProgramBimbel::firstOrCreate(
            ['kategori_program' => 'SR', 'program_kelas' => 'Gold', 'metode_belajar' => 'Online'],
            ['tipe_program' => 'Intensif', 'jenjang_program' => 'SMA', 'biaya_pendidikan' => 1000000, 'aktif' => true],
        );

        return [$branch, $admin, $pic, $school, $program];
    }

    private function importFor(User $user): Import
    {
        return Import::create([
            'file_name' => 'leads.csv',
            'file_path' => 'testing/leads.csv',
            'importer' => LeadImporter::class,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);
    }

    /** @return array<string, string> */
    private function columnMap(): array
    {
        return array_combine(array_keys($this->row()), array_keys($this->row()));
    }

    /** @return array<string, string> */
    private function row(array $overrides = []): array
    {
        return array_replace([
            'lead_id' => '',
            'nama' => 'Alya Putri',
            'no_wa' => '+6281234567890',
            'kota_asal' => 'Bandung',
            'kelas' => 'XII',
            'sekolah_npsn' => '',
            'sekolah_nama' => '',
            'program_bimbel' => '',
            'sumber_lead' => 'Instagram',
            'status_lead' => 'Warm',
            'cabang' => 'Bandung',
            'pic_email' => 'staff.bandung@sivmi.test',
            'tanggal_masuk' => '2026-08-21',
        ], $overrides);
    }
}
