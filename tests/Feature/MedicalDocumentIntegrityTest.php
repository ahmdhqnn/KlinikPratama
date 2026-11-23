<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\MedicalLetterData;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\SuratMedis;
use App\Models\TindakanKunjungan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MedicalDocumentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_document_uses_effective_addendum_and_completed_procedure_with_frozen_facility_identity(): void
    {
        KlinikSetting::create(['nama_klinik' => 'Klinik Snapshot Awal', 'alamat' => 'Alamat Awal']);
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOC-SNAPSHOT', 'nama' => 'Dokter Snapshot', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'SNAPSHOT', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SNAPSHOT', 'nama' => 'Pasien Sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-SNAPSHOT', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'selesai']);
        $exam = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Keluhan awal', 'pemeriksaan_fisik' => 'Temuan awal']);
        $diagnosis = $exam->diagnosa()->create(['kode_icd10' => 'Z00.0', 'nama_diagnosa' => 'Diagnosis final sintetis', 'jenis' => 'utama']);
        $prescription = $visit->resep()->create(['no_resep' => 'RX-SNAPSHOT', 'dokter_id' => $doctor->id]);
        $medicine = $prescription->resepObat()->create(['nama_obat' => 'Obat final sintetis', 'jumlah' => 2, 'satuan' => 'tablet', 'aturan_pakai' => 'Petunjuk final sintetis']);
        $procedure = TindakanKunjungan::create(['kunjungan_id' => $visit->id, 'nama_tindakan' => 'Perawatan luka', 'jumlah' => 1, 'execution_role' => 'perawat', 'execution_status' => 'completed']);
        $recorder = app(ClinicalNoteRecorder::class);
        $final = $recorder->recordFinal($exam, $recorder->snapshot($visit, $exam), $user->id);
        $exam->update(['signed_at' => now(), 'signed_by_user_id' => $user->id, 'status' => 'selesai']);
        $corrected = $recorder->recordAddendum($exam, [...$final->payload, 'anamnesis' => 'Keluhan yang telah dikoreksi', 'edukasi' => 'Edukasi hasil koreksi'], $user->id, 'Koreksi sesuai klarifikasi pasien');
        // Simulate drift in mutable legacy projections; documents must use the signed version.
        $diagnosis->update(['nama_diagnosa' => 'Diagnosis diubah di luar versi']);
        $medicine->update(['nama_obat' => 'Obat diubah di luar versi', 'jumlah' => 99]);
        $procedure->update(['nama_tindakan' => 'Tindakan diubah di luar versi']);
        $snapshot = app(MedicalLetterData::class)->snapshot($visit->fresh(), $doctor, today());
        $this->assertSame('Keluhan yang telah dikoreksi', $snapshot['clinical']['anamnesis']);
        $this->assertSame('Edukasi hasil koreksi', $snapshot['clinical']['education']);
        $this->assertSame($corrected->id, $snapshot['clinicalVersion']['id']);
        $this->assertStringNotContainsString('direncanakan', $snapshot['clinical']['therapy']);
        $this->assertSame(['Diagnosis final sintetis (Z00.0)'], $snapshot['clinical']['diagnoses']);
        $this->assertStringContainsString('Obat final sintetis - 2 tablet; Petunjuk final sintetis', $snapshot['clinical']['therapy']);
        $this->assertStringContainsString('Perawatan luka', $snapshot['clinical']['therapy']);
        $this->assertStringNotContainsString('diubah di luar versi', json_encode($snapshot));
        $this->assertSame('Keluhan awal', $exam->fresh()->anamnesis);
        $letter = SuratMedis::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'jenis' => 'ringkasan', 'tanggal' => today(), 'nomor_surat' => 'RSM-TEST', 'konten' => 'Resume sintetis', 'document_data' => $snapshot]);
        KlinikSetting::query()->update(['alamat' => 'Alamat Berubah']);
        $this->actingAs($user)->get(route('persuratan.surat.cetak', $letter))->assertInertia(fn (Assert $page) => $page
            ->where('document.clinic.address', 'Alamat Awal')->where('document.clinical.anamnesis', 'Keluhan yang telah dikoreksi'));
    }
}
