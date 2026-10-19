<?php

namespace Tests\Feature;

use App\Models\DepoObat;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhaseOneUatAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_asn_schedule_observes_weekends_national_holidays_and_joint_leave_with_authorized_exceptions(): void
    {
        $this->travelTo('2026-10-16 09:00:00');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-UAT', 'nama' => 'Dokter UAT', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-UAT', 'nama' => 'Poli Umum UAT', 'jenis' => 'umum', 'is_active' => true]);

        $this->actingAs($admin)->post(route('pendaftaran.jadwal-praktik.asn'), [
            'dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('jadwal_dokter', 5);
        $this->assertDatabaseHas('jadwal_dokter', ['dokter_id' => $doctor->id, 'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00']);

        foreach (['2026-10-17', '2026-12-24', '2026-12-25'] as $closedDate) {
            $this->getJson(route('pendaftaran.dokter.by-poli', ['poliklinik_id' => $clinic->id, 'tanggal' => $closedDate]))
                ->assertOk()->assertJsonCount(0);
        }
        $this->getJson(route('pendaftaran.dokter.by-poli', ['poliklinik_id' => $clinic->id, 'tanggal' => '2026-10-19']))
            ->assertOk()->assertJsonFragment(['id' => $doctor->id]);
        $this->actingAs(User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]))
            ->getJson(route('pendaftaran.dokter.terjadwal', ['tanggal' => '2026-10-19']))
            ->assertOk()->assertJsonFragment([
                'id' => $doctor->id,
                'poliklinik_id' => $clinic->id,
                'poliklinik_nama' => $clinic->nama,
            ]);

        $exception = [
            'dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'tanggal' => '2026-12-24',
            'is_active' => true, 'jam_mulai' => '09:00', 'jam_selesai' => '12:00', 'alasan' => 'Layanan khusus UAT',
        ];
        $this->actingAs($nurse)->post(route('pendaftaran.jadwal-praktik.pengecualian.store'), $exception)->assertForbidden();
        $this->actingAs($admin)->post(route('pendaftaran.jadwal-praktik.pengecualian.store'), $exception)->assertSessionHasNoErrors();
        $this->getJson(route('pendaftaran.dokter.by-poli', ['poliklinik_id' => $clinic->id, 'tanggal' => '2026-12-24']))
            ->assertOk()->assertJsonFragment(['id' => $doctor->id]);
    }

    public function test_inventory_receipt_records_stock_by_batch(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $depot = DepoObat::create(['kode' => 'APT-UAT', 'nama' => 'Apotek UAT', 'is_active' => true]);
        $medicine = Obat::create(['kode' => 'OBT-UAT', 'nama' => 'Obat UAT', 'jenis' => 'obat', 'stok' => 0, 'is_active' => true]);

        $this->actingAs($admin)->post(route('stok.persediaan.receive', $medicine), [
            'depo_id' => $depot->id, 'nomor_batch' => 'B-UAT', 'expired_at' => '2027-10-16',
            'jumlah' => 7, 'sumber' => 'distribusi', 'referensi' => 'Distribusi internal UAT',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('obat_batch', ['obat_id' => $medicine->id, 'nomor_batch' => 'B-UAT', 'stok' => 7]);
        $this->assertDatabaseHas('stok_mutasi', ['obat_id' => $medicine->id, 'jenis' => 'masuk', 'jumlah' => 7]);
        $this->assertSame(7.0, (float) $medicine->fresh()->stok);
    }

    public function test_retired_financial_procurement_utilization_and_package_pages_are_unreachable(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));

        foreach (['/pelayanan/kasir', '/stok/purchase-order', '/stok/penjualan-langsung', '/laporan/utilisasi', '/laporan/pendapatan', '/master/paket-tindakan', '/master/biaya-admin', '/master/biaya-pendaftaran'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->assertSame(25, DB::table('hari_libur_praktik')->whereYear('tanggal', 2026)->count());
    }
}
