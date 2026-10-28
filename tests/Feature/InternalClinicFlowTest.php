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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class InternalClinicFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_sample_preserves_identifiers_and_is_idempotent_for_internal_staff(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 8));
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $sheet = $this->sampleWorkbook();
        $sheet->getActiveSheet()->setCellValueExplicit('C2', 198501152010121027, DataType::TYPE_NUMERIC);
        $file = $this->workbook($sheet);
        foreach ([1, 2] as $attempt) {
            $this->actingAs($actor)->post(route('kepesertaan.import'), ['file' => $file, 'referensi_bukti' => 'Daftar sumber CONTOH'])->assertSessionHasNoErrors();
            $this->assertDatabaseCount('kepesertaan', 4);
        }
        $employee = Kepesertaan::where('nik', '0000000000000001')->firstOrFail();
        $staff = Kepesertaan::where('jenis_lainnya', 'staf')->firstOrFail();
        $custom = Kepesertaan::where('jenis_lainnya', 'Tenaga pendukung')->firstOrFail();
        $this->assertSame('198501152010121027', $employee->nip);
        $this->assertSame('Jakarta', $employee->tempat_lahir);
        $this->assertSame('1990-01-01', $employee->tanggal_lahir?->toDateString());
        $this->assertNull($employee->rt);
        $this->assertSame('Bagian Umum', $employee->unit_kerja);
        $this->assertNull($staff->pegawai_penanggung_id);
        $this->assertSame($actor->id, $employee->verified_by);
        $this->assertNull(app(HakLayananVerifier::class)->reason($staff));
        $this->assertNull(app(HakLayananVerifier::class)->reason($custom));
        $this->assertDatabaseHas('clinical_audit_events', ['membership_id' => $employee->id, 'action' => 'membership.changed']);
        $this->actingAs($actor)->get(route('kepesertaan.show', $employee))->assertInertia(fn (Assert $page) => $page
            ->component('kepesertaan/show')
            ->where('member.nik', '0000000000000001')
            ->where('member.tempatLahir', 'Jakarta')
            ->where('member.unitKerja', 'Bagian Umum')
        );
        $download = $this->get(route('kepesertaan.template'));
        $download->assertDownload('template-kepesertaan-internal.xlsx');
        $downloadedSheet = IOFactory::load($download->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('alamat', $downloadedSheet->getCell('N1')->getValue());
        $this->assertNull($downloadedSheet->getCell('O1')->getValue());
        $this->assertSame('0000000000000001', $downloadedSheet->getCell('B2')->getValue());
        $this->assertSame('000000000000000001', $downloadedSheet->getCell('C2')->getValue());
        $this->assertSame('staf', $downloadedSheet->getCell('E4')->getValue());
        $this->assertSame('Tenaga pendukung', $downloadedSheet->getCell('E5')->getValue());
        $this->assertCount(14, (new KepesertaanTemplateExport)->headings());
        $this->assertNotContains('rt', (new KepesertaanTemplateExport)->headings());
    }

    public function test_legacy_workbook_import_preserves_saved_area_data_when_updated_with_current_template(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $legacy = $this->sampleWorkbook();
        $legacySheet = $legacy->getActiveSheet();
        $legacySheet->fromArray(['rt', 'rw', 'kelurahan', 'kecamatan'], null, 'O1');
        $legacySheet->fromArray(['001', '002', 'Sukamaju', 'Cibeunying'], null, 'O2');

        $this->actingAs($actor)->post(route('kepesertaan.import'), [
            'file' => $this->workbook($legacy), 'referensi_bukti' => 'Daftar instansi lama',
        ])->assertSessionHasNoErrors();

        $member = Kepesertaan::where('nik', '0000000000000001')->firstOrFail();
        $this->assertSame('001', $member->rt);
        $this->assertSame('Sukamaju', $member->kelurahan);

        $this->post(route('kepesertaan.import'), [
            'file' => $this->workbook($this->sampleWorkbook()), 'referensi_bukti' => 'Daftar instansi terbaru',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('kepesertaan', 4);
        $this->assertSame('001', $member->fresh()->rt);
        $this->assertSame('Sukamaju', $member->fresh()->kelurahan);
        $this->assertSame('Jl. Contoh No. 1, Jakarta', $member->fresh()->alamat);

        $this->put(route('kepesertaan.update', $member), [
            'nama' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
            'kategori' => 'asn', 'jenis_lainnya' => '', 'status_kepegawaian' => 'aktif',
            'unit_kerja' => 'Bagian Pelayanan', 'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1990-01-01', 'jenis_kelamin' => 'L', 'agama' => 'Islam',
            'alamat' => 'Jl. Contoh No. 1, Jakarta', 'hak_layanan' => true,
            'referensi_bukti' => 'Verifikasi ulang instansi',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Bagian Pelayanan', $member->fresh()->unit_kerja);
        $this->assertSame('001', $member->fresh()->rt);
        $this->assertSame('Sukamaju', $member->fresh()->kelurahan);
        $this->get(route('kepesertaan.index'))->assertInertia(fn (Assert $page) => $page
            ->missing('members.data.0.rt')->missing('members.data.0.kelurahan'));
        $this->get(route('kepesertaan.show', $member))->assertInertia(fn (Assert $page) => $page
            ->missing('member.rt')->missing('member.kelurahan'));
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

    public function test_manual_ten_person_sample_imports_complete_internal_identity(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $sheet = $this->sampleWorkbook();
        $worksheet = $sheet->getActiveSheet();
        $sample = $worksheet->rangeToArray('A2:N2')[0];
        $worksheet->removeRow(2, $worksheet->getHighestRow() - 1);
        for ($index = 0; $index < 10; $index++) {
            $row = $sample;
            $row[0] = $index === 0 ? 'Andi Firmansyah' : ($index === 9 ? 'Rina Kurniasih' : 'Peserta Contoh '.($index + 1));
            $row[1] = sprintf('%016d', $index + 1);
            $row[2] = $index === 9 ? '' : sprintf('%018d', $index + 1);
            $row[3] = $index === 9 ? 'lainnya' : 'asn';
            $row[4] = $index === 9 ? 'staf' : '';
            $row[9] = '1986-10-12';
            foreach ($row as $column => $value) {
                $worksheet->setCellValueExplicit([$column + 1, $index + 2], (string) $value, DataType::TYPE_STRING);
            }
        }

        $this->actingAs($actor)->post(route('kepesertaan.import'), [
            'file' => $this->workbook($sheet), 'referensi_bukti' => 'SIMULASI - Data sampel internal 10 peserta',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('kepesertaan', 10);
        $employee = Kepesertaan::where('nama', 'Andi Firmansyah')->firstOrFail();
        $staff = Kepesertaan::where('nama', 'Rina Kurniasih')->firstOrFail();
        $this->assertSame('1986-10-12', $employee->tanggal_lahir?->toDateString());
        $this->assertNull($employee->rt);
        $this->assertNull($staff->pegawai_penanggung_id);
    }

    public function test_fractional_numeric_identifier_and_missing_header_are_rejected_without_partial_import(): void
    {
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        foreach (['fractional_numeric', 'header', 'duplicate', 'family'] as $invalid) {
            $sheet = $this->sampleWorkbook();
            if ($invalid === 'fractional_numeric') {
                $sheet->getActiveSheet()->setCellValueExplicit('C2', 12345678.5, DataType::TYPE_NUMERIC);
            } elseif ($invalid === 'header') {
                $sheet->getActiveSheet()->setCellValue('J1', 'salah_kolom');
            } elseif ($invalid === 'duplicate') {
                $sheet->getActiveSheet()->setCellValueExplicit('B3', '0000000000000001', DataType::TYPE_STRING);
            } else {
                $sheet->getActiveSheet()->setCellValue('E4', 'keluarga');
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
        $visit->update(['status' => 'selesai']);
        $member->update(['hak_layanan' => false]);
        $this->assertSame($member->unit_kerja, $visit->fresh()->hak_layanan_snapshot['unit_kerja']);
        $this->post(route('pelayanan.kunjungan.store'), $payload)->assertSessionHasErrors('pasien_id');
        $this->assertDatabaseCount('kunjungan', 1);
    }

    public function test_legacy_family_and_retirees_have_no_entitlement_and_clinical_staff_cannot_import(): void
    {
        $sponsor = Kepesertaan::factory()->create();
        $family = Kepesertaan::factory()->create(['kategori' => 'lainnya', 'jenis_lainnya' => 'keluarga', 'nip' => null, 'pegawai_penanggung_id' => $sponsor->id, 'hubungan_keluarga' => 'anak']);
        $verifier = app(HakLayananVerifier::class);
        $this->assertNotNull($verifier->reason($family->fresh()));
        $retiree = Kepesertaan::factory()->create(['kategori' => 'lainnya', 'jenis_lainnya' => 'pensiunan', 'status_kepegawaian' => 'pensiun']);
        $this->assertNotNull($verifier->reason($retiree));
        $misclassified = Kepesertaan::factory()->create(['kategori' => 'asn', 'jenis_lainnya' => 'keluarga']);
        $this->assertNotNull($verifier->reason($misclassified));
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->actingAs($doctor)->post(route('kepesertaan.import'))->assertForbidden();
        $this->assertDatabaseCount('kepesertaan', 4);
    }

    public function test_membership_directory_does_not_store_validity_dates(): void
    {
        $member = Kepesertaan::factory()->create();

        $this->assertNull(app(HakLayananVerifier::class)->reason($member));
        $this->assertFalse(Schema::hasColumn('kepesertaan', 'berlaku_mulai'));
        $this->assertFalse(Schema::hasColumn('kepesertaan', 'berlaku_sampai'));
    }

    public function test_only_admin_can_manage_internal_membership_and_deleting_it_preserves_patient_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $payload = [
            'nama' => 'Pegawai Internal', 'nik' => '3273015205900001', 'nip' => '',
            'kategori' => 'lainnya', 'jenis_lainnya' => 'staf', 'status_kepegawaian' => 'aktif',
            'unit_kerja' => 'Unit Pelayanan', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-05-20',
            'jenis_kelamin' => 'P', 'agama' => 'Islam', 'alamat' => 'Bandung',
            'hak_layanan' => true, 'referensi_bukti' => 'Daftar pegawai internal',
        ];

        $this->actingAs($registration)->get(route('kepesertaan.index'))->assertInertia(fn (Assert $page) => $page->where('canManage', false));
        $this->post(route('kepesertaan.store'), $payload)->assertForbidden();
        $this->post(route('kepesertaan.import'))->assertForbidden();
        $this->assertDatabaseCount('kepesertaan', 0);

        $this->actingAs($admin)->post(route('kepesertaan.store'), $payload)->assertSessionHasNoErrors();
        $member = Kepesertaan::firstOrFail();
        $this->get(route('kepesertaan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('canManage', true)->where('categories.lainnya', 'Lainnya')
            ->where('otherTypes.magang', 'Magang')->where('otherTypes.staf', 'Staff')->where('otherTypes.lainnya', 'Lainnya'));
        $this->actingAs($registration)->put(route('kepesertaan.update', $member), $payload)->assertForbidden();
        $this->delete(route('kepesertaan.destroy', $member))->assertForbidden();

        $this->actingAs($admin)->put(route('kepesertaan.update', $member), [...$payload, 'unit_kerja' => 'Unit Baru'])->assertSessionHasNoErrors();
        $this->assertSame('Unit Baru', $member->fresh()->unit_kerja);
        $patient = Pasien::create(['no_rm' => 'RM-INTERNAL-001', 'nama' => $member->nama, 'nik' => $member->nik, 'kepesertaan_id' => $member->id]);
        $this->delete(route('kepesertaan.destroy', $member))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('kepesertaan', ['id' => $member->id]);
        $this->assertNull($patient->fresh()->kepesertaan_id);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'membership.deleted']);
    }

    public function test_family_and_retired_membership_cannot_be_created_by_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $payload = [
            'nama' => 'Peserta Uji', 'nik' => '3273015205900002', 'kategori' => 'lainnya',
            'jenis_lainnya' => 'keluarga', 'status_kepegawaian' => 'aktif', 'unit_kerja' => 'Unit Uji',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-05-20', 'jenis_kelamin' => 'P',
            'agama' => 'Islam', 'alamat' => 'Bandung', 'hak_layanan' => true, 'referensi_bukti' => 'Uji',
        ];
        foreach (['keluarga', 'Keluarga ASN', 'pensiunan', 'Tamu instansi', 'lainnya'] as $invalidType) {
            $this->actingAs($admin)->post(route('kepesertaan.store'), [...$payload, 'jenis_lainnya' => $invalidType])->assertSessionHasErrors('jenis_lainnya');
        }
        $this->post(route('kepesertaan.store'), [...$payload, 'jenis_lainnya' => 'staf', 'status_kepegawaian' => 'pensiun'])->assertSessionHasErrors('status_kepegawaian');
        $this->assertDatabaseCount('kepesertaan', 0);
    }

    public function test_admin_can_save_and_update_custom_internal_participant_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $payload = [
            'nama' => 'Peserta Internal', 'nik' => '3273015205900003', 'kategori' => 'lainnya',
            'jenis_lainnya' => 'Tenaga pendukung', 'status_kepegawaian' => 'aktif', 'unit_kerja' => 'Unit Pelayanan',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-05-20', 'jenis_kelamin' => 'P',
            'agama' => 'Islam', 'alamat' => 'Bandung', 'hak_layanan' => true, 'referensi_bukti' => 'Daftar pegawai internal',
        ];

        $this->actingAs($admin)->post(route('kepesertaan.store'), $payload)->assertSessionHasNoErrors();
        $member = Kepesertaan::firstOrFail();
        $this->assertSame('Tenaga pendukung', $member->jenis_lainnya);
        $this->assertSame('Lainnya · Tenaga pendukung', $member->membershipLabel());
        $this->assertNull(app(HakLayananVerifier::class)->reason($member));

        $this->put(route('kepesertaan.update', $member), [...$payload, 'jenis_lainnya' => 'staff'])->assertSessionHasNoErrors();
        $this->assertSame('staf', $member->fresh()->jenis_lainnya);
        $this->put(route('kepesertaan.update', $member), [...$payload, 'jenis_lainnya' => 'Tenaga layanan'])->assertSessionHasNoErrors();
        $this->assertSame('Tenaga layanan', $member->fresh()->jenis_lainnya);
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
