<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Exports\SekolahExporter;
use App\Filament\Imports\SekolahImporter;
use App\Models\Sekolah;
use App\Models\User;
use App\Support\SekolahCsvTemplate;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SchoolCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_new_school_and_update_by_npsn(): void
    {
        $admin = User::factory()->create(['role' => UserRole::AdminCabang, 'aktif' => true]);
        $import = $this->importFor($admin);
        $importer = new SekolahImporter($import, $this->columnMap(), []);

        $importer($this->row(['npsn' => '20200001', 'nama_sekolah' => 'SMA Nusantara', 'kota' => 'Bandung']));
        $school = Sekolah::where('npsn', '20200001')->firstOrFail();
        $this->assertSame('SMA Nusantara', $school->nama_sekolah);
        $this->assertTrue($school->aktif);

        $importer($this->row(['npsn' => '20200001', 'nama_sekolah' => 'SMA Nusantara Utama', 'kota' => 'Cimahi']));
        $this->assertSame(1, Sekolah::where('npsn', '20200001')->count());
        $this->assertSame('Cimahi', $school->refresh()->kota);
        $this->assertSame('SMA Nusantara Utama', $school->nama_sekolah);
    }

    public function test_import_validates_npsn_and_rejects_read_only_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $importer = new SekolahImporter($this->importFor($admin), $this->columnMap(), []);
        try {
            $importer($this->row(['npsn' => '123', 'nama_sekolah' => 'Sekolah Salah', 'kota' => 'Bandung']));
            $this->fail('NPSN tidak valid seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('npsn', $exception->errors());
        }

        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $readonlyImporter = new SekolahImporter($this->importFor($direksi), $this->columnMap(), []);
        $this->expectException(HttpException::class);
        $readonlyImporter($this->row(['npsn' => '20200002', 'nama_sekolah' => 'Sekolah Tidak Diizinkan', 'kota' => 'Jakarta']));
    }

    public function test_import_accepts_multiple_schools_without_npsn_and_validates_grade(): void
    {
        $admin = User::factory()->create(['role' => UserRole::AdminCabang, 'aktif' => true]);
        $importer = new SekolahImporter($this->importFor($admin), $this->columnMap(), []);

        $importer($this->row(['npsn' => '', 'nama_sekolah' => 'Sekolah Tanpa NPSN Satu', 'kluster_sekolah' => 'Grade A']));
        $importer($this->row(['npsn' => '', 'nama_sekolah' => 'Sekolah Tanpa NPSN Dua', 'kluster_sekolah' => 'Grade E']));

        $this->assertSame(2, Sekolah::whereNull('npsn')->count());

        $this->expectException(ValidationException::class);
        $importer($this->row(['npsn' => '', 'nama_sekolah' => 'Sekolah Grade Salah', 'kluster_sekolah' => 'Prioritas']));
    }

    public function test_exporter_outputs_expected_columns_and_neutralizes_formulas(): void
    {
        $user = User::factory()->create(['role' => UserRole::Superadmin, 'aktif' => true]);
        $school = Sekolah::create($this->row(['npsn' => '20200003', 'nama_sekolah' => '=HYPERLINK("https://example.test")', 'kota' => 'Bandung']));
        $export = Export::create(['file_disk' => 'local', 'file_name' => 'sekolah', 'exporter' => SekolahExporter::class, 'total_rows' => 1, 'user_id' => $user->id]);
        $exporter = new SekolahExporter($export, array_combine(array_keys($this->row()), array_keys($this->row())), []);
        $data = $exporter($school);

        $this->assertSame('20200003', $data[0]);
        $this->assertStringStartsWith("'=HYPERLINK", $data[1]);
        $this->assertSame('1', $data[8]);
    }

    public function test_example_csv_and_page_actions_are_available_by_role(): void
    {
        $response = SekolahCsvTemplate::download();
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString('npsn,nama_sekolah,status_sekolah', $content);
        $this->assertStringContainsString('SMA Contoh Nusantara', $content);

        $admin = User::factory()->create(['role' => UserRole::AdminCabang, 'aktif' => true]);
        $this->actingAs($admin)->get('/admin/sekolah')->assertOk()->assertSee('Contoh CSV')->assertSee('Impor CSV')->assertSee('Ekspor CSV');

        $direksi = User::factory()->create(['role' => UserRole::Direksi, 'aktif' => true]);
        $this->actingAs($direksi)->get('/admin/sekolah')->assertOk()->assertSee('Contoh CSV')->assertDontSee('Impor CSV')->assertSee('Ekspor CSV');
    }

    private function importFor(User $user): Import
    {
        return Import::create(['file_name' => 'sekolah.csv', 'file_path' => 'testing/sekolah.csv', 'importer' => SekolahImporter::class, 'total_rows' => 1, 'user_id' => $user->id]);
    }

    private function columnMap(): array
    {
        return array_combine(array_keys($this->row()), array_keys($this->row()));
    }

    private function row(array $overrides = []): array
    {
        return array_replace(['npsn' => '20200001', 'nama_sekolah' => 'SMA Contoh', 'status_sekolah' => 'Negeri', 'alamat' => 'Jl. Pendidikan', 'kecamatan' => 'Coblong', 'kota' => 'Bandung', 'provinsi' => 'Jawa Barat', 'kluster_sekolah' => 'Grade A', 'aktif' => '1'], $overrides);
    }
}
