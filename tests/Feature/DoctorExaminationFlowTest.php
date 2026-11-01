<?php

namespace Tests\Feature;

use App\Models\ClinicalNoteVersion;
use App\Models\Diagnosa;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DoctorExaminationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_examination_keeps_unanswered_optional_history_and_notes_empty(): void
    {
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DOK-DEF', 'nama' => 'dr. Pemeriksa', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-DEF', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-UMUM-DEF', 'nama' => 'Pasien Tanpa Riwayat']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-UMUM-DEF', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
        ]);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.examinationFormDefaults.anamnesis', null)
                ->where('visit.examinationFormDefaults.pastHistory', null)
                ->where('visit.examinationFormDefaults.familyHistory', null)
                ->where('visit.examinationFormDefaults.allergyHistory', null)
            );

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'anamnesis' => 'Demam sejak pagi',
            'pemeriksaan_fisik' => 'Keadaan umum baik',
        ])->assertSessionHasNoErrors();

        $examination = Pemeriksaan::where('kunjungan_id', $visit->id)->firstOrFail();
        $this->assertNull($examination->riwayat_penyakit_sekarang);
        $this->assertNull($examination->riwayat_penyakit_dahulu);
        $this->assertNull($examination->riwayat_penyakit_keluarga);
        $this->assertNull($examination->riwayat_alergi);
        $this->assertNull($examination->catatan);
        $this->assertNull($examination->edukasi);

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.examination.currentHistory', null)
                ->where('visit.examination.allergyHistory', null)
                ->where('visit.examination.notes', null)
            );

        $this->actingAs($user)->get(route('pelayanan.pasien.rekam-medis', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.0.examination.currentHistory', 'Belum dicatat')
                ->where('visits.0.examination.allergyHistory', 'Belum dicatat')
            );
    }

    public function test_doctor_only_sees_assigned_visits_and_does_not_deduct_stock_when_prescribing(): void
    {
        $user = User::create([
            'name' => 'Dokter Umum',
            'email' => 'dokter@klinik.test',
            'password' => 'password',
            'role' => 'dokter',
            'is_active' => true,
        ]);
        $doctor = Nakes::create([
            'kode' => 'DOK-001',
            'nama' => 'dr. Umum',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $otherDoctor = Nakes::create([
            'kode' => 'DOK-002',
            'nama' => 'dr. Gigi',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'is_active' => true,
        ]);
        $poli = Poliklinik::create([
            'kode' => 'UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Satu']);
        $otherPatient = Pasien::create(['no_rm' => 'RM-000002', 'nama' => 'Pasien Dua']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
        ]);
        $otherVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-002',
            'pasien_id' => $otherPatient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $otherDoctor->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
        ]);
        $obat = Obat::create([
            'kode' => 'OBT-001',
            'nama' => 'Paracetamol',
            'satuan_kecil' => 'tablet',
            'stok' => 10,
            'is_active' => true,
        ]);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id]);
        Screening::create([
            'kunjungan_id' => $visit->id,
            'keluhan' => 'Demam sejak kemarin',
            'td_sistole' => 120,
            'td_diastole' => 80,
            'berat_badan' => 68,
            'tinggi_badan' => 172,
            'riwayat_penyakit' => 'Asma',
            'riwayat_penyakit_keluarga' => 'Hipertensi',
            'riwayat_alergi' => 'Alergi udang',
            'risiko_jatuh' => 'rendah',
            'submitted_at' => now(),
            'skala_nyeri' => 2,
        ]);
        Diagnosa::create([
            'pemeriksaan_id' => $examination->id,
            'kode_icd10' => 'R50.9',
            'nama_diagnosa' => 'Demam tidak spesifik',
            'jenis' => 'utama',
        ]);

        $this->actingAs($user)->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/dashboard')
            ->where('auth.user.role', 'dokter')
            ->where('stats.visitsToday', 1)
            ->has('schedule')
            ->has('recentVisits.data', 1)
        );
        $visit->update(['status' => 'selesai']);
        $this->actingAs($user)->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.visitsToday', 1)
            ->where('stats.completedToday', 1)
            ->has('recentVisits.data', 1)
            ->where('recentVisits.data.0.status', 'selesai')
            ->where('recentVisits.data.0.actionUrl', route('pelayanan.pasien.rekam-medis', $patient))
            ->where('recentVisits.data.0.actionLabel', 'Lihat RME')
        );
        $visit->update(['status' => 'pemeriksaan']);
        $this->actingAs($user)->get(route('dokter.janji-kunjungan', [
            'dari' => today()->toDateString(),
            'sampai' => today()->addDay()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/appointments')
            ->where('filters.dari', today()->toDateString())
            ->where('filters.sampai', today()->addDay()->toDateString())
            ->has('visits.data', 1)
            ->where('visits.data.0.number', $visit->no_kunjungan)
            ->where('visits.data.0.recordUrl', route('pelayanan.pasien.rekam-medis', $patient))
        );
        $this->actingAs($user)->get(route('dokter.janji-kunjungan', [
            'dari' => 'invalid-date',
        ]))->assertSessionHasErrors('dari');
        $this->actingAs($user)->get(route('dokter.pasien'))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/patients')
            ->has('patients.data', 1)
            ->where('patients.data.0.medicalRecordNumber', $patient->no_rm)
        );
        $this->actingAs($user)->get(route('dokter.stok-obat'))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/stock')
            ->has('medicines.data', 1)
            ->where('medicines.data.0.code', $obat->kode)
        );
        $this->actingAs($user)->get(route('dokter.laporan-top-diagnosa', [
            'dari' => today()->toDateString(),
            'sampai' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/top-diagnoses')
            ->where('filters.dari', today()->toDateString())
            ->has('diagnoses.data', 1)
            ->where('diagnoses.data.0.code', 'R50.9')
            ->where('diagnoses.data.0.count', 1)
        );
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pemeriksaan/index')
            ->has('visits.data', 1)
            ->where('visits.data.0.number', $visit->no_kunjungan)
            ->where('visits.data.0.diagnosisCount', 1)
            ->where('visits.data.0.recordUrl', route('pelayanan.pasien.rekam-medis', $patient))
        );
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pemeriksaan/show')
            ->where('visit.patient.name', 'Pasien Satu')
            ->where('visit.recordUrl', route('pelayanan.pasien.rekam-medis', $patient))
            ->where('visit.examination.startedAt', $examination->created_at->locale('id')->isoFormat('D MMMM Y HH:mm'))
            ->where('visit.screening.complaint', 'Demam sejak kemarin')
            ->where('visit.screening.medicalHistory', 'Asma')
            ->where('visit.screening.familyHistory', 'Hipertensi')
            ->where('visit.screening.allergyHistory', 'Alergi udang')
            ->where('visit.examinationFormDefaults.anamnesis', 'Demam sejak kemarin')
            ->where('visit.examinationFormDefaults.pastHistory', 'Asma')
            ->where('visit.examinationFormDefaults.familyHistory', 'Hipertensi')
            ->where('visit.examinationFormDefaults.allergyHistory', 'Alergi udang')
            ->where('visit.screening.painScale', 2)
            ->where('visit.diagnoses.0.code', 'R50.9')
            ->where('medicines.0.name', 'Paracetamol')
        );
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $otherVisit))
            ->assertForbidden();
        $this->actingAs($user)->get(route('pelayanan.pasien.rekam-medis', $otherPatient))
            ->assertForbidden();

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'anamnesis' => 'Demam sejak kemarin',
            'riwayat_penyakit_sekarang' => 'Keluhan memberat sejak malam',
            'riwayat_penyakit_dahulu' => 'Asma terkontrol',
            'riwayat_penyakit_keluarga' => 'Hipertensi pada ayah',
            'riwayat_alergi' => 'Alergi udang',
            'pemeriksaan_fisik' => 'Kondisi umum baik',
            'pemeriksaan_fisik_terstruktur' => ['Thorax' => 'Suara napas vesikuler'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pemeriksaan', [
            'kunjungan_id' => $visit->id,
            'anamnesis' => 'Demam sejak kemarin',
            'riwayat_penyakit_sekarang' => 'Keluhan memberat sejak malam',
            'riwayat_penyakit_dahulu' => 'Asma terkontrol',
        ]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'R05',
            'nama_diagnosa' => 'Batuk',
            'jenis' => 'tambahan',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('diagnosa', ['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R05']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $obat->id,
            'jumlah' => 0.5,
            'jenis' => 'jadi',
        ])->assertSessionHasErrors('jumlah');
        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'stok' => 10]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $obat->id,
            'jumlah' => 3,
            'aturan_pakai' => '3 x 1',
            'jenis' => 'jadi',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'stok' => 10]);
        $this->assertDatabaseHas('resep_obat', ['obat_id' => $obat->id, 'stok_dikurangi' => false]);
        $this->assertDatabaseHas('resep_obat', ['obat_id' => $obat->id, 'catatan' => null]);

        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'nama_obat' => 'Obat luar sesuai instruksi dokter',
            'jumlah' => 1,
            'satuan' => 'tablet',
            'aturan_pakai' => '1 x 1 setelah makan',
            'jenis' => 'jadi',
            'is_resep_luar' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('resep_obat', [
            'obat_id' => null,
            'nama_obat' => 'Obat luar sesuai instruksi dokter',
            'is_resep_luar' => true,
            'stok_dikurangi' => false,
        ]);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect();
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'farmasi']);

        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $obat->id,
            'jumlah' => 2,
            'jenis' => 'jadi',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'stok' => 10]);
        $this->assertDatabaseCount('resep_obat', 2);
    }

    public function test_doctor_can_claim_an_unassigned_visit_only_when_scheduled_for_that_clinic_and_date(): void
    {
        $this->travelTo('2026-10-09 10:00:00');
        $user = User::factory()->create(['name' => 'Dokter Gigi', 'role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DOK-CLAIM',
            'nama' => 'drg. Terjadwal',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $otherUser = User::factory()->create(['name' => 'Dokter Lain', 'role' => 'dokter', 'is_active' => true]);
        $otherDoctor = Nakes::create([
            'kode' => 'DOK-OTHER',
            'nama' => 'drg. Tidak Terjadwal',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $otherUser->id,
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'GIGI', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $catalogTreatment = Tindakan::create([
            'kode' => 'TIN-CLAIM',
            'nama' => 'Konsultasi gigi',
            'poliklinik_id' => $clinic->id,
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-CLAIM', 'nama' => 'Pasien Belum Ditugaskan']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-CLAIM',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => '2026-10-09',
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
        ]);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'status' => 'draft']);
        Screening::create(['kunjungan_id' => $visit->id, 'keluhan' => 'Nyeri gigi saat mengunyah', 'submitted_at' => now()]);
        JadwalDokter::create([
            'dokter_id' => $doctor->id,
            'poliklinik_id' => $clinic->id,
            'hari' => 'jumat',
            'berlaku_mulai' => '2026-10-01',
            'berlaku_sampai' => '2026-10-31',
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.visitsToday', 1)
            ->where('stats.waitingExaminations', 1)
            ->where('recentVisits.data.0.number', $visit->no_kunjungan)
            ->where('recentVisits.data.0.actionUrl', route('pelayanan.pemeriksaan.show', $visit))
            ->where('recentVisits.data.0.actionLabel', 'Lanjutkan pemeriksaan')
            ->where('recentVisits.data.0.examinationStarted', true)
        );
        $this->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page
            ->has('visits.data', 1)
            ->where('visits.data.0.number', $visit->no_kunjungan)
            ->where('visits.data.0.canClaim', true)
            ->where('visits.data.0.recordUrl', null)
        );
        $this->get(route('pelayanan.pemeriksaan.index', ['tanggal' => 'not-a-date']))->assertSessionHasErrors('tanggal');
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.canClaim', true)
            ->where('visit.recordUrl', null)
            ->where('visit.doctorId', null)
            ->where('visit.screening.complaint', 'Nyeri gigi saat mengunyah')
        );
        $this->post(route('pelayanan.pemeriksaan.store', $visit), [
            'anamnesis' => 'Belum boleh disimpan sebelum penugasan',
        ])->assertConflict();
        $this->assertDatabaseHas('pemeriksaan', ['id' => $examination->id, 'dokter_id' => null, 'anamnesis' => null]);
        $this->post(route('pelayanan.pemeriksaan.claim', $visit))->assertRedirect();

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'dokter_id' => $doctor->id]);
        $this->assertDatabaseHas('pemeriksaan', ['id' => $examination->id, 'dokter_id' => $doctor->id]);
        $this->assertDatabaseHas('clinical_audit_events', [
            'kunjungan_id' => $visit->id,
            'actor_id' => $user->id,
            'action' => 'clinical_examination.visit_claim',
        ]);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.canClaim', false)
            ->where('visit.recordUrl', route('pelayanan.pasien.rekam-medis', $patient))
            ->where('visit.doctorId', $doctor->id)
        );

        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'nama_tindakan_manual' => 'Pembersihan karang gigi',
            'jumlah' => 1,
            'tooth_fdi' => '46',
            'catatan' => 'Kalkulus supragingiva di regio posterior.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', [
            'kunjungan_id' => $visit->id,
            'tindakan_id' => null,
            'nama_tindakan_manual' => 'Pembersihan karang gigi',
            'tooth_fdi' => '46',
            'catatan' => 'Kalkulus supragingiva di regio posterior.',
        ]);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.treatments.0.name', 'Pembersihan karang gigi')
            ->where('visit.treatments.0.note', 'Kalkulus supragingiva di regio posterior.')
        );

        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'tindakan_id' => $catalogTreatment->id,
            'nama_tindakan_manual' => 'Tindakan ganda',
            'jumlah' => 1,
        ])->assertSessionHasErrors('tindakan_id');
        $this->assertDatabaseCount('tindakan_kunjungan', 1);

        $this->actingAs($otherUser)->post(route('pelayanan.pemeriksaan.claim', $visit))->assertConflict();
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertForbidden();
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'dokter_id' => $doctor->id]);
    }

    #[TestWith(['umum', 'anamnesis'])]
    #[TestWith(['gigi', 'anamnesis'])]
    #[TestWith(['umum', 'pemeriksaan'])]
    #[TestWith(['gigi', 'pemeriksaan'])]
    #[TestWith(['umum', 'diagnosis'])]
    #[TestWith(['gigi', 'diagnosis'])]
    #[TestWith(['umum', 'rencana'])]
    #[TestWith(['gigi', 'rencana'])]
    public function test_saving_a_clinical_section_preserves_other_draft_sections(string $clinicType, string $section): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections($clinicType);
        $payloads = [
            'anamnesis' => ['anamnesis' => 'Keluhan diperbarui', 'pemeriksaan_fisik' => null, 'diagnosis_banding' => null, 'kontrol_berikutnya' => null],
            'pemeriksaan' => ['pemeriksaan_fisik' => 'Temuan diperbarui', 'anamnesis' => null, 'riwayat_alergi' => 'Tidak ada alergi', 'diagnosis_banding' => null, 'catatan' => null],
            'diagnosis' => ['diagnosis_banding' => 'Periodontitis', 'anamnesis' => null, 'pemeriksaan_fisik' => null, 'riwayat_alergi' => 'Tidak ada alergi', 'edukasi' => null],
            'rencana' => ['kontrol_berikutnya' => '2026-11-03', 'catatan' => 'Evaluasi ulang', 'edukasi' => 'Anjuran diperbarui', 'anamnesis' => null, 'pemeriksaan_fisik' => null, 'riwayat_alergi' => 'Tidak ada alergi'],
        ];
        $expectedChanges = [
            'anamnesis' => ['anamnesis' => 'Keluhan diperbarui'],
            'pemeriksaan' => ['pemeriksaan_fisik' => 'Temuan diperbarui'],
            'diagnosis' => ['diagnosis_banding' => 'Periodontitis'],
            'rencana' => ['kontrol_berikutnya' => '2026-11-03', 'catatan' => 'Evaluasi ulang', 'edukasi' => 'Anjuran diperbarui'],
        ];

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => $section, 'oral_hygiene_index' => 5, ...$payloads[$section],
        ])->assertSessionHasNoErrors();

        $expected = array_replace([
            'id' => $examination->id, 'anamnesis' => 'Keluhan awal', 'pemeriksaan_fisik' => 'Temuan awal',
            'diagnosis_banding' => 'Diagnosis banding awal', 'riwayat_alergi' => 'Penisilin',
            'kontrol_berikutnya' => '2026-11-01', 'catatan' => 'Catatan awal', 'edukasi' => 'Edukasi awal',
            'oral_hygiene_index' => 1.25,
        ], $expectedChanges[$section]);
        $expectedDate = $expected['kontrol_berikutnya'];
        unset($expected['kontrol_berikutnya']);
        $this->assertDatabaseHas('pemeriksaan', $expected);
        $this->assertSame($expectedDate, $examination->fresh()->kontrol_berikutnya?->toDateString());
    }

    public function test_clearing_follow_up_date_does_not_clear_anamnesis_or_allergy(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('gigi');

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'rencana', 'kontrol_berikutnya' => '', 'catatan' => '', 'edukasi' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pemeriksaan', [
            'id' => $examination->id, 'kontrol_berikutnya' => null, 'catatan' => null, 'edukasi' => null,
            'anamnesis' => 'Keluhan awal', 'pemeriksaan_fisik' => 'Temuan awal', 'riwayat_alergi' => 'Penisilin',
        ]);
    }

    public function test_invalid_follow_up_date_leaves_existing_plan_unchanged(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('umum');

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'rencana', 'kontrol_berikutnya' => '2026-02-30', 'edukasi' => 'Isian baru',
        ])->assertSessionHasErrors('kontrol_berikutnya');

        $this->assertDatabaseHas('pemeriksaan', ['id' => $examination->id, 'edukasi' => 'Edukasi awal']);
        $this->assertSame('2026-11-01', $examination->fresh()->kontrol_berikutnya?->toDateString());
    }

    public function test_unknown_section_cannot_overwrite_a_draft_examination(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('umum');

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'tidak-dikenal', 'anamnesis' => 'Ganti catatan',
        ])->assertSessionHasErrors('section');

        $this->assertDatabaseHas('pemeriksaan', ['id' => $examination->id, 'anamnesis' => 'Keluhan awal']);
    }

    #[TestWith(['umum', 'Belum dicatat'])]
    #[TestWith(['gigi', 'Belum di catat'])]
    #[TestWith(['umum', ' BELUM   DIISI. '])]
    #[TestWith(['gigi', 'Belum dikaji'])]
    public function test_unknown_history_stays_empty_in_doctor_form_and_is_not_recorded_as_absent(string $clinicType, string $placeholder): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections($clinicType);
        $visit->pasien->update(['riwayat_alergi' => $placeholder]);
        $examination->update([
            'riwayat_penyakit_sekarang' => $placeholder, 'riwayat_penyakit_dahulu' => $placeholder,
            'riwayat_penyakit_keluarga' => $placeholder, 'riwayat_alergi' => $placeholder,
        ]);
        Screening::where('kunjungan_id', $visit->id)->update([
            'riwayat_penyakit' => $placeholder,
            'riwayat_penyakit_keluarga' => $placeholder, 'riwayat_alergi' => $placeholder,
        ]);

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.currentHistory', null)
            ->where('visit.examinationFormDefaults.pastHistory', null)
            ->where('visit.examinationFormDefaults.familyHistory', null)
            ->where('visit.examinationFormDefaults.allergyHistory', null)
        );
        $this->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'anamnesis', 'anamnesis' => 'Keluhan masih dirasakan',
            'riwayat_penyakit_sekarang' => $placeholder, 'riwayat_penyakit_dahulu' => '',
            'riwayat_penyakit_keluarga' => '', 'riwayat_alergi' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pemeriksaan', [
            'id' => $examination->id, 'riwayat_penyakit_sekarang' => null, 'riwayat_penyakit_dahulu' => null,
            'riwayat_penyakit_keluarga' => null, 'riwayat_alergi' => null,
        ]);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.currentHistory', 'Belum dicatat')
            ->where('visits.0.examination.pastHistory', 'Belum dicatat')
            ->where('visits.0.examination.familyHistory', 'Belum dicatat')
            ->where('visits.0.examination.allergyHistory', 'Belum dicatat')
        );
    }

    public function test_real_nursing_history_is_carried_forward_despite_legacy_doctor_placeholders(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('gigi');
        $examination->update(['riwayat_penyakit_dahulu' => 'Belum dicatat', 'riwayat_penyakit_keluarga' => 'Belum dicatat']);
        Screening::where('kunjungan_id', $visit->id)->update(['riwayat_penyakit' => 'Diabetes', 'riwayat_penyakit_keluarga' => 'Hipertensi pada ayah']);

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.pastHistory', 'Diabetes')
            ->where('visit.examinationFormDefaults.familyHistory', 'Hipertensi pada ayah')
        );
    }

    public function test_explicitly_confirmed_absent_histories_are_preserved_in_signed_medical_record(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('gigi');
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        OdontogramFinding::create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'recorded_by_user_id' => $user->id]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'anamnesis', 'anamnesis' => 'Nyeri gigi',
            'riwayat_penyakit_dahulu' => 'Tidak ada riwayat penyakit dahulu menurut pasien',
            'riwayat_penyakit_keluarga' => 'Tidak ada riwayat penyakit keluarga menurut pasien',
            'riwayat_alergi' => 'Tidak ada riwayat alergi menurut pasien',
        ])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect();

        $payload = ClinicalNoteVersion::sole()->payload;
        $this->assertSame('Tidak ada riwayat penyakit dahulu menurut pasien', $payload['riwayat_penyakit_dahulu']);
        $this->assertSame('Tidak ada riwayat penyakit keluarga menurut pasien', $payload['riwayat_penyakit_keluarga']);
        $this->assertSame('Tidak ada riwayat alergi menurut pasien', $payload['riwayat_alergi']);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.familyHistory', 'Tidak ada riwayat penyakit keluarga menurut pasien')
            ->where('visits.0.examination.allergyHistory', 'Tidak ada riwayat alergi menurut pasien')
        );
    }

    public function test_blank_doctor_allergy_preserves_current_history_and_patient_master_until_finalization(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('umum');

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'anamnesis', 'anamnesis' => 'Keluhan awal', 'riwayat_alergi' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pemeriksaan', ['id' => $examination->id, 'riwayat_alergi' => 'Penisilin']);
        $this->assertDatabaseHas('pasien', ['id' => $visit->pasien_id, 'riwayat_alergi' => 'Udang']);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
            ->where('patient.allergies', 'Udang')
            ->where('visits.0.examination.allergyHistory', 'Penisilin')
        );
    }

    #[TestWith(['anamnesis'])]
    #[TestWith(['pemeriksaan_fisik'])]
    public function test_placeholder_is_not_sufficient_clinical_content_for_finalization(string $field): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('umum');
        $examination->update([$field => 'Belum dicatat']);
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R50.9', 'nama_diagnosa' => 'Demam', 'jenis' => 'utama']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertUnprocessable();

        $this->assertDatabaseMissing('clinical_note_versions', ['pemeriksaan_id' => $examination->id]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'pemeriksaan']);
    }

    public function test_final_snapshot_keeps_legacy_unknown_histories_null_and_preserves_known_patient_allergy(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('umum');
        $examination->update([
            'riwayat_penyakit_sekarang' => 'Belum diisi', 'riwayat_penyakit_dahulu' => 'Belum di catat',
            'riwayat_penyakit_keluarga' => 'Belum dicatat', 'riwayat_alergi' => 'Belum diketahui',
        ]);
        Screening::where('kunjungan_id', $visit->id)->update(['riwayat_penyakit_keluarga' => 'Hipertensi pada ayah']);
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R50.9', 'nama_diagnosa' => 'Demam', 'jenis' => 'utama']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect();

        $payload = ClinicalNoteVersion::sole()->payload;
        $this->assertNull($payload['riwayat_penyakit_sekarang']);
        $this->assertNull($payload['riwayat_penyakit_dahulu']);
        $this->assertNull($payload['riwayat_penyakit_keluarga']);
        $this->assertNull($payload['riwayat_alergi']);
        $this->assertDatabaseHas('pasien', ['id' => $visit->pasien_id, 'riwayat_alergi' => 'Udang']);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.familyHistory', null)
            ->where('visit.examinationFormDefaults.allergyHistory', null)
            ->where('visit.screening.familyHistory', 'Hipertensi pada ayah')
        );
    }

    #[TestWith(['umum', 'menunggu', 'Selesaikan pengkajian awal sebelum mencatat pemeriksaan dokter.'])]
    #[TestWith(['gigi', 'screening', 'Selesaikan pengkajian awal sebelum mencatat pemeriksaan dokter.'])]
    #[TestWith(['umum', 'batal', 'Kunjungan dibatalkan dan tidak dapat dilanjutkan.'])]
    public function test_blocked_examination_explains_the_actual_visit_stage(string $clinicType, string $status, string $message): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections($clinicType);
        $visit->update(['status' => $status]);

        $this->actingAs($user)->postJson(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'anamnesis', 'anamnesis' => 'Tidak boleh tersimpan',
        ])->assertUnprocessable()->assertJsonPath('message', $message);
        $this->assertSame('Keluhan awal', $examination->fresh()->anamnesis);
    }

    public function test_signed_examination_cannot_be_overwritten_even_if_its_status_is_still_draft(): void
    {
        [$user, $visit, $examination] = $this->examinationWithSections('gigi');
        $examination->forceFill(['status' => 'draft', 'signed_at' => now()])->save();

        $this->actingAs($user)->putJson(route('pelayanan.pemeriksaan.update', $visit), [
            'section' => 'anamnesis', 'anamnesis' => 'Tidak boleh menimpa catatan bertanda tangan',
        ])->assertUnprocessable()->assertJsonPath('message', 'Pemeriksaan telah difinalisasi dan tidak dapat diubah langsung.');
        $this->assertSame('Keluhan awal', $examination->fresh()->anamnesis);
    }

    public function test_future_dental_reservation_has_context_and_its_own_patient_record_link(): void
    {
        [$user, $visit] = $this->examinationWithSections('gigi');
        $visit->update(['tanggal' => today()->addDay(), 'status' => 'menunggu']);

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.isReservation', true)
            ->where('visit.status', 'menunggu')
            ->where('visit.recordUrl', route('pelayanan.pasien.rekam-medis', $visit->pasien_id))
        );
    }

    /**
     * @return array{User, Kunjungan, Pemeriksaan}
     */
    private function examinationWithSections(string $clinicType): array
    {
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DOK-SECTION', 'nama' => 'Dokter Pemeriksa', 'kategori' => 'medis', 'jabatan' => 'dokter',
            'user_id' => $user->id, 'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'POLI-SECTION', 'nama' => 'Poli Pemeriksaan', 'jenis' => $clinicType, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SECTION', 'nama' => 'Pasien Pemeriksaan', 'riwayat_alergi' => 'Udang']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-SECTION', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);
        $examination = Pemeriksaan::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'anamnesis' => 'Keluhan awal', 'pemeriksaan_fisik' => 'Temuan awal', 'riwayat_alergi' => 'Penisilin',
            'diagnosis_banding' => 'Diagnosis banding awal', 'kontrol_berikutnya' => '2026-11-01',
            'catatan' => 'Catatan awal', 'edukasi' => 'Edukasi awal', 'oral_hygiene_index' => 1.25,
        ]);

        return [$user, $visit, $examination];
    }
}
