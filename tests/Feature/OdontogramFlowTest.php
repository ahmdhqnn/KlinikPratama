<?php

namespace Tests\Feature;

use App\Models\ClinicalNoteVersion;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OdontogramFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dental_findings_and_tooth_location_are_versioned_after_finalization(): void
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DOK-GIGI-UAT', 'nama' => 'drg. UAT', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'GIGI', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-UAT', 'nama' => 'Pasien Gigi UAT']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-GIGI-UAT', 'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        $examination = Pemeriksaan::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'anamnesis' => 'Nyeri geraham kanan bawah',
            'pemeriksaan_fisik' => 'Karies oklusal gigi 46',
            'pemeriksaan_ekstraoral' => 'Tidak ada pembengkakan wajah',
            'oral_hygiene_index' => 1.25,
        ]);
        Screening::create([
            'kunjungan_id' => $visit->id,
            'keluhan' => 'Sakit gigi saat makan manis',
            'lokasi_nyeri_gigi' => 'Geraham kanan bawah',
            'pemicu_nyeri_gigi' => ['manis', 'dingin'],
            'durasi_keluhan_gigi' => '3 hari',
            'risiko_medis_gigi' => ['diabetes', 'hipertensi'],
            'riwayat_infeksi_gigi' => ['hepatitis'],
        ]);
        Diagnosa::create([
            'pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9',
            'nama_diagnosa' => 'Karies gigi', 'jenis' => 'utama',
        ]);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '19', 'surface' => 'O', 'finding_code' => 'caries',
        ])->assertSessionHasErrors('tooth_fdi');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'missing',
        ])->assertSessionHasErrors('surface');

        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'notes' => 'Kavitas kecil',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('odontogram_findings', [
            'kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries',
        ]);

        $treatment = Tindakan::create([
            'kode' => 'TDK-GIGI-UAT', 'nama' => 'Restorasi gigi', 'kategori' => 'medis',
            'poliklinik_id' => $clinic->id, 'tarif' => 50000, 'is_active' => true,
        ]);
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'tindakan_id' => $treatment->id, 'jumlah' => 1, 'tooth_fdi' => '46',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46']);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect();
        $final = ClinicalNoteVersion::where('pemeriksaan_id', $examination->id)->sole();
        $this->assertSame('46', $final->payload['odontogram'][0]['tooth_fdi']);
        $this->assertSame('caries', $final->payload['odontogram'][0]['finding_code']);
        $this->assertSame('46', $final->payload['treatments'][0]['tooth_fdi']);

        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'restored',
        ])->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), [
            'reason' => 'Koreksi setelah evaluasi ulang', 'tooth_fdi' => '46',
            'surface' => 'O', 'finding_code' => 'restored', 'notes' => 'Telah direstorasi',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'finding_code' => 'caries']);
        $this->assertDatabaseCount('clinical_note_versions', 2);
        $addendum = ClinicalNoteVersion::where('version', 2)->sole();
        $this->assertSame('restored', $addendum->payload['odontogram'][0]['finding_code']);
        $this->assertSame($final->content_hash, $addendum->previous_hash);

        $otherDoctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        Nakes::create([
            'kode' => 'DOK-GIGI-LAIN', 'nama' => 'drg. Lain', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $otherDoctorUser->id, 'is_active' => true,
        ]);
        $unauthorizedAddendum = [
            'reason' => 'Koreksi tanpa penugasan klinis', 'tooth_fdi' => '46',
            'surface' => 'O', 'finding_code' => 'caries',
        ];
        $this->actingAs($otherDoctorUser)->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), $unauthorizedAddendum)->assertForbidden();
        $adminUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($adminUser)->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), $unauthorizedAddendum)->assertForbidden();
        $this->actingAs($doctorUser);

        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.odontogram.0.toothFdi', '46')
            ->where('visit.odontogram.0.findingCode', 'restored')
            ->where('visit.screening.dentalPainLocation', 'Geraham kanan bawah')
            ->where('visit.screening.dentalMedicalRisks.0', 'diabetes')
            ->where('visit.examination.extraoralExam', 'Tidak ada pembengkakan wajah')
            ->where('visit.examination.oralHygieneIndex', 1.25)
            ->where('visit.examination.versions.0.odontogram.0.findingCode', 'caries')
            ->where('visit.examination.versions.1.odontogram.0.findingCode', 'restored')
        );
        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertInertia(fn (Assert $page) => $page
            ->where('patient.age', null)
            ->where('visits.0.clinicType', 'gigi')
            ->where('visits.0.odontogram.0.findingCode', 'restored')
            ->where('visits.0.examination.versions.0.odontogram.0.findingCode', 'caries')
            ->where('visits.0.treatments.0.toothFdi', '46')
        );

        $otherClinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $otherVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-UMUM-UAT', 'pasien_id' => $patient->id,
            'poliklinik_id' => $otherClinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $otherVisit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries',
        ])->assertUnprocessable();

        $this->assertSame(1, OdontogramFinding::count());
    }
}
