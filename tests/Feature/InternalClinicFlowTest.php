<?php

namespace Tests\Feature;

use App\ClinicDocumentNumber;
use App\HakLayananVerifier;
use App\Models\Kepesertaan;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        $sheet = IOFactory::load(resource_path('templates/contoh-kepesertaan-internal.xlsx'));
        $sheet->getActiveSheet()->setCellValueExplicit('C2', 198501152010121027, DataType::TYPE_NUMERIC);
        $sheet->getActiveSheet()->setCellValueExplicit('H5', '198501152010121027', DataType::TYPE_STRING);
        $file = $this->workbook($sheet);
        foreach ([1, 2] as $attempt) {
            $this->actingAs($actor)->post(route('kepesertaan.import'), ['file' => $file, 'referensi_bukti' => 'Daftar sumber CONTOH'])->assertSessionHasNoErrors();
            $this->assertDatabaseCount('kepesertaan', 6);
        }
        $employee = Kepesertaan::where('nik', '0000000000000001')->firstOrFail();
        $family = Kepesertaan::where('kategori', 'keluarga')->firstOrFail();
        $this->assertSame('198501152010121027', $employee->nip);
        $this->assertSame('Bandung', $employee->tempat_lahir);
        $this->assertSame('1985-01-15', $employee->tanggal_lahir?->toDateString());
        $this->assertSame('001', $employee->rt);
        $this->assertSame('Biro Umum Contoh', $employee->unit_kerja);
        $this->assertSame($employee->id, $family->pegawai_penanggung_id);
        $this->assertSame($actor->id, $employee->verified_by);
        $this->assertNull(app(HakLayananVerifier::class)->reason($family, '2026-10-08'));
        $this->assertNotNull(app(HakLayananVerifier::class)->reason(Kepesertaan::where('status_kepegawaian', 'nonaktif')->first(), '2026-10-08'));
        $this->assertDatabaseHas('clinical_audit_events', ['membership_id' => $employee->id, 'action' => 'membership.changed']);
        $this->actingAs($actor)->get(route('kepesertaan.show', $employee))->assertInertia(fn (Assert $page) => $page
            ->component('kepesertaan/show')
            ->where('member.nik', '0000000000000001')
            ->where('member.tempatLahir', 'Bandung')
            ->where('member.unitKerja', 'Biro Umum Contoh')
        );
        $this->get(route('kepesertaan.template'))->assertDownload('contoh-kepesertaan-internal.xlsx');
    }

    public function test_invalid_row_rolls_back_preceding_rows_and_verification_audit(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $sheet = IOFactory::load(resource_path('templates/contoh-kepesertaan-internal.xlsx'));
        $sheet->getActiveSheet()->setCellValue('G3', '');
        $file = $this->workbook($sheet);
        $this->actingAs($actor)->post(route('kepesertaan.import'), ['file' => $file, 'referensi_bukti' => 'Daftar tidak lengkap'])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('kepesertaan', 0);
        $this->assertDatabaseCount('clinical_audit_events', 0);
    }

    public function test_manual_ten_person_sample_imports_complete_identity_and_family_links(): void
    {
        $actor = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $sheet = IOFactory::load(base_path('outputs/manual-test-kepesertaan/data-peserta-internal-sampel-10.xlsx'));
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
            $sheet = IOFactory::load(resource_path('templates/contoh-kepesertaan-internal.xlsx'));
            if ($invalid === 'fractional_numeric') {
                $sheet->getActiveSheet()->setCellValueExplicit('C2', 123456789012345.5, DataType::TYPE_NUMERIC);
            } elseif ($invalid === 'header') {
                $sheet->getActiveSheet()->setCellValue('L1', 'salah_kolom');
            } elseif ($invalid === 'duplicate') {
                $sheet->getActiveSheet()->setCellValueExplicit('B3', '0000000000000001', DataType::TYPE_STRING);
            } else {
                $sheet->getActiveSheet()->setCellValueExplicit('H5', '999999999999999999', DataType::TYPE_STRING);
            }
            $this->actingAs($actor)->post(route('kepesertaan.import'), ['file' => $this->workbook($sheet), 'referensi_bukti' => 'Sumber uji'])->assertSessionHasErrors('file');
            $this->assertDatabaseCount('kepesertaan', 0);
        }
    }

    public function test_visit_captures_verified_entitlement_and_ignores_forged_billing_and_cost_center(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $member = Kepesertaan::factory()->create(['nik' => '0000000000000001', 'cost_center' => 'CC-ASLI', 'berlaku_mulai' => today(), 'berlaku_sampai' => today()]);
        $patient = Pasien::create(['no_rm' => 'RM-I-001', 'nama' => $member->nama, 'nik' => $member->nik, 'kepesertaan_id' => $member->id]);
        $clinic = Poliklinik::create(['kode' => 'I-UMUM', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $payload = ['pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today()->toDateString(), 'jenis_pasien' => 'lama', 'jenis_bayar' => 'umum', 'cost_center' => 'PALSU', 'verified_by' => 999];
        $this->actingAs($actor)->post(route('pelayanan.kunjungan.store'), $payload)->assertSessionHasNoErrors();
        $visit = Kunjungan::firstOrFail();
        $this->assertSame('internal', $visit->jenis_bayar);
        $this->assertSame('CC-ASLI', $visit->cost_center);
        $this->assertSame($actor->id, $visit->verified_by);
        $this->assertSame(0, $visit->hak_layanan_snapshot['patient_payable']);
        $member->update(['hak_layanan' => false, 'cost_center' => 'CC-BARU']);
        $this->assertSame('CC-ASLI', $visit->fresh()->hak_layanan_snapshot['cost_center']);
        $this->post(route('pelayanan.kunjungan.store'), $payload)->assertSessionHasErrors('pasien_id');
        $this->assertDatabaseCount('kunjungan', 1);
        $this->assertDatabaseCount('tagihan', 0);
    }

    public function test_family_entitlement_expires_with_sponsor_and_clinical_staff_cannot_import(): void
    {
        $sponsor = Kepesertaan::factory()->create(['berlaku_sampai' => today()]);
        $family = Kepesertaan::factory()->create(['kategori' => 'keluarga', 'pegawai_penanggung_id' => $sponsor->id, 'hubungan_keluarga' => 'anak']);
        $verifier = app(HakLayananVerifier::class);
        $this->assertNull($verifier->reason($family, today()->toDateString()));
        $this->assertNotNull($verifier->reason($family, today()->addDay()->toDateString()));
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->actingAs($doctor)->post(route('kepesertaan.import'))->assertForbidden();
        $this->assertDatabaseCount('kepesertaan', 2);
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
        $actor = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $member = Kepesertaan::factory()->create(['nik' => '0000000000000001']);
        Pasien::create(['no_rm' => 'RM-HISTORIS', 'nama' => 'Historis', 'nik' => $member->nik]);
        $clinic = Poliklinik::create(['kode' => 'DUP-TEST', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $this->actingAs($actor)->post(route('pendaftaran.store-pasien-baru'), ['kepesertaan_id' => $member->id, 'nama' => 'PALSU', 'nik' => '9999999999999999', 'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'poliklinik_id' => $clinic->id])->assertSessionHasErrors('kepesertaan_id');
        $this->assertDatabaseCount('pasien', 1);
        $this->assertDatabaseCount('kunjungan', 0);
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
