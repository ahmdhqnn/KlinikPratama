<?php

namespace Tests\Feature;

use App\Models\LabBhp;
use App\Models\LabIndikator;
use App\Models\Laboratorium;
use App\Models\Obat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LaboratoriumInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_laboratories_indicators_and_supplies_from_inertia_pages(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-lab@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $laboratory = Laboratorium::create([
            'kode' => 'LAB-001',
            'nama' => 'Darah lengkap',
            'tarif' => 125000,
            'deskripsi' => 'Pemeriksaan hematologi',
            'is_active' => true,
        ]);
        $medicine = Obat::create([
            'kode' => 'BHP-LAB-001',
            'nama' => 'Tabung EDTA',
            'jenis' => 'bhp',
            'satuan_kecil' => 'pcs',
            'stok' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.laboratorium.index', ['search' => 'LAB-001']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/laboratorium/index')
                ->has('laboratories.data', 1)
                ->where('laboratories.data.0.name', 'Darah lengkap')
                ->where('laboratories.data.0.tariff', 125000)
            );

        $this->put(route('master.laboratorium.update', $laboratory), [
            'kode' => 'LAB-001',
            'nama' => 'Darah lengkap rutin',
            'poliklinik_id' => null,
            'tarif' => 130000,
            'deskripsi' => 'Pemeriksaan hematologi rutin',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('laboratorium', ['id' => $laboratory->id, 'tarif' => 0]);
        $this->post(route('master.laboratorium.store'), ['kode' => 'LAB-002', 'nama' => 'Pemeriksaan internal', 'is_active' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('laboratorium', ['kode' => 'LAB-002', 'tarif' => 0]);

        $this->post(route('master.laboratorium.indikator.store', $laboratory), [
            'nama' => 'Hemoglobin',
            'satuan' => 'g/dL',
            'nilai_rujukan_min' => '12.0',
            'nilai_rujukan_max' => '17.0',
            'format_input' => 'number',
        ])->assertSessionHasNoErrors();
        $this->post(route('master.laboratorium.indikator.store', $laboratory), [
            'nama' => 'Golongan darah',
            'format_input' => 'select',
            'pilihan' => 'A, B, AB, O',
        ])->assertSessionHasNoErrors();
        $this->post(route('master.laboratorium.bhp.store', $laboratory), [
            'obat_id' => $medicine->id,
            'jumlah' => 1.5,
        ])->assertSessionHasNoErrors();

        $this->get(route('master.laboratorium.show', $laboratory))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/laboratorium/show')
                ->where('laboratory.name', 'Darah lengkap rutin')
                ->has('laboratory.indicators', 2)
                ->where('laboratory.indicators.1.choices.0', 'A')
                ->where('laboratory.indicators.1.choices.3', 'O')
                ->has('laboratory.supplies', 1)
                ->where('laboratory.supplies.0.quantity', 1.5)
            );

        $indicator = LabIndikator::query()->where('nama', 'Hemoglobin')->firstOrFail();
        $supply = LabBhp::query()->firstOrFail();
        $this->delete(route('master.laboratorium.indikator.destroy', $indicator))->assertSessionHasNoErrors();
        $this->delete(route('master.laboratorium.bhp.destroy', $supply))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lab_indikator', ['id' => $indicator->id]);
        $this->assertDatabaseMissing('lab_bhp', ['id' => $supply->id]);
    }
}
