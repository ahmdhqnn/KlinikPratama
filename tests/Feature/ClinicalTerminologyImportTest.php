<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ClinicalTerminologyImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_import_a_standard_header_csv_and_codes_are_searchable_by_system(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $file = UploadedFile::fake()->createWithContent('icd10-who.csv', "Code,Title\nI10,Essential (primary) hypertension\nJ00,Acute nasopharyngitis [common cold]\n");

        $this->actingAs($admin)->post(route('master.terminologi.import'), [
            'file' => $file,
            'code_system' => 'icd10_who',
            'release' => 'WHO 2016',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('clinical_terminologies', 2);
        $this->assertDatabaseHas('clinical_terminologies', [
            'code_system' => 'icd10_who',
            'release' => 'WHO 2016',
            'code' => 'I10',
            'display' => 'Essential (primary) hypertension',
            'code_type' => 'diagnosis',
        ]);

        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->actingAs($doctor)->get(route('pelayanan.icd10.search', ['q' => 'hypertension', 'code_system' => 'icd10_who']))
            ->assertOk()
            ->assertJsonFragment(['kode' => 'I10', 'nama' => 'Essential (primary) hypertension', 'code_system' => 'icd10_who']);

        $this->actingAs($admin)->get(route('master.terminologi.index'))->assertInertia(fn (Assert $page) => $page
            ->component('master/terminologi/index')
            ->where('systems.0.system', 'icd10_who')
            ->where('systems.0.release', 'WHO 2016')
            ->where('systems.0.count', 2)
        );
    }

    public function test_import_is_atomic_when_a_code_row_is_missing_its_display_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $file = UploadedFile::fake()->createWithContent('incomplete.csv', "Code,Title\nI10,Hypertension\nJ00,\n");

        $this->actingAs($admin)->from(route('master.terminologi.index'))->post(route('master.terminologi.import'), [
            'file' => $file,
            'code_system' => 'icd10_who',
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('clinical_terminologies', 0);
    }

    public function test_official_icd9_cm_archive_imports_the_selected_dx_flat_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $archivePath = tempnam(sys_get_temp_dir(), 'icd9-');
        $archive = new ZipArchive;
        $this->assertSame(true, $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $archive->addFromString('ICD-9-CM-v32-master-descriptions/CMS32_DESC_LONG_DX.txt', "25000 Type 2 diabetes mellitus\n");
        $archive->close();
        $file = new UploadedFile($archivePath, 'cms-icd9.zip', 'application/zip', null, true);

        try {
            $this->actingAs($admin)->post(route('master.terminologi.import'), [
                'file' => $file,
                'code_system' => 'icd9cm_diagnosis',
                'release' => 'CMS Version 32',
            ])->assertSessionHasNoErrors();
        } finally {
            if (is_file($archivePath)) {
                unlink($archivePath);
            }
        }

        $this->assertDatabaseHas('clinical_terminologies', [
            'code_system' => 'icd9cm_diagnosis',
            'release' => 'CMS Version 32',
            'code' => '250.00',
            'display' => 'Type 2 diabetes mellitus',
        ]);
    }

    public function test_non_admin_cannot_import_terminology_catalogs(): void
    {
        $registrationUser = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        $this->actingAs($registrationUser)->get(route('master.terminologi.index'))->assertRedirect(route('pendaftaran.dashboard'));
    }
}
