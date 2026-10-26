<?php

namespace Tests\Feature;

use App\Models\Alkes;
use App\Models\DepoObat;
use App\Models\Kunjungan;
use App\Models\Laboratorium;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SimplifiedClinicModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_modules_reject_reads_and_writes_without_removing_historical_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $depot = DepoObat::create(['kode' => 'OLD-DEP', 'nama' => 'Depo lama', 'is_active' => true]);
        $laboratory = Laboratorium::create(['kode' => 'OLD-LAB', 'nama' => 'Laboratorium lama', 'is_active' => true]);
        $equipment = Alkes::create(['kode' => 'OLD-ALK', 'nama' => 'Alat lama', 'is_active' => true]);
        $treatment = Tindakan::create(['kode' => 'OLD-MED', 'nama' => 'Tindakan lama', 'kategori' => 'medis', 'is_active' => true]);
        $this->actingAs($admin);

        foreach (['depo-obat' => $depot, 'laboratorium' => $laboratory, 'alkes' => $equipment, 'tindakan' => $treatment] as $resource => $record) {
            $this->get("/master/{$resource}")->assertNotFound();
            $this->get("/master/{$resource}/{$record->id}")->assertNotFound();
            $this->post("/master/{$resource}", ['kode' => 'NEW', 'nama' => 'Item baru'])->assertNotFound();
            $this->put("/master/{$resource}/{$record->id}", ['nama' => 'Diubah'])->assertNotFound();
            $this->delete("/master/{$resource}/{$record->id}")->assertNotFound();
            $this->assertModelExists($record);
            $this->assertNull($record->fresh()->deleted_at);
            $this->assertSame($record->nama, $record->fresh()->nama);
        }
        $this->post(route('master.laboratorium.indikator.store', $laboratory), [])->assertNotFound();
        $this->post(route('master.laboratorium.bhp.store', $laboratory), [])->assertNotFound();
        $this->assertDatabaseCount('depo_obat', 1);
        $this->assertDatabaseCount('laboratorium', 1);
        $this->assertDatabaseCount('alkes', 1);
        $this->assertDatabaseCount('tindakan', 1);
        $this->get(route('master.tindakan.terminologi'))->assertNotFound();
        $this->post(route('master.tindakan.bhp.store', $treatment), [])->assertNotFound();
        $this->get(route('master.terminologi.index'))->assertOk();
    }

    #[TestWith(['admin'])]
    #[TestWith(['manajemen'])]
    public function test_laboratory_report_is_unavailable_for_report_roles(string $role): void
    {
        $actor = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($actor)->get(route('laporan.laboratorium'))->assertNotFound();
    }

    #[TestWith(['admin', 'obat'])]
    #[TestWith(['admin', 'bhp'])]
    #[TestWith(['farmasi', 'obat'])]
    #[TestWith(['farmasi', 'bhp'])]
    public function test_inventory_receipts_work_without_any_depot_and_ignore_stale_depot_input(string $role, string $type): void
    {
        $this->freezeTime();
        $actor = User::factory()->create(['role' => $role, 'is_active' => true]);
        $medicine = $this->medicine($type);

        $this->actingAs($actor)->post(route('stok.persediaan.receive', $medicine), [
            ...$this->receipt(), 'depo_id' => 99999,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('obat_batch', ['obat_id' => $medicine->id, 'depo_id' => null, 'stok' => 5, 'nomor_batch' => 'BATCH-NEW']);
        $this->assertDatabaseHas('stok_mutasi', ['obat_id' => $medicine->id, 'depo_id' => null, 'jumlah' => 5, 'jenis' => 'masuk', 'actor_id' => $actor->id]);
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 5]);
        $this->assertDatabaseCount('depo_obat', 0);
        $this->get(route('stok.persediaan.show', $medicine))->assertInertia(fn (Assert $page) => $page
            ->where('depotsEnabled', false)->has('depots', 0)->where('batches.0.depot', null)
            ->where('modules.laboratory', false)->where('modules.medicine_depots', false)->where('modules.medical_equipment', false));
    }

    public function test_quarantined_legacy_batch_can_be_verified_without_selecting_or_losing_its_old_depot(): void
    {
        $this->freezeTime();
        $actor = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $medicine = $this->medicine('bhp');
        $medicine->update(['stok' => 8]);
        $depot = DepoObat::create(['kode' => 'OLD', 'nama' => 'Depo lama', 'is_active' => false]);
        $batch = ObatBatch::factory()->create(['obat_id' => $medicine->id, 'depo_id' => $depot->id, 'stok' => 8, 'status' => 'karantina']);

        $this->actingAs($actor)->post(route('stok.batch.reconcile', $batch), $this->receipt())->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('3.00', $batch->fresh()->stok);
        $this->assertDatabaseHas('obat_batch', ['obat_id' => $medicine->id, 'nomor_batch' => 'BATCH-NEW', 'stok' => 5, 'depo_id' => $depot->id, 'status' => 'tersedia']);
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 8]);
    }

    public function test_depot_transfer_is_disabled_without_changing_stock(): void
    {
        $actor = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $medicine = $this->medicine('obat');
        $medicine->update(['stok' => 5]);
        $batch = ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 5]);

        $this->actingAs($actor)->post(route('stok.batch.transfer', $batch), ['depo_id' => 123, 'jumlah' => 2, 'alasan' => 'Pindah stok'])->assertNotFound();

        $this->assertSame('5.00', $batch->fresh()->stok);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    public function test_clinic_settings_omit_depots_and_laboratory_and_preserve_existing_depot_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $depot = DepoObat::create(['kode' => 'OLD', 'nama' => 'Depo lama', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli umum', 'jenis' => 'umum', 'depo_obat_id' => $depot->id, 'is_active' => true]);
        Poliklinik::create(['kode' => 'LAB', 'nama' => 'Laboratorium', 'jenis' => 'lab', 'is_active' => true]);

        $this->actingAs($admin)->get(route('master.poliklinik.index'))->assertInertia(fn (Assert $page) => $page
            ->has('clinics.data', 1)->where('depotsEnabled', false)->has('depots', 0)
            ->where('clinicTypes', fn ($types): bool => ! collect($types)->contains('value', 'lab')));
        $this->put(route('master.poliklinik.update', $clinic), [
            'kode' => 'UMUM', 'nama' => 'Poli Umum Klinik', 'jenis' => 'umum', 'is_active' => true, 'depo_obat_id' => '',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('poliklinik', ['id' => $clinic->id, 'nama' => 'Poli Umum Klinik', 'depo_obat_id' => $depot->id]);
        $this->post(route('master.poliklinik.store'), ['kode' => 'LAB-NEW', 'nama' => 'Lab baru', 'jenis' => 'lab', 'is_active' => true])->assertSessionHasErrors('jenis');
        $this->assertDatabaseMissing('poliklinik', ['kode' => 'LAB-NEW']);
    }

    public function test_treatment_catalog_hides_laboratory_and_rejects_new_laboratory_treatments(): void
    {
        config(['clinic.modules.medical_treatments' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Tindakan::create(['kode' => 'MEDIS', 'nama' => 'Perawatan luka', 'kategori' => 'medis', 'is_active' => true]);
        $lab = Tindakan::create(['kode' => 'LAB', 'nama' => 'Tes lama', 'kategori' => 'lab', 'is_active' => true]);

        $this->actingAs($admin)->get(route('master.tindakan.index'))->assertInertia(fn (Assert $page) => $page
            ->has('treatments.data', 1)->where('treatments.data.0.category', 'medis')
            ->has('categories', 1)->where('categories.0.value', 'medis'));
        $this->get(route('master.tindakan.show', $lab))->assertNotFound();
        $this->post(route('master.tindakan.bhp.store', $lab), [])->assertNotFound();
        $this->post(route('master.tindakan.store'), ['kode' => 'LAB-NEW', 'nama' => 'Lab baru', 'kategori' => 'lab', 'is_active' => true])->assertSessionHasErrors('kategori');
        $this->assertDatabaseMissing('tindakan', ['kode' => 'LAB-NEW']);
        $this->assertModelExists($lab);
    }

    public function test_doctor_cannot_add_hidden_laboratory_treatment_but_can_record_medical_treatment(): void
    {
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'DOK', 'nama' => 'Dokter', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-TEST', 'nama' => 'Pasien']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-TEST', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $staff->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $staff->id]);
        $lab = Tindakan::create(['kode' => 'LAB', 'nama' => 'Tes lama', 'kategori' => 'lab', 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $medical = Tindakan::create(['kode' => 'MEDIS', 'nama' => 'Perawatan luka', 'kategori' => 'medis', 'poliklinik_id' => $clinic->id, 'is_active' => true]);

        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['tindakan_id' => $lab->id, 'jumlah' => 1])->assertSessionHasErrors(['tindakan_id' => 'Layanan laboratorium tidak tersedia. Pilih tindakan klinik yang aktif.']);
        $this->assertDatabaseCount('tindakan_kunjungan', 0);
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['tindakan_id' => $medical->id, 'jumlah' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', ['kunjungan_id' => $visit->id, 'tindakan_id' => $medical->id]);
    }

    private function medicine(string $type): Obat
    {
        return Obat::create(['kode' => 'ITEM-TEST', 'nama' => 'Item uji', 'jenis' => $type, 'stok' => 0, 'is_active' => true, 'satuan_kecil' => 'pcs']);
    }

    /** @return array{nomor_batch: string, expired_at: string, jumlah: int, sumber: string, referensi: string} */
    private function receipt(): array
    {
        return ['nomor_batch' => 'BATCH-NEW', 'expired_at' => today()->addYear()->toDateString(), 'jumlah' => 5, 'sumber' => 'distribusi', 'referensi' => 'Penerimaan persediaan klinik'];
    }
}
