<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Http\Middleware\AuditAdminAssistanceFailures;
use App\Models\ClinicalNoteVersion;
use App\Models\ClinicalTerminology;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminClinicalAssistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_server_failure_is_audited_without_request_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $route = Route::getRoutes()->getByName('pelayanan.pemeriksaan.start');
        $this->assertNotNull($route);
        $request = Request::create('/pelayanan/pemeriksaan/123/mulai', 'POST', ['sensitive_note' => 'must not be stored']);
        $route->bind($request);
        $route->setParameter('kunjungan', '123');
        $request->setUserResolver(fn () => $admin);
        $request->setRouteResolver(fn () => $route);

        $response = (new AuditAdminAssistanceFailures)->handle($request, fn () => new Response('internal error', 500));

        $this->assertSame(500, $response->getStatusCode());
        $event = DB::table('clinical_audit_events')->where('actor_id', $admin->id)->where('action', 'admin_assistance.attempt_rejected')->sole();
        $this->assertSame(123, (int) $event->kunjungan_id);
        $this->assertSame('pelayanan.pemeriksaan.start', $event->route_name);
        $this->assertSame(['failure_kind' => 'server_error', 'status' => 500], json_decode($event->metadata, true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('sensitive_note', $event->metadata);
    }

    #[TestWith(['umum'])]
    #[TestWith(['gigi'])]
    public function test_admin_draft_assistance_preserves_doctor_and_cannot_finalize(string $type): void
    {
        [, $staff, $visit] = $this->visitContext();
        $visit->poliklinik->update(['jenis' => $type]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('pelayanan.pemeriksaan.index'))->assertForbidden();
        foreach (['start', 'assist', 'store', 'selesai', 'addendum'] as $action) {
            $this->post(route('pelayanan.pemeriksaan.'.$action, $visit), ['reason' => 'Bantuan tidak memberikan kewenangan klinis'])->assertForbidden();
        }
        $this->assertDatabaseCount('pemeriksaan', 0);
        $this->assertSame($staff->id, $visit->fresh()->dokter_id);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'access.denied', 'actor_id' => $admin->id]);
    }

    public function test_assistance_is_scoped_to_actor_assignment_and_expiry(): void
    {
        [, , $visit] = $this->visitContext();
        $admin = $this->activeAdmin();
        $previousAdmin = User::factory()->create(['role' => 'admin', 'is_active' => false]);

        foreach ([[$previousAdmin, now()->addHour()], [$admin, now()->subMinute()]] as [$actor, $expiry]) {
            DB::table('admin_clinical_assistances')->insert(['actor_id' => $actor->id, 'kunjungan_id' => $visit->id, 'dokter_id' => $visit->dokter_id, 'poliklinik_id' => $visit->poliklinik_id, 'service_date' => today(), 'reason' => 'Bukti bantuan historis', 'expires_at' => $expiry, 'created_at' => now(), 'updated_at' => now()]);
            $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.start', $visit))->assertForbidden();
        }
        $this->assertDatabaseCount('pemeriksaan', 0);
    }

    public function test_assistance_does_not_bypass_screening_or_signed_records(): void
    {
        [, $staff, $visit] = $this->visitContext();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $visit->screening()->update(['submitted_at' => null]);
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertForbidden();
        $this->assertDatabaseCount('admin_clinical_assistances', 0);
        $visit->screening()->update(['submitted_at' => now()]);
        $visit->pemeriksaan()->create(['dokter_id' => $staff->id, 'status' => 'selesai', 'signed_at' => now()]);
        $this->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertForbidden();
        $this->assertDatabaseCount('admin_clinical_assistances', 0);
    }

    #[TestWith(['umum', false, false])]
    #[TestWith(['umum', true, false])]
    #[TestWith(['umum', false, true])]
    #[TestWith(['umum', true, true])]
    #[TestWith(['gigi', false, false])]
    #[TestWith(['gigi', true, false])]
    #[TestWith(['gigi', false, true])]
    #[TestWith(['gigi', true, true])]
    public function test_doctor_reviews_assisted_clinical_draft_and_signs_the_correct_care_branch(string $type, bool $medicine, bool $procedure): void
    {
        [$doctor, $admin, $visit] = $this->assistedContext($type);
        if ($medicine) {
            $drug = Obat::create(['kode' => 'HELP-DRUG', 'nama' => 'Obat uji', 'satuan_kecil' => 'tablet', 'jenis' => 'obat', 'stok' => 20, 'is_active' => true]);
            $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), ['obat_id' => $drug->id, 'jumlah' => 2, 'jenis' => 'jadi', 'aturan_pakai' => 'Sesuai instruksi dokter'])->assertSessionHasNoErrors();
        }
        if ($procedure) {
            $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan perawat sesuai rencana', 'jumlah' => 1, 'execution_role' => 'perawat', 'tooth_fdis' => $type === 'gigi' ? ['46'] : []])->assertSessionHasNoErrors();
        }
        $this->classifySyntheticProceduresAsNotRequiringConsent($visit, $doctor);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_performed_procedures' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password'])->assertSessionHasErrors('review_admin_assistance');
        $this->assertDatabaseCount('clinical_note_versions', 0);
        $page = $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertOk();
        $review = $page->inertiaProps('assistanceReview');
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true, 'review_performed_procedures' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true, 'review_admin_assistance' => true,
            'assistance_review_id' => $review['auditId'], 'assistance_review_hash' => $review['draftHash'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($procedure ? 'tindakan' : ($medicine ? 'farmasi' : 'selesai'), $visit->fresh()->status);
        $this->assertSame($doctor->id, $visit->fresh()->pemeriksaan->signed_by_user_id);
        $version = ClinicalNoteVersion::sole();
        $this->assertSame($doctor->id, $version->actor_id);
        $this->assertSame('Edukasi sesuai kondisi pasien', $version->payload['edukasi']);
        $this->assertSame($medicine ? 1 : 0, count($version->payload['prescriptions']));
        $this->assertSame($procedure ? 1 : 0, count($version->payload['treatments']));
        $this->assertTrue(app(ClinicalNoteRecorder::class)->verify($visit->fresh()->pemeriksaan));
        $reviewEvent = DB::table('clinical_audit_events')->where('action', 'admin_assistance.reviewed')->sole();
        $metadata = json_decode($reviewEvent->metadata, true);
        $this->assertSame($doctor->id, $reviewEvent->actor_id);
        $this->assertSame($admin->id, $metadata['operator_id']);
        $this->assertSame($version->content_hash, $metadata['content_hash']);
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.store', $visit), ['anamnesis' => 'Menimpa catatan'])->assertForbidden();
        if ($type === 'gigi') {
            $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'reason' => 'Koreksi catatan final oleh operator'])->assertForbidden();
            $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46']);
        }
    }

    public function test_follow_up_plan_syncs_a_pending_reservation_until_registration_confirms_it(): void
    {
        [$doctor, $admin, $visit] = $this->assistedContext();
        $date = today()->addDays(7);
        $data = ['section' => 'rencana', 'kontrol_berikutnya' => $date->toDateString(), 'catatan' => 'Evaluasi setelah terapi.'];
        $saveUrl = route('pelayanan.pemeriksaan.store', $visit);

        $this->actingAs($doctor)->post($saveUrl, $data)->assertSessionHasNoErrors();
        $reservation = Kunjungan::where('kontrol_asal_id', $visit->id)->sole();
        $this->assertSame($data['catatan'], $reservation->kontrol_catatan);
        $this->assertTrue($reservation->isPendingReservation());
        $this->assertFalse($reservation->canUpdateScreening());
        $this->get(route('pendaftaran.cetak-antrian', $reservation))->assertForbidden();

        $this->put(route('pelayanan.pemeriksaan.update', $visit), [...$data, 'catatan' => 'Catatan kontrol diperbarui.'])->assertSessionHasNoErrors();
        $this->assertSame('Catatan kontrol diperbarui.', $reservation->fresh()->kontrol_catatan);
        $this->assertDatabaseCount('kunjungan', 2);

        $page = $this->actingAs($admin)->get(route('pendaftaran.kunjungan-per-poli'))->inertiaProps('reservations');
        $this->actingAs($doctor);
        $this->assertSame($reservation->id, $page['data'][0]['id']);

        $registrar = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $alternateUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $alternate = Nakes::create(['kode' => 'DOCTOR-ALT', 'nama' => 'Dokter Lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $visit->poliklinik_id, 'user_id' => $alternateUser->id, 'is_active' => true]);
        JadwalDokter::create([
            'dokter_id' => $alternate->id, 'poliklinik_id' => $visit->poliklinik_id, 'hari' => 'senin',
            'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true,
        ]);

        $confirmation = ['poliklinik_id' => $visit->poliklinik_id];
        $confirmUrl = route('pendaftaran.update-kunjungan', $reservation);
        $editorUrl = route('pendaftaran.edit-kunjungan', $reservation);
        $this->actingAs($admin)->get(route('pelayanan.kunjungan.edit', $reservation))->assertRedirect($editorUrl);
        $this->actingAs($registrar)->get($editorUrl)->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/edit-kunjungan')
            ->where('visit.date', $date->toDateString())
            ->where('visit.doctorId', (string) $visit->dokter_id));
        $this->put($confirmUrl, $confirmation)->assertSessionHasErrors('dokter_id');
        $this->assertTrue($reservation->fresh()->isPendingReservation());
        JadwalDokter::create([
            'dokter_id' => $visit->dokter_id, 'poliklinik_id' => $visit->poliklinik_id, 'hari' => 'senin',
            'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true,
        ]);
        $this->put($confirmUrl, $confirmation)->assertSessionHasNoErrors();
        $this->assertFalse($reservation->fresh()->isPendingReservation());
        $this->assertSame($visit->dokter_id, $reservation->fresh()->dokter_id);
        $this->assertSame($date->toDateString(), $reservation->fresh()->tanggal->toDateString());
        $this->get(route('pendaftaran.cetak-antrian', $reservation))->assertOk();

        $this->actingAs($doctor);
        $later = $date->copy()->addDay()->toDateString();
        $this->post($saveUrl, [...$data, 'kontrol_berikutnya' => $later])->assertSessionHasNoErrors();
        $this->assertSame($later, $reservation->fresh()->tanggal->toDateString());
        $this->assertTrue($reservation->fresh()->isPendingReservation());
        $this->assertSame(0, $this->actingAs($admin)->get(route('pendaftaran.kunjungan-per-poli'))->inertiaProps('reservations')['total']);
        $this->actingAs($doctor);
        $this->post($saveUrl, [...$data, 'kontrol_berikutnya' => null])->assertSessionHasNoErrors();
        $this->assertSame('batal', $reservation->fresh()->status);
    }

    public function test_changed_assisted_content_requires_a_fresh_review_before_signing(): void
    {
        [$doctor, $admin, $visit] = $this->assistedContext();
        $review = $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->inertiaProps('assistanceReview');
        $visit->pemeriksaan()->update(['edukasi' => 'Perubahan edukasi terbaru']);
        DB::table('clinical_audit_events')->insert(['actor_id' => $admin->id, 'kunjungan_id' => $visit->id, 'action' => 'admin_assistance.draft_write', 'occurred_at' => now()]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true, 'review_performed_procedures' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true, 'review_admin_assistance' => true,
            'assistance_review_id' => $review['auditId'], 'assistance_review_hash' => $review['draftHash'],
        ])->assertSessionHasErrors('assistance_review_id');
        $review = $this->get(route('pelayanan.pemeriksaan.show', $visit))->inertiaProps('assistanceReview');
        $visit->pemeriksaan()->update(['edukasi' => 'Isi berubah setelah tinjauan']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true, 'review_performed_procedures' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true, 'review_admin_assistance' => true,
            'assistance_review_id' => $review['auditId'], 'assistance_review_hash' => $review['draftHash'],
        ])->assertSessionHasErrors('assistance_review_hash');
        $this->assertDatabaseCount('clinical_note_versions', 0);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'admin_assistance.reviewed']);
    }

    public function test_attending_doctor_corrects_legacy_assisted_draft_and_snapshot_uses_completed_state(): void
    {
        [$doctor, $admin, $visit] = $this->assistedContext('gigi');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), ['is_manual' => true, 'nama_diagnosa' => 'Diagnosis tambahan sementara', 'jenis' => 'tambahan'])->assertSessionHasNoErrors();
        $diagnosis = $visit->pemeriksaan->diagnosa()->where('jenis', 'tambahan')->sole();
        $this->delete(route('pelayanan.pemeriksaan.diagnosa.destroy', $diagnosis), ['reason' => 'Koreksi entri draf oleh dokter'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('diagnosa')->where('id', $diagnosis->id)->value('voided_at'));
        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), ['is_resep_luar' => true, 'nama_obat' => 'Obat resep luar', 'jumlah' => 2, 'satuan' => 'tablet', 'jenis' => 'jadi', 'aturan_pakai' => 'Sesuai instruksi dokter'])->assertSessionHasNoErrors();
        $item = $visit->resep()->sole()->resepObat()->sole();
        $this->delete(route('pelayanan.pemeriksaan.resep.destroy', $item), ['reason' => 'Koreksi entri draf oleh dokter'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('resep_obat')->where('id', $item->id)->value('voided_at'));
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan sementara', 'jumlah' => 1])->assertSessionHasNoErrors();
        $procedure = $visit->tindakanKunjungan()->sole();
        $this->delete(route('pelayanan.pemeriksaan.tindakan.destroy', $procedure), ['reason' => 'Koreksi entri draf oleh dokter'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('tindakan_kunjungan')->where('id', $procedure->id)->value('voided_at'));
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'reason' => 'Koreksi setelah pemeriksaan ulang'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'voided_at' => null]);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => ['icdas' => '5', 'activity' => 'active', 'site' => 'primary', 'depth' => 'dentin', 'pulpal' => 'reversible', 'periapical' => 'normal', 'assessment' => 'Kavitas dentin, respons dingin singkat. Rencana restorasi.']])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan dokter', 'jumlah' => 1, 'execution_role' => 'dokter'])->assertSessionHasNoErrors();
        $this->actingAs($doctor);
        $this->classifySyntheticProceduresAsNotRequiringConsent($visit, $doctor);
        $review = $this->get(route('pelayanan.pemeriksaan.show', $visit))->inertiaProps('assistanceReview');
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_performed_procedures' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true, 'review_admin_assistance' => true, 'assistance_review_id' => $review['auditId'], 'assistance_review_hash' => $review['draftHash']])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->assertSame($doctor->id, $visit->tindakanKunjungan()->sole()->performed_by);
        $this->assertSame('completed', ClinicalNoteVersion::sole()->payload['treatments'][0]['execution_status']);
    }

    /** @return array{User, User, Kunjungan} */
    private function assistedContext(string $type = 'umum'): array
    {
        [$doctor, , $visit] = $this->visitContext();
        $visit->poliklinik->update(['jenis' => $type]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($doctor);
        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['anamnesis' => 'Keluhan pasien dua hari', 'pemeriksaan_fisik' => 'Temuan pemeriksaan dokter', 'pemeriksaan_ekstraoral' => 'Wajah simetris tanpa pembengkakan', 'non_farmakologis' => 'Perawatan sesuai kondisi', 'edukasi' => 'Edukasi sesuai kondisi pasien'])->assertSessionHasNoErrors();
        ClinicalTerminology::create(['code_system' => 'icd10_who', 'release' => 'WHO 2016', 'code' => 'Z00.0', 'display' => 'Diagnosis klinis dokter', 'code_type' => 'diagnosis', 'is_active' => true]);
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), ['kode_icd10' => 'Z00.0', 'code_system' => 'icd10_who', 'nama_diagnosa' => 'Diagnosis klinis dokter', 'jenis' => 'utama'])->assertSessionHasNoErrors();
        if ($type === 'gigi') {
            $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => ['icdas' => '5', 'activity' => 'active', 'site' => 'primary', 'depth' => 'dentin', 'pulpal' => 'reversible', 'periapical' => 'normal', 'assessment' => 'Kavitas dentin, respons dingin singkat. Rencana restorasi.'], 'notes' => 'Temuan dokter gigi'])->assertSessionHasNoErrors();
        }

        DB::table('clinical_audit_events')->insert(['actor_id' => $admin->id, 'patient_id' => $visit->pasien_id, 'kunjungan_id' => $visit->id, 'action' => 'admin_assistance.draft_write', 'occurred_at' => now()]);

        return [$doctor, $admin, $visit];
    }

    /** @return array{User, Nakes, Kunjungan} */
    private function visitContext(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->setTime(9, 0));
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'DOCTOR', 'nama' => 'Dokter Pemeriksa', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $clinic->id, 'user_id' => $doctor->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-START', 'nama' => 'Pasien Pemeriksaan']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-START', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $staff->id, 'tanggal' => today(), 'status' => 'pemeriksaan', 'jenis_pasien' => 'lama']);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);

        return [$doctor, $staff, $visit];
    }
}
