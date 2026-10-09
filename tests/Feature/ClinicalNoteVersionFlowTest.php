<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\ClinicalNoteVersion;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClinicalNoteVersionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalization_requires_clinical_content_and_addendum_preserves_signed_note(): void
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DOK-VERSION', 'nama' => 'dr. Versi', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-VERSION-1', 'nama' => 'Pasien Versi']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-VERSION-1', 'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id]);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.selesai', $visit))->assertUnprocessable();
        $this->assertDatabaseCount('clinical_note_versions', 0);

        $examination->update(['anamnesis' => 'Demam dua hari', 'pemeriksaan_fisik' => 'Suhu 38 C']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit))->assertUnprocessable();
        $this->assertDatabaseCount('clinical_note_versions', 0);

        Diagnosa::create([
            'pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R50.9',
            'nama_diagnosa' => 'Demam, tidak spesifik', 'jenis' => 'utama',
        ]);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit))->assertRedirect(route('pelayanan.pemeriksaan.index'));
        $this->assertDatabaseHas('pemeriksaan', [
            'id' => $examination->id, 'status' => 'selesai', 'signed_by_user_id' => $doctorUser->id,
            'anamnesis' => 'Demam dua hari',
        ]);
        $this->assertNotNull($examination->fresh()->signed_at);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'selesai']);

        $final = ClinicalNoteVersion::sole();
        $this->assertSame('finalized', $final->kind);
        $this->assertSame(1, $final->version);
        $this->assertSame('Demam dua hari', $final->payload['anamnesis']);
        $this->assertSame('R50.9', $final->payload['diagnoses'][0]['code']);
        $this->assertNull($final->previous_hash);
        $this->assertSame(64, strlen($final->content_hash));
        $this->assertTrue((new ClinicalNoteRecorder)->verify($examination));

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit))->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.store', $visit), [
            'anamnesis' => 'Menimpa catatan lama',
        ])->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Koreksi catatan', 'anamnesis' => 'Demam dua hari',
        ])->assertSessionHasErrors('reason');

        $otherDoctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        Nakes::create([
            'kode' => 'DOK-OTHER', 'nama' => 'dr. Lain', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $otherDoctorUser->id, 'is_active' => true,
        ]);
        $this->actingAs($otherDoctorUser)->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Koreksi kondisi pasien', 'anamnesis' => 'Demam tiga hari',
        ])->assertForbidden();

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Pasien mengoreksi lama demam', 'anamnesis' => 'Demam tiga hari',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pemeriksaan', ['id' => $examination->id, 'anamnesis' => 'Demam dua hari']);
        $this->assertDatabaseCount('clinical_note_versions', 2);

        $addendum = ClinicalNoteVersion::where('version', 2)->sole();
        $this->assertSame('addendum', $addendum->kind);
        $this->assertSame('Pasien mengoreksi lama demam', $addendum->reason);
        $this->assertSame('Demam tiga hari', $addendum->payload['anamnesis']);
        $this->assertSame($final->content_hash, $addendum->previous_hash);
        $this->assertTrue((new ClinicalNoteRecorder)->verify($examination));
        $this->assertDatabaseHas('clinical_audit_events', ['kunjungan_id' => $visit->id, 'action' => 'clinical_examination.finalize']);
        $this->assertDatabaseHas('clinical_audit_events', ['kunjungan_id' => $visit->id, 'action' => 'clinical_examination.addendum']);

        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examination.anamnesis', 'Demam tiga hari')
            ->where('visit.examination.versions.0.anamnesis', 'Demam dua hari')
            ->where('visit.examination.versions.1.reason', 'Pasien mengoreksi lama demam')
        );
        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.anamnesis', 'Demam tiga hari')
            ->where('visits.0.examination.versions.0.anamnesis', 'Demam dua hari')
        );

        DB::table('clinical_note_versions')->where('id', $addendum->id)->update(['reason' => 'Alasan disunting tanpa addendum']);
        $this->assertFalse((new ClinicalNoteRecorder)->verify($examination));
        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Koreksi lanjutan dengan alasan sah', 'anamnesis' => 'Demam empat hari',
        ])->assertUnprocessable();
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examination.integrityValid', false)
            ->where('visit.examination.anamnesis', 'Demam dua hari')
        );

        DB::table('clinical_note_versions')->where('id', $addendum->id)->update(['reason' => 'Pasien mengoreksi lama demam']);
        $this->assertTrue((new ClinicalNoteRecorder)->verify($examination));
        DB::table('clinical_note_versions')->where('id', $addendum->id)->delete();
        $this->assertFalse((new ClinicalNoteRecorder)->verify($examination));
    }
}
