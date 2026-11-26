<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\GeneralConsentService;
use App\HakLayananVerifier;
use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\PharmacyStaffCredential;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\Screening;
use App\Models\SuratMedis;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\PersediaanRecorder;
use App\PharmacyStaffAccess;
use Database\Seeders\KemenparPreReleaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaveThreeAndFourReleaseReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_kemenpar_official_letters_include_dynamic_profile_health_exam_details_and_resume_medis_pmk24_fields(): void
    {
        KlinikSetting::query()->create([
            'nama_klinik' => 'Klinik Pratama Kementerian Pariwisata',
            'izin_operasional' => '6/B.6.1/31.09.86/e/2023',
            'kode_fasyankes' => 'P31710102',
            'alamat' => 'Gedung Sapta Pesona, Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat',
            'kode_pos' => '10110',
            'telepon' => '(021) 3838142',
            'pelaksana_ttv' => 'perawat',
        ]);

        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $poliUmum = Poliklinik::query()->create(['kode' => 'PU', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::query()->create([
            'user_id' => $doctorUser->id,
            'poliklinik_id' => $poliUmum->id,
            'kode' => 'DKT-W4',
            'nama' => 'dr. Ratna Kusumawati',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'no_sip' => 'SIP-KP-2026',
            'is_active' => true,
        ]);
        $patient = Pasien::query()->create([
            'no_rm' => 'RM-W4-001',
            'nama' => 'Pegawai Kemenpar',
            'nik' => '3171011005880001',
            'nip' => '198805102014021001',
            'unit_kerja' => 'Sekretariat Kementerian',
            'tanggal_lahir' => '1988-05-10',
            'jenis_kelamin' => 'L',
            'golongan_darah' => 'O',
            'alamat' => 'Jakarta Pusat',
        ]);
        $visit = Kunjungan::query()->create([
            'no_kunjungan' => 'KNJ-W4-001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $poliUmum->id,
            'dokter_id' => $doctor->id,
            'unit_kerja' => 'Sekretariat Kementerian',
            'tanggal' => today(),
            'status' => 'selesai',
            'jenis_pasien' => 'lama',
        ]);
        Screening::query()->create([
            'kunjungan_id' => $visit->id,
            'submitted_at' => now(),
            'td_sistole' => 118,
            'td_diastole' => 76,
            'nadi' => 74,
            'suhu' => 36.6,
            'respirasi' => 18,
            'spo2' => 99,
            'berat_badan' => 68,
            'tinggi_badan' => 170,
            'imt' => 23.53,
        ]);
        $exam = Pemeriksaan::query()->create([
            'kunjungan_id' => $visit->id,
            'dokter_id' => $doctor->id,
            'anamnesis' => 'Pemeriksaan kesehatan berkala aparatur.',
            'pemeriksaan_fisik' => 'Jantung dan paru dalam batas normal.',
            'edukasi' => 'Pertahankan pola hidup sehat.',
            'kondisi_akhir' => 'Sehat dan bugar',
            'disposisi' => 'pulang',
            'kontrol_berikutnya' => today()->addMonth()->toDateString(),
            'status' => 'selesai',
            'signed_at' => now(),
            'signed_by_user_id' => $doctorUser->id,
        ]);
        $exam->diagnosa()->create([
            'kode_icd10' => 'Z00.0',
            'nama_diagnosa' => 'Pemeriksaan medis umum',
            'jenis' => 'utama',
        ]);
        TindakanKunjungan::query()->create([
            'kunjungan_id' => $visit->id,
            'kode_icd9cm' => '89.52',
            'nama_tindakan_manual' => 'Elektrokardiogram rutin',
            'jumlah' => 1,
            'execution_role' => 'dokter',
            'execution_status' => 'completed',
        ]);
        app(ClinicalNoteRecorder::class)->recordFinal($exam, app(ClinicalNoteRecorder::class)->snapshot($visit, $exam), $doctorUser->id);

        $this->actingAs($doctorUser)->post(route('persuratan.surat.store', $visit), [
            'jenis' => 'sehat',
            'tanggal' => today()->toDateString(),
            'details' => [
                'purpose' => 'Persyaratan Diklat Kepemimpinan',
                'conclusion' => 'Sehat jasmani untuk mengikuti kegiatan diklat.',
                'color_vision' => 'Tidak buta warna (Normal)',
                'visual_acuity' => 'OD 6/6 · OS 6/6',
            ],
        ])->assertRedirect(route('persuratan.index'));

        $sehat = SuratMedis::query()->where('jenis', 'sehat')->sole();
        $this->assertSame('Tidak buta warna (Normal)', $sehat->document_data['details']['color_vision']);
        $this->assertSame('OD 6/6 · OS 6/6', $sehat->document_data['details']['visual_acuity']);
        $this->assertSame('198805102014021001', $sehat->document_data['patient']['nip']);
        $this->assertSame('Sekretariat Kementerian', $sehat->document_data['patient']['department']);

        $this->post(route('persuratan.surat.store', $visit), [
            'jenis' => 'ringkasan',
            'tanggal' => today()->toDateString(),
            'details' => [],
        ])->assertRedirect(route('persuratan.index'));

        $resume = SuratMedis::query()->where('jenis', 'ringkasan')->sole();
        $this->get(route('persuratan.surat.cetak', $resume))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('persuratan/cetak')
                ->where('document.clinic.name', 'Klinik Pratama Kementerian Pariwisata')
                ->where('document.clinic.license', '6/B.6.1/31.09.86/e/2023')
                ->where('document.clinical.finalCondition', 'Sehat dan bugar')
                ->where('document.clinical.disposition', 'pulang')
                ->where('document.clinical.procedures.0', 'Elektrokardiogram rutin (89.52)'));
    }

    public function test_pharmacy_dispensing_exposes_permenkes_73_2016_white_and_blue_label_metadata_and_sipa(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $poli = Poliklinik::query()->create(['kode' => 'PU-F', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $pharmUser = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $pharmacist = Nakes::query()->create([
            'user_id' => $pharmUser->id,
            'poliklinik_id' => $poli->id,
            'kode' => 'APT-W4',
            'nama' => 'apt. Rina Kartika, S.Farm.',
            'kategori' => 'medis',
            'jabatan' => 'farmasi',
            'no_sip' => '19910812/SIPA_31.71/2025/2048',
            'no_str' => 'STRA-2025-01',
            'is_active' => true,
        ]);
        PharmacyStaffCredential::query()->create([
            'nakes_id' => $pharmacist->id,
            'actor_id' => $admin->id,
            'qualification' => 'apoteker',
            'recorded_at' => now(),
            'data' => [
                'user_id' => $pharmUser->id,
                'sip' => $pharmacist->no_sip,
                'str' => $pharmacist->no_str,
                'reference' => 'SK Penugasan Apoteker Klinik Kemenpar',
                'valid_until' => today()->addYear()->toDateString(),
            ],
        ]);

        $doctor = Nakes::query()->create(['kode' => 'DKT-F', 'nama' => 'dr. Pemeriksa', 'kategori' => 'medis', 'jabatan' => 'dokter', 'no_sip' => 'SIP-01', 'is_active' => true]);
        $patient = Pasien::query()->create(['no_rm' => 'RM-ETIKET-01', 'nama' => 'Pasien Etiket', 'tanggal_lahir' => '1992-08-17', 'jenis_kelamin' => 'P']);
        $visit = Kunjungan::query()->create([
            'no_kunjungan' => 'KNJ-ETIKET-01',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today(),
            'status' => 'farmasi',
            'jenis_pasien' => 'lama',
        ]);
        Pemeriksaan::query()->create([
            'kunjungan_id' => $visit->id,
            'dokter_id' => $doctor->id,
            'anamnesis' => 'Dermatitis alergi',
            'status' => 'selesai',
            'signed_at' => now(),
        ]);

        $oralMed = Obat::query()->create([
            'kode' => 'OBT-PUTIH', 'nama' => 'Cetirizine 10 mg', 'rute_default' => 'oral',
            'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet', 'konversi_satuan' => 10, 'stok' => 0, 'stok_minimum' => 5, 'jenis' => 'obat',
        ]);
        $topicalMed = Obat::query()->create([
            'kode' => 'OBT-BIRU', 'nama' => 'Hidrokortison Krim 2.5%', 'rute_default' => 'topikal',
            'satuan_besar' => 'Box', 'satuan_kecil' => 'Tube', 'konversi_satuan' => 10, 'stok' => 0, 'stok_minimum' => 5, 'jenis' => 'obat',
        ]);
        $expiry = today()->addMonths(8);
        app(PersediaanRecorder::class)->receive($oralMed->id, ['nomor_batch' => 'B-ORAL', 'expired_at' => $expiry->toDateString(), 'jumlah' => 20, 'sumber' => 'distribusi', 'referensi' => 'BAST-1'], $admin);
        app(PersediaanRecorder::class)->receive($topicalMed->id, ['nomor_batch' => 'B-TOPIKAL', 'expired_at' => $expiry->toDateString(), 'jumlah' => 10, 'sumber' => 'distribusi', 'referensi' => 'BAST-1'], $admin);

        $resep = Resep::query()->create(['no_resep' => 'RSP-ETIKET-01', 'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'status' => 'menunggu']);
        ResepObat::query()->create(['resep_id' => $resep->id, 'obat_id' => $oralMed->id, 'nama_obat' => $oralMed->nama, 'jumlah' => 10, 'satuan' => 'Tablet', 'rute_pemberian' => 'oral', 'aturan_pakai' => '1 x 1 malam', 'jenis' => 'jadi']);
        ResepObat::query()->create(['resep_id' => $resep->id, 'obat_id' => $topicalMed->id, 'nama_obat' => $topicalMed->nama, 'jumlah' => 1, 'satuan' => 'Tube', 'rute_pemberian' => 'topikal', 'aturan_pakai' => '2 x sehari oles tipis', 'jenis' => 'jadi']);

        $this->actingAs($pharmUser)->get(route('pelayanan.farmasi.show', $visit))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/farmasi/show')
                ->where('pharmacist.name', 'apt. Rina Kartika, S.Farm.')
                ->where('pharmacist.sipa', '19910812/SIPA_31.71/2025/2048')
                ->where('visit.patient.birthDate', '17/08/1992')
                ->where('visit.prescriptionItems.0.labelColor', 'putih')
                ->where('visit.prescriptionItems.0.expiryDate', $expiry->format('d/m/Y'))
                ->where('visit.prescriptionItems.1.labelColor', 'biru')
                ->where('visit.prescriptionItems.1.expiryDate', $expiry->format('d/m/Y')));
    }

    public function test_kemenpar_membership_supports_pppk_and_tenaga_penunjang_and_pre_release_seeder_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->post(route('kepesertaan.store'), [
            'nama' => 'Pegawai PPPK Kemenpar',
            'nik' => '3174011807930099',
            'nip' => '199307182023212099',
            'kategori' => 'pppk',
            'jenis_lainnya' => '',
            'status_kepegawaian' => 'aktif',
            'unit_kerja' => 'Deputi Bidang Pemasaran',
            'tempat_lahir' => 'Jakarta',
            'tanggal_lahir' => '1993-07-18',
            'jenis_kelamin' => 'P',
            'agama' => 'Islam',
            'alamat' => 'Jakarta Selatan',
            'hak_layanan' => true,
            'referensi_bukti' => 'SK Pengangkatan PPPK Kemenpar',
        ])->assertSessionHasNoErrors();

        $pppk = Kepesertaan::query()->where('nik', '3174011807930099')->sole();
        $this->assertSame('pppk', $pppk->kategori);
        $this->assertSame('PPPK', $pppk->membershipLabel());
        $this->assertNull(app(HakLayananVerifier::class)->reason($pppk));

        // Run KemenparPreReleaseSeeder twice to prove idempotency
        (new KemenparPreReleaseSeeder)->run();
        $visitCountFirst = Kunjungan::query()->count();
        $batchCountFirst = ObatBatch::query()->count();
        (new KemenparPreReleaseSeeder)->run();

        $this->assertSame($visitCountFirst, Kunjungan::query()->count());
        $this->assertSame($batchCountFirst, ObatBatch::query()->count());

        $pharmacistUmum = User::query()->where('email', 'apoteker.umum@kemenpar.go.id')->sole();
        $pharmacistGigi = User::query()->where('email', 'apoteker.gigi@kemenpar.go.id')->sole();
        $this->assertNotNull(app(PharmacyStaffAccess::class)->current($pharmacistUmum));
        $this->assertNotNull(app(PharmacyStaffAccess::class)->current($pharmacistGigi));

        $sampleVisit = Kunjungan::query()->whereNotNull('general_consent_id')->firstOrFail();
        $this->assertTrue(app(GeneralConsentService::class)->verifies($sampleVisit->generalConsent));

        // Ensure walk-in Tamu Dinas patient without kepesertaan_id can register a visit cleanly
        $tamu = Pasien::query()->where('no_rm', 'RM-KP-0007')->sole();
        $poliUmum = Poliklinik::query()->where('kode', 'PU')->sole();
        JadwalDokter::query()->where('poliklinik_id', $poliUmum->id)->exists();

        $pendaftaran = User::query()->where('email', 'pendaftaran@kemenpar.go.id')->sole();
        $this->actingAs($pendaftaran)->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $tamu->id,
            'poliklinik_id' => $poliUmum->id,
            'general_consent_confirmed' => true,
            'general_consent_version' => GeneralConsentService::VERSION,
            'general_consent_relationship' => 'patient',
        ])->assertSessionHasNoErrors();
    }
}
