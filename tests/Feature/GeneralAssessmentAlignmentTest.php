<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Exports\ScreeningExport;
use App\GeneralAssessment;
use App\Models\ClinicalNoteVersion;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\ScreeningVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class GeneralAssessmentAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assessment_must_start_and_server_records_times_only_when_observations_are_saved(): void
    {
        [$nurse, , $visit] = $this->context();
        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), $this->payload())->assertUnprocessable();
        $this->assertDatabaseCount('screening', 0);
        $this->post(route('pelayanan.screening.start', $visit))->assertSessionHasNoErrors();
        $this->assertSame('2026-11-06 09:00', $visit->fresh()->screening->started_at->format('Y-m-d H:i'));
        $this->assertNull($visit->fresh()->screening->vitals_measured_at);

        $this->travel(5)->minutes();
        $payload = [...$this->payload(), 'vitals_measured_at' => '2000-01-01T00:00', 'triaged_at' => '2099-01-01T00:00'];
        $this->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $first = $visit->fresh()->screening;
        $this->assertSame('2026-11-06 09:05', $first->vitals_measured_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-06 09:05', $first->triaged_at->format('Y-m-d H:i'));

        $this->travel(5)->minutes();
        $payload = [...$payload, 'revision_reason' => 'Melengkapi riwayat keluarga pasien', 'screening_revision' => $first->revisionToken(), 'riwayat_penyakit_keluarga' => 'Hipertensi pada ibu'];
        $this->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-06 09:05', $visit->fresh()->screening->vitals_measured_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-06 09:05', $visit->fresh()->screening->triaged_at->format('Y-m-d H:i'));
        $payload['screening_revision'] = $visit->fresh()->screening->revisionToken();
        $payload['nadi'] = 84;
        $this->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-06 09:10', $visit->fresh()->screening->vitals_measured_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-06 09:05', $visit->fresh()->screening->triaged_at->format('Y-m-d H:i'));
    }

    public function test_checklist_details_are_required_and_translated_for_doctor_handover(): void
    {
        [$nurse, , $visit] = $this->context();
        $this->actingAs($nurse)->beginNursingAssessment($visit);
        $payload = $this->payload();
        $payload['general_assessment'] = ['arrival' => 'dibantu', 'hospitalization_status' => 'tidak_ada', 'needs_status' => 'ada'];
        $this->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasErrors('general_assessment.needs');
        $payload['general_assessment']['needs'] = 'Memerlukan bantuan mobilisasi';
        $payload['general_assessment']['airway'] = 'perlu_penilaian';
        $this->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $summary = $visit->fresh()->screening->handoverSummary();
        $this->assertSame('segera', $visit->fresh()->screening->prioritas_layanan);
        $this->assertSame('Dibantu', $summary['Kondisi kedatangan']);
        $this->assertSame('Tidak ada', $summary['Riwayat rawat inap']);
        $this->assertSame('Memerlukan bantuan mobilisasi', $summary['Masalah / kebutuhan teridentifikasi']);
    }

    public function test_general_nursing_assessment_reaches_doctor_print_export_and_has_explicit_unassessed_systems(): void
    {
        [$nurse, $doctor, $visit, $staff] = $this->context();
        $payload = $this->payload();
        $notes = "Temuan ditinjau bersama pasien.\nPengkajian dilanjutkan sesuai kondisi pasien.";
        $payload['general_assessment']['systems_notes'] = $notes;
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $screening = $visit->fresh()->screening;
        $this->assertSame($notes, $screening->general_assessment['systems_notes']);
        $this->assertSame($staff->id, $screening->petugas_id);
        $this->assertSame($nurse->id, $screening->recorded_by_user_id);
        $this->assertSame('2026-11-06 09:00', $screening->vitals_measured_at->format('Y-m-d H:i'));
        $this->assertSame('Positif / abnormal — Batuk berdahak', $screening->handoverSummary()['Pengkajian sistem - Respirasi']);
        $this->assertSame('Belum dikaji', $screening->handoverSummary()['Pengkajian sistem - Genitourinaria']);
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('document.sections.general.Pengkajian sistem - Respirasi', 'Positif / abnormal — Batuk berdahak')
            ->where('document.sections.general.Catatan pengkajian sistem tubuh', $notes)
            ->where('document.sections.vitals.Waktu pencatatan TTV', '06/11/2026 09:00'));
        $export = (new ScreeningExport([], $staff))->map($screening);
        $headings = (new ScreeningExport([], $staff))->headings();
        $this->assertCount(count($headings), $export);
        $this->assertSame($notes, array_combine($headings, $export)['Catatan pengkajian sistem tubuh']);
        $this->assertSame('Positif / abnormal — Batuk berdahak', $export[array_search('Pengkajian sistem - Respirasi', $headings, true)]);
        $this->assertStringContainsString('Batuk berdahak', implode(';', $export));
        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.riwayat_obat', 'Parasetamol menurut pasien')
            ->where('visit.screening.summary.Catatan pengkajian sistem tubuh', $notes)
            ->where('visit.screening.summary.Catatan penapisan', 'Mohon evaluasi batuk'));
    }

    public function test_positive_system_findings_require_details_and_unknown_fields_are_rejected(): void
    {
        [$nurse, , $visit] = $this->context();
        $payload = $this->payload();
        $payload['general_assessment']['systems']['respiratory']['notes'] = '';
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasErrors('general_assessment.systems.respiratory.notes');
        $payload = $this->payload();
        $payload['general_assessment']['diagnosis'] = 'Tidak boleh dicatat perawat';
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasErrors('general_assessment');
        $this->assertNull($visit->fresh()->screening?->submitted_at);
    }

    #[TestWith(['umum'])]
    #[TestWith(['gigi'])]
    public function test_revisions_preserve_content_reject_stale_tabs_and_retry_once(string $clinicType): void
    {
        [$nurse, , $visit] = $this->context($clinicType);
        $payload = [...$this->payload(), 'request_id' => (string) Str::uuid()];
        if ($clinicType === 'gigi') {
            $payload['general_assessment'] = [];
        }
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $revision = $visit->fresh()->screening->revisionToken();
        $this->assertSame($nurse->id, $visit->screening->submitted_by_user_id);
        $originalSubmission = $visit->screening->submitted_at->toISOString();
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('screening_versions', 1);
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), [...$payload, 'keluhan' => 'Isi berbeda'])
            ->assertSessionHasErrors('request_id');
        $changed = [...$payload, 'request_id' => (string) Str::uuid(), 'screening_revision' => $revision, 'keluhan' => 'Batuk membaik'];
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $changed)->assertSessionHasErrors('revision_reason');
        $changed['revision_reason'] = 'Koreksi setelah wawancara ulang';
        $this->travel(5)->minutes();
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $changed)->assertSessionHasNoErrors();
        $updated = $visit->fresh()->screening;
        $this->assertSame($originalSubmission, $updated->submitted_at->toISOString());
        $this->assertSame('2026-11-06 09:05', $updated->last_submitted_at->format('Y-m-d H:i'));
        $this->assertDatabaseCount('screening_versions', 2);
        $this->assertSame('Batuk sejak kemarin', ScreeningVersion::first()->payload['keluhan']);
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), [...$changed, 'request_id' => (string) Str::uuid()])
            ->assertSessionHasErrors('screening_revision');
        $this->assertSame('Batuk membaik', $visit->fresh()->screening->keluhan);
    }

    public function test_general_partial_vitals_require_observation_reason_and_doctor_follow_up(): void
    {
        [$nurse, $doctor, $visit] = $this->context();
        $payload = [...$this->payload(), 'vitals_unmeasurable' => true, 'td_sistole' => null, 'td_diastole' => null];
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasErrors('alasan_pengukuran_tidak_lengkap');
        $payload['alasan_pengukuran_tidak_lengkap'] = 'Pasien belum kooperatif, dokter dihubungi untuk pengukuran ulang.';
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame('segera', $visit->fresh()->screening->prioritas_layanan);
        $this->assertSame('Sebagian TTV tidak dapat diukur', $visit->screening->vitalMeasurementSummary());
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.store', $visit), ['anamnesis' => 'Batuk', 'pemeriksaan_fisik' => 'Keadaan umum cukup'])->assertSessionHasNoErrors();
        $visit->fresh()->pemeriksaan->diagnosa()->create(['nama_diagnosa' => 'Batuk', 'jenis' => 'utama', 'code_system' => 'manual']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password', 'review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening->revisionToken()])
            ->assertSessionHasErrors('nursing_clarification');
        $this->assertDatabaseCount('clinical_note_versions', 0);
    }

    public function test_legacy_assessment_is_archived_before_revision_without_inventing_its_original_author(): void
    {
        [$nurse, , $visit, $staff] = $this->context();
        $visit->update(['status' => 'pemeriksaan']);
        $screening = Screening::create(['kunjungan_id' => $visit->id, 'petugas_id' => $staff->id, 'keluhan' => 'Keluhan lama', 'submitted_at' => now()->subHour()]);
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), [
            ...$this->payload(), 'screening_revision' => $screening->fresh()->revisionToken(), 'revision_reason' => 'Melengkapi setelah pengkajian ulang',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('screening_versions', 2);
        $original = ScreeningVersion::orderBy('version')->first();
        $this->assertSame('legacy_snapshot', $original->kind);
        $this->assertNull($original->actor_id);
        $this->assertSame('Keluhan lama', $original->payload['keluhan']);
        $this->assertNull($visit->fresh()->screening->submitted_by_user_id);
        $this->assertSame('2026-11-06 08:00', $visit->screening->submitted_at->format('Y-m-d H:i'));
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('document.versions.0.actor', null)
            ->where('document.versions.0.summary.Keluhan utama', 'Keluhan lama'));
    }

    public function test_general_doctor_verification_ros_and_addendum_preserve_signed_content(): void
    {
        [$nurse, $doctor, $visit] = $this->context();
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $this->payload())->assertSessionHasNoErrors();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.start', $visit))->assertSessionHasNoErrors();
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $this->payload())->assertUnprocessable();
        $ros = ['respiratory' => ['status' => 'positive', 'notes' => 'Batuk tanpa sesak'], 'constitutional' => ['status' => 'negative', 'notes' => null]];
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'anamnesis' => 'Batuk sejak kemarin', 'pemeriksaan_fisik' => 'Keadaan umum baik', 'riwayat_obat' => 'Parasetamol dikonfirmasi',
            'review_of_systems' => $ros, 'riwayat_sosial' => 'Tidak merokok', 'kondisi_akhir' => 'Stabil', 'disposisi' => 'pulang',
            'supporting_exams' => ['rapid' => 'Tidak dilakukan', 'results' => 'Tidak ada hasil tambahan'],
        ])->assertSessionHasNoErrors();
        $exam = $visit->fresh()->pemeriksaan;
        $exam->diagnosa()->create(['nama_diagnosa' => 'Batuk', 'jenis' => 'utama', 'code_system' => 'manual']);
        $signature = ['signature_password' => 'password', 'review_final_plan' => true];
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $signature)->assertSessionHasErrors('review_nursing_assessment');
        $signature = [...$signature, 'review_nursing_assessment' => true, 'screening_revision' => str_repeat('a', 64)];
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $signature)->assertSessionHasErrors('screening_revision');
        $signature['screening_revision'] = $visit->fresh()->screening->revisionToken();
        $signature['nursing_clarification'] = 'Obat telah diverifikasi langsung';
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $signature)->assertSessionHasNoErrors();
        $final = ClinicalNoteVersion::sole();
        $this->assertSame($ros, $final->payload['review_of_systems']);
        $this->assertSame($doctor->id, $final->payload['nursing_review']['reviewed_by']);
        $this->assertSame('Batuk berdahak', $final->payload['nursing_review']['screening']['general_assessment']['systems']['respiratory']['notes']);
        $this->assertSame('pulang', $final->payload['disposisi']);
        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), ['reason' => 'Klarifikasi pada evaluasi lanjutan', 'review_of_systems' => ['respiratory' => ['status' => 'negative', 'notes' => 'Batuk membaik']]])->assertSessionHasNoErrors();
        $this->assertSame($ros, $final->fresh()->payload['review_of_systems']);
        $this->assertSame('negative', $exam->clinicalNoteVersions()->latest('version')->first()->payload['review_of_systems']['respiratory']['status']);
        $this->assertTrue(app(ClinicalNoteRecorder::class)->verify($exam->fresh()));
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.generalClinicalSummary.Respirasi', 'Negatif setelah dikaji — Batuk membaik')
            ->where('visits.0.examination.generalClinicalSummary.Status akhir', 'pulang')
            ->where('visits.0.screening.summary.Pengkajian sistem - Respirasi', 'Positif / abnormal — Batuk berdahak')
            ->missing('visits.0.examination.generalClinicalSummary.Konstitusional')
            ->where('visits.0.examination.generalClinicalSummary.Riwayat sosial', 'Tidak merokok')
            ->where('visits.0.examination.versions.0.generalClinicalSummary.Konstitusional', 'Negatif setelah dikaji')
            ->missing('visits.0.examination.versions.0.generalClinicalSummary.Kardiovaskular'));
    }

    #[TestWith(['umum'])]
    #[TestWith(['gigi'])]
    public function test_shared_handover_is_validated_saved_and_received_by_doctor(string $clinicType): void
    {
        [$nurse, $doctor, $visit] = $this->context($clinicType);
        $handover = ['needs_status' => 'ada', 'needs' => 'Perlu bantuan mobilisasi', 'initial_actions_status' => 'ada', 'initial_actions' => 'Dibantu duduk, pasien nyaman', 'handover_follow_up' => 'Temuan disampaikan kepada dokter'];
        $handover = [...$handover, 'general_condition' => 'baik', 'progression' => 'memburuk', 'progression_notes' => 'Nyeri bertambah sejak tadi malam'];
        $payload = [...$this->payload(), 'general_assessment' => $handover, 'keluhan_tambahan' => 'Mual; Demam; Sulit makan'];
        $this->actingAs($nurse)->beginNursingAssessment($visit);
        $invalid = $payload;
        unset($invalid['general_assessment']['initial_actions']);
        $this->post(route('pelayanan.screening.store', $visit), $invalid)->assertSessionHasErrors('general_assessment.initial_actions');
        $this->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame($handover, $visit->fresh()->screening->general_assessment);
        $this->assertSame($handover['handover_follow_up'], $visit->screening->handoverSummary()['Tindak lanjut serah terima']);
        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.summary.Keluhan tambahan', 'Mual; Demam; Sulit makan')
            ->where('visit.screening.summary.Rincian perkembangan keluhan', 'Nyeri bertambah sejak tadi malam')
            ->where('visit.screening.summary.Tindak lanjut serah terima', $handover['handover_follow_up'])
            ->where('visit.screening.summary.Tindakan awal sesuai kewenangan', $handover['initial_actions']));
    }

    public function test_print_is_specific_to_current_episode_and_clinic_and_has_no_empty_assessment_cells(): void
    {
        [$nurse, , $visit] = $this->context();
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $this->payload())->assertSessionHasNoErrors();
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->missing('document.sections.previousVisit')
            ->where('document.sections.general.Tujuan kunjungan (catatan lama)', 'Berobat')
            ->where('document.sections.general.Awitan keluhan (catatan lama)', 'Tidak tercatat pada catatan lama')
            ->where('document.sections', fn ($sections) => collect($sections)->filter(fn ($rows) => is_array($rows) || $rows instanceof Collection)->every(fn ($rows) => collect($rows)->every(fn ($value) => filled($value)))));

        $otherClinic = Poliklinik::create(['kode' => 'OTHER-PRINT', 'nama' => 'Poli Gigi Lain', 'jenis' => 'gigi', 'is_active' => true]);
        $previous = Kunjungan::create(['no_kunjungan' => 'ALIGN-OLD', 'pasien_id' => $visit->pasien_id, 'poliklinik_id' => $otherClinic->id, 'dokter_id' => $visit->dokter_id, 'tanggal' => today()->subDay(), 'status' => 'selesai', 'jenis_pasien' => 'lama']);
        Screening::create(['kunjungan_id' => $previous->id, 'keluhan' => 'Kontrol batuk sebelumnya']);
        $other = Kunjungan::create(['no_kunjungan' => 'ALIGN-CANCELLED', 'pasien_id' => $visit->pasien_id, 'poliklinik_id' => $visit->poliklinik_id, 'dokter_id' => $visit->dokter_id, 'tanggal' => today()->subDay(), 'status' => 'batal', 'jenis_pasien' => 'lama']);
        Screening::create(['kunjungan_id' => $other->id, 'keluhan' => 'Tidak boleh digunakan']);
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->missing('document.sections.previousVisit')
            ->where('document.visitNumber', $visit->no_kunjungan)
            ->where('document.clinicName', $visit->poliklinik->nama)
            ->where('document.sections.history.Keluhan utama', 'Batuk sejak kemarin'));
    }

    public function test_general_doctor_continues_nurse_history_and_systems_while_preserving_original_findings(): void
    {
        [$nurse, $doctor, $visit] = $this->context();
        $payload = $this->payload();
        $payload['keluhan_tambahan'] = 'Mual; Demam';
        $payload['general_assessment']['location'] = 'Dada';
        $payload['general_assessment']['progression'] = 'memburuk';
        $payload['general_assessment']['systems']['digestive'] = ['status' => 'negative', 'notes' => null];
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $source = $visit->fresh()->screening->general_assessment;
        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.anamnesis', 'Batuk sejak kemarin')
            ->where('visit.examinationFormDefaults.currentHistory', fn ($value) => str_contains($value, 'Pasien mengeluhkan Batuk sejak kemarin.') && str_contains($value, 'Keluhan berlangsung selama 1 hari.') && str_contains($value, 'Gejala penyerta: Mual; Demam.') && str_contains($value, 'Keluhan berlokasi di Dada.'))
            ->where('visit.examinationFormDefaults.reviewOfSystems', [])
            ->where('visit.screening.systemAssessment', $source['systems']));
        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertSessionHasNoErrors();
        $review = [...$source['systems'], 'respiratory' => ['status' => 'positive', 'notes' => 'Batuk berdahak, tanpa sesak setelah dikonfirmasi']];
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'anamnesis', 'anamnesis' => 'Batuk sejak kemarin', 'review_of_systems' => $review])->assertSessionHasNoErrors();
        $this->assertSame($source, $visit->fresh()->screening->general_assessment);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.reviewOfSystems.respiratory.notes', 'Batuk berdahak, tanpa sesak setelah dikonfirmasi')
            ->where('visit.screening.systemAssessment.respiratory.notes', 'Batuk berdahak'));
    }

    public function test_current_illness_narrative_contains_only_recorded_findings(): void
    {
        $this->assertNull(GeneralAssessment::currentIllnessNarrative([], null, null, null));
        $this->assertSame('Tidak ada pemicu keluhan yang dilaporkan.', GeneralAssessment::currentIllnessNarrative(['triggers' => 'Tidak ada'], null, null, null));
        $this->assertSame('Pasien mengeluhkan Pusing. Keluhan dirasakan sejak kemarin.', GeneralAssessment::currentIllnessNarrative([], 'Pusing', 'sejak kemarin', null));
        $narrative = GeneralAssessment::currentIllnessNarrative(['progression' => 'memburuk', 'progression_notes' => 'Lebih sering saat berdiri', 'triggers' => 'Perubahan posisi'], 'Pusing', '2 hari', 'Mual', 0);
        $this->assertSame('Pasien mengeluhkan Pusing. Keluhan berlangsung selama 2 hari. Keluhan dipicu oleh Perubahan posisi. Keluhan memburuk. Rincian perkembangan: Lebih sering saat berdiri. Gejala penyerta: Mual. Skala nyeri 0 dari 10.', $narrative);
        $this->assertStringNotContainsString('berlokasi', $narrative);
        $this->assertStringNotContainsString('berkurang', $narrative);
    }

    public function test_dental_current_history_continues_nurse_narrative_and_preserves_doctor_edits(): void
    {
        [$nurse, $doctor, $visit] = $this->context('gigi');
        $payload = [...$this->payload(), 'keluhan' => 'Nyeri gigi geraham', 'general_assessment' => ['progression' => 'memburuk', 'progression_notes' => 'Lebih sering saat malam'], 'lokasi_nyeri_gigi' => 'Geraham kanan bawah', 'nyeri_karakter' => 'Berdenyut', 'durasi_keluhan_gigi' => 'Hilang timbul', 'pemicu_nyeri_gigi' => ['dingin'], 'nyeri_pereda' => 'Istirahat', 'keluhan_tambahan' => 'Mual', 'skala_nyeri' => 4];
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.currentHistory', fn ($value) => str_contains($value, 'Pasien mengeluhkan Nyeri gigi geraham.') && str_contains($value, 'Keluhan berlokasi di Geraham kanan bawah.') && str_contains($value, 'Keluhan dipicu oleh Dingin.') && str_contains($value, 'Keluhan memburuk.') && str_contains($value, 'Skala nyeri 4 dari 10.')));
        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'anamnesis', 'anamnesis' => 'Nyeri gigi geraham', 'riwayat_penyakit_sekarang' => 'Hasil klarifikasi dokter gigi: nyeri spontan pada malam hari'])->assertSessionHasNoErrors();
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.currentHistory', 'Hasil klarifikasi dokter gigi: nyeri spontan pada malam hari'));
    }

    private function context(string $clinicType = 'umum'): array
    {
        $this->travelTo('2026-11-06 09:00:00');
        $clinic = Poliklinik::create(['kode' => 'ALIGN', 'nama' => 'Poli '.$clinicType, 'jenis' => $clinicType, 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'ALIGN-N', 'nama' => 'Perawat Pengkaji', 'kategori' => 'medis', 'jabatan' => 'perawat', 'poliklinik_id' => $clinic->id, 'user_id' => $nurse->id, 'is_active' => true]);
        $examiner = Nakes::create(['kode' => 'ALIGN-D', 'nama' => 'Dokter Pemeriksa', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $clinic->id, 'user_id' => $doctor->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'ALIGN-RM', 'nama' => 'Pasien Umum']);
        $visit = Kunjungan::create(['no_kunjungan' => 'ALIGN-K', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $examiner->id, 'tanggal' => today(), 'status' => 'menunggu', 'jenis_pasien' => 'lama']);

        return [$nurse, $doctor, $visit, $staff];
    }

    private function payload(): array
    {
        return [
            'keluhan' => 'Batuk sejak kemarin', 'lama_keluhan' => '1 hari', 'sumber_informasi' => 'pasien',
            'status_alergi' => 'belum_diketahui', 'status_riwayat_penyakit' => 'belum_diketahui', 'konfirmasi_riwayat' => true,
            'status_riwayat_obat' => 'ada', 'riwayat_obat' => 'Parasetamol menurut pasien',
            'td_sistole' => 120, 'td_diastole' => 80, 'nadi' => 80, 'suhu' => 36.5, 'respirasi' => 18,
            'kesadaran' => 'sadar', 'pola_pernapasan' => 'normal', 'vitals_measured_at' => '2026-11-06T08:55',
            'catatan_medis_gigi' => 'Riwayat penyakit belum dapat dikonfirmasi', 'triaged_at' => '2026-11-06T08:54', 'risiko_medis_gigi' => ['belum_diketahui'],
            'catatan_triase' => 'Mohon evaluasi batuk',
            'general_assessment' => ['visit_purpose' => 'Berobat', 'systems' => ['respiratory' => ['status' => 'positive', 'notes' => 'Batuk berdahak']]],
        ];
    }
}
