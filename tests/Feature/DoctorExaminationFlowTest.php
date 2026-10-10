<?php

namespace Tests\Feature;

use App\Models\Diagnosa;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DoctorExaminationFlowTest extends TestCase
{
    use RefreshDatabase;

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
            'jenis_bayar' => 'umum',
        ]);
        $otherVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-002',
            'pasien_id' => $otherPatient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $otherDoctor->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);
        $obat = Obat::create([
            'kode' => 'OBT-001',
            'nama' => 'Paracetamol',
            'satuan_kecil' => 'tablet',
            'stok' => 10,
            'harga_jual' => 1000,
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
            ->has('recentVisits', 1)
        );
        $visit->update(['status' => 'selesai']);
        $this->actingAs($user)->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.visitsToday', 1)
            ->where('stats.completedToday', 1)
            ->has('recentVisits', 1)
            ->where('recentVisits.0.status', 'selesai')
            ->where('recentVisits.0.actionUrl', route('pelayanan.pasien.rekam-medis', $patient))
            ->where('recentVisits.0.actionLabel', 'Lihat RME')
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
        );
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pemeriksaan/show')
            ->where('visit.patient.name', 'Pasien Satu')
            ->where('visit.examination.startedAt', $examination->created_at->locale('id')->isoFormat('D MMMM Y HH:mm'))
            ->where('visit.screening.complaint', 'Demam sejak kemarin')
            ->where('visit.screening.medicalHistory', 'Asma')
            ->where('visit.screening.familyHistory', 'Hipertensi')
            ->where('visit.screening.allergyHistory', 'Alergi udang')
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
            'jenis_bayar' => 'internal',
        ]);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'status' => 'draft']);
        Screening::create(['kunjungan_id' => $visit->id, 'keluhan' => 'Nyeri gigi saat mengunyah']);
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
            ->where('recentVisits.0.number', $visit->no_kunjungan)
            ->where('recentVisits.0.actionUrl', route('pelayanan.pemeriksaan.show', $visit))
            ->where('recentVisits.0.actionLabel', 'Pemeriksaan')
        );
        $this->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page
            ->has('visits.data', 1)
            ->where('visits.data.0.number', $visit->no_kunjungan)
            ->where('visits.data.0.canClaim', true)
        );
        $this->get(route('pelayanan.pemeriksaan.index', ['tanggal' => 'not-a-date']))->assertSessionHasErrors('tanggal');
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.canClaim', true)
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
}
