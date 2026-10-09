<?php

namespace Tests\Feature;

use App\Models\DepoObat;
use App\Models\Kepesertaan;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RmeEndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_clinical_workflow_from_membership_to_dispensing_without_billing(): void
    {
        // 1. Setup Admin & Master Data
        $admin = User::create([
            'name' => 'Admin Klinik',
            'email' => 'admin@klinik.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);

        $doctor = Nakes::create([
            'kode' => 'DOK-E2E',
            'nama' => 'Dokter Umum',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);

        $depo = DepoObat::create(['kode' => 'APT', 'nama' => 'Apotek Utama', 'is_active' => true]);
        $poli = Poliklinik::create(['kode' => 'PU', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'depo_obat_id' => $depo->id, 'is_active' => true]);
        $obat = Obat::create([
            'kode' => 'OBT01',
            'nama' => 'Paracetamol 500mg',
            'satuan_besar' => 'Box',
            'satuan_kecil' => 'Tablet',
            'konversi_satuan' => 10,
            'harga_beli' => 1000,
            'harga_jual' => 2000,
            'stok' => 100,
            'stok_minimum' => 10,
            'jenis' => 'obat',
            'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $obat->id, 'stok' => 100, 'depo_id' => $depo->id]);
        $member = Kepesertaan::factory()->create(['nama' => 'Ahmad Pasien', 'nik' => '3201234567890001']);
        $tindakan = Tindakan::create([
            'kode' => 'TDK01',
            'nama' => 'Konsultasi & Pemeriksaan Dokter',
            'kategori' => 'medis',
            'poliklinik_id' => $poli->id,
            'tarif' => 50000,
            'tarif_dokter' => 35000,
            'tarif_asisten' => 5000,
            'tarif_klinik' => 10000,
            'is_active' => true,
        ]);

        $supply = Obat::create(['kode' => 'BHP-E2E', 'nama' => 'Kasa', 'satuan_kecil' => 'lembar', 'jenis' => 'bhp', 'stok' => 5, 'is_active' => true]);
        ObatBatch::factory()->create(['obat_id' => $supply->id, 'stok' => 5, 'harga_beli' => 50]);
        $tindakan->bhp()->create(['obat_id' => $supply->id, 'jumlah' => 2]);

        // 2. Registrasi Pasien Baru
        $resPasien = $this->actingAs($admin)->post('/pelayanan/pasien', [
            'kepesertaan_id' => $member->id,
            'nama' => 'Ahmad Pasien',
            'nik' => '3201234567890001',
            'tanggal_lahir' => '1995-08-17',
            'jenis_kelamin' => 'L',
            'telepon' => '08123456789',
        ]);
        $resPasien->assertRedirect();
        $this->assertDatabaseHas('pasien', ['nama' => 'Ahmad Pasien']);
        $pasien = Pasien::where('nama', 'Ahmad Pasien')->first();

        // 3. Pendaftaran Kunjungan
        $resKunjungan = $this->actingAs($admin)->post('/pelayanan/kunjungan', [
            'pasien_id' => $pasien->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
            'jenis_pasien' => 'baru',
            'jenis_bayar' => 'umum',
        ]);
        $resKunjungan->assertRedirect();
        $kunjungan = Kunjungan::where('pasien_id', $pasien->id)->first();
        $this->assertNotNull($kunjungan);
        $this->assertEquals('menunggu', $kunjungan->status);

        // 4. Screening Tanda Vital
        $resScreening = $this->actingAs($nurse)->post("/pelayanan/kunjungan/{$kunjungan->id}/screening", [
            'td_sistole' => 120,
            'td_diastole' => 80,
            'nadi' => 78,
            'suhu' => 36.6,
            'keluhan' => 'Demam dan sakit kepala ringan',
            'nyeri_dada' => 'tidak',
            'kondisi_psikiatri' => 'normal',
            'nadi_teraba' => 'teraba',
            'kejang' => 'tidak',
            'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar',
            'risiko_jatuh_visual' => 'rendah',
        ]);
        $resScreening->assertRedirect();
        $kunjungan->refresh();
        $this->assertEquals('pemeriksaan', $kunjungan->status);
        $this->assertDatabaseHas('screening', ['kunjungan_id' => $kunjungan->id, 'td_sistole' => 120]);

        // 5. Dokter Konsultasi: Diagnosa, Tindakan, Resep
        $this->actingAs($doctorUser)->post("/pelayanan/pemeriksaan/{$kunjungan->id}", [
            'anamnesis' => 'Pasien mengeluh demam 2 hari',
            'pemeriksaan_fisik' => 'Faring hiperemis (-), Cor/Pulmo DBN',
        ]);

        $this->actingAs($doctorUser)->post("/pelayanan/pemeriksaan/{$kunjungan->id}/diagnosa", [
            'kode_icd10' => 'R50.9',
            'nama_diagnosa' => 'Demam, tidak spesifik',
            'jenis' => 'utama',
        ]);

        $this->actingAs($doctorUser)->post("/pelayanan/pemeriksaan/{$kunjungan->id}/tindakan", [
            'tindakan_id' => $tindakan->id,
            'jumlah' => 1,
        ]);

        $this->actingAs($doctorUser)->post("/pelayanan/pemeriksaan/{$kunjungan->id}/resep", [
            'obat_id' => $obat->id,
            'jumlah' => 10,
            'aturan_pakai' => '3 x 1 tablet sehari sesudah makan',
            'jenis' => 'jadi',
        ]);

        // Selesai pemeriksaan dokter -> diteruskan ke farmasi
        $this->actingAs($doctorUser)->post("/pelayanan/pemeriksaan/{$kunjungan->id}/selesai");
        $kunjungan->refresh();
        $this->assertEquals('farmasi', $kunjungan->status);
        $this->assertEquals(100, $obat->fresh()->stok);
        $this->assertEquals(3, $supply->fresh()->stok);
        $this->post('/pelayanan/pemeriksaan/'.$kunjungan->id.'/selesai')->assertUnprocessable();
        $this->assertEquals(3, $supply->fresh()->stok);

        $this->actingAs($nurse)->post("/pelayanan/kunjungan/{$kunjungan->id}/screening", [
            'nyeri_dada' => 'tidak',
            'kondisi_psikiatri' => 'normal',
            'nadi_teraba' => 'teraba',
            'kejang' => 'tidak',
            'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar',
            'risiko_jatuh_visual' => 'rendah',
        ])->assertUnprocessable();
        $this->get(route('pelayanan.screening.show', $kunjungan))->assertUnprocessable();
        $this->assertDatabaseHas('kunjungan', ['id' => $kunjungan->id, 'status' => 'farmasi']);

        // 6. Farmasi Dispensing & Potong Stok
        $resFarmasiStore = $this->actingAs($pharmacist)->post("/pelayanan/farmasi/{$kunjungan->id}", [
            'items' => [
                [
                    'obat_id' => $obat->id,
                    'jumlah_diberikan' => 10,
                    'aturan_pakai' => '3 x 1 tablet sehari',
                ],
            ],
        ]);
        $resFarmasiStore->assertSessionHasNoErrors();

        $resFarmasiSelesai = $this->actingAs($pharmacist)->post("/pelayanan/farmasi/{$kunjungan->id}/selesai");
        $resFarmasiSelesai->assertRedirect(route('pelayanan.farmasi.index'));
        $kunjungan->refresh();
        $this->assertEquals('selesai', $kunjungan->status);

        // Verifikasi stok obat berkurang dari 100 menjadi 90
        $obat->refresh();
        $this->assertEquals(90, $obat->stok);

        $this->assertDatabaseCount('tagihan', 0);
        $this->assertSame('internal', $kunjungan->jenis_bayar);
        $this->assertSame($member->cost_center, $kunjungan->cost_center);
        $this->post('/pelayanan/farmasi/'.$kunjungan->id.'/selesai')->assertRedirect();
        $this->assertEquals(90, $obat->fresh()->stok);
        $this->assertDatabaseCount('stok_mutasi', 2);
    }
}
