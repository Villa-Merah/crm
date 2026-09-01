<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\Leads\Pages\CreateLead;
use App\Models\Cabang;
use App\Models\Sekolah;
use App\Models\User;
use App\Services\Schools\CreateSchoolFromLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateSchoolFromLeadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_school_with_only_the_name(): void
    {
        $school = app(CreateSchoolFromLead::class)->create([
            'nama_sekolah_baru' => ' sman 5 bandung ',
        ]);

        $this->assertSame('SMAN 5 BANDUNG', $school->nama_sekolah);
        $this->assertSame('Negeri', $school->status_sekolah);
        $this->assertNull($school->kota);
        $this->assertNull($school->npsn);
        $this->assertTrue($school->aktif);
    }

    public function test_it_accepts_and_normalizes_an_alphanumeric_npsn(): void
    {
        $school = app(CreateSchoolFromLead::class)->create([
            'nama_sekolah_baru' => 'PKBM Madani',
            'npsn' => 'p9984383',
        ]);

        $this->assertSame('P9984383', $school->npsn);

        $sameSchool = app(CreateSchoolFromLead::class)->create([
            'nama_sekolah_baru' => 'PKBM Madani Baru',
            'npsn' => 'P9984383',
        ]);

        $this->assertTrue($school->is($sameSchool));
        $this->assertSame(1, Sekolah::where('npsn', 'P9984383')->count());
    }

    public function test_it_creates_a_school_with_a_normalized_supported_prefix(): void
    {
        $school = app(CreateSchoolFromLead::class)->create([
            'prefix' => 'SMAN',
            'nama_sekolah_baru' => ' sman   3 bandung ',
            'npsn' => '',
            'kota' => ' kota bandung ',
            'provinsi' => 'jawa barat',
        ]);

        $this->assertSame('SMAN 3 BANDUNG', $school->nama_sekolah);
        $this->assertSame('Negeri', $school->status_sekolah);
        $this->assertSame('Kota Bandung', $school->kota);
        $this->assertSame('Jawa Barat', $school->provinsi);
        $this->assertNull($school->npsn);
        $this->assertTrue($school->aktif);
    }

    public function test_it_uses_the_existing_school_instead_of_creating_a_duplicate(): void
    {
        $existing = Sekolah::create([
            'nama_sekolah' => 'SMAS HARAPAN JAKARTA',
            'status_sekolah' => 'Swasta',
            'kota' => 'Jakarta Selatan',
            'aktif' => false,
        ]);

        $resolved = app(CreateSchoolFromLead::class)->create([
            'prefix' => 'SMAS',
            'nama_sekolah_baru' => 'Harapan   Jakarta',
            'kota' => 'Jakarta Selatan',
        ]);

        $this->assertTrue($existing->is($resolved));
        $this->assertTrue($resolved->fresh()->aktif);
        $this->assertSame(1, Sekolah::count());
    }

    public function test_each_supported_format_sets_the_expected_school_status(): void
    {
        $service = app(CreateSchoolFromLead::class);

        $this->assertSame('Swasta', $service->create(['prefix' => 'SMAS', 'nama_sekolah_baru' => 'Pelita', 'kota' => 'Bandung'])->status_sekolah);
        $this->assertSame('Negeri', $service->create(['prefix' => 'SMKN', 'nama_sekolah_baru' => '5 Bandung', 'kota' => 'Bandung'])->status_sekolah);
        $this->assertSame('Swasta', $service->create(['prefix' => 'SMKS', 'nama_sekolah_baru' => 'Insan Mulia', 'kota' => 'Bandung'])->status_sekolah);
        $this->assertSame('Negeri', $service->create(['prefix' => 'MAN', 'nama_sekolah_baru' => '1 Jakarta', 'kota' => 'Jakarta'])->status_sekolah);
    }

    public function test_alumni_gapyear_does_not_require_a_school_name_or_npsn(): void
    {
        $school = app(CreateSchoolFromLead::class)->create([
            'prefix' => 'ALUMNI/GAPYEAR',
            'kota' => 'Bandung',
        ]);

        $this->assertSame('ALUMNI/GAPYEAR', $school->nama_sekolah);
        $this->assertSame('Lainnya', $school->status_sekolah);
        $this->assertSame('Bandung', $school->kota);
        $this->assertNull($school->npsn);
    }

    public function test_same_school_name_in_different_cities_is_not_treated_as_a_duplicate(): void
    {
        $service = app(CreateSchoolFromLead::class);

        $service->create(['prefix' => 'SMAN', 'nama_sekolah_baru' => '1', 'kota' => 'Bandung']);
        $service->create(['prefix' => 'SMAN', 'nama_sekolah_baru' => '1', 'kota' => 'Jakarta Pusat']);

        $this->assertSame(2, Sekolah::where('nama_sekolah', 'SMAN 1')->count());
    }

    public function test_staff_can_add_and_select_a_missing_school_from_the_lead_form(): void
    {
        $branch = Cabang::create([
            'kode_cabang' => 'BDG',
            'nama' => 'Bandung',
            'kota' => 'Bandung',
            'aktif' => true,
        ]);
        $staff = User::factory()->create([
            'cabang_id' => $branch->getKey(),
            'role' => UserRole::Staff,
            'aktif' => true,
        ]);

        $component = Livewire::actingAs($staff)
            ->test(CreateLead::class)
            ->assertSee('Cari dari API Sekolah Indonesia')
            ->assertFormComponentActionExists('sekolah_id', 'createOption')
            ->mountFormComponentAction('sekolah_id', 'createOption')
            ->set('mountedActions.0.data', [
                'nama_sekolah_baru' => 'MAN 2 Bandung',
                'npsn' => '',
                'kota' => '',
                'provinsi' => '',
            ])
            ->callMountedFormComponentAction()
            ->assertHasNoFormComponentActionErrors();

        $school = Sekolah::where('nama_sekolah', 'MAN 2 BANDUNG')->firstOrFail();

        $component->assertFormSet(['sekolah_id' => $school->getKey()]);
    }
}
