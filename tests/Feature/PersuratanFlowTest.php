<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\CorrespondenceTemplate;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\RujukanInternal;
use App\Models\Screening;
use App\Models\SuratMedis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersuratanFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_issue_a_letter_from_correspondence_after_the_examination_is_final(): void
    {
        [$doctorUser, $doctor, $visit] = $this->visitFixture();
        $otherUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->createDoctor($otherUser, 'DOK-OTHER-FINAL');
        $examination = Pemeriksaan::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'status' => 'selesai', 'signed_at' => now(), 'signed_by_user_id' => $doctorUser->id,
        ]);
        app(ClinicalNoteRecorder::class)->recordFinal($examination, ['anamnesis' => 'Pemeriksaan selesai'], $doctorUser->id);
        $visit->update(['status' => 'selesai']);

        $this->actingAs($doctorUser)->get(route('persuratan.index'))
            ->assertInertia(fn (Assert $page) => $page->where('eligibleVisits.total', 1)
                ->where('eligibleVisits.data.0.letterUrl', route('persuratan.surat.create', $visit)));
        $this->get(route('persuratan.surat.create', $visit))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/terbitkan')->where('visit.id', $visit->id));
        $this->post(route('persuratan.surat.store', $visit), [
            'details' => ['rest_start' => today()->toDateString(), 'rest_end' => today()->toDateString()],
            'jenis' => 'sakit', 'tanggal' => today()->toDateString(), 'konten' => 'Surat pemeriksaan final.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('surat_medis', ['kunjungan_id' => $visit->id, 'konten' => 'Surat pemeriksaan final.']);

        $this->actingAs($otherUser)->get(route('persuratan.index'))
            ->assertInertia(fn (Assert $page) => $page->where('eligibleVisits.total', 0));
        $this->get(route('persuratan.surat.create', $visit))->assertNotFound();
        $this->post(route('persuratan.surat.store', $visit), [
            'details' => ['rest_start' => today()->toDateString(), 'rest_end' => today()->toDateString()],
            'jenis' => 'sakit', 'tanggal' => today()->toDateString(), 'konten' => 'Tidak berwenang.',
        ])->assertNotFound();
    }

    public function test_admin_manages_templates_while_doctors_only_see_active_templates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->createDoctor($doctorUser, 'DOK-TEMPLATE');
        $active = CorrespondenceTemplate::factory()->create(['is_active' => true]);
        CorrespondenceTemplate::factory()->create(['is_active' => false]);

        $this->actingAs($admin)->get(route('persuratan.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/index')->where('isAdmin', true)->has('templates.data', 2));
        $this->actingAs($doctorUser)->get(route('persuratan.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/index')->where('isAdmin', false)->has('templates.data', 1));
        $this->post(route('persuratan.template.store'), $this->templatePayload())->assertForbidden();
        $this->actingAs($admin)->post(route('persuratan.template.store'), $this->templatePayload())
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('correspondence_templates', ['kode' => 'SKS-TEST', 'is_active' => true]);
        $this->put(route('persuratan.template.update', $active), $this->templatePayload(['kode' => 'SKS-UPDATED']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('correspondence_templates', ['id' => $active->id, 'kode' => 'SKS-UPDATED']);
    }

    public function test_admin_can_review_the_document_issuance_tab_but_only_the_attending_doctor_can_issue(): void
    {
        [, $doctor, $visit] = $this->visitFixture();
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'status' => 'selesai', 'signed_at' => now()]);
        $visit->update(['status' => 'selesai']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('persuratan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('isAdmin', true)
            ->where('eligibleVisits.total', 1)
            ->where('eligibleVisits.data.0.letterUrl', null));
        $this->get(route('persuratan.surat.create', $visit))->assertForbidden();
    }

    public function test_doctor_can_issue_numbered_letter_and_internal_referral_then_print_them(): void
    {
        [$doctorUser, $doctor, $visit, $origin, $target, $patient] = $this->visitFixture();
        Storage::fake('local');
        $this->actingAs($doctorUser)->post(route('profile.signature.upload'), [
            'signature' => UploadedFile::fake()->image('signature.png'),
        ])->assertSessionHasNoErrors();
        $signature = 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($doctorUser->fresh()->signature_path));
        $letterTemplate = CorrespondenceTemplate::factory()->create([
            'jenis' => 'sakit', 'prefix' => 'SKS', 'nomor_berikutnya' => 7,
            'isi' => 'Pasien [pasien] diperiksa oleh [dokter] pada [tanggal].',
        ]);
        $referralTemplate = CorrespondenceTemplate::factory()->create([
            'jenis' => 'rujukan_internal', 'prefix' => 'RI', 'nomor_berikutnya' => 2,
            'isi' => 'Mohon konsultasi [pasien] ke [poli_tujuan].',
        ]);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.surat.store', $visit), [
            'details' => ['rest_start' => today()->toDateString(), 'rest_end' => today()->toDateString()],
            'jenis' => 'sakit', 'tanggal' => today()->toDateString(), 'surat_template_id' => $letterTemplate->id, 'konten' => '',
        ])->assertSessionHasNoErrors();
        $letter = SuratMedis::sole();
        $this->assertSame('SKS/0007/'.today()->format('m').'/'.today()->format('Y'), $letter->nomor_surat);
        $this->assertSame('Pasien '.$patient->nama.' diperiksa oleh '.$doctor->nama.' pada '.today()->locale('id')->isoFormat('D MMMM Y').'.', $letter->konten);

        $this->post(route('pelayanan.pemeriksaan.rujukan.store', $visit), [
            'ke_poli_id' => $target->id, 'surat_template_id' => $referralTemplate->id, 'catatan' => 'Evaluasi lanjutan', 'konten_surat' => '',
        ])->assertSessionHasNoErrors();
        $referral = RujukanInternal::sole();
        $this->assertSame('RI/0002/'.today()->format('m').'/'.today()->format('Y'), $referral->nomor_surat);
        $this->assertSame('Mohon konsultasi '.$patient->nama.' ke '.$target->nama.'.', $referral->konten_surat);

        $this->get(route('persuratan.surat.cetak', $letter))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.kind', 'surat')->where('document.doctor.signatureDataUrl', $signature)->where('document.number', $letter->nomor_surat)->where('document.backUrl', route('persuratan.index')));
        $this->get(route('persuratan.surat.cetak', $letter).'?asal=pemeriksaan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.backUrl', route('pelayanan.pemeriksaan.show', $visit).'?tab=surat')->where('document.backLabel', 'Kembali ke surat medis'));
        $this->get(route('persuratan.rujukan.cetak', $referral))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.kind', 'rujukan_internal')->where('document.doctor.signatureDataUrl', $signature)->where('document.toClinic', $target->nama)->where('document.backUrl', route('persuratan.index')));
        $this->get(route('persuratan.rujukan.cetak', $referral).'?asal=pemeriksaan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.backUrl', route('pelayanan.pemeriksaan.show', $visit).'?tab=rujukan'));
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'medical_letter.print', 'kunjungan_id' => $visit->id]);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'internal_referral.print', 'kunjungan_id' => $visit->id]);
    }

    public function test_doctor_can_print_external_prescription_but_cannot_read_another_doctors_documents(): void
    {
        [$doctorUser, , $visit] = $this->visitFixture();
        $otherUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $otherDoctor = $this->createDoctor($otherUser, 'DOK-OTHER');
        [, , $otherVisit] = $this->visitFixture($otherUser, $otherDoctor);
        $prescription = Resep::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $visit->dokter_id,
            'no_resep' => 'RSP-TEST-1', 'status' => 'menunggu',
        ]);
        ResepObat::create([
            'resep_id' => $prescription->id, 'nama_obat' => 'Paracetamol', 'jumlah' => 10,
            'satuan' => 'tablet', 'aturan_pakai' => '3 x 1 sesudah makan', 'jenis' => 'jadi',
            'is_resep_luar' => true, 'stok_dikurangi' => false,
        ]);

        $this->actingAs($doctorUser)->get(route('persuratan.resep.cetak', $visit))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.kind', 'resep_luar')->where('document.items.0.name', 'Paracetamol')->where('document.backUrl', route('persuratan.index')));
        $this->get(route('persuratan.resep.cetak', $visit).'?asal=pemeriksaan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('persuratan/cetak')->where('document.backUrl', route('pelayanan.pemeriksaan.show', $visit).'?tab=resep-luar'));
        $this->get(route('persuratan.resep.cetak', $otherVisit))->assertNotFound();
    }

    public function test_print_uses_the_recorded_issuer_signature_instead_of_the_current_doctor_or_viewer(): void
    {
        Storage::fake('local');
        [$issuer, $doctor, $visit] = $this->visitFixture();
        $signaturePath = UploadedFile::fake()->image('issuer.png', 80, 30)->store('signatures', 'local');
        $issuer->update(['signature_path' => $signaturePath]);
        $signature = 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($signaturePath));
        $viewer = User::factory()->create([
            'role' => 'admin', 'is_active' => true,
            'signature_path' => UploadedFile::fake()->image('viewer.png', 30, 30)->store('signatures', 'local'),
        ]);
        $replacementUser = User::factory()->create([
            'role' => 'dokter', 'is_active' => true, 'signature_path' => $viewer->signature_path,
        ]);
        $replacementDoctor = $this->createDoctor($replacementUser, 'DOK-REPLACEMENT');
        $replacementDoctor->update(['nama' => 'dr. Pengganti']);
        $letter = SuratMedis::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'jenis' => 'sakit',
            'tanggal' => today(), 'konten' => 'Surat diterbitkan dokter awal.',
        ]);
        $prescription = Resep::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'no_resep' => 'RSP-SIGNATURE', 'status' => 'menunggu',
        ]);
        ResepObat::create([
            'resep_id' => $prescription->id, 'nama_obat' => 'Paracetamol', 'jumlah' => 5,
            'satuan' => 'tablet', 'jenis' => 'jadi', 'is_resep_luar' => true, 'stok_dikurangi' => false,
        ]);
        $visit->update(['dokter_id' => $replacementDoctor->id]);

        $this->actingAs($viewer);
        foreach ([route('persuratan.surat.cetak', $letter), route('persuratan.resep.cetak', $visit)] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('document.doctor.name', $doctor->nama)
                ->where('document.doctor.signatureDataUrl', $signature));
        }

        Storage::disk('local')->delete($signaturePath);
        $this->get(route('persuratan.surat.cetak', $letter))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('document.doctor.signatureDataUrl', null));
        $issuer->update(['signature_path' => null]);
        $this->get(route('persuratan.resep.cetak', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('document.doctor.signatureDataUrl', null));
    }

    public function test_standard_letters_have_automatic_numbers_and_keep_the_clinical_data_at_issuance(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        [$user, $doctor, $visit, , , $patient] = $this->visitFixture();
        Storage::fake('local');
        $signaturePath = UploadedFile::fake()->image('arsip.png', 80, 30)->store('signatures', 'local');
        $user->update(['signature_path' => $signaturePath]);
        $signature = 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($signaturePath));
        $patient->update(['tanggal_lahir' => '1990-11-02', 'alamat' => 'Alamat awal']);
        $examination = Pemeriksaan::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'anamnesis' => 'Nyeri geraham kanan bawah.', 'non_farmakologis' => 'Kompres dingin',
            'status' => 'selesai', 'signed_at' => now(), 'signed_by_user_id' => $user->id,
        ]);
        $examination->diagnosa()->create(['nama_diagnosa' => 'Pulpitis', 'kode_icd10' => 'K04.0', 'jenis' => 'utama']);
        app(ClinicalNoteRecorder::class)->recordFinal($examination, ['anamnesis' => $examination->anamnesis], $user->id);
        $visit->update(['status' => 'selesai']);
        $cases = [
            'sakit' => ['rest_start' => '2026-11-01', 'rest_end' => '2026-11-03'],
            'sehat' => ['purpose' => 'Administrasi', 'conclusion' => 'Kesimpulan dokter sesuai pemeriksaan.'],
            'rujukan' => ['recipient' => 'drg. Spesialis', 'recipient_address' => 'Rumah sakit tujuan'],
            'laboratorium' => ['requested_tests' => "Hematologi rutin\nGlukosa puasa", 'result_delivery' => 'dokter'],
            'rontgen_gigi' => ['xray_types' => ['Dental', 'Panoramic'], 'teeth' => [46], 'result_delivery' => 'pasien'],
            'ringkasan' => [],
            'lainnya' => [],
        ];
        $this->actingAs($user);
        foreach ($cases as $type => $details) {
            $this->post(route('persuratan.surat.store', $visit), [
                'jenis' => $type, 'tanggal' => '2026-11-01', 'details' => $details,
                'konten' => $type === 'lainnya' ? 'Keterangan dokter.' : null,
            ])->assertSessionHasNoErrors()->assertRedirect(route('persuratan.index'));
            $letter = SuratMedis::query()->where('jenis', $type)->sole();
            $this->assertNotEmpty($letter->nomor_surat);
            $this->assertSame('Nyeri geraham kanan bawah.', $letter->document_data['clinical']['anamnesis']);
            $this->assertSame(['Pulpitis (K04.0)'], $letter->document_data['clinical']['diagnoses']);
            $this->assertSame($details, $letter->document_data['details']);
        }
        $this->assertDatabaseCount('correspondence_numbers', 7);
        $letter = SuratMedis::query()->where('jenis', 'rontgen_gigi')->sole();
        $this->assertSame('XRG/0001/KP/11/2026', $letter->nomor_surat);
        $patient->update(['nama' => 'Nama yang diperbarui', 'alamat' => 'Alamat baru']);
        $examination->update(['anamnesis' => 'Catatan berikutnya.']);
        $doctor->update(['nama' => 'Nama dokter baru']);
        Storage::disk('local')->delete($signaturePath);
        $this->get(route('persuratan.surat.cetak', $letter))->assertInertia(fn (Assert $page) => $page
            ->where('document.clinic.name', 'Klinik Pratama Kementerian Pariwisata')
            ->where('document.patient.name', 'Pasien Surat')->where('document.patient.age', 35)
            ->where('document.patient.address', 'Alamat awal')
            ->where('document.doctor.name', 'dr. Uji Persuratan')
            ->where('document.doctor.signatureDataUrl', $signature)
            ->where('document.clinical.anamnesis', 'Nyeri geraham kanan bawah.')
            ->where('document.clinical.diagnoses.0', 'Pulpitis (K04.0)')
            ->where('document.details.teeth.0', 46));
    }

    public function test_invalid_letter_details_do_not_consume_numbers_or_create_documents(): void
    {
        [$user, , $visit] = $this->visitFixture();
        $this->actingAs($user);
        foreach ([
            ['sakit', ['rest_start' => '2026-11-03', 'rest_end' => '2026-11-01'], 'details.rest_end'],
            ['sehat', ['purpose' => 'Administrasi'], 'details.conclusion'],
            ['rujukan', [], 'details.recipient'],
            ['laboratorium', ['result_delivery' => 'dokter'], 'details.requested_tests'],
            ['rontgen_gigi', ['xray_types' => ['Dental'], 'teeth' => [99], 'result_delivery' => 'pasien'], 'details.teeth.0'],
            ['rontgen_gigi', ['xray_types' => ['Tidak valid'], 'result_delivery' => 'pasien'], 'details.xray_types.0'],
            ['lainnya', [], 'konten'],
        ] as [$type, $details, $error]) {
            $this->post(route('pelayanan.pemeriksaan.surat.store', $visit), [
                'jenis' => $type, 'tanggal' => '2026-11-01', 'details' => $details,
            ])->assertSessionHasErrors($error);
        }
        $this->assertDatabaseCount('surat_medis', 0);
        $this->assertDatabaseCount('correspondence_numbers', 0);
    }

    public function test_number_allocation_skips_existing_letters_and_shared_template_collisions(): void
    {
        $this->travelTo('2026-11-01');
        [$user, , $visit] = $this->visitFixture();
        SuratMedis::create(['kunjungan_id' => $visit->id, 'jenis' => 'ringkasan', 'tanggal' => today(), 'nomor_surat' => 'SHARED/0001/11/2026']);
        $first = CorrespondenceTemplate::factory()->create(['jenis' => 'ringkasan', 'prefix' => 'SHARED']);
        $second = CorrespondenceTemplate::factory()->create(['jenis' => 'ringkasan', 'prefix' => 'SHARED']);
        $this->actingAs($user);
        foreach ([$first, $second] as $template) {
            $this->post(route('pelayanan.pemeriksaan.surat.store', $visit), [
                'jenis' => 'ringkasan', 'tanggal' => '2026-11-01', 'surat_template_id' => $template->id,
            ])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('surat_medis', ['surat_template_id' => $first->id, 'nomor_surat' => 'SHARED/0002/11/2026']);
        $this->assertDatabaseHas('surat_medis', ['surat_template_id' => $second->id, 'nomor_surat' => 'SHARED/0003/11/2026']);
        $this->assertDatabaseCount('correspondence_numbers', 2);
    }

    public function test_mismatched_template_returns_validation_error_and_rolls_back_numbering(): void
    {
        [$user, , $visit] = $this->visitFixture();
        $template = CorrespondenceTemplate::factory()->create(['jenis' => 'sehat']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.surat.store', $visit), [
            'jenis' => 'ringkasan', 'tanggal' => today()->toDateString(), 'surat_template_id' => $template->id,
        ])->assertSessionHasErrors('surat_template_id');
        $this->assertDatabaseCount('surat_medis', 0);
        $this->assertDatabaseCount('correspondence_numbers', 0);
        $this->assertSame(1, $template->fresh()->nomor_berikutnya);
    }

    public function test_internal_referral_without_a_template_is_numbered_and_keeps_its_destination(): void
    {
        $this->travelTo('2026-11-01');
        [$user, $doctor, $visit, , $target] = $this->visitFixture();
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Nyeri gigi', 'status' => 'proses']);
        $examination->diagnosa()->create(['nama_diagnosa' => 'Pulpitis', 'kode_icd10' => 'K04.0', 'jenis' => 'utama']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.rujukan.store', $visit), [
            'ke_poli_id' => $target->id, 'catatan' => 'Evaluasi lanjutan',
        ])->assertSessionHasNoErrors();
        $referral = RujukanInternal::sole();
        $this->assertSame('RI/0001/KP/11/2026', $referral->nomor_surat);
        $target->update(['nama' => 'Nama tujuan yang baru']);
        $this->get(route('persuratan.rujukan.cetak', $referral))->assertInertia(fn (Assert $page) => $page
            ->where('document.toClinic', 'Poli Gigi')
            ->where('document.clinical.anamnesis', 'Nyeri gigi')
            ->where('document.clinical.diagnoses.0', 'Pulpitis (K04.0)'));
    }

    public function test_admin_cannot_rewind_number_counter_or_remove_sequence_token(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $template = CorrespondenceTemplate::factory()->create(['nomor_berikutnya' => 8]);
        $this->actingAs($admin)->put(route('persuratan.template.update', $template), $this->templatePayload([
            'kode' => $template->kode, 'nomor_berikutnya' => 7,
        ]))->assertSessionHasErrors('nomor_berikutnya');
        $this->post(route('persuratan.template.store'), $this->templatePayload([
            'format_nomor' => '[prefix]/[tahun]',
        ]))->assertSessionHasErrors('format_nomor');
        $this->assertSame(8, $template->fresh()->nomor_berikutnya);
    }

    /** @return array{User, Nakes, Kunjungan, Poliklinik, Poliklinik, Pasien} */
    private function visitFixture(?User $user = null, ?Nakes $doctor = null): array
    {
        $user ??= User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor ??= $this->createDoctor($user, 'DOK-LETTER');
        $origin = Poliklinik::create(['kode' => 'UMUM-L-'.$doctor->id, 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $target = Poliklinik::create(['kode' => 'GIGI-L-'.$doctor->id, 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-LETTER-'.$doctor->id, 'nama' => 'Pasien Surat', 'jenis_kelamin' => 'P']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-LETTER-'.$doctor->id,
            'pasien_id' => $patient->id,
            'poliklinik_id' => $origin->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
        ]);

        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);

        return [$user, $doctor, $visit, $origin, $target, $patient];
    }

    private function createDoctor(User $user, string $code): Nakes
    {
        return Nakes::create([
            'kode' => $code, 'nama' => 'dr. Uji Persuratan', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function templatePayload(array $overrides = []): array
    {
        return array_merge([
            'kode' => 'SKS-TEST', 'nama' => 'Surat keterangan sakit', 'jenis' => 'sakit',
            'prefix' => 'SKS', 'format_nomor' => '[prefix]/[urut]/[bulan]/[tahun]',
            'nomor_berikutnya' => 1, 'isi' => 'Untuk [pasien] pada [tanggal].', 'is_active' => true,
        ], $overrides);
    }
}
