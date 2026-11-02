<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Http\Middleware\AuditAdminAssistanceFailures;
use App\Models\ClinicalNoteVersion;
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
        [$doctor, $staff, $visit] = $this->visitContext();
        $visit->poliklinik->update(['jenis' => $type]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('pelayanan.pemeriksaan.index'))->assertOk();
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page->where('assistance.isAdmin', true)->where('assistance.active', false));
        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertForbidden();
        $this->post(route('pelayanan.pemeriksaan.assist', $visit), [])->assertSessionHasErrors('reason');
        $this->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => str_repeat(' ', 12)])->assertSessionHasErrors('reason');
        $rejectedEvents = DB::table('clinical_audit_events')->where('actor_id', $admin->id)->where('action', 'admin_assistance.attempt_rejected')->where('route_name', 'pelayanan.pemeriksaan.assist')->get();
        $this->assertCount(2, $rejectedEvents);
        foreach ($rejectedEvents as $event) {
            $metadata = json_decode($event->metadata, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(['failure_kind', 'status'], array_keys($metadata));
            $this->assertSame('validation_or_workflow', $metadata['failure_kind']);
            $this->assertSame(302, $metadata['status']);
            $this->assertSame($visit->id, (int) $event->kunjungan_id);
            $this->assertSame('pelayanan.pemeriksaan.assist', $event->route_name);
        }
        $this->assertDatabaseHas('clinical_audit_events', [
            'actor_id' => $admin->id,
            'kunjungan_id' => $visit->id,
            'action' => 'admin_assistance.attempt_rejected',
            'route_name' => 'pelayanan.pemeriksaan.start',
        ]);
        $deniedEvent = DB::table('clinical_audit_events')->where('actor_id', $admin->id)->where('action', 'admin_assistance.attempt_rejected')->where('route_name', 'pelayanan.pemeriksaan.start')->first();
        $this->assertSame(['failure_kind' => 'access_denied', 'status' => 403], json_decode($deniedEvent->metadata, true, flags: JSON_THROW_ON_ERROR));
        $this->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Akun layanan sedang mengalami gangguan.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'anamnesis', 'anamnesis' => 'Keluhan pasien dicatat sesuai hasil pemeriksaan dokter.'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($staff->id, $visit->fresh()->dokter_id);
        $this->assertDatabaseHas('pemeriksaan', ['kunjungan_id' => $visit->id, 'dokter_id' => $staff->id, 'signed_at' => null]);
        $this->assertDatabaseHas('clinical_audit_events', ['actor_id' => $admin->id, 'kunjungan_id' => $visit->id, 'action' => 'admin_assistance.draft_write']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertForbidden();
        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), [])->assertForbidden();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertForbidden();
        $this->assertDatabaseMissing('clinical_audit_events', ['actor_id' => $doctor->id, 'action' => 'admin_assistance.attempt_rejected']);
    }

    public function test_assistance_is_scoped_to_actor_assignment_and_expiry(): void
    {
        [, , $visit] = $this->visitContext();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertRedirect();
        $other = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($other)->post(route('pelayanan.pemeriksaan.start', $visit))->assertForbidden();
        DB::table('admin_clinical_assistances')->update(['expires_at' => now()->subMinute()]);
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.start', $visit))->assertForbidden();
        $this->assertDatabaseCount('pemeriksaan', 0);
        $this->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertRedirect();
        $visit->update(['dokter_id' => null]);
        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertForbidden();
        $this->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertUnprocessable();
        $this->assertDatabaseCount('pemeriksaan', 0);
    }

    public function test_assistance_does_not_bypass_screening_or_signed_records(): void
    {
        [, $staff, $visit] = $this->visitContext();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $visit->screening()->update(['submitted_at' => null]);
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertUnprocessable();
        $this->assertDatabaseCount('admin_clinical_assistances', 0);
        $visit->screening()->update(['submitted_at' => now()]);
        $visit->pemeriksaan()->create(['dokter_id' => $staff->id, 'status' => 'selesai', 'signed_at' => now()]);
        $this->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sementara.'])->assertUnprocessable();
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
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasErrors('review_admin_assistance');
        $this->assertDatabaseCount('clinical_note_versions', 0);
        $page = $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertOk();
        $review = $page->inertiaProps('assistanceReview');
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'signature_password' => 'password', 'review_admin_assistance' => true,
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
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.store', $visit), ['anamnesis' => 'Menimpa catatan'])->assertUnprocessable();
        if ($type === 'gigi') {
            $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'reason' => 'Koreksi catatan final oleh operator'])->assertForbidden();
            $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46']);
        }
    }

    public function test_changed_assisted_content_requires_a_fresh_review_before_signing(): void
    {
        [$doctor, $admin, $visit] = $this->assistedContext();
        $review = $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->inertiaProps('assistanceReview');
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.store', $visit), ['section' => 'rencana', 'edukasi' => 'Perubahan edukasi terbaru'])->assertSessionHasNoErrors();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'signature_password' => 'password', 'review_admin_assistance' => true,
            'assistance_review_id' => $review['auditId'], 'assistance_review_hash' => $review['draftHash'],
        ])->assertSessionHasErrors('assistance_review_id');
        $review = $this->get(route('pelayanan.pemeriksaan.show', $visit))->inertiaProps('assistanceReview');
        $visit->pemeriksaan()->update(['edukasi' => 'Isi berubah setelah tinjauan']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'signature_password' => 'password', 'review_admin_assistance' => true,
            'assistance_review_id' => $review['auditId'], 'assistance_review_hash' => $review['draftHash'],
        ])->assertSessionHasErrors('assistance_review_hash');
        $this->assertDatabaseCount('clinical_note_versions', 0);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'admin_assistance.reviewed']);
    }

    public function test_admin_can_correct_draft_entries_and_doctor_procedure_snapshot_uses_completed_state(): void
    {
        [$doctor, $admin, $visit] = $this->assistedContext('gigi');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), ['is_manual' => true, 'nama_diagnosa' => 'Diagnosis tambahan sementara', 'jenis' => 'tambahan'])->assertSessionHasNoErrors();
        $diagnosis = $visit->pemeriksaan->diagnosa()->where('jenis', 'tambahan')->sole();
        $this->delete(route('pelayanan.pemeriksaan.diagnosa.destroy', $diagnosis))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('diagnosa', ['id' => $diagnosis->id]);
        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), ['is_resep_luar' => true, 'nama_obat' => 'Obat resep luar', 'jumlah' => 2, 'satuan' => 'tablet', 'jenis' => 'jadi', 'aturan_pakai' => 'Sesuai instruksi dokter'])->assertSessionHasNoErrors();
        $item = $visit->resep()->sole()->resepObat()->sole();
        $this->delete(route('pelayanan.pemeriksaan.resep.destroy', $item))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('resep_obat', ['id' => $item->id]);
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan sementara', 'jumlah' => 1])->assertSessionHasNoErrors();
        $procedure = $visit->tindakanKunjungan()->sole();
        $this->delete(route('pelayanan.pemeriksaan.tindakan.destroy', $procedure))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('tindakan_kunjungan', ['id' => $procedure->id]);
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['tooth_fdi' => '46', 'surface' => 'O'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46']);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan dokter', 'jumlah' => 1, 'execution_role' => 'dokter'])->assertSessionHasNoErrors();
        $this->actingAs($doctor);
        $review = $this->get(route('pelayanan.pemeriksaan.show', $visit))->inertiaProps('assistanceReview');
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password', 'review_admin_assistance' => true, 'assistance_review_id' => $review['auditId'], 'assistance_review_hash' => $review['draftHash']])->assertSessionHasNoErrors();
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
        $this->actingAs($admin)->post(route('pelayanan.pemeriksaan.assist', $visit), ['reason' => 'Bantuan pencatatan sesuai pemeriksaan dokter.'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.start', $visit))->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['anamnesis' => 'Keluhan pasien dua hari', 'pemeriksaan_fisik' => 'Temuan pemeriksaan dokter', 'non_farmakologis' => 'Perawatan sesuai kondisi', 'edukasi' => 'Edukasi sesuai kondisi pasien'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), ['is_manual' => true, 'nama_diagnosa' => 'Diagnosis klinis dokter', 'jenis' => 'utama'])->assertSessionHasNoErrors();
        if ($type === 'gigi') {
            $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'notes' => 'Temuan dokter gigi'])->assertSessionHasNoErrors();
        }

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
