<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaporanInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_administration_reports_render_inertia_data_and_filters(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-report@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Laporan']);
        Kunjungan::create([
            'no_kunjungan' => 'KNJ-20261007-0001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'selesai',
            'jenis_pasien' => 'lama',
        ]);
        Obat::create([
            'kode' => 'OBT-001',
            'nama' => 'Paracetamol',
            'jenis' => 'obat',
            'satuan_kecil' => 'tablet',
            'stok' => 4,
            'stok_minimum' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('laporan.kunjungan', [
            'dari' => today()->toDateString(),
            'sampai' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('laporan/kunjungan')
            ->where('stats.total', 1)
            ->where('visits.data.0.patient', 'Pasien Laporan')
        );

        $this->get('/laporan/pendapatan')->assertNotFound();
        $this->get('/laporan/utilisasi')->assertNotFound();

        $this->get(route('laporan.stok', ['jenis' => 'obat', 'stok_rendah' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('laporan/stok')
                ->where('stats.lowStockItems', 1)
                ->where('medicines.data.0.name', 'Paracetamol')
            );

        $this->get(route('laporan.laboratorium', [
            'dari' => today()->toDateString(),
            'sampai' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('laporan/laboratorium')
            ->has('results.data', 0)
        );
    }
}
