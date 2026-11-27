<?php

namespace Tests\Feature;

use App\ClinicalWorkflowSettings;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClinicalWorkflowSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function createClinicFixtures(string $jenis = 'umum'): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $poli = Poliklinik::create([
            'kode' => $jenis === 'gigi' ? 'GIGI' : 'UMUM',
            'nama' => $jenis === 'gigi' ? 'Poli Gigi dan Mulut' : 'Poli Umum',
            'jenis' => $jenis,
            'is_active' => true,
        ]);

        $nurseUser = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $nurse = Nakes::create([
            'kode' => 'PRW-WF-'.strtoupper($jenis),
            'user_id' => $nurseUser->id,
            'poliklinik_id' => $poli->id,
            'nama' => 'Ns. Rina Pratama',
            'kategori' => 'medis',
            'jabatan' => 'perawat',
            'is_active' => true,
        ]);

        $doctorUser = User::factory()->create([
            'role' => 'dokter',
            'is_active' => true,
            'password' => Hash::make('secret-pass'),
        ]);
        $doctor = Nakes::create([
            'kode' => 'DOK-WF-'.strtoupper($jenis),
            'user_id' => $doctorUser->id,
            'poliklinik_id' => $poli->id,
            'nama' => 'dr. Budi Santoso',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'is_active' => true,
        ]);

        $patient = Pasien::create([
            'no_rm' => 'RM-WF-'.strtoupper($jenis).'-001',
            'nama' => 'Pasien Uji Alur',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => now()->subYears(32)->toDateString(),
        ]);

        $visit = Kunjungan::create([
            'no_kunjungan' => 'KJ-WF-'.strtoupper($jenis).'-001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
            'status' => 'screening',
            'jenis_pasien' => 'lama',
            'no_antrian' => 1,
        ]);

        return compact('admin', 'poli', 'nurseUser', 'nurse', 'doctorUser', 'doctor', 'patient', 'visit');
    }

    public function test_default_workflow_removes_redundant_doctor_complaint_forms_and_keeps_ros_on_doctor(): void
    {
        $defaults = ClinicalWorkflowSettings::current();

        $this->assertSame('nonaktif', $defaults['umum_complaint_details']);
        $this->assertSame('dokter', $defaults['umum_system_review']);
        $this->assertSame('perawat', $defaults['umum_nutrition_needs']);
        $this->assertSame('ringkas', $defaults['umum_anthropometry_mode']);
        $this->assertSame('nonaktif', $defaults['gigi_pain_details']);
        $this->assertSame('perawat', $defaults['gigi_mucosa_observation']);
        $this->assertFalse($defaults['dokter_soap_tab']);
        $this->assertTrue($defaults['dokter_repeat_vitals']);

        // Legacy stored values safely map to the streamlined options
        $legacyResolved = ClinicalWorkflowSettings::resolve([
            'umum_complaint_details' => 'dokter',
            'gigi_pain_details' => 'keduanya',
        ]);
        $this->assertSame('nonaktif', $legacyResolved['umum_complaint_details']);
        $this->assertSame('perawat', $legacyResolved['gigi_pain_details']);
    }

    public function test_admin_can_view_and_update_clinical_workflow_with_audit_trail(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);

        $this->actingAs($admin)->get(route('setting.index', ['tab' => 'alur-klinis']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('setting/index')
                ->where('initialTab', 'alur-klinis')
                ->where('clinicalWorkflow.umum_complaint_details', 'nonaktif')
                ->where('clinicalWorkflow.umum_system_review', 'dokter')
                ->where('clinicalWorkflow.gigi_pain_details', 'nonaktif')
                ->where('clinicalWorkflow.dokter_soap_tab', false)
            );

        $payload = [
            'pelaksana_ttv' => 'perawat',
            'clinical_workflow' => [
                'umum_complaint_details' => 'perawat',
                'umum_system_review' => 'dokter',
                'umum_nutrition_needs' => 'nonaktif',
                'umum_anthropometry_mode' => 'lengkap',
                'gigi_pain_details' => 'perawat',
                'gigi_mucosa_observation' => 'dokter',
                'gigi_additional_assessment' => 'nonaktif',
                'dokter_soap_tab' => true,
                'dokter_repeat_vitals' => false,
            ],
        ];

        $this->actingAs($nurse)->put(route('setting.clinical-workflow.update'), $payload)->assertForbidden();

        $this->actingAs($admin)->put(route('setting.clinical-workflow.update'), $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $saved = KlinikSetting::clinicalWorkflow();
        $this->assertSame('perawat', $saved['umum_complaint_details']);
        $this->assertSame('nonaktif', $saved['umum_nutrition_needs']);
        $this->assertSame('lengkap', $saved['umum_anthropometry_mode']);
        $this->assertSame('perawat', $saved['gigi_pain_details']);
        $this->assertSame('dokter', $saved['gigi_mucosa_observation']);
        $this->assertTrue($saved['dokter_soap_tab']);
        $this->assertFalse($saved['dokter_repeat_vitals']);

        // Admin can also disable ROS and dental mucosa observation for both nurse and doctor
        $disablePayload = $payload;
        $disablePayload['clinical_workflow']['umum_system_review'] = 'nonaktif';
        $disablePayload['clinical_workflow']['gigi_mucosa_observation'] = 'nonaktif';
        $this->actingAs($admin)->put(route('setting.clinical-workflow.update'), $disablePayload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $disabled = KlinikSetting::clinicalWorkflow();
        $this->assertSame('nonaktif', $disabled['umum_system_review']);
        $this->assertSame('nonaktif', $disabled['gigi_mucosa_observation']);

        $this->assertDatabaseHas('clinical_audit_events', [
            'actor_id' => $admin->id,
            'action' => 'clinic_setting.clinical_workflow_updated',
        ]);
    }

    public function test_general_nurse_submits_core_screening_and_doctor_captures_complaint_details_and_ros(): void
    {
        ['nurseUser' => $nurseUser, 'nurse' => $nurse, 'doctorUser' => $doctorUser, 'doctor' => $doctor, 'patient' => $patient, 'visit' => $visit] = $this->createClinicFixtures('umum');

        $this->actingAs($nurseUser)->get(route('pelayanan.screening.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/screening/show')
                ->where('workflowSettings.umum_complaint_details', 'nonaktif')
                ->where('workflowSettings.umum_system_review', 'dokter')
            );

        $this->actingAs($nurseUser)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), [
            'tindakan' => 'kirim',
            'keluhan' => 'Demam dan batuk sejak dua hari lalu',
            'lama_keluhan' => '2 hari',
            'sumber_informasi' => 'pasien',
            'status_alergi' => 'tidak_ada',
            'status_riwayat_penyakit' => 'tidak_ada',
            'konfirmasi_riwayat' => true,
            'td_sistole' => 120,
            'td_diastole' => 80,
            'nadi' => 82,
            'suhu' => 37.8,
            'respirasi' => 18,
            'spo2' => 98,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $visit->refresh();
        $this->assertSame('pemeriksaan', $visit->status);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.start', $visit))->assertRedirect();

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'anamnesis',
            'anamnesis' => 'Demam dan batuk berdahak',
            'riwayat_penyakit_sekarang' => 'Batuk berdahak jernih sejak dua hari lalu, memburuk malam hari.',
            'clinical_assessment' => [
                'complaint_details' => [
                    'location' => 'Saluran napas atas',
                    'character' => 'Batuk berdahak',
                    'pattern' => 'Memberat malam hari',
                    'triggers' => 'Udara dingin',
                    'relief' => 'Minum air hangat',
                    'progression' => 'menetap',
                    'pain_scale' => 2,
                ],
            ],
            'review_of_systems' => [
                'respiratory' => ['status' => 'positive', 'notes' => 'Batuk berdahak tanpa sesak'],
                'cardiovascular' => ['status' => 'negative', 'notes' => ''],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $exam = Pemeriksaan::where('kunjungan_id', $visit->id)->firstOrFail();
        $this->assertSame('Saluran napas atas', $exam->clinical_assessment['complaint_details']['location']);
        $this->assertSame(2, $exam->clinical_assessment['complaint_details']['pain_scale']);
        $this->assertSame('positive', $exam->review_of_systems['respiratory']['status']);

        $this->actingAs($doctorUser)->get(route('pelayanan.pasien.rekam-medis', $patient))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/pasien/rekam-medis')
                ->where('visits.0.examination.generalClinicalSummary.Rincian keluhan (dokter) - Lokasi', 'Saluran napas atas')
                ->where('visits.0.examination.generalClinicalSummary.Skala nyeri (dokter)', '2 / 10')
                ->where('visits.0.examination.generalClinicalSummary.Respirasi', 'Positif / abnormal — Batuk berdahak tanpa sesak')
            );
    }

    public function test_dental_doctor_can_save_pain_and_mucosa_across_separate_sections_without_overwriting(): void
    {
        ['doctorUser' => $doctorUser, 'doctor' => $doctor, 'nurse' => $nurse, 'visit' => $visit] = $this->createClinicFixtures('gigi');

        Screening::create([
            'kunjungan_id' => $visit->id,
            'petugas_id' => $nurse->id,
            'keluhan' => 'Nyeri geraham kanan bawah',
            'lama_keluhan' => '3 hari',
            'sumber_informasi' => 'pasien',
            'status_alergi' => 'tidak_ada',
            'status_riwayat_penyakit' => 'tidak_ada',
            'td_sistole' => 118,
            'td_diastole' => 76,
            'nadi' => 76,
            'suhu' => 36.6,
            'respirasi' => 18,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now()->subMinutes(5),
        ]);
        $visit->update(['status' => 'pemeriksaan']);
        Pemeriksaan::create([
            'kunjungan_id' => $visit->id,
            'dokter_id' => $doctor->id,
            'status' => 'draft',
            'started_at' => now(),
        ]);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'anamnesis',
            'anamnesis' => 'Nyeri geraham bawah kanan',
            'clinical_assessment' => [
                'dental_pain' => [
                    'location' => 'Gigi 46',
                    'pain_scale' => 6,
                    'triggers' => ['dingin', 'mengunyah'],
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'pemeriksaan',
            'pemeriksaan_fisik' => 'Karies profunda gigi 46',
            'pemeriksaan_ekstraoral' => 'Simetris, tidak ada edema',
            'clinical_assessment' => [
                'mucosa_observation' => [
                    'lidah' => 'normal',
                    'gusi' => 'abnormal',
                    'catatan' => ['gusi' => 'Gingiva sekitar gigi 46 kemerahan'],
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $exam = Pemeriksaan::where('kunjungan_id', $visit->id)->firstOrFail();
        $this->assertSame('Gigi 46', $exam->clinical_assessment['dental_pain']['location']);
        $this->assertSame(6, $exam->clinical_assessment['dental_pain']['pain_scale']);
        $this->assertSame('abnormal', $exam->clinical_assessment['mucosa_observation']['gusi']);
        $this->assertSame('Gingiva sekitar gigi 46 kemerahan', $exam->clinical_assessment['mucosa_observation']['catatan']['gusi']);
    }

    public function test_historical_nurse_system_assessment_is_preserved_even_when_workflow_moves_to_doctor(): void
    {
        ['nurseUser' => $nurseUser, 'nurse' => $nurse, 'visit' => $visit] = $this->createClinicFixtures('umum');

        $screening = Screening::create([
            'kunjungan_id' => $visit->id,
            'petugas_id' => $nurse->id,
            'keluhan' => 'Sesak napas',
            'lama_keluhan' => '1 hari',
            'sumber_informasi' => 'pasien',
            'status_alergi' => 'tidak_ada',
            'status_riwayat_penyakit' => 'tidak_ada',
            'td_sistole' => 125,
            'td_diastole' => 80,
            'nadi' => 88,
            'suhu' => 36.9,
            'respirasi' => 22,
            'started_at' => now()->subMinutes(15),
            'general_assessment' => [
                'systems' => [
                    'respiratory' => ['status' => 'positive', 'notes' => 'Mengi ringan'],
                ],
            ],
        ]);

        // Even if workflow defaults to 'dokter' and payload omits systems, historical systems stay intact
        $this->actingAs($nurseUser)->post(route('pelayanan.screening.store', $visit), [
            'tindakan' => 'draf',
            'keluhan' => 'Sesak napas sejak pagi',
            'general_assessment' => [
                'general_condition' => 'cukup',
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $screening->refresh();
        $this->assertSame('Mengi ringan', $screening->general_assessment['systems']['respiratory']['notes']);
    }
}
