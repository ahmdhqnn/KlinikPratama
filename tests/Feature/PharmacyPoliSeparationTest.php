<?php

namespace Tests\Feature;

use App\Models\DepoObat;
use App\Models\FarmasiMandiri;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\User;
use App\PersediaanRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PharmacyPoliSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacists_are_strictly_isolated_by_assigned_poliklinik_and_depo(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $depoUmum = DepoObat::create(['kode' => 'DEPO-PU', 'nama' => 'Depo Poli Umum', 'is_default' => true, 'is_active' => true]);
        $depoGigi = DepoObat::create(['kode' => 'DEPO-PG', 'nama' => 'Depo Poli Gigi', 'is_default' => false, 'is_active' => true]);

        $poliUmum = Poliklinik::create(['kode' => 'PU', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'depo_obat_id' => $depoUmum->id, 'is_active' => true]);
        $poliGigi = Poliklinik::create(['kode' => 'PG', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'depo_obat_id' => $depoGigi->id, 'is_active' => true]);

        $aptUmum = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $nakesUmum = Nakes::create(['kode' => 'APT-PU', 'nama' => 'Apoteker Umum', 'kategori' => 'medis', 'jabatan' => 'farmasi', 'user_id' => $aptUmum->id, 'poliklinik_id' => $poliUmum->id, 'is_active' => true]);

        $aptGigi = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $nakesGigi = Nakes::create(['kode' => 'APT-PG', 'nama' => 'Apoteker Gigi', 'kategori' => 'medis', 'jabatan' => 'farmasi', 'user_id' => $aptGigi->id, 'poliklinik_id' => $poliGigi->id, 'is_active' => true]);

        $unassignedApt = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        Nakes::create(['kode' => 'APT-NONE', 'nama' => 'Apoteker Tanpa Poli', 'kategori' => 'medis', 'jabatan' => 'farmasi', 'user_id' => $unassignedApt->id, 'poliklinik_id' => null, 'is_active' => true]);

        $docUmum = Nakes::create(['kode' => 'DR-PU', 'nama' => 'Dokter Umum', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $poliUmum->id, 'is_active' => true]);
        $docGigi = Nakes::create(['kode' => 'DR-PG', 'nama' => 'Dokter Gigi', 'kategori' => 'medis', 'jabatan' => 'dokter', 'poliklinik_id' => $poliGigi->id, 'is_active' => true]);

        $patientUmum = Pasien::create(['no_rm' => 'RM-ISO-PU', 'nama' => 'Pasien Umum']);
        $patientGigi = Pasien::create(['no_rm' => 'RM-ISO-PG', 'nama' => 'Pasien Gigi']);

        $visitUmum = Kunjungan::create(['no_kunjungan' => 'KNJ-ISO-PU', 'pasien_id' => $patientUmum->id, 'poliklinik_id' => $poliUmum->id, 'dokter_id' => $docUmum->id, 'tanggal' => today(), 'status' => 'farmasi']);
        $visitGigi = Kunjungan::create(['no_kunjungan' => 'KNJ-ISO-PG', 'pasien_id' => $patientGigi->id, 'poliklinik_id' => $poliGigi->id, 'dokter_id' => $docGigi->id, 'tanggal' => today(), 'status' => 'farmasi']);

        Pemeriksaan::create(['kunjungan_id' => $visitUmum->id, 'dokter_id' => $docUmum->id, 'anamnesis' => 'Demam', 'status' => 'selesai', 'signed_at' => now()]);
        Pemeriksaan::create(['kunjungan_id' => $visitGigi->id, 'dokter_id' => $docGigi->id, 'anamnesis' => 'Sakit gigi', 'status' => 'selesai', 'signed_at' => now()]);

        $med = Obat::create([
            'kode' => 'OBT-ISO', 'nama' => 'Paracetamol 500 mg', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet',
            'konversi_satuan' => 10, 'stok' => 0, 'stok_minimum' => 5, 'jenis' => 'obat', 'is_active' => true,
        ]);

        app(PersediaanRecorder::class)->receive($med->id, [
            'nomor_batch' => 'B-PU-01', 'expired_at' => today()->addYear()->toDateString(),
            'jumlah' => 40, 'sumber' => 'distribusi', 'referensi' => 'BAST-PU', 'depo_id' => $depoUmum->id,
        ], $admin);
        app(PersediaanRecorder::class)->receive($med->id, [
            'nomor_batch' => 'B-PG-01', 'expired_at' => today()->addYear()->toDateString(),
            'jumlah' => 15, 'sumber' => 'distribusi', 'referensi' => 'BAST-PG', 'depo_id' => $depoGigi->id,
        ], $admin);

        $resepUmum = Resep::create(['no_resep' => 'RSP-ISO-PU', 'kunjungan_id' => $visitUmum->id, 'dokter_id' => $docUmum->id, 'status' => 'menunggu']);
        ResepObat::create(['resep_id' => $resepUmum->id, 'obat_id' => $med->id, 'nama_obat' => $med->nama, 'jumlah' => 6, 'satuan' => 'Tablet', 'aturan_pakai' => '3x1', 'jenis' => 'jadi']);

        $resepGigi = Resep::create(['no_resep' => 'RSP-ISO-PG', 'kunjungan_id' => $visitGigi->id, 'dokter_id' => $docGigi->id, 'status' => 'menunggu']);
        ResepObat::create(['resep_id' => $resepGigi->id, 'obat_id' => $med->id, 'nama_obat' => $med->nama, 'jumlah' => 4, 'satuan' => 'Tablet', 'aturan_pakai' => '3x1', 'jenis' => 'jadi']);

        FarmasiMandiri::create([
            'nomor' => 'FMD-ISO-PU', 'pasien_id' => $patientUmum->id, 'poliklinik_id' => $poliUmum->id, 'depo_id' => $depoUmum->id,
            'obat_id' => $med->id, 'jumlah' => 2, 'aturan_pakai' => '1x1', 'dasar_pemberian' => 'Keluhan ringan',
            'petugas_id' => $aptUmum->id, 'diserahkan_pada' => now(),
        ]);
        FarmasiMandiri::create([
            'nomor' => 'FMD-ISO-PG', 'pasien_id' => $patientGigi->id, 'poliklinik_id' => $poliGigi->id, 'depo_id' => $depoGigi->id,
            'obat_id' => $med->id, 'jumlah' => 2, 'aturan_pakai' => '1x1', 'dasar_pemberian' => 'Nyeri gigi ringan',
            'petugas_id' => $aptGigi->id, 'diserahkan_pada' => now(),
        ]);

        // Unassigned pharmacist is blocked with 403
        $this->actingAs($unassignedApt)->get(route('farmasi.dashboard'))->assertForbidden();
        $this->actingAs($unassignedApt)->get(route('pelayanan.farmasi.index'))->assertForbidden();
        $this->actingAs($unassignedApt)->get(route('stok.persediaan.index'))->assertForbidden();

        // Apoteker Poli Umum can access Poli Umum visit & cannot access Poli Gigi visit (404)
        $this->actingAs($aptUmum)->get(route('pelayanan.farmasi.show', $visitUmum))->assertOk();
        $this->actingAs($aptUmum)->get(route('pelayanan.farmasi.show', $visitGigi))->assertNotFound();
        $this->actingAs($aptUmum)->get(route('pelayanan.farmasi.review.show', $visitGigi))->assertNotFound();

        // Apoteker Poli Gigi can access Poli Gigi visit & cannot access Poli Umum visit (404)
        $this->actingAs($aptGigi)->get(route('pelayanan.farmasi.show', $visitGigi))->assertOk();
        $this->actingAs($aptGigi)->get(route('pelayanan.farmasi.show', $visitUmum))->assertNotFound();

        // Queue index only lists assigned poli visits even if ?poli=gigi is tampered
        $this->actingAs($aptUmum)->get(route('pelayanan.farmasi.index', ['poli' => 'gigi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('clinicType', 'umum')
                ->where('visits.total', 1)
                ->where('visits.data.0.prescriptionNumber', 'RSP-ISO-PU'));

        // Direct medicine records are isolated by poli
        $this->actingAs($aptGigi)->get(route('farmasi.pemberian-obat.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('records.total', 1)
                ->where('records.data.0.number', 'FMD-ISO-PG'));

        // Depo stock isolation: Poli Umum sees 40 usable stock, Poli Gigi sees 15 usable stock
        $this->actingAs($aptUmum)->get(route('stok.persediaan.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('medicines.data.0.usable', 40));

        $this->actingAs($aptGigi)->get(route('stok.persediaan.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('medicines.data.0.usable', 15));

        // Stock export works for both pharmacists and admin
        $this->actingAs($aptUmum)->get(route('stok.persediaan.export'))->assertOk();
        $this->actingAs($admin)->get(route('stok.persediaan.export', ['poliklinik_id' => $poliGigi->id]))->assertOk();

        // Admin can supervise across both clinics
        $this->actingAs($admin)->get(route('stok.persediaan.index', ['poliklinik_id' => $poliUmum->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('medicines.data.0.usable', 40));
        $this->actingAs($admin)->get(route('stok.persediaan.index', ['poliklinik_id' => $poliGigi->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('medicines.data.0.usable', 15));
    }
}
