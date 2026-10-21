<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientAllergyContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_screening_prefills_previous_allergy_and_blank_submission_preserves_it_through_examination(): void
    {
        [$nurse, $doctor, $patient, $visit, $previous] = $this->context();
        Screening::create(['kunjungan_id' => $previous->id, 'riwayat_alergi' => 'Penisilin', 'alergi_jenis' => 'Obat', 'alergi_reaksi' => 'Gatal']);

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.riwayat_alergi', 'Penisilin')
            ->where('visit.screening.alergi_jenis', 'Obat')->where('visit.screening.alergi_reaksi', 'Gatal'));
        $this->post(route('pelayanan.screening.store', $visit), [...$this->triage(), 'riwayat_alergi' => ''])
            ->assertSessionHasNoErrors()->assertRedirect(route('pelayanan.kunjungan.show', $visit));
        $this->assertDatabaseHas('screening', ['kunjungan_id' => $visit->id, 'riwayat_alergi' => 'Penisilin', 'alergi_reaksi' => 'Gatal']);
        $this->assertSame('Penisilin', $patient->fresh()->riwayat_alergi);
        $this->assertSame('pemeriksaan', $visit->fresh()->status);

        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.allergyHistory', 'Penisilin'));
        $this->post(route('pelayanan.pemeriksaan.store', $visit), ['anamnesis' => 'Keluhan uji', 'riwayat_alergi' => ''])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pemeriksaan', ['kunjungan_id' => $visit->id, 'riwayat_alergi' => 'Penisilin']);
    }

    public function test_latest_signed_addendum_is_used_by_next_screening_and_pharmacy(): void
    {
        [$nurse, $doctor, $patient, $visit, $previous] = $this->context();
        $examination = Pemeriksaan::create(['kunjungan_id' => $previous->id, 'riwayat_alergi' => 'Penisilin', 'status' => 'selesai']);
        $notes = app(ClinicalNoteRecorder::class);
        $notes->recordFinal($examination, ['riwayat_alergi' => 'Penisilin'], $doctor->id);
        $examination->update(['signed_by_user_id' => $doctor->id, 'signed_at' => now()]);
        $notes->recordAddendum($examination, ['riwayat_alergi' => 'Penisilin dan udang'], $doctor->id, 'Konfirmasi riwayat alergi tambahan');

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.riwayat_alergi', 'Penisilin dan udang'));
        $previous->update(['status' => 'farmasi']);
        $this->actingAs(User::factory()->create(['role' => 'farmasi']))->get(route('pelayanan.farmasi.show', $previous))
            ->assertInertia(fn (Assert $page) => $page->where('visit.patient.examinationAllergies', 'Penisilin dan udang'));
    }

    public function test_history_excludes_other_patients_cancelled_and_future_visits(): void
    {
        [$nurse, $doctor, $patient, $visit, $previous] = $this->context();
        $patient->update(['riwayat_alergi' => 'Alergi yang tersimpan']);
        $previous->update(['status' => 'batal']);
        Screening::create(['kunjungan_id' => $previous->id, 'riwayat_alergi' => 'Kunjungan batal']);
        $future = Kunjungan::create(['no_kunjungan' => 'FUTURE', 'pasien_id' => $patient->id, 'poliklinik_id' => $visit->poliklinik_id, 'tanggal' => '2026-10-21', 'status' => 'menunggu']);
        Screening::create(['kunjungan_id' => $future->id, 'riwayat_alergi' => 'Riwayat masa depan']);
        $otherPatient = Pasien::create(['no_rm' => 'RM-OTHER', 'nama' => 'Pasien lain']);
        $otherVisit = Kunjungan::create(['no_kunjungan' => 'OTHER', 'pasien_id' => $otherPatient->id, 'poliklinik_id' => $visit->poliklinik_id, 'tanggal' => '2026-10-19', 'status' => 'selesai']);
        Screening::create(['kunjungan_id' => $otherVisit->id, 'riwayat_alergi' => 'Pasien lain']);

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.riwayat_alergi', 'Alergi yang tersimpan'));
    }

    public function test_unknown_allergy_is_not_filled_as_no_allergy(): void
    {
        [$nurse, $doctor, $patient, $visit] = $this->context();
        $visit->update(['status' => 'pemeriksaan']);

        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.examinationFormDefaults.allergyHistory', null));
    }

    public function test_untrusted_signed_note_does_not_replace_known_allergy(): void
    {
        [$nurse, $doctor, $patient, $visit, $previous] = $this->context();
        $patient->update(['riwayat_alergi' => 'Penisilin']);
        Pemeriksaan::create(['kunjungan_id' => $previous->id, 'riwayat_alergi' => 'Tidak ada alergi', 'status' => 'selesai', 'signed_at' => now(), 'signed_by_user_id' => $doctor->id]);

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.riwayat_alergi', 'Penisilin'));
    }

    /** @return array{User, User, Pasien, Kunjungan, Kunjungan} */
    private function context(): array
    {
        $this->travelTo('2026-10-20 09:00:00');
        $nurse = User::factory()->create(['role' => 'perawat']);
        $doctor = User::factory()->create(['role' => 'dokter']);
        $clinic = Poliklinik::create(['kode' => 'AL-UMUM', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        Nakes::create(['kode' => 'AL-NURSE', 'nama' => 'Perawat', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $doctorProfile = Nakes::create(['kode' => 'AL-DOCTOR', 'nama' => 'Dokter', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctor->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-ALLERGY', 'nama' => 'Pasien Uji']);
        $attributes = ['pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctorProfile->id, 'jenis_pasien' => 'lama'];
        $previous = Kunjungan::create([...$attributes, 'no_kunjungan' => 'AL-PREVIOUS', 'tanggal' => '2026-10-19', 'status' => 'selesai']);
        $visit = Kunjungan::create([...$attributes, 'no_kunjungan' => 'AL-CURRENT', 'tanggal' => '2026-10-20', 'status' => 'menunggu']);

        return [$nurse, $doctor, $patient, $visit, $previous];
    }

    /** @return array<string, mixed> */
    private function triage(): array
    {
        return [
            'keluhan' => 'Keluhan pasien', 'lama_keluhan' => '2 hari', 'sumber_informasi' => 'pasien',
            'status_alergi' => 'belum_diketahui', 'status_riwayat_penyakit' => 'belum_diketahui', 'konfirmasi_riwayat' => true,
            'triase_perawat' => 'hijau',
            'td_sistole' => 120, 'td_diastole' => 80, 'nadi' => 80, 'suhu' => 36.5, 'respirasi' => 18, 'berat_badan' => 60, 'tinggi_badan' => 160,
            'nyeri_dada' => 'tidak', 'kondisi_psikiatri' => 'normal', 'nadi_teraba' => 'teraba', 'kejang' => 'tidak', 'pola_pernapasan' => 'normal', 'kesadaran' => 'sadar', 'risiko_jatuh_visual' => 'rendah'];
    }
}
