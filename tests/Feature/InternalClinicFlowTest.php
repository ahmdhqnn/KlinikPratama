<?php

namespace Tests\Feature;

use App\ClinicDocumentNumber;
use App\Exports\KepesertaanTemplateExport;
use App\HakLayananVerifier;
use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InternalClinicFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_sample_preserves_identifiers_links_family_and_is_idempotent(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $actor = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $sheet = $this->sampleWorkbook();
        $sheet->getActiveSheet()->setCellValueExplicit('C2', 198501152010121027, DataType::TYPE_NUMERIC);
        $sheet->getActiveSheet()->setCellValueExplicit('H4', '198501152010121027', DataType::TYPE_STRING);
        $file = $this->workbook($sheet);
        foreach ([1, 2] as $attempt) {
            $this->actingAs($actor)->post(route('kepesertaan.import'), ['file' => $file, 'referensi_bukti' => 'Daftar sumber CONTOH'])->assertSessionHasNoErrors();
            $this->assertDatabaseCount('kepesertaan', 3);
        }
        $employee = Kepesertaan::where('nik', '0000000000000001')->firstOrFail();
        $family = Kepesertaan::where('jenis_lainnya', 'keluarga')->firstOrFail();
        $this->assertSame('198501152010121027', $employee->nip);
        $this->assertSame('Jakarta', $employee->tempat_lahir);
        $this->assertSame('1990-01-01', $employee->tanggal_lahir?->toDateString());
        $this->assertSame('001', $employee->rt);
        $this->assertSame('Unit Contoh', $employee->unit_kerja);
        $this->assertSame($employee->id, $family->pegawai_penanggung_id);
        $this->assertSame($actor->id, $employee->verified_by);
        $this->assertNull(app(HakLayananVerifier::class)->reason($family));
        $this->assertDatabaseHas('clinical_audit_events', ['membership_id' => $employee->id, 'action' => 'membership.changed']);
        $this->actingAs($actor)->get(route('kepesertaan.show', $employee))->assertInertia(fn (Assert $page) => $page
            ->component('kepesertaan/show')
            ->where('member.nik', '0000000000000001')
            ->where('member.tempatLahir', 'Jakarta')
            ->where('member.unitKerja', 'Unit Contoh')
        );
        $this->get(route('kepesertaan.template'))->assertDownload('template-kepesertaan-internal.xlsx');
    }

    public function test_invalid_row_rolls_back_preceding_rows_and_verification_audit(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $sheet = $this->sampleWorkbook();
        $sheet->getActiveSheet()->setCellValue('G3', '');
        $file = $this->workbook($sheet);
        $this->actingAs($actor)->post(route('kepesertaan.import'), ['file' => $file, 'referensi_bukti' => 'Daftar tidak lengkap'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('kepesertaan', 0);
        $this->assertDatabaseCount('clinical_audit_events', 0);
    }

    public function test_manual_ten_person_sample_imports_complete_identity_and_family_links(): void
    {
        $actor = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $sheet = $this->sampleWorkbook();
        $worksheet = $sheet->getActiveSheet();
        $sample = $worksheet->rangeToArray('A2:T2')[0];
        $worksheet->removeRow(2, $worksheet->getHighestRow() - 1);
        for ($index = 0; $index < 10; $index++) {
            $row = $sample;
            $row[0] = $index === 0 ? 'Andi Firmansyah' : ($index === 9 ? 'Rina Kurniasih' : 'Peserta Contoh '.($index + 1));
            $row[1] = sprintf('%016d', $index + 1);
            $row[2] = $index === 9 ? '' : sprintf('%018d', $index + 1);
            $row[3] = $index === 9 ? 'lainnya' : 'asn';
            $row[4] = $index === 9 ? 'keluarga' : '';
            $row[7] = $index === 9 ? sprintf('%018d', 1) : '';
            $row[8] = $index === 9 ? 'istri' : '';
            $row[11] = '1986-10-12';
            $row[16] = '003';
            foreach ($row as $column => $value) {
                $worksheet->setCellValueExplicit([$column + 1, $index + 2], (string) $value, DataType::TYPE_STRING);
            }
        }

        $this->actingAs($actor)->post(route('kepesertaan.import'), [
            'file' => $this->workbook($sheet), 'referensi_bukti' => 'SIMULASI - Data sampel internal 10 peserta',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('kepesertaan', 10);
        $employee = Kepesertaan::where('nama', 'Andi Firmansyah')->firstOrFail();
        $family = Kepesertaan::where('nama', 'Rina Kurniasih')->firstOrFail();
        $this->assertSame('1986-10-12', $employee->tanggal_lahir?->toDateString());
        $this->assertSame('003', $employee->rt);
        $this->assertSame($employee->id, $family->pegawai_penanggung_id);
    }

    public function test_fractional_numeric_identifier_and_missing_header_are_rejected_without_partial_import(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        foreach (['fractional_numeric', 'header', 'duplicate', 'sponsor'] as $invalid) {
            $sheet = $this->sampleWorkbook();
            if ($invalid === 'fractional_numeric') {
                $sheet->getActiveSheet()->setCellValueExplicit('C2', 123456789012345.5, DataType::TYPE_NUMERIC);
            } elseif ($invalid === 'header') {
                $sheet->getActiveSheet()->setCellValue('L1', 'salah_kolom');
            } elseif ($invalid === 'duplicate') {
                $sheet->getActiveSheet()->setCellValueExplicit('B3', '0000000000000001', DataType::TYPE_STRING);
            } else {
                $sheet->getActiveSheet()->setCellValueExplicit('H4', '999999999999999999', DataType::TYPE_STRING);
            }
            $this->actingAs($actor)->post(route('kepesertaan.import'), ['file' => $this->workbook($sheet), 'referensi_bukti' => 'Sumber uji'])->assertSessionHasErrors('file');
            $this->assertDatabaseCount('kepesertaan', 0);
        }
    }

    public function test_visit_captures_verified_entitlement_and_ignores_untrusted_fields(): void
    {
        $this->travelTo('2026-10-16 09:00:00');
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $member = Kepesertaan::factory()->create(['nik' => '0000000000000001']);
        $patient = Pasien::create(['no_rm' => 'RM-I-001', 'nama' => $member->nama, 'nik' => $member->nik, 'kepesertaan_id' => $member->id]);
        $clinic = Poliklinik::create(['kode' => 'I-UMUM', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-INTERNAL', 'nama' => 'Dokter Internal', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'jumat', 'berlaku_mulai' => '2026-10-16', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);
        $payload = ['pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today()->toDateString(), 'jenis_pasien' => 'lama', 'verified_by' => 999];
        $this->actingAs($actor)->post(route('pelayanan.kunjungan.store'), $payload)->assertSessionHasNoErrors();
        $visit = Kunjungan::firstOrFail();
        $this->assertSame($actor->id, $visit->verified_by);
        $this->assertArrayNotHasKey('patient_payable', $visit->hak_layanan_snapshot);
        $member->update(['hak_layanan' => false]);
        $this->assertSame($member->unit_kerja, $visit->fresh()->hak_layanan_snapshot['unit_kerja']);
        $this->post(route('pelayanan.kunjungan.store'), $payload)->assertSessionHasErrors('pasien_id');
        $this->assertDatabaseCount('kunjungan', 1);
    }

    public function test_family_entitlement_is_revoked_with_sponsor_and_clinical_staff_cannot_import(): void
    {
        $sponsor = Kepesertaan::factory()->create();
        $family = Kepesertaan::factory()->create(['kategori' => 'lainnya', 'jenis_lainnya' => 'keluarga', 'nip' => null, 'pegawai_penanggung_id' => $sponsor->id, 'hubungan_keluarga' => 'anak']);
        $verifier = app(HakLayananVerifier::class);
        $this->assertNull($verifier->reason($family));
        $sponsor->update(['hak_layanan' => false]);
        $this->assertNotNull($verifier->reason($family->fresh()));
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->actingAs($doctor)->post(route('kepesertaan.import'))->assertForbidden();
        $this->assertDatabaseCount('kepesertaan', 2);
    }

    public function test_membership_directory_does_not_store_validity_dates(): void
    {
        $member = Kepesertaan::factory()->create();

        $this->assertNull(app(HakLayananVerifier::class)->reason($member));
        $this->assertFalse(Schema::hasColumn('kepesertaan', 'berlaku_mulai'));
        $this->assertFalse(Schema::hasColumn('kepesertaan', 'berlaku_sampai'));
    }

    public function test_document_numbers_do_not_reuse_deleted_visits_or_soft_deleted_patient_numbers(): void
    {
        $number = app(ClinicDocumentNumber::class);
        $patient = Pasien::create(['no_rm' => 'RM-000009', 'nama' => 'Historis']);
        $patient->delete();
        $this->assertSame('RM-000010', $number->next('pasien', 'RM-', 6, 'pasien', 'no_rm'));
        $this->assertSame('RM-000011', $number->next('pasien', 'RM-', 6, 'pasien', 'no_rm'));
        $first = Kunjungan::generateNomor();
        $second = Kunjungan::generateNomor();
        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('-0002', $second);
    }

    public function test_existing_legacy_identity_cannot_be_duplicated_by_forging_registration_nik(): void
    {
        $this->travelTo('2026-10-16 09:00:00');
        $actor = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $member = Kepesertaan::factory()->create(['nik' => '0000000000000001']);
        Pasien::create(['no_rm' => 'RM-HISTORIS', 'nama' => 'Historis', 'nik' => $member->nik]);
        $clinic = Poliklinik::create(['kode' => 'DUP-TEST', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-DUP', 'nama' => 'Dokter Duplikat', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'jumat', 'berlaku_mulai' => '2026-10-16', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);
        $this->actingAs($actor)->post(route('pendaftaran.store-pasien-baru'), ['kepesertaan_id' => $member->id, 'nama' => 'PALSU', 'nik' => '9999999999999999', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'poliklinik_id' => $clinic->id])->assertSessionHasErrors('kepesertaan_id');
        $this->assertDatabaseCount('pasien', 1);
        $this->assertDatabaseCount('kunjungan', 0);
    }

    private function sampleWorkbook(): Spreadsheet
    {
        $template = new KepesertaanTemplateExport;
        $workbook = new Spreadsheet;
        $worksheet = $workbook->getActiveSheet();
        $worksheet->fromArray($template->headings(), null, 'A1');
        foreach ($template->array() as $index => $row) {
            $worksheet->fromArray($row, null, 'A'.($index + 2));
            foreach (['B' => 1, 'C' => 2, 'H' => 7] as $column => $offset) {
                if ($row[$offset] !== '') {
                    $worksheet->setCellValueExplicit($column.($index + 2), $row[$offset], DataType::TYPE_STRING);
                }
            }
        }

        return $workbook;
    }

    private function workbook(Spreadsheet $sheet): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'membership-');
        (new Xlsx($sheet))->save($path);
        $bytes = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('peserta.xlsx', $bytes);
    }
}
