<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\GeneralConsentService;
use App\Models\Diagnosa;
use App\Models\GeneralConsent;
use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\ProcedureConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NurseProcedureConsentComplianceTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, User, Poliklinik, Kunjungan, Pemeriksaan} */
    private function createContext(string $priority = 'normal', bool $generalConsentRequired = true, bool $recordGeneralConsent = true): array
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $nurseUser = User::factory()->create(['role' => 'perawat', 'is_active' => true]);

        $clinic = Poliklinik::create([
            'kode' => 'UMUM-NC',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);

        $doctor = Nakes::create([
            'kode' => 'DOK-NC',
            'user_id' => $doctorUser->id,
            'poliklinik_id' => $clinic->id,
            'nama' => 'dr. Penanggung Jawab',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'is_active' => true,
        ]);

        Nakes::create([
            'kode' => 'PRW-NC',
            'user_id' => $nurseUser->id,
            'poliklinik_id' => $clinic->id,
            'nama' => 'Ns. Pelaksana Tindakan',
            'kategori' => 'medis',
            'jabatan' => 'perawat',
            'is_active' => true,
        ]);

        $patient = Pasien::create([
            'no_rm' => 'RM-NC-001',
            'nama' => 'Pasien Tindakan Uji',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1990-01-15',
        ]);

        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-NC-001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
            'status' => 'pemeriksaan',
            'general_consent_required' => $generalConsentRequired,
        ]);

        if ($recordGeneralConsent) {
            $gc = new GeneralConsent([
                'pasien_id' => $patient->id,
                'kunjungan_id' => $visit->id,
                'request_id' => (string) Str::uuid(),
                'request_hash' => hash('sha256', (string) Str::uuid()),
                'decision' => 'accepted',
                'satusehat_sharing_decision' => 'accepted',
                'text_version' => GeneralConsentService::VERSION,
                'text_snapshot' => GeneralConsentService::TEXT,
                'signer_name' => $patient->nama,
                'signer_relationship' => 'patient',
                'recorded_by' => $admin->id,
                'recorded_at' => now(),
                'signature_token' => (string) Str::uuid(),
            ]);
            $gc->signature_hash = app(GeneralConsentService::class)->signature($gc, (string) config('app.key'));
            $gc->save();
            $visit->forceFill(['general_consent_id' => $gc->id])->save();
        }

        Screening::create([
            'kunjungan_id' => $visit->id,
            'started_at' => now()->subMinutes(20),
            'submitted_at' => now()->subMinutes(15),
            'kesimpulan_triase' => $priority === 'darurat' ? 'merah' : 'hijau',
            'prioritas_layanan' => $priority,
        ]);

        $exam = Pemeriksaan::create([
            'kunjungan_id' => $visit->id,
            'dokter_id' => $doctor->id,
            'anamnesis' => 'Keluhan klinis untuk tindakan perawat.',
            'pemeriksaan_fisik' => 'Pemeriksaan fisik terdokumentasi.',
        ]);
        Diagnosa::create([
            'pemeriksaan_id' => $exam->id,
            'kode_icd10' => 'S01.8',
            'nama_diagnosa' => 'Luka terbuka superfisial',
            'jenis' => 'utama',
        ]);

        return [$admin, $doctorUser, $nurseUser, $clinic, $visit, $exam];
    }

    private function signExaminationDirectly(Kunjungan $visit, Pemeriksaan $exam, User $doctor): void
    {
        $exam->update([
            'status' => 'selesai',
            'signed_at' => now(),
            'signed_by_user_id' => $doctor->id,
        ]);
        app(ClinicalNoteRecorder::class)->recordFinal(
            $exam,
            app(ClinicalNoteRecorder::class)->snapshot($visit->fresh(), $exam),
            $doctor->id,
        );
        $visit->update(['status' => 'tindakan']);
    }

    private function baseExplanation(): array
    {
        return [
            'indication' => 'Indikasi klinis terdokumentasi',
            'method' => 'Metode tindakan sesuai SPO',
            'benefits' => 'Manfaat yang diharapkan',
            'risks' => 'Risiko dan komplikasi relevan',
            'alternatives' => 'Alternatif tindakan tersedia',
            'without_procedure' => 'Konsekuensi tanpa tindakan',
            'prognosis' => 'Prognosis baik sesuai evaluasi',
        ];
    }

    public function test_admin_can_configure_master_procedure_consent_mode_and_delegation(): void
    {
        [$admin, , , $clinic] = $this->createContext();

        $this->actingAs($admin)
            ->post(route('master.poliklinik.tindakan.store', $clinic), [
                'kode' => 'TDK-INV-01',
                'kode_icd9' => '93.57',
                'nama' => 'Perawatan Luka Steril',
                'can_delegate_to_nurse' => true,
                'consent_mode' => 'written',
            ])
            ->assertSessionHasNoErrors();

        $procedureMaster = Tindakan::where('kode', 'TDK-INV-01')->firstOrFail();
        $this->assertSame('written', $procedureMaster->consent_mode);
        $this->assertTrue($procedureMaster->can_delegate_to_nurse);

        $this->actingAs($admin)
            ->put(route('master.poliklinik.tindakan.update', $procedureMaster), [
                'kode' => 'TDK-INV-01',
                'kode_icd9' => '93.57',
                'nama' => 'Perawatan Luka Steril Lanjutan',
                'can_delegate_to_nurse' => false,
                'consent_mode' => 'oral',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $procedureMaster->refresh();
        $this->assertSame('oral', $procedureMaster->consent_mode);
        $this->assertFalse($procedureMaster->can_delegate_to_nurse);

        $this->actingAs($admin)
            ->get(route('master.poliklinik.show', $clinic))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/poliklinik/show')
                ->where('clinic.treatments.0.code', 'TDK-INV-01')
                ->where('clinic.treatments.0.consentMode', 'oral')
                ->where('clinic.treatments.0.canDelegateToNurse', false));
    }

    public function test_master_written_consent_mode_forbids_skip_and_oral_and_requires_written_evidence_for_nurse(): void
    {
        [, $doctor, $nurse, $clinic, $visit, $exam] = $this->createContext();

        $master = Tindakan::create([
            'kode' => 'TDK-WR-01',
            'nama' => 'Perawatan Luka Invasif',
            'kategori' => 'medis',
            'can_delegate_to_nurse' => true,
            'consent_mode' => 'written',
            'poliklinik_id' => $clinic->id,
            'is_active' => true,
        ]);

        $procedure = $visit->tindakanKunjungan()->create([
            'tindakan_id' => $master->id,
            'dokter_id' => $visit->dokter_id,
            'nama_tindakan' => $master->nama,
            'jumlah' => 1,
            'execution_role' => 'perawat',
            'execution_status' => 'pending',
            'consent_review_required' => true,
            'coding_status' => 'not_applicable',
            'coding_reviewed_by' => $doctor->id,
            'coding_reviewed_at' => now(),
        ]);

        $storeUrl = route('procedure-consent.store', [$visit, $procedure]);
        $base = [
            'request_id' => (string) Str::uuid(),
            'current_id' => null,
            'plan_hash' => app(ProcedureConsentService::class)->planHash($procedure),
            'text_version' => ProcedureConsentService::VERSION,
            'signature_password' => 'password',
            'explained' => true,
            'reason' => 'Penjelasan klinis telah disampaikan secara menyeluruh.',
            'explanation' => $this->baseExplanation(),
        ];

        // Doctor CANNOT bypass written master procedure with not_required or oral
        $this->actingAs($doctor)
            ->post($storeUrl, [...$base, 'decision' => 'not_required'])
            ->assertSessionHasErrors('decision');

        $this->actingAs($doctor)
            ->post($storeUrl, [...$base, 'request_id' => (string) Str::uuid(), 'decision' => 'oral', 'signer_name' => 'Pasien Tindakan Uji', 'signer_relationship' => 'patient'])
            ->assertSessionHasErrors('decision');

        // Even if exam were signed directly, nurse CANNOT start without written consent
        $this->signExaminationDirectly($visit, $exam, $doctor);
        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.start', $visit))
            ->assertSessionHasErrors('procedure_consent');

        // Doctor records valid written informed consent
        $this->actingAs($doctor)
            ->post($storeUrl, [
                ...$base,
                'request_id' => (string) Str::uuid(),
                'decision' => 'accepted',
                'signer_name' => 'Pasien Tindakan Uji',
                'signer_relationship' => 'patient',
                'witness_name' => 'Ns. Pelaksana Tindakan',
                'evidence' => UploadedFile::fake()->createWithContent('ic-luka.pdf', "%PDF-1.4\nBukti\n%%EOF"),
            ])
            ->assertSessionHasNoErrors();

        // Nurse now sees verified consent metadata and can start & complete
        $this->actingAs($nurse)
            ->get(route('pelayanan.tindakan.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.delegation.verified', true)
                ->where('visit.generalConsent.active', true)
                ->where('visit.treatments.0.consent.decision', 'accepted')
                ->where('visit.treatments.0.consent.canPerform', true)
                ->where('visit.treatments.0.consent.signerName', 'Pasien Tindakan Uji')
                ->where('visit.treatments.0.consent.witnessName', 'Ns. Pelaksana Tindakan'));

        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.start', $visit))
            ->assertSessionHasNoErrors();

        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.complete', [$visit, $procedure]), ['execution_result' => 'Luka dibersihkan dan ditutup kasa steril, pasien stabil.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('completed', $procedure->fresh()->execution_status);
    }

    public function test_routine_procedure_requires_active_general_consent_before_classification_and_execution(): void
    {
        [, $doctor, $nurse, $clinic, $visit, $exam] = $this->createContext(generalConsentRequired: true, recordGeneralConsent: false);

        $master = Tindakan::create([
            'kode' => 'TDK-RTN-01',
            'nama' => 'Kompres Hangat Keperawatan',
            'kategori' => 'medis',
            'can_delegate_to_nurse' => true,
            'consent_mode' => 'general_consent',
            'poliklinik_id' => $clinic->id,
            'is_active' => true,
        ]);

        $procedure = $visit->tindakanKunjungan()->create([
            'tindakan_id' => $master->id,
            'dokter_id' => $visit->dokter_id,
            'nama_tindakan' => $master->nama,
            'jumlah' => 1,
            'execution_role' => 'perawat',
            'execution_status' => 'pending',
            'consent_review_required' => true,
        ]);

        // Doctor cannot classify as not_required (Cukup General Consent) when General Consent is not active
        $this->actingAs($doctor)
            ->post(route('procedure-consent.store', [$visit, $procedure]), [
                'request_id' => (string) Str::uuid(),
                'current_id' => null,
                'plan_hash' => app(ProcedureConsentService::class)->planHash($procedure),
                'text_version' => ProcedureConsentService::VERSION,
                'decision' => 'not_required',
                'signature_password' => 'password',
                'explained' => true,
                'reason' => 'Tindakan keperawatan rutin non-invasif mengacu pada general consent.',
            ])
            ->assertSessionHasErrors('decision');

        // Also if not_required already existed, nurse execution is blocked without active General Consent
        $this->signExaminationDirectly($visit, $exam, $doctor);
        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.start', $visit))
            ->assertSessionHasErrors('procedure_consent');
    }

    public function test_nurse_can_record_patient_refusal_or_withdrawal_at_treatment_room_which_locks_execution(): void
    {
        [, $doctor, $nurse, $clinic, $visit, $exam] = $this->createContext();

        $master = Tindakan::create([
            'kode' => 'TDK-ORL-01',
            'nama' => 'Terapi Nebulisasi',
            'kategori' => 'medis',
            'can_delegate_to_nurse' => true,
            'consent_mode' => 'oral',
            'poliklinik_id' => $clinic->id,
            'is_active' => true,
        ]);

        $procedure = $visit->tindakanKunjungan()->create([
            'tindakan_id' => $master->id,
            'dokter_id' => $visit->dokter_id,
            'nama_tindakan' => $master->nama,
            'jumlah' => 1,
            'execution_role' => 'perawat',
            'execution_status' => 'pending',
            'consent_review_required' => true,
        ]);

        // Doctor records valid oral consent (allowed by master consent_mode = oral)
        $this->actingAs($doctor)
            ->post(route('procedure-consent.store', [$visit, $procedure]), [
                'request_id' => (string) Str::uuid(),
                'current_id' => null,
                'plan_hash' => app(ProcedureConsentService::class)->planHash($procedure),
                'text_version' => ProcedureConsentService::VERSION,
                'decision' => 'oral',
                'signature_password' => 'password',
                'explained' => true,
                'signer_name' => 'Pasien Tindakan Uji',
                'signer_relationship' => 'patient',
                'reason' => 'Pasien menyetujui nebulisasi secara lisan setelah penjelasan.',
                'explanation' => $this->baseExplanation(),
            ])
            ->assertSessionHasNoErrors();

        $this->signExaminationDirectly($visit, $exam, $doctor);

        // Doctor cannot call nurse refusal endpoint (role boundary)
        $this->actingAs($doctor)
            ->post(route('pelayanan.tindakan.refuse', [$visit, $procedure]), [
                'decision' => 'withdrawn',
                'signer_name' => 'Pasien Tindakan Uji',
                'signer_relationship' => 'patient',
                'reason' => 'Pasien berubah pikiran di ruang tindakan dan menolak masker.',
                'confirmed' => true,
            ])
            ->assertForbidden();

        // Nurse records withdrawal at treatment room
        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.refuse', [$visit, $procedure]), [
                'decision' => 'withdrawn',
                'signer_name' => 'Pasien Tindakan Uji',
                'signer_relationship' => 'patient',
                'reason' => 'Pasien merasa cemas dengan masker nebulizer dan mencabut persetujuan; dokter telah diinformasikan.',
                'confirmed' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $latest = InformedConsent::latest('id')->firstOrFail();
        $this->assertSame('withdrawn', $latest->decision);
        $this->assertSame('perawat', $latest->procedure_snapshot['recorded_by_role']);
        $this->assertDatabaseHas('clinical_audit_events', [
            'action' => 'nursing_procedure.consent_withdrawn',
            'kunjungan_id' => $visit->id,
        ]);

        // Execution is now strictly locked
        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.start', $visit))
            ->assertSessionHasErrors('procedure_consent');
    }

    public function test_nurse_emergency_override_requires_emergency_triage_and_cannot_bypass_refusal(): void
    {
        [, $doctor, $nurse, $clinic, $visitNormal, $examNormal] = $this->createContext(priority: 'normal');
        $procNormal = $visitNormal->tindakanKunjungan()->create([
            'dokter_id' => $visitNormal->dokter_id,
            'nama_tindakan' => 'Tindakan Uji',
            'jumlah' => 1,
            'execution_role' => 'perawat',
            'execution_status' => 'pending',
            'consent_review_required' => true,
        ]);
        $this->signExaminationDirectly($visitNormal, $examNormal, $doctor);

        // Non-emergency triage visit MUST reject nurse emergency mode
        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.emergency', [$visitNormal, $procNormal]), [
                'reason' => 'Alasan darurat tidak sah pada kunjungan rutin.',
                'emergency_without_available_signer' => true,
            ])
            ->assertSessionHasErrors('procedure_consent');

        // Upgrade visit to emergency triage
        $visitNormal->screening->update(['kesimpulan_triase' => 'merah', 'prioritas_layanan' => 'darurat']);

        // Nurse activates emergency mode on emergency triage visit
        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.emergency', [$visitNormal, $procNormal]), [
                'reason' => 'Pasien sesak berat dan penurunan kesadaran tanpa pendamping; tindakan penyelamatan segera dilakukan dan penjelasan menyusul.',
                'emergency_without_available_signer' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('warning');

        $record = InformedConsent::latest('id')->firstOrFail();
        $this->assertSame('emergency', $record->decision);
        $this->assertTrue($record->procedure_snapshot['followup_documentation_required']);
        $this->assertSame('perawat', $record->procedure_snapshot['recorded_by_role']);
        $this->assertDatabaseHas('clinical_audit_events', [
            'action' => 'nursing_procedure.emergency_override',
            'kunjungan_id' => $visitNormal->id,
        ]);

        // Nurse can now start and complete the life-saving procedure
        $this->actingAs($nurse)->post(route('pelayanan.tindakan.start', $visitNormal))->assertSessionHasNoErrors();
        $this->actingAs($nurse)->post(route('pelayanan.tindakan.complete', [$visitNormal, $procNormal]), [
            'execution_result' => 'Tindakan gawat darurat dilaksanakan sesuai SPO, jalan napas stabil.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $procNormal->fresh()->execution_status);

        // Emergency mode CANNOT bypass an explicit refusal
        $procRefused = $visitNormal->tindakanKunjungan()->create([
            'dokter_id' => $visitNormal->dokter_id,
            'nama_tindakan' => 'Tindakan Ditolak',
            'jumlah' => 1,
            'execution_role' => 'perawat',
            'execution_status' => 'pending',
            'consent_review_required' => true,
        ]);
        $visitNormal->refresh()->update(['status' => 'tindakan']);
        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.refuse', [$visitNormal, $procRefused]), [
                'decision' => 'refused',
                'signer_name' => 'Pasien Tindakan Uji',
                'signer_relationship' => 'patient',
                'reason' => 'Pasien sadar menolak tindakan kedua secara tegas dan dokter diberitahu.',
                'confirmed' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($nurse)
            ->post(route('pelayanan.tindakan.emergency', [$visitNormal, $procRefused]), [
                'reason' => 'Mencoba melewati penolakan pasien dengan mode darurat.',
                'emergency_without_available_signer' => true,
            ])
            ->assertSessionHasErrors('procedure_consent');
    }
}
