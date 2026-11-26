<?php

namespace Tests\Feature;

use App\Exports\ProcedureConsentTemplateWorkbook;
use App\Models\ClinicalTerminology;
use App\Models\Poliklinik;
use App\Models\ProcedureConsentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProcedureConsentWorkbookTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_downloads_template_imports_draft_and_exports_the_filtered_workbook(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->terminology();
        $blank = $this->get(route('master.consent-templates.template'))->assertDownload('template-sop-persetujuan.xlsx');
        $sheet = IOFactory::load($blank->baseResponse->getFile()->getPathname());
        $this->assertSame($this->headings(), $sheet->getActiveSheet()->toArray()[0]);

        $this->post(route('master.consent-templates.import'), ['file' => $this->workbook([$this->row()], ['published_at'])])
            ->assertRedirect(route('master.consent-templates.index'))->assertSessionHasNoErrors();
        $draft = ProcedureConsentTemplate::sole();
        $this->assertNull($draft->published_at);
        $this->assertSame('=TEKS', $draft->explanation['indication']);

        $download = $this->get(route('master.consent-templates.export', ['poli' => 'umum']))->assertDownload();
        $book = IOFactory::load($download->baseResponse->getFile()->getPathname());
        $this->assertSame('08.10', $book->getActiveSheet()->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $book->getActiveSheet()->getCell('H2')->getDataType());
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'procedure_consent_template.imported']);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'procedure_consent_template.exported']);
    }

    public function test_duplicate_row_rolls_back_entire_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->terminology();
        $this->post(route('master.consent-templates.import'), ['file' => $this->workbook([$this->row(), $this->row()])])
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('procedure_consent_templates', 0);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'procedure_consent_template.imported']);
    }

    public function test_doctor_cannot_import_or_export_master_data(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'dokter']));
        $this->get(route('master.consent-templates.template'))->assertForbidden();
        $this->get(route('master.consent-templates.export'))->assertForbidden();
        $this->post(route('master.consent-templates.import'))->assertForbidden();
        $this->get(route('master.data.export', 'pengguna'))->assertForbidden();
    }

    public function test_account_export_excludes_credentials(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'name' => 'Admin Uji', 'email' => 'internal@example.test']));
        $download = $this->get(route('master.data.export', 'pengguna'))->assertDownload();
        $book = IOFactory::load($download->baseResponse->getFile()->getPathname());
        $this->assertSame([
            ['Nama akun', 'Peran', 'Status akses'],
            ['Admin Uji', 'Administrator', 'Aktif'],
        ], $book->getActiveSheet()->toArray());
    }

    public function test_admin_can_download_master_catalogs_and_room_export_is_scoped(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['tenaga-kerja', 'kepesertaan', 'poliklinik', 'referensi-medis', 'template-surat'] as $catalog) {
            $this->get(route('master.data.export', $catalog))->assertDownload($catalog.'-'.today()->toDateString().'.xlsx');
        }
        $this->get(route('master.terminologi.template'))->assertDownload('template-referensi-medis.xlsx');

        $general = Poliklinik::create(['kode' => 'EXPORT-UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $dental = Poliklinik::create(['kode' => 'EXPORT-GIGI', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $general->ruangPoli()->create(['nama' => 'Ruang A', 'is_active' => true]);
        $dental->ruangPoli()->create(['nama' => 'Ruang B', 'is_active' => true]);

        $download = $this->get(route('master.data.export', ['catalog' => 'ruang-poli', 'poliklinik_id' => $general->id]))->assertDownload();
        $book = IOFactory::load($download->baseResponse->getFile()->getPathname());
        $this->assertSame([['Poliklinik', 'Nama ruang', 'Status'], ['Poli Umum', 'Ruang A', 'Aktif']], $book->getActiveSheet()->toArray());
    }

    private function terminology(): void
    {
        ClinicalTerminology::create(['code_system' => 'icd9cm_procedure', 'code_type' => 'procedure',
            'release' => 'TEST', 'code' => '08.10', 'display' => 'Uji sintetis', 'is_active' => true]);
    }

    private function headings(): array
    {
        return [...ProcedureConsentTemplateWorkbook::COLUMNS, ...ProcedureConsentTemplate::FIELDS];
    }

    private function row(): array
    {
        return ['08.10', 'TEST', 'umum', 'v1', 'written', null, 'SOP sintetis', '=TEKS', ...array_fill(0, 6, null)];
    }

    private function workbook(array $rows, array $extraHeadings = []): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([...$this->headings(), ...$extraHeadings]);
        foreach ($rows as $index => $row) {
            foreach ($row as $column => $value) {
                if ($value !== null) {
                    $sheet->setCellValueExplicit([$column + 1, $index + 2], $value, DataType::TYPE_STRING);
                }
            }
            if ($extraHeadings !== []) {
                $sheet->setCellValue('O'.($index + 2), '2026-01-01');
            }
        }
        ob_start();
        (new Xlsx($book))->save('php://output');
        $content = ob_get_clean();

        return UploadedFile::fake()->createWithContent('sop.xlsx', $content);
    }
}
