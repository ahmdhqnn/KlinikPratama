<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ClinicalSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_open_clinical_services_or_json_endpoints(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['nama' => 'Pasien Sintetis', 'no_rm' => 'RM-HARDEN']);
        $this->actingAs($admin);
        foreach (['pelayanan.screening.index', 'pelayanan.pemeriksaan.index', 'pelayanan.tindakan.index', 'pelayanan.farmasi.index', 'farmasi.pemberian-obat.create', 'farmasi.pasien'] as $route) {
            $this->get(route($route))->assertForbidden();
        }
        $this->getJson(route('pelayanan.pasien.rekam-medis', $patient))->assertForbidden();
        $this->get(route('pelayanan.pasien.show', $patient))->assertInertia(fn (Assert $page) => $page->where('permissions.viewRme', false)->where('patient.allergies', null));
    }

    public function test_administrative_report_contains_only_aggregate_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $visit = $this->visit();
        $response = $this->actingAs($admin)->get(route('laporan.kunjungan'));
        $response->assertInertia(fn (Assert $page) => $page->component('laporan/agregat')->has('rows', 1)->where('rows.0.total', 1)->missing('visits')->missing('rows.0.patient'));
        $this->assertStringNotContainsString($visit->pasien->nama, $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_legacy_delete_cancels_with_reason_and_preserves_the_visit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $visit = $this->visit();
        $this->actingAs($admin)->delete(route('pelayanan.kunjungan.destroy', $visit))->assertSessionHasErrors('reason');
        $this->delete(route('pelayanan.kunjungan.destroy', $visit), ['reason' => 'Pasien membatalkan jadwal'])->assertRedirect();
        $this->assertModelExists($visit);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'batal', 'cancelled_by' => $admin->id]);
        $this->assertDatabaseHas('clinical_audit_events', ['kunjungan_id' => $visit->id, 'action' => 'visit.cancelled', 'actor_role' => 'admin']);
    }

    public function test_polling_does_not_extend_idle_and_expired_activity_cannot_revive_session(): void
    {
        $this->freezeTime();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get('/')->assertOk();
        $this->travel(14)->minutes();
        $this->get('/')->assertOk();
        $this->travel(1)->minutes();
        $this->postJson(route('session.activity'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_activity_returns_the_server_deadline_and_does_not_accept_disabled_accounts(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->postJson(route('session.activity'))->assertOk()
            ->assertJsonPath('userId', $user->id)
            ->assertJsonPath('expiresAt', now()->addMinutes(15)->timestamp * 1000)
            ->assertJsonPath('serverNow', now()->getTimestampMs());
        $user->update(['is_active' => false]);
        $this->postJson(route('session.activity'))->assertForbidden();
    }

    public function test_session_status_identifies_account_without_extending_idle_time(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($user)->getJson(route('session.status'))->assertOk()->assertJsonPath('userId', $user->id);
        $deadline = now()->addMinutes(15)->timestamp * 1000;
        $this->travel(14)->minutes();
        $this->getJson(route('session.status'))->assertOk()->assertJsonPath('expiresAt', $deadline);
        $this->travel(1)->minutes();
        $this->getJson(route('session.status'))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_password_reset_revokes_existing_session_and_requires_a_new_personal_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->actingAs($admin)->post(route('users.reset-password', $doctor), ['password' => 'Temporary123!'])->assertRedirect();
        $this->assertTrue($doctor->fresh()->must_change_password);
        $this->assertSame(1, $doctor->fresh()->session_version);
        $this->post('/logout')->assertRedirect();
        $this->post('/login', ['email' => $doctor->email, 'password' => 'Temporary123!'])->assertRedirect(route('profile.show'));
        $this->get(route('dokter.dashboard'))->assertRedirect(route('profile.show'));
        $this->put(route('profile.password'), ['current_password' => 'Temporary123!', 'password' => 'Personal456!', 'password_confirmation' => 'Personal456!'])->assertSessionHasNoErrors();
        $this->assertFalse($doctor->fresh()->must_change_password);
    }

    public function test_failed_login_has_anonymous_audit_without_credentials(): void
    {
        $this->post('/login', ['email' => 'synthetic@example.test', 'password' => 'do-not-log-this'])->assertSessionHasErrors();
        $event = DB::table('clinical_audit_events')->where('action', 'auth.failed_login')->first();
        $this->assertNotNull($event);
        $this->assertNull($event->actor_id);
        $this->assertSame('denied', $event->outcome);
        $this->assertStringNotContainsString('do-not-log-this', json_encode($event));
    }

    private function visit(): Kunjungan
    {
        $patient = Pasien::create(['nama' => 'Pasien Rahasia Sintetis', 'no_rm' => 'RM-SECURITY']);
        $clinic = Poliklinik::create(['kode' => 'HARDEN', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);

        return Kunjungan::create(['pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'no_kunjungan' => 'KNJ-SECURITY', 'tanggal' => today(), 'status' => 'menunggu']);
    }

    public function test_database_rejects_raw_audit_updates_and_transaction_deletes(): void
    {
        $visit = $this->visit();
        $id = DB::table('clinical_audit_events')->insertGetId(['actor_id' => null, 'action' => 'security.test', 'occurred_at' => now()]);
        foreach ([
            fn () => DB::table('clinical_audit_events')->where('id', $id)->update(['action' => 'rewritten']),
            fn () => DB::table('clinical_audit_events')->where('id', $id)->delete(),
            fn () => DB::table('kunjungan')->where('id', $visit->id)->delete(),
            fn () => DB::table('pasien')->where('id', $visit->pasien_id)->delete(),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Mutasi bukti klinis seharusnya ditolak database.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Catatan klinis dilindungi', $exception->getMessage());
            }
        }
        $this->assertModelExists($visit);
        $this->assertDatabaseHas('clinical_audit_events', ['id' => $id, 'action' => 'security.test']);
    }

    public function test_security_check_verifies_guards_after_all_schema_upgrades(): void
    {
        $this->artisan('clinic:security-check', ['--json' => true])->assertSuccessful();
    }

    public function test_admin_audit_exports_preserve_filters_and_never_export_raw_clinical_metadata(): void
    {
        $admin = User::factory()->create(['name' => '=SUM(1,1)', 'role' => 'admin', 'is_active' => true]);
        DB::table('clinical_audit_events')->insert(['actor_id' => $admin->id, 'actor_role' => 'admin', 'action' => 'synthetic.export', 'occurred_at' => now(), 'metadata' => json_encode(['clinical_note' => 'PRIVATE-SYNTHETIC-CONTENT'])]);
        $this->actingAs($admin);
        $response = $this->get(route('admin.log-aktivitas.export', ['aksi' => 'synthetic.export']))->assertDownload();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame(2, $sheet->getHighestDataRow());
        $this->assertSame('s', $sheet->getCell('D2')->getDataType());
        $this->assertStringNotContainsString('PRIVATE-SYNTHETIC-CONTENT', json_encode($sheet->toArray()));
        $print = $this->get(route('admin.log-aktivitas.print', ['aksi' => 'synthetic.export']))->assertOk();
        $print->assertDontSee('PRIVATE-SYNTHETIC-CONTENT')->assertSee('Cetak / Simpan PDF');
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'audit.export', 'actor_id' => $admin->id]);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'audit.print', 'actor_id' => $admin->id]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $this->actingAs($nurse)->get(route('admin.log-aktivitas.export'))->assertForbidden();
    }

    public function test_rare_diagnosis_aggregates_require_five_distinct_patients(): void
    {
        $visit = $this->visit();
        $exam = $visit->pemeriksaan()->create(['status' => 'selesai']);
        $exam->diagnosa()->create(['kode_icd10' => 'J00', 'nama_diagnosa' => 'Diagnosis Sintetis', 'jenis' => 'utama']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('pendaftaran.laporan-top-diagnosa'))->assertInertia(fn (Assert $page) => $page->where('summary.diagnosisCount', 0));
        $this->expandSyntheticDiagnosisPopulation($visit);
        $this->get(route('pendaftaran.laporan-top-diagnosa'))->assertInertia(fn (Assert $page) => $page->where('summary.diagnosisCount', 1)->where('diagnoses.data.0.count', 5));
    }

    public function test_admin_identity_edit_cannot_overwrite_a_verified_clinical_allergy(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-ALLERGY-GUARD', 'nama' => 'Pasien Uji', 'riwayat_alergi' => 'Penisilin']);
        $this->actingAs($admin)->put(route('pelayanan.pasien.update', $patient), ['nama' => 'Pasien Uji Diperbarui', 'riwayat_alergi' => 'Tidak ada alergi'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Penisilin', $patient->fresh()->riwayat_alergi);
    }

    public function test_excel_identifiers_keep_all_digits_and_whitespace_prefixed_text_is_never_a_formula(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Pasien::create(['no_rm' => '000001', 'nama' => "\t=HYPERLINK(\"https://example.test\",\"synthetic\")", 'nik' => '0123456789012345', 'telepon' => '001234567890']);
        $response = $this->actingAs($admin)->get(route('pelayanan.pasien.export'))->assertDownload();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();
        $this->assertSame('000001', $sheet->getCell('A2')->getValue());
        $this->assertSame('0123456789012345', $sheet->getCell('B2')->getValue());
        $this->assertSame('s', $sheet->getCell('B2')->getDataType());
        $this->assertSame('s', $sheet->getCell('C2')->getDataType());
        $this->assertStringContainsString('HYPERLINK', $sheet->getCell('C2')->getValue());
        $this->actingAs(User::factory()->create(['role' => 'perawat', 'is_active' => true]))->get(route('pelayanan.pasien.export'))->assertForbidden();
    }
}
