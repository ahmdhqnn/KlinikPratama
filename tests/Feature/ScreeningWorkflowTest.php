<?php

namespace Tests\Feature;

use App\Exports\ScreeningExport;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ScreeningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_marks_the_visit_in_progress_and_records_the_actual_nurse(): void
    {
        [$nurse, $visit, $staff] = $this->context();

        $this->actingAs($nurse)->post(route('pelayanan.screening.start', $visit))
            ->assertRedirect(route('pelayanan.screening.show', $visit));

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'screening']);
        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'petugas_id' => $staff->id, 'submitted_at' => null, 'kesimpulan_triase' => null]);
        $this->assertNotNull($visit->fresh()->screening->started_at);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'screening.started', 'kunjungan_id' => $visit->id]);
    }

    public function test_dental_draft_allows_incomplete_answers_without_forwarding_or_replacing_verified_allergy(): void
    {
        [$nurse, $visit] = $this->context('gigi');
        $visit->pasien->update(['riwayat_alergi' => 'Penisilin']);

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new',
            'tindakan' => 'draf', 'keluhan' => 'Nyeri gigi', 'konfirmasi_riwayat' => false,
            'risiko_medis_gigi' => [], 'status_alergi' => 'tidak_ada',
        ])->assertSessionHasNoErrors()->assertRedirect(route('pelayanan.screening.show', $visit));

        $this->assertSame('screening', $visit->fresh()->status);
        $this->assertNull($visit->screening->submitted_at);
        $this->assertNull($visit->screening->kesimpulan_triase);
        $this->assertSame('Penisilin', $visit->pasien->fresh()->riwayat_alergi);
        $this->get(route('pelayanan.screening.print', $visit))->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/screening/print')
                ->where('document.isDraft', true)
                ->where('document.backUrl', route('pelayanan.screening.index')));
    }

    public function test_sending_requires_assessment_and_explicit_history_confirmation(): void
    {
        [$nurse, $visit] = $this->context('gigi');

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new'])
            ->assertSessionHasErrors(['keluhan', 'lama_keluhan', 'sumber_informasi', 'status_alergi', 'status_riwayat_penyakit', 'konfirmasi_riwayat', 'risiko_medis_gigi']);

        $this->assertNull($visit->fresh()->screening?->submitted_at);
        $this->assertSame('screening', $visit->fresh()->status);
    }

    public function test_vitals_remain_required_while_anthropometry_and_triage_are_optional(): void
    {
        [$nurse, $visit] = $this->context();
        $payload = $this->payload();
        unset($payload['td_sistole'], $payload['berat_badan']);

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasErrors(['td_sistole' => 'Isi hasil pengukuran sebelum meneruskan pasien.']);

        $this->assertNull($visit->fresh()->screening?->submitted_at);
        $this->assertSame('screening', $visit->fresh()->status);
    }

    public function test_urgent_handoff_allows_optional_anthropometry_and_triage_and_cannot_downgrade_red_flags(): void
    {
        [$nurse, $visit] = $this->context();
        $payload = $this->payload();
        unset($payload['berat_badan'], $payload['tinggi_badan'], $payload['kejang'], $payload['nadi_teraba'], $payload['pola_pernapasan'], $payload['kesadaran'], $payload['kondisi_psikiatri'], $payload['risiko_jatuh_visual']);
        $payload = [...$payload, 'nyeri_dada' => 'ya', 'triase_perawat' => 'hijau'];

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('pelayanan.kunjungan.show', $visit));

        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'berat_badan' => null, 'tinggi_badan' => null, 'kesimpulan_triase' => 'merah', 'prioritas_layanan' => 'darurat']);
        $this->assertNotNull($visit->fresh()->screening->submitted_at);
        $this->assertSame('pemeriksaan', $visit->fresh()->status);
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$payload, 'tindakan' => 'draf'])->assertSessionHasErrors('tindakan');
        $this->assertSame('merah', $visit->fresh()->screening->kesimpulan_triase);
    }

    public function test_dental_handover_without_vitals_requires_reason_and_fast_observations(): void
    {
        [$nurse, $visit] = $this->context('gigi', 'terapis_gigi');
        $payload = [...$this->payload(), 'dental_vitals_unmeasurable' => true, 'risiko_medis_gigi' => ['tidak_ada']];
        unset($payload['td_sistole'], $payload['td_diastole'], $payload['nadi'], $payload['suhu'], $payload['respirasi'], $payload['kesadaran'], $payload['pola_pernapasan']);
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasErrors(['alasan_pengukuran_tidak_lengkap', 'kesadaran', 'pola_pernapasan']);
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new',
            ...$payload, 'kesadaran' => 'sadar', 'pola_pernapasan' => 'normal',
            'alasan_pengukuran_tidak_lengkap' => 'Pasien belum kooperatif. Dokter menerima serah terima verbal.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'td_sistole' => null, 'dental_vitals_unmeasurable' => true, 'prioritas_layanan' => 'segera']);
    }

    public function test_dental_therapist_can_submit_and_print_a_complete_assessment_with_unknowns_preserved(): void
    {
        [$nurse, $visit] = $this->context('gigi', 'terapis_gigi');
        $payload = [...$this->payload(), 'riwayat_penyakit' => 'Diabetes', 'status_riwayat_penyakit' => 'ada', 'risiko_medis_gigi' => ['diabetes'], 'riwayat_obat' => 'Obat rutin sesuai keterangan pasien', 'keluhan_tambahan' => '<script>alert(1)</script>'];

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();

        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'riwayat_obat' => 'Obat rutin sesuai keterangan pasien', 'skala_nyeri' => null]);
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/screening/print')
            ->where('document.clinicType', 'gigi')
            ->where('document.isDraft', false)
            ->where('document.sections.dental.Kondisi kesehatan yang perlu diketahui dokter gigi', 'Diabetes melitus')
            ->where('document.sections.history.Keluhan tambahan', '<script>alert(1)</script>'));
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'screening.print', 'kunjungan_id' => $visit->id]);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.screening.summary.Lama keluhan', '2 hari')
            ->where('visits.0.screening.summary.Obat yang digunakan', 'Obat rutin sesuai keterangan pasien'));
    }

    public function test_dental_risks_cannot_combine_none_or_unknown_with_a_known_condition(): void
    {
        [$nurse, $visit] = $this->context('gigi');

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$this->payload(), 'risiko_medis_gigi' => ['tidak_ada', 'diabetes']])
            ->assertSessionHasErrors('risiko_medis_gigi');

        $this->assertNull($visit->fresh()->screening?->submitted_at);
    }

    public function test_unknown_dental_systemic_history_requires_a_handoff_note(): void
    {
        [$nurse, $visit] = $this->context('gigi');

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$this->payload(), 'risiko_medis_gigi' => ['belum_diketahui']])
            ->assertSessionHasErrors('catatan_medis_gigi');

        $this->assertNull($visit->fresh()->screening?->submitted_at);
    }

    public function test_print_and_start_remain_scoped_to_the_nurses_clinic(): void
    {
        [$nurse, $visit] = $this->context();
        $foreign = Poliklinik::create(['kode' => 'FOREIGN', 'nama' => 'Poli Lain', 'jenis' => 'gigi', 'is_active' => true]);
        $visit->update(['poliklinik_id' => $foreign->id]);
        Screening::create(['kunjungan_id' => $visit->id, 'keluhan' => 'Rahasia poli lain']);

        $this->actingAs($nurse)->get(route('pelayanan.screening.print', $visit))->assertNotFound();
        $this->post(route('pelayanan.screening.start', $visit))->assertNotFound();

        $this->assertSame('menunggu', $visit->fresh()->status);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'screening.print', 'kunjungan_id' => $visit->id]);
    }

    public function test_print_requires_login_and_rejects_registration_role(): void
    {
        [$nurse, $visit] = $this->context();
        Screening::create(['kunjungan_id' => $visit->id]);
        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        $this->get(route('pelayanan.screening.print', $visit))->assertRedirect(route('login'));
        $this->actingAs($registration)->get(route('pelayanan.screening.print', $visit))->assertForbidden();
    }

    public function test_submitted_assessment_is_completed_on_service_detail(): void
    {
        [$nurse, $visit] = $this->context('gigi', 'terapis_gigi');

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new',
            ...$this->payload(), 'risiko_medis_gigi' => ['tidak_ada'], 'status_alergi' => 'tidak_ada',
        ])->assertSessionHasNoErrors()->assertRedirect(route('pelayanan.kunjungan.show', $visit));

        $this->get(route('pelayanan.kunjungan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.progress.1.done', true)
            ->where('visit.progress.1.active', false)
            ->where('visit.progress.1.note', null)
            ->where('visit.patient.allergies', 'Tidak ada riwayat alergi menurut pasien')
            ->where('permissions.processScreening', true));
    }

    public function test_submitted_assessment_can_be_updated_until_the_doctor_starts(): void
    {
        [$nurse, $visit] = $this->context();
        $payload = $this->payload();

        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pelayanan.kunjungan.show', $visit));

        $this->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.editable', true)
            ->where('visit.submitted', true));

        $this->get(route('pelayanan.screening.index', ['search' => $visit->no_kunjungan]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.data.0.actionUrl', route('pelayanan.screening.show', $visit))
                ->where('visits.data.0.actionLabel', 'Perbarui pengkajian'));

        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$payload, 'keluhan' => 'Keluhan diperbarui'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('pelayanan.screening.show', $visit));

        $this->assertDatabaseHas('screening', [
            'kunjungan_id' => $visit->id,
            'keluhan' => 'Keluhan diperbarui',
        ]);
        $this->assertNotNull($visit->fresh()->screening->submitted_at);
        $this->assertSame('pemeriksaan', $visit->fresh()->status);
    }

    public function test_submitted_assessment_cannot_be_updated_after_the_doctor_starts(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $this->payload())->assertSessionHasNoErrors();
        $doctor = Nakes::create(['kode' => 'SKR-DOCTOR', 'nama' => 'Dokter Uji', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $visit->poliklinik_id, 'is_active' => true]);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'status' => 'draft', 'started_at' => now()]);

        $this->get(route('pelayanan.screening.show', $visit))->assertUnprocessable();
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$this->payload(), 'keluhan' => 'Tidak boleh berubah'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'keluhan' => 'Keluhan pasien']);
    }

    public function test_submitted_assessment_cannot_be_saved_as_an_incomplete_draft(): void
    {
        [$nurse, $visit] = $this->context();
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $this->payload())->assertSessionHasNoErrors();

        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', 'tindakan' => 'draf'])
            ->assertSessionHasErrors(['tindakan', 'keluhan']);

        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'keluhan' => 'Keluhan pasien']);
        $this->assertNotNull($visit->fresh()->screening->submitted_at);
    }

    public function test_assessment_print_only_signs_completed_records_using_the_nurses_profile(): void
    {
        Storage::fake('local');
        [$nurse, $visit] = $this->context();
        $path = UploadedFile::fake()->image('nurse.jpg', 80, 30)->store('signatures', 'local');
        $nurse->update(['signature_path' => $path]);
        $signature = 'data:image/jpeg;base64,'.base64_encode(Storage::disk('local')->get($path));
        $viewerPath = UploadedFile::fake()->image('viewer.png')->store('signatures', 'local');
        $viewer = User::factory()->create(['role' => 'admin', 'is_active' => true, 'signature_path' => $viewerPath]);
        $viewerSignature = 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($viewerPath));

        $this->actingAs($nurse)->post(route('pelayanan.screening.start', $visit))->assertRedirect();
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('document.isDraft', true)
            ->where('document.signatureDataUrl', null));
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $this->payload())->assertSessionHasNoErrors();

        $this->actingAs($viewer)->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('document.isDraft', false)
            ->where('document.signatureDataUrl', $signature));
        Storage::disk('local')->delete($path);
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('document.signatureDataUrl', null));
    }

    #[TestWith(['umum'])]
    #[TestWith(['gigi'])]
    public function test_admin_assessment_tracks_operator_reason_and_staff_without_using_the_staff_signature(string $type): void
    {
        Storage::fake('local');
        [$nurse, $visit, $staff] = $this->context($type);
        $path = UploadedFile::fake()->image('nurse.png')->store('signatures', 'local');
        $nurse->update(['signature_path' => $path]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reason = 'Akun petugas sedang mengalami gangguan.';
        $this->actingAs($admin)->post(route('pelayanan.screening.start', $visit), ['petugas_id' => $staff->id])->assertSessionHasErrors('assistance_reason');
        $this->assertNull($visit->fresh()->screening?->submitted_at);
        $this->post(route('pelayanan.screening.start', $visit), ['petugas_id' => $staff->id, 'assistance_reason' => $reason])->assertRedirect();
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', 'petugas_id' => $staff->id, 'tindakan' => 'draf', 'assistance_reason' => $reason, 'keluhan' => 'Keluhan awal'])->assertSessionHasNoErrors();
        $this->assertSame('screening', $visit->fresh()->status);
        $this->assertNull($visit->fresh()->screening->submitted_at);
        $payload = [...$this->payload(), 'petugas_id' => $staff->id, 'assistance_reason' => $reason, 'risiko_medis_gigi' => ['tidak_ada']];
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame('pemeriksaan', $visit->fresh()->status);
        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'petugas_id' => $staff->id, 'recorded_by_user_id' => $admin->id]);
        $event = DB::table('clinical_audit_events')->where('action', 'admin_assistance.screening_write')->where('kunjungan_id', $visit->id)->latest('id')->first();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($reason, json_decode($event->metadata, true)['reason']);
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page->where('document.signatureDataUrl', null)->where('document.recordedBy', $admin->name));
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), [...$payload, 'revision_reason' => 'Konfirmasi langsung oleh perawat', 'screening_revision' => $visit->fresh()->screening->revisionToken()])->assertSessionHasNoErrors();
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page->where('document.signatureDataUrl', fn ($signature) => is_string($signature) && str_starts_with($signature, 'data:image/')));
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'started_at' => now()]);
        $this->actingAs($admin)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertUnprocessable();
    }

    public function test_admin_assessment_rejects_blank_reason_and_staff_from_another_clinic(): void
    {
        [, $visit, $staff] = $this->context();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', 'tindakan' => 'draf', 'petugas_id' => $staff->id, 'assistance_reason' => str_repeat(' ', 20)])->assertSessionHasErrors('assistance_reason');
        $other = Poliklinik::create(['kode' => 'OTHER', 'nama' => 'Poli Lain', 'jenis' => 'gigi', 'is_active' => true]);
        $staff->update(['poliklinik_id' => $other->id]);
        $payload = ['tindakan' => 'draf', 'petugas_id' => $staff->id, 'assistance_reason' => 'Bantuan diperlukan untuk pengkajian.'];
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasErrors('petugas_id');
        $this->post(route('pelayanan.screening.start', $visit), $payload)->assertSessionHasErrors('petugas_id');
        $this->assertNull($visit->fresh()->screening?->submitted_at);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'screening']);
    }

    /** @return array{User, Kunjungan, Nakes} */
    private function context(string $type = 'umum', string $position = 'perawat'): array
    {
        $this->travelTo('2026-10-21 09:00:00');
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'SKR-'.$type, 'nama' => 'Poli '.$type, 'jenis' => $type, 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'SKR-NURSE', 'nama' => 'Petugas Asesmen', 'kategori' => 'medis', 'jabatan' => $position, 'poliklinik_id' => $clinic->id, 'user_id' => $nurse->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SKR', 'nama' => 'Pasien Asesmen', 'tanggal_lahir' => '1990-01-01']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-SKR', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'menunggu', 'jenis_pasien' => 'lama']);

        return [$nurse, $visit, $staff];
    }

    public function test_disease_status_changes_reach_the_doctor_without_stale_details(): void
    {
        [$nurse, $visit] = $this->context('gigi');
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'BRIDGE-DOCTOR', 'nama' => 'Dokter Handover', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'poliklinik_id' => $visit->poliklinik_id,
            'user_id' => $doctorUser->id, 'is_active' => true,
        ]);
        $visit->update(['dokter_id' => $doctor->id]);
        $payload = [
            ...$this->payload(), 'status_riwayat_penyakit' => 'ada', 'riwayat_penyakit' => 'Hipertensi',
            'penyakit_nama' => 'Hipertensi lama', 'penyakit_keterangan' => 'Catatan lama',
            'risiko_medis_gigi' => ['lainnya'], 'risiko_medis_gigi_lainnya' => 'Kondisi lain perlu dikonfirmasi', 'extraoral_symmetry' => 'asymmetric',
            'extraoral_notes' => 'Asimetri pipi kanan, disampaikan kepada dokter.',
        ];
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->actingAs($doctorUser)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.medicalHistory', 'Hipertensi'));

        foreach (['belum_diketahui' => null, 'tidak_ada' => 'Pasien menyangkal riwayat penyakit'] as $status => $expected) {
            $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new',
                ...$payload, 'status_riwayat_penyakit' => $status,
            ])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('screening', [
                'kunjungan_id' => $visit->id, 'riwayat_penyakit' => $expected,
                'penyakit_nama' => null, 'penyakit_keterangan' => null,
            ]);
            $this->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
                ->where('visit.screening.status_riwayat_penyakit', $status)
                ->where('visit.screening.riwayat_penyakit', $expected));
            $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
                ->where('document.sections.dental.Catatan visual ekstraoral awal perawat', $payload['extraoral_notes']));
            $this->actingAs($doctorUser)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
                ->where('visit.screening.medicalHistory', $expected)
                ->where('visit.screening.summary.Nama penyakit', null)
                ->where('visit.screening.summary.Keterangan riwayat penyakit', null)
                ->where('visit.screening.summary.Catatan visual ekstraoral awal perawat', $payload['extraoral_notes'])
                ->where('visit.examination', null));
        }
    }

    public function test_dental_other_details_require_explanation_and_clear_when_deselected(): void
    {
        [$nurse, $visit] = $this->context('gigi');
        $payload = [...$this->payload(), 'extraoral_notes' => 'Temuan disampaikan kepada dokter'];
        foreach (Screening::OTHER_DETAILS as $field => [$option, $detail]) {
            $payload[$field] = [$option];
        }
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)
            ->assertSessionHasErrors(array_column(Screening::OTHER_DETAILS, 1));
        foreach (Screening::OTHER_DETAILS as [$option, $detail]) {
            $payload[$detail] = 'Keterangan pasien untuk dokter';
        }
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.dental_red_flags_other', 'Keterangan pasien untuk dokter'));
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$this->payload(), 'risiko_medis_gigi' => ['tidak_ada']])->assertSessionHasNoErrors();
        foreach (Screening::OTHER_DETAILS as [$option, $detail]) {
            $this->assertNull($visit->fresh()->screening->getAttribute($detail));
        }
    }

    public function test_handover_describes_recorded_measurements_and_confirmed_absence_without_inventing_optional_notes(): void
    {
        [$nurse, $visit] = $this->context('gigi');
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$this->payload(),
            'td_sistole' => 120, 'td_diastole' => 80, 'nadi' => 80, 'suhu' => 36.5, 'respirasi' => 18,
            'status_riwayat_obat' => 'tidak_ada', 'risiko_medis_gigi' => ['tidak_ada'],
            'risiko_medis_gigi_lainnya' => 'Rincian lama yang tidak dipilih',
            'extraoral_symmetry' => 'symmetric', 'extraoral_swelling' => 'none',
            'mukosa_mulut' => ['lidah' => 'normal', 'gusi' => 'abnormal'],
            'kelainan_mukosa' => 'Gusi kemerahan',
        ])->assertSessionHasNoErrors();
        $screening = $visit->fresh()->screening;
        $summary = $screening->handoverSummary();
        $this->assertSame('Tekanan darah, nadi, suhu, dan frekuensi napas tercatat', $summary['Status pengukuran TTV']);
        $this->assertSame('Tidak menggunakan obat menurut pasien', $summary['Obat yang digunakan']);
        $this->assertSame('Tidak ada, telah dikaji', $summary['Kondisi kesehatan yang perlu diketahui dokter gigi']);
        $this->assertNull($summary['Keterangan kondisi sistemik lainnya']);
        $this->assertNull($summary['Catatan visual ekstraoral awal perawat']);
        $this->assertSame('Normal', $summary['Observasi mukosa - Lidah']);
        $this->assertSame('Abnormal', $summary['Observasi mukosa - Gusi']);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.screening.summary.Obat yang digunakan', 'Tidak menggunakan obat menurut pasien')
            ->where('visits.0.screening.summary.Observasi mukosa - Gusi', 'Abnormal')
            ->where('visits.0.screening.summary.Catatan visual ekstraoral awal perawat', null));
        $this->get(route('pelayanan.pasien.rekam-medis', [$visit->pasien, 'kunjungan_id' => $visit->id]))
            ->assertInertia(fn (Assert $page) => $page->has('visits', 1)->where('visits.0.id', $visit->id));
        $this->get(route('pelayanan.pasien.rekam-medis', [$visit->pasien, 'kunjungan_id' => 999999]))
            ->assertInertia(fn (Assert $page) => $page->has('visits', 0));
        $screening->respirasi = null;
        $screening->dental_vitals_unmeasurable = true;
        $this->assertSame('Sebagian TTV tidak dapat diukur', $screening->vitalMeasurementSummary());
        $screening->status_riwayat_obat = 'belum_diketahui';
        $this->assertSame('Belum dapat dikonfirmasi', $screening->handoverSummary()['Obat yang digunakan']);
        $empty = new Screening;
        $this->assertNull($empty->vitalMeasurementSummary());
        $this->assertNull($empty->handoverSummary()['Obat yang digunakan']);
    }

    public function test_unconfirmed_allergy_is_distinct_from_no_allergy_and_reaches_handover(): void
    {
        [$nurse, $visit] = $this->context('gigi');
        $payload = [...$this->payload(), 'status_alergi' => 'belum_dikonfirmasi', 'risiko_medis_gigi' => ['tidak_ada'], 'alergi_verifikasi' => 'Perlu konfirmasi dengan keluarga', 'nyeri_karakter' => 'Berdenyut, tidak menjalar', 'nyeri_pereda' => 'Istirahat'];
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $summary = $visit->fresh()->screening->handoverSummary();
        $this->assertSame('Belum dapat dikonfirmasi', $summary['Status alergi saat ini']);
        $this->assertSame('Perlu konfirmasi dengan keluarga', $summary['Catatan verifikasi alergi']);
        $this->assertSame('Berdenyut, tidak menjalar', $summary['Karakter dan penjalaran nyeri']);
    }

    public function test_structured_oral_observation_and_habits_reach_handover_and_print(): void
    {
        [$nurse, $visit] = $this->context('gigi');
        $payload = [...$this->payload(), 'status_riwayat_penyakit' => 'ada', 'risiko_medis_gigi' => ['diabetes'],
            'status_riwayat_obat' => 'ada', 'dental_medication_categories' => ['antidiabetic'], 'riwayat_obat' => 'Metformin menurut pasien',
            'mukosa_mulut' => ['lidah' => 'normal', 'pipi' => 'normal', 'bibir' => 'normal', 'palatum' => 'belum_dinilai', 'gusi' => 'abnormal'],
            'kelainan_mukosa' => 'Gusi kanan tampak bengkak', 'kebiasaan_gigi_opsi' => ['teh_kopi', 'satu_sisi']];
        $this->actingAs($nurse)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasNoErrors();
        $screening = $visit->fresh()->screening;
        $this->assertSame('abnormal', $screening->mukosa_mulut['gusi']);
        $this->assertSame('Diabetes melitus', $screening->reportedMedicalHistory());
        $this->assertSame(['teh_kopi', 'satu_sisi'], $screening->kebiasaan_gigi_opsi);
        $this->assertSame('Abnormal', $screening->handoverSummary()['Observasi mukosa - Gusi']);
        $this->assertStringContainsString('Mengunyah satu sisi', $screening->handoverSummary()['Kebiasaan terkait gigi dan mulut']);
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page->where('document.sections.dental.Observasi mukosa - Gusi', 'Abnormal'));
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$payload, 'kelainan_mukosa' => null])->assertSessionHasErrors('mukosa_mulut.catatan.gusi');
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$payload, 'mukosa_mulut' => ['gusi' => 'invalid']])->assertSessionHasErrors('mukosa_mulut.gusi');
        $this->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), ['revision_reason' => 'Koreksi setelah konfirmasi ulang', 'screening_revision' => $visit->fresh()->screening?->revisionToken() ?? 'new', ...$payload, 'kebiasaan_gigi_opsi' => ['tidak_ada', 'merokok']])->assertSessionHasErrors('kebiasaan_gigi_opsi');
        $history = ['respiratory' => ['status' => 'positive', 'notes' => 'Catatan historis']];
        $screening->update(['general_assessment' => ['systems' => $history]]);
        $mucosaNote = 'Palatum belum terlihat jelas karena pasien sulit membuka mulut.';
        $finding = 'Gusi kanan tampak bengkak.';
        $revision = [
            'revision_reason' => 'Koreksi setelah konfirmasi ulang',
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            ...$payload,
            'status_riwayat_obat' => 'tidak_ada',
            'mukosa_mulut' => ['gusi' => 'abnormal', 'palatum' => 'belum_dinilai', 'catatan' => ['palatum' => $mucosaNote, 'gusi' => $finding]],
            'kelainan_mukosa' => null,
        ];
        $this->post(route('pelayanan.screening.store', $visit), [...$revision, 'general_assessment' => ['systems' => $history]])
            ->assertSessionHasErrors('general_assessment');
        $this->post(route('pelayanan.screening.store', $visit), [...$revision, 'general_assessment' => ['general_condition' => 'baik']])
            ->assertSessionHasNoErrors();

        $screening = $visit->fresh()->screening;
        $this->assertSame($mucosaNote, $screening->mukosa_mulut['catatan']['palatum']);
        $this->assertSame($history, $screening->general_assessment['systems']);
        $this->assertSame('Abnormal — '.$finding, $screening->handoverSummary()['Observasi mukosa - Gusi']);
        $this->get(route('pelayanan.screening.print', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('document.sections.dental.Observasi mukosa - Palatum', 'Belum dapat dinilai — '.$mucosaNote)
            ->missing('document.sections.general.Pengkajian sistem - Respirasi'));

        foreach ([null, $screening->petugas] as $staff) {
            $export = new ScreeningExport([], $staff);
            $row = array_combine($export->headings(), $export->map($screening));
            $this->assertStringContainsString('Palatum: Belum dapat dinilai — '.$mucosaNote, $row['Observasi mukosa mulut']);
            $this->assertNull($row['Pengkajian sistem - Respirasi'] ?? null);
        }
        $this->assertNull($visit->fresh()->screening->riwayat_obat);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'keluhan' => 'Keluhan pasien', 'lama_keluhan' => '2 hari', 'sumber_informasi' => 'pasien',
            'status_alergi' => 'belum_diketahui', 'status_riwayat_penyakit' => 'belum_diketahui', 'konfirmasi_riwayat' => true,
            'td_sistole' => 120, 'td_diastole' => 80, 'nadi' => 80, 'suhu' => 36.5, 'respirasi' => 18, 'berat_badan' => 60, 'tinggi_badan' => 160,
            'nyeri_dada' => 'tidak', 'kejang' => 'tidak', 'nadi_teraba' => 'teraba', 'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar', 'kondisi_psikiatri' => 'normal', 'risiko_jatuh_visual' => 'rendah',
        ];
    }
}
