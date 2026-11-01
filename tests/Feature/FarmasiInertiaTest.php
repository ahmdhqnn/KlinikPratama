<?php

namespace Tests\Feature;

use App\Models\Farmasi;
use App\Models\FarmasiMandiri;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\Screening;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FarmasiInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pharmacist_can_record_direct_medicine_dispensing_with_audited_stock_decrement(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-DIRECT-1', 'nama' => 'Pasien Langsung', 'riwayat_alergi' => 'Tidak ada alergi yang diketahui']);
        $medicine = Obat::create(['kode' => 'OBT-DIRECT', 'nama' => 'Parasetamol', 'satuan_kecil' => 'tablet', 'stok' => 8, 'is_active' => true, 'jenis' => 'obat']);
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 8]);

        $this->actingAs($pharmacist)->get(route('farmasi.pemberian-obat.create'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/pemberian-obat/form')->has('medicines', 0));
        $this->post(route('farmasi.pemberian-obat.store'), [
            'pasien_id' => $patient->id, 'obat_id' => $medicine->id, 'jumlah' => 2,
            'aturan_pakai' => 'Sesuai petunjuk pada kemasan.', 'dasar_pemberian' => 'Permintaan pasien.',
        ])->assertSessionHasErrors('obat_id');
        $medicine->update(['boleh_diberikan_langsung' => true]);
        $batch = $medicine->batches()->firstOrFail();
        $batch->update(['expired_at' => today()->subDay()]);
        $this->get(route('farmasi.pemberian-obat.create'))->assertInertia(fn (Assert $page) => $page
            ->where('medicines.0.name', 'Parasetamol')->where('medicines.0.stock', 0));
        $batch->update(['expired_at' => today()->addYear()]);
        $this->get(route('farmasi.pemberian-obat.create'))->assertInertia(fn (Assert $page) => $page
            ->where('medicines.0.stock', 8));

        $this->actingAs($pharmacist)->post(route('farmasi.pemberian-obat.store'), [
            'pasien_id' => $patient->id,
            'obat_id' => $medicine->id,
            'jumlah' => 2,
            'aturan_pakai' => '1 tablet bila perlu, maksimal sesuai petunjuk kemasan.',
            'dasar_pemberian' => 'Permintaan pasien untuk keluhan ringan.',
            'catatan' => 'Petunjuk penyimpanan sudah dijelaskan.',
        ])->assertRedirect();

        $this->assertDatabaseHas('farmasi_mandiris', [
            'pasien_id' => $patient->id, 'obat_id' => $medicine->id, 'petugas_id' => $pharmacist->id, 'jumlah' => 2,
            'catatan' => 'Petunjuk penyimpanan sudah dijelaskan.',
        ]);
        $directRecord = FarmasiMandiri::query()->firstOrFail();
        $this->get(route('farmasi.pemberian-obat.cetak', $directRecord))->assertInertia(fn (Assert $page) => $page
            ->where('record.notes', 'Petunjuk penyimpanan sudah dijelaskan.'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 6]);
        $this->assertDatabaseHas('stok_mutasi', ['referensi_type' => 'FarmasiMandiri', 'jenis' => 'keluar', 'kunjungan_id' => null, 'jumlah' => 2]);

        $this->get(route('farmasi.laporan.pemberian-langsung'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/laporan')->where('stats.total', 1)->where('rows.data.0.patient', 'Pasien Langsung'));
        $this->get(route('farmasi.pemberian-obat.index'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/pemberian-obat/index')->where('stats.today', 1)
            ->where('todayRecords.data.0.patient', 'Pasien Langsung')->where('records.data.0.medicine', 'Parasetamol'));
        $this->get(route('farmasi.pasien'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/pasien')->where('directRecords.0.patient', 'Pasien Langsung'));
        $this->get(route('farmasi.laporan.pemberian-langsung.export'))->assertDownload('laporan-pemberian-obat-'.today()->format('Y-m-d').'.xlsx');

        $this->get(route('farmasi.laporan'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/laporan-penyerahan')
            ->where('stats.total', 0));

        $outgoingMovement = StokMutasi::where('referensi_type', 'FarmasiMandiri')->firstOrFail();
        $this->post(route('stok.mutasi.return', $outgoingMovement), ['jumlah' => 1, 'alasan' => 'Obat dikembalikan utuh'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stok_mutasi', ['referensi_type' => 'FarmasiMandiriRetur', 'jenis' => 'retur', 'kunjungan_id' => null]);
        $this->assertDatabaseHas('obat_batch', ['id' => $outgoingMovement->batch_id, 'status' => 'tersedia', 'stok' => 6]);
        $this->assertDatabaseHas('obat_batch', ['obat_id' => $medicine->id, 'status' => 'karantina', 'stok' => 1]);
    }

    public function test_zero_dispensed_quantity_stays_zero_after_saving_and_reloading(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'ZERO', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-ZERO', 'nama' => 'Pasien Uji']);
        $visit = Kunjungan::create(['no_kunjungan' => 'ZERO', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi']);
        $medicine = Obat::create(['kode' => 'MED-ZERO', 'nama' => 'Obat Uji', 'satuan_kecil' => 'tablet', 'stok' => 0, 'is_active' => true]);
        $prescription = Resep::create(['no_resep' => 'RSP-ZERO', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $item = $prescription->resepObat()->create(['obat_id' => $medicine->id, 'nama_obat' => $medicine->nama, 'jumlah' => 3, 'jenis' => 'jadi', 'is_resep_luar' => false]);
        Farmasi::create(['kunjungan_id' => $visit->id, 'resep_id' => $prescription->id, 'status' => 'menunggu']);

        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 0]],
            'catatan' => 'Obat belum tersedia; pasien diarahkan ke apotek luar.',
        ])->assertSessionHasNoErrors();
        $this->get(route('pelayanan.farmasi.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.prescriptionItems.0.dispensedQuantity', 0));
        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertSessionHasNoErrors();
        $this->get(route('pelayanan.farmasi.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.prescriptionItems.0.dispensedQuantity', 0));
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    public function test_future_reservation_cannot_be_dispensed_even_if_its_stage_is_incorrect(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'FUTURE', 'nama' => 'Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-FUTURE', 'nama' => 'Pasien Reservasi']);
        $visit = Kunjungan::create(['no_kunjungan' => 'FUTURE', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => '2026-10-21', 'status' => 'farmasi']);

        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), ['items' => []])->assertUnprocessable();
        $this->post(route('pelayanan.farmasi.selesai', $visit), ['items' => []])->assertUnprocessable();
        $this->assertDatabaseCount('farmasi', 0);
        $this->assertDatabaseCount('stok_mutasi', 0);
        $this->assertSame('farmasi', $visit->fresh()->status);
    }

    public function test_pharmacist_dashboard_summarizes_queue_and_restricts_access_to_pharmacy_role(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-DASH-FAR', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $waitingPatient = Pasien::create(['no_rm' => 'RM-DASH-FAR-1', 'nama' => 'Pasien Menunggu']);
        $processingPatient = Pasien::create(['no_rm' => 'RM-DASH-FAR-2', 'nama' => 'Pasien Diproses']);
        $waitingVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-DASH-FAR-1', 'pasien_id' => $waitingPatient->id, 'poliklinik_id' => $clinic->id,
            'tanggal' => today(), 'status' => 'farmasi', 'jenis_pasien' => 'lama',
        ]);
        $processingVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-DASH-FAR-2', 'pasien_id' => $processingPatient->id, 'poliklinik_id' => $clinic->id,
            'tanggal' => today(), 'status' => 'farmasi', 'jenis_pasien' => 'lama',
        ]);
        Farmasi::create(['kunjungan_id' => $processingVisit->id, 'status' => 'diproses']);
        $completedPatient = Pasien::create(['no_rm' => 'RM-DASH-FAR-3', 'nama' => 'Pasien Selesai']);
        $completedVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-DASH-FAR-3', 'pasien_id' => $completedPatient->id, 'poliklinik_id' => $clinic->id,
            'tanggal' => today()->subDay(), 'status' => 'farmasi', 'jenis_pasien' => 'lama',
        ]);
        Farmasi::create(['kunjungan_id' => $completedVisit->id, 'status' => 'selesai', 'dispensed_at' => today()->subDay()]);
        $lowStockMedicine = Obat::create([
            'kode' => 'OBT-DASH-LOW', 'nama' => 'Obat stok rendah', 'satuan_kecil' => 'tablet', 'stok' => 8,
            'stok_minimum' => 10, 'jenis' => 'obat', 'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $lowStockMedicine->id, 'status' => 'karantina', 'stok' => 8]);
        $availableMedicine = Obat::create([
            'kode' => 'OBT-DASH-OK', 'nama' => 'Obat stok cukup', 'satuan_kecil' => 'tablet', 'stok' => 10,
            'stok_minimum' => 5, 'jenis' => 'obat', 'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $availableMedicine->id, 'stok' => 10]);

        $this->actingAs($pharmacist)->get(route('dashboard'))->assertRedirect(route('farmasi.dashboard'));
        $this->get(route('farmasi.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/dashboard')
            ->where('stats.waitingVisits', 1)
            ->where('stats.processingVisits', 1)
            ->where('stats.completedToday', 0)
            ->where('stats.lowStockMedicines', 1)
            ->where('stats.totalPharmacyVisits', 3)
            ->has('recentVisits.data', 2)
            ->where('followUp.overdue', 0)
            ->where('recentVisits.data.0.number', 'KNJ-DASH-FAR-1')
            ->where('recentVisits.data.0.actionUrl', route('pelayanan.farmasi.show', $waitingVisit))
            ->where('recentVisits.data.0.actionLabel', 'Proses resep')
            ->where('recentVisits.data.1.actionLabel', 'Lanjutkan'));

        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $this->actingAs($doctor)->get(route('farmasi.dashboard'))->assertForbidden();
    }

    public function test_pharmacist_can_list_and_print_only_signed_external_prescriptions(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'RESEP-LUAR', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-LUAR', 'nama' => 'Pasien Resep Luar']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-LUAR', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'selesai']);
        $prescription = Resep::create(['no_resep' => 'RSP-LUAR', 'kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $prescription->resepObat()->create(['nama_obat' => 'Obat dari luar klinik', 'jumlah' => 2, 'satuan' => 'tablet', 'jenis' => 'jadi', 'is_resep_luar' => true]);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'status' => 'draf']);

        $this->actingAs($pharmacist)->get(route('farmasi.resep-luar.index'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/resep-luar')->where('stats.today', 0)->where('todayRecords.total', 0));
        $this->get(route('farmasi.resep-luar.cetak', $visit))->assertNotFound();

        $examination->update(['status' => 'final', 'signed_at' => now()]);
        $this->get(route('farmasi.resep-luar.index'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.today', 1)->where('todayRecords.data.0.patient', 'Pasien Resep Luar')
            ->where('todayRecords.data.0.itemCount', 1));
        $this->get(route('farmasi.resep-luar.cetak', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('persuratan/cetak')->where('document.kind', 'resep_luar')
            ->where('document.items.0.name', 'Obat dari luar klinik')
            ->where('document.backUrl', route('farmasi.resep-luar.index')));
    }

    public function test_pharmacy_redirects_only_out_of_stock_items_to_external_prescription_without_changing_the_doctor_order(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'STOCK-OUT', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-STOCK-OUT', 'nama' => 'Pasien Stok Kosong']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-STOCK-OUT', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi']);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'status' => 'final', 'signed_at' => now()]);
        $prescription = Resep::create(['no_resep' => 'RSP-STOCK-OUT', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $available = Obat::create(['kode' => 'OBT-AVAILABLE', 'nama' => 'Obat Tersedia', 'jenis' => 'obat', 'is_active' => true, 'stok' => 4]);
        ObatBatch::factory()->create(['obat_id' => $available->id, 'stok' => 4]);
        $availableItem = $prescription->resepObat()->create(['obat_id' => $available->id, 'nama_obat' => $available->nama, 'jumlah' => 2, 'jenis' => 'jadi']);
        $unavailable = Obat::create(['kode' => 'OBT-EMPTY', 'nama' => 'Obat Kosong', 'jenis' => 'obat', 'is_active' => true, 'stok' => 0]);
        $emptyItem = $prescription->resepObat()->create(['obat_id' => $unavailable->id, 'nama_obat' => $unavailable->nama, 'jumlah' => 1, 'jenis' => 'jadi']);

        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.resep-luar', [$visit, $availableItem]))->assertSessionHasErrors('resep_luar');
        $this->assertDatabaseCount('farmasi_resep_luar', 0);
        $this->post(route('pelayanan.farmasi.resep-luar', [$visit, $emptyItem]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farmasi_resep_luar', ['resep_obat_id' => $emptyItem->id, 'petugas_id' => $pharmacist->id]);
        $this->assertDatabaseHas('resep_obat', ['id' => $emptyItem->id, 'is_resep_luar' => false]);
        $this->get(route('pelayanan.farmasi.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.prescriptionItems.1.external', true)->where('visit.prescriptionItems.1.externalByPharmacy', true));
        $this->get(route('farmasi.resep-luar.cetak', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('document.items.0.name', 'Obat Kosong'));
        $this->get(route('farmasi.resep-luar.cetak', $visit).'?asal=penyerahan')->assertInertia(fn (Assert $page) => $page
            ->where('document.backUrl', route('pelayanan.farmasi.show', $visit)));
        $this->get(route('farmasi.laporan.resep-luar'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/laporan-resep-luar')->where('stats.items', 1)
            ->where('stats.prescriptions', 1)->where('stats.redirected', 1)
            ->where('rows.data.0.source', 'Dialihkan farmasi'));
        $this->get(route('farmasi.resep-luar.cetak', $visit).'?asal=laporan')->assertInertia(fn (Assert $page) => $page
            ->where('document.backUrl', route('farmasi.laporan.resep-luar')));
        $this->get(route('farmasi.laporan.resep-luar.export'))->assertDownload('laporan-resep-luar-'.today()->format('Y-m-d').'.xlsx');
    }

    public function test_dispensing_pages_process_clinic_stock_and_allow_external_prescriptions(): void
    {
        $pharmacist = User::create([
            'name' => 'Petugas Farmasi',
            'email' => 'farmasi-test@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'farmasi',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Farmasi', 'riwayat_alergi' => 'Alergi penisilin']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-20261007-0001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'farmasi',
            'jenis_pasien' => 'lama',
        ]);
        $medicine = Obat::create([
            'kode' => 'OBT-001',
            'nama' => 'Paracetamol',
            'satuan_kecil' => 'tablet',
            'stok' => 8,
            'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => $medicine->stok]);
        $prescription = Resep::create([
            'no_resep' => 'RSP-20261007-0001',
            'kunjungan_id' => $visit->id,
            'status' => 'menunggu',
        ]);
        $clinicItem = ResepObat::create([
            'resep_id' => $prescription->id,
            'obat_id' => $medicine->id,
            'nama_obat' => $medicine->nama,
            'jumlah' => 3,
            'satuan' => 'tablet',
            'aturan_pakai' => '3 x 1',
            'jenis' => 'jadi',
            'is_resep_luar' => false,
            'stok_dikurangi' => false,
        ]);
        $externalItem = ResepObat::create([
            'resep_id' => $prescription->id,
            'nama_obat' => 'Vitamin luar',
            'jumlah' => 1,
            'satuan' => 'tablet',
            'aturan_pakai' => '1 x 1',
            'jenis' => 'jadi',
            'is_resep_luar' => true,
            'stok_dikurangi' => false,
        ]);

        $this->actingAs($pharmacist)->get(route('pelayanan.farmasi.index'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/farmasi/index')
            ->has('visits.data', 1)
            ->has('todayVisits.data', 1)
            ->where('stats.today', 1)
            ->where('stats.waiting', 1)
            ->where('visits.data.0.patient', 'Pasien Farmasi')
        );
        $this->get(route('pelayanan.farmasi.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/farmasi/show')
            ->where('visit.patient.name', 'Pasien Farmasi')
            ->where('visit.prescriptionItems.1.external', true)
        );

        $this->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [['resep_obat_id' => $clinicItem->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 1.5]],
        ])->assertSessionHasErrors('items.0.jumlah_diberikan');

        $this->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [
                ['resep_obat_id' => $clinicItem->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 3, 'aturan_pakai' => '3 x 1'],
                ['resep_obat_id' => $externalItem->id, 'obat_id' => null, 'jumlah_diberikan' => 0, 'aturan_pakai' => '1 x 1'],
            ],
            'catatan' => 'Sudah diverifikasi',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'diproses']);
        $this->assertDatabaseCount('farmasi_item', 1);

        $storedItem = $visit->farmasi->items->sole();
        $storedItem->update(['resep_obat_id' => $externalItem->id]);
        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertSessionHasErrors('items');
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 8]);
        $this->assertDatabaseCount('stok_mutasi', 0);
        $storedItem->update(['resep_obat_id' => $clinicItem->id]);

        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertRedirect(route('pelayanan.farmasi.index'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 5]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('resep', ['id' => $prescription->id, 'status' => 'selesai']);
        $this->get(route('farmasi.pasien.kunjungan', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/pasien-kunjungan')
            ->where('visit.patient', 'Pasien Farmasi')
            ->where('visit.items.0.dispensed', 3)
            ->where('visit.items.1.external', true));
        $this->get(route('farmasi.pasien'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.detailUrl', route('farmasi.pasien.kunjungan', $visit)));
        $this->get(route('farmasi.laporan'))->assertInertia(fn (Assert $page) => $page
            ->component('farmasi/laporan-penyerahan')
            ->where('stats.total', 1)
            ->where('stats.patients', 1)
            ->where('rows.data.0.patient', 'Pasien Farmasi')
            ->where('rows.data.0.detailUrl', route('farmasi.pasien.kunjungan', $visit).'?asal=laporan'));
        $this->get(route('farmasi.laporan.export'))->assertDownload('laporan-penyerahan-obat-'.today()->format('Y-m-d').'.xlsx');

        $this->get(route('pelayanan.farmasi.index'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.id', $visit->id)
            ->where('visits.data.0.pharmacyStatus', 'selesai')
            ->where('todayVisits.total', 0)
            ->where('stats.completed', 1));
        $this->get(route('pelayanan.farmasi.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/farmasi/show')
            ->where('visit.status', 'selesai')
            ->where('visit.pharmacy.status', 'selesai')
            ->where('visit.prescriptionItems.0.dispensedQuantity', 3));

        $this->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [
                ['resep_obat_id' => $clinicItem->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 3],
            ],
        ])->assertUnprocessable();

        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertRedirect(route('pelayanan.farmasi.index'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 5]);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseCount('stok_mutasi', 1);

    }

    public function test_finalization_rolls_back_all_items_if_one_batch_expires_after_draft_is_saved(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'ATOMIC', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-ATOMIC', 'nama' => 'Pasien Uji']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-ATOMIC', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi']);
        $prescription = Resep::create(['no_resep' => 'RSP-ATOMIC', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $items = [];
        $medicines = [];
        foreach ([1, 2] as $index) {
            $medicine = Obat::create(['kode' => 'ATOMIC-'.$index, 'nama' => 'Obat '.$index, 'jenis' => 'obat', 'stok' => 5, 'is_active' => true]);
            ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 5]);
            $item = $prescription->resepObat()->create(['obat_id' => $medicine->id, 'nama_obat' => $medicine->nama, 'jumlah' => 3, 'jenis' => 'jadi']);
            $items[] = ['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 3];
            $medicines[] = $medicine;
        }
        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), ['items' => $items])->assertSessionHasNoErrors();
        $medicines[1]->batches()->update(['expired_at' => today()]);
        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertSessionHasErrors('items');
        foreach ($medicines as $medicine) {
            $this->assertEquals(5, $medicine->fresh()->stok);
            $this->assertEquals(5, $medicine->batches()->sum('stok'));
        }
        $this->assertDatabaseCount('stok_mutasi', 0);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'diproses', 'dispensed_at' => null]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'farmasi']);
    }

    public function test_pharmacist_can_finish_dispensing_without_saving_a_draft_first(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'DIRECT-FAR', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create([
            'no_rm' => 'RM-DIRECT-FAR',
            'nama' => 'Pasien Tanpa Alergi',
            'riwayat_alergi' => 'Tidak ada riwayat alergi',
        ]);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-DIRECT-FAR', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id,
            'tanggal' => today(), 'status' => 'farmasi', 'jenis_pasien' => 'lama',
        ]);
        Screening::create(['kunjungan_id' => $visit->id, 'riwayat_alergi' => 'Alergi udang']);
        $medicine = Obat::create([
            'kode' => 'OBT-DIRECT-FAR', 'nama' => 'Obat Dispensing Langsung', 'satuan_kecil' => 'tablet',
            'stok' => 5, 'jenis' => 'obat', 'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 5]);
        $prescription = Resep::create([
            'no_resep' => 'RSP-DIRECT-FAR', 'kunjungan_id' => $visit->id, 'status' => 'menunggu',
        ]);
        $prescriptionItem = ResepObat::create([
            'resep_id' => $prescription->id, 'obat_id' => $medicine->id, 'nama_obat' => $medicine->nama,
            'jumlah' => 3, 'satuan' => 'tablet', 'aturan_pakai' => '3 x 1', 'jenis' => 'jadi', 'stok_dikurangi' => false,
        ]);

        $this->actingAs($pharmacist)->get(route('pelayanan.farmasi.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.patient.allergies', 'Alergi udang')
                ->where('visit.patient.screeningAllergies', 'Alergi udang')
                ->where('visit.pharmacy', null)
            );

        $dispensingData = [
            'items' => [[
                'resep_obat_id' => $prescriptionItem->id,
                'obat_id' => $medicine->id,
                'jumlah_diberikan' => 4,
                'aturan_pakai' => '3 x 1',
            ]],
        ];
        $this->post(route('pelayanan.farmasi.selesai', $visit), $dispensingData)
            ->assertSessionHasErrors('items.0.jumlah_diberikan');
        $this->assertDatabaseMissing('farmasi', ['kunjungan_id' => $visit->id]);
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 5]);

        $dispensingData['items'][0]['jumlah_diberikan'] = 3;
        $this->post(route('pelayanan.farmasi.selesai', $visit), $dispensingData)
            ->assertRedirect(route('pelayanan.farmasi.index'));

        $this->assertDatabaseHas('farmasi', [
            'kunjungan_id' => $visit->id,
            'resep_id' => $prescription->id,
            'status' => 'selesai',
        ]);
        $this->assertDatabaseHas('farmasi_item', [
            'resep_obat_id' => $prescriptionItem->id,
            'obat_id' => $medicine->id,
            'jumlah_diberikan' => 3,
        ]);
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 2]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('clinical_audit_events', [
            'kunjungan_id' => $visit->id,
            'action' => 'pharmacy.dispensed',
        ]);
        $this->assertDatabaseMissing('clinical_audit_events', [
            'kunjungan_id' => $visit->id,
            'action' => 'pharmacy.draft_saved',
        ]);
    }

    public function test_partial_dispensing_only_deducts_the_actual_quantity_with_follow_up_notes(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-PARTIAL-1', 'nama' => 'Pasien Parsial']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-PARTIAL-1',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'farmasi',
        ]);
        $medicine = Obat::create([
            'kode' => 'OBT-PARTIAL-1',
            'nama' => 'Obat Uji',
            'satuan_kecil' => 'tablet',
            'stok' => 10,
            'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => $medicine->stok]);
        $prescription = Resep::create(['no_resep' => 'RSP-PARTIAL-1', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $item = ResepObat::create([
            'resep_id' => $prescription->id,
            'obat_id' => $medicine->id,
            'nama_obat' => $medicine->nama,
            'jumlah' => 3,
            'satuan' => 'tablet',
            'jenis' => 'jadi',
            'stok_dikurangi' => false,
        ]);

        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 4]],
        ])->assertSessionHasErrors('items.0.jumlah_diberikan');
        $this->assertDatabaseCount('farmasi_item', 0);

        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 1]],
            'catatan' => 'Dua tablet belum diambil; pasien akan kembali setelah konfirmasi dokter.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('farmasi_item', [
            'farmasi_id' => Farmasi::where('kunjungan_id', $visit->id)->value('id'),
            'resep_obat_id' => $item->id,
            'aturan_pakai' => null,
            'catatan' => null,
        ]);

        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertRedirect(route('pelayanan.farmasi.index'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 9]);
        $this->assertDatabaseHas('stok_mutasi', ['obat_id' => $medicine->id, 'jenis' => 'keluar', 'jumlah' => 1]);
    }
}
