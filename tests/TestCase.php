<?php

namespace Tests;

use App\GeneralConsentService;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\PharmacyStaffCredential;
use App\Models\User;
use App\PharmacyClinicalReviewService;
use App\PharmacyStaffAccess;
use App\ProcedureCodingReview;
use App\ProcedureConsentService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function activeAdmin(): User
    {
        return User::query()->where('role', 'admin')->where('is_active', true)->first()
            ?? User::factory()->create(['role' => 'admin']);
    }

    protected function verifySyntheticPharmacyStaff(User $pharmacist): void
    {
        $profile = Nakes::where('user_id', $pharmacist->id)->first();
        $profile ??= Nakes::create(['kode' => 'FARM-TEST-'.$pharmacist->id, 'nama' => 'Apoteker sintetis', 'kategori' => 'medis',
            'jabatan' => 'farmasi', 'user_id' => $pharmacist->id, 'is_active' => true]);
        $profile->update(['no_sip' => $profile->no_sip ?: 'SIP-SYNTHETIC', 'no_str' => $profile->no_str ?: 'STR-SYNTHETIC-'.$profile->id]);
        if (! app(PharmacyStaffAccess::class)->current($pharmacist)) {
            $admin = $this->activeAdmin();
            $this->actingAs($admin)->post(route('master.nakes.pharmacy-credential.store', $profile), [
                'current_id' => PharmacyStaffCredential::where('nakes_id', $profile->id)->max('id'),
                'qualification' => 'apoteker', 'reference' => 'Dokumen dan penugasan apoteker sintetis diperiksa untuk fixture.',
                'valid_until' => today()->addMonth()->toDateString(), 'documents_verified' => true, 'signature_password' => 'password',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->actingAs($pharmacist);
    }

    protected function reviewSyntheticPharmacyPlan(Kunjungan $visit, User $pharmacist): void
    {
        $this->verifySyntheticPharmacyStaff($pharmacist);
        $service = app(PharmacyClinicalReviewService::class);
        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.review.store', $visit), [
            'source_hash' => $service->hash($visit->fresh()), 'current_id' => $service->latest($visit)?->id,
            'decision' => 'approved', 'administrative_review' => true, 'pharmaceutical_review' => true, 'clinical_review' => true,
            'notes' => 'Resep dan rencana penyerahan sintetis diperiksa oleh apoteker fixture.',
            'clarification' => 'not_needed', 'signature_password' => 'password',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    protected function syntheticPharmacyHandover(): array
    {
        return ['recipient_name' => 'Pasien sintetis', 'recipient_relationship' => 'Pasien sendiri', 'recipient_verified' => true,
            'counseling_completed' => true, 'counseling_notes' => 'Petunjuk penggunaan, penyimpanan dan tindak lanjut dipahami pasien sintetis.'];
    }

    protected function syntheticDirectPharmacyReview(): array
    {
        return $this->syntheticPharmacyHandover() + ['administrative_review' => true, 'pharmaceutical_review' => true, 'clinical_review' => true,
            'review_notes' => 'Identitas, dasar pemberian, alergi, dosis dan kondisi relevan diperiksa apoteker sintetis.'];
    }

    protected function recordSyntheticGeneralConsent(Kunjungan $visit, User $registrar): void
    {
        Storage::fake('local');
        $this->actingAs($registrar)->post(route('general-consent.store', $visit), [
            'request_id' => (string) Str::uuid(), 'current_id' => null,
            'decision' => 'accepted', 'text_version' => GeneralConsentService::VERSION,
            'signer_name' => 'Pasien Sintetis', 'signer_relationship' => 'patient', 'explained' => true,
            'evidence' => UploadedFile::fake()->createWithContent('consent-sintetis.pdf', "%PDF-1.4\nBukti sintetis\n%%EOF"),
        ])->assertSessionHasNoErrors();
    }

    /**
     * Explicitly classify synthetic low-risk procedures used by a test fixture.
     * Production code never calls this helper and no patient decision is manufactured.
     */
    protected function classifySyntheticProceduresAsNotRequiringConsent(Kunjungan $visit, User $doctor): void
    {
        $this->actingAs($doctor);
        foreach ($visit->tindakanKunjungan()->where('consent_review_required', true)->orderBy('id')->get() as $procedure) {
            if ($procedure->coding_status === 'unresolved' && blank($procedure->kode_icd9cm)) {
                $this->post(route('pelayanan.pemeriksaan.tindakan.coding', [$visit, $procedure]), [
                    'revision' => app(ProcedureCodingReview::class)->revision($procedure),
                    'classification' => 'not_applicable', 'reviewed' => true,
                    'reason' => 'Fixture perawatan suportif tanpa prosedur medis berkode, ditinjau dokter sintetis.',
                ])->assertSessionHasNoErrors();
                $procedure->refresh();
            }
            if ($procedure->informedConsents()->exists()) {
                continue;
            }
            $this->post(route('procedure-consent.store', [$visit, $procedure]), [
                'request_id' => (string) Str::uuid(), 'current_id' => null,
                'plan_hash' => app(ProcedureConsentService::class)->planHash($procedure),
                'text_version' => ProcedureConsentService::VERSION,
                'decision' => 'not_required', 'signature_password' => 'password', 'explained' => true,
                'reason' => 'Fixture tindakan risiko rendah yang ditinjau eksplisit oleh dokter sintetis.',
                'explanation' => [
                    'indication' => 'Indikasi fixture telah ditinjau', 'method' => 'Metode fixture telah ditinjau',
                    'benefits' => 'Manfaat fixture telah ditinjau', 'risks' => 'Risiko fixture telah ditinjau',
                    'alternatives' => 'Alternatif fixture telah ditinjau', 'without_procedure' => 'Konsekuensi fixture telah ditinjau',
                    'prognosis' => 'Prognosis fixture telah ditinjau',
                ],
            ])->assertSessionHasNoErrors();
        }
    }

    protected function expandSyntheticDiagnosisPopulation(Kunjungan $visit, int $additionalPatients = 4): void
    {
        $visit->load(['pasien', 'pemeriksaan.diagnosa']);
        for ($index = 0; $index < $additionalPatients; $index++) {
            $patient = $visit->pasien->replicate();
            $patient->no_rm = $visit->pasien->no_rm.'-POP-'.$visit->id.'-'.$index;
            $patient->nik = null;
            $patient->nip = null;
            $patient->kepesertaan_id = null;
            $patient->save();
            $copy = $visit->replicate();
            $copy->no_kunjungan = $visit->no_kunjungan.'-POP-'.$index;
            $copy->pasien_id = $patient->id;
            $copy->save();
            $examination = $visit->pemeriksaan->replicate();
            $examination->kunjungan_id = $copy->id;
            $examination->save();
            foreach ($visit->pemeriksaan->diagnosa as $diagnosis) {
                $row = $diagnosis->replicate();
                $row->pemeriksaan_id = $examination->id;
                $row->save();
            }
        }
    }

    protected function beginNursingAssessment(Kunjungan $visit): static
    {
        $current = $visit->fresh(['screening']);
        if (in_array($current->status, ['menunggu', 'screening'], true) && ! $current->screening?->started_at) {
            $staff = Nakes::where('poliklinik_id', $current->poliklinik_id)
                ->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('is_active', true)->first();
            $this->post(route('pelayanan.screening.start', $visit), [
                'petugas_id' => $staff?->id, 'assistance_reason' => 'Memulai pengkajian untuk pengujian alur pelayanan',
            ]);
        }

        return $this;
    }
}
