<?php

namespace Tests\Feature;

use App\Models\Asuransi;
use App\Models\AsuransiHarga;
use App\Models\Obat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AsuransiInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_insurance_and_special_medicine_prices(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-insurance@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $insurance = Asuransi::create([
            'kode' => 'BPJS-001',
            'nama' => 'BPJS Kesehatan',
            'jenis' => 'bpjs',
            'telepon' => '021123456',
            'is_active' => true,
        ]);
        $medicine = Obat::create([
            'kode' => 'OBT-ASR-001',
            'nama' => 'Paracetamol',
            'harga_jual' => 10000,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.asuransi.index', ['search' => 'BPJS-001', 'jenis' => 'bpjs']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/asuransi/index')
                ->has('insurances.data', 1)
                ->where('insurances.data.0.name', 'BPJS Kesehatan')
                ->where('filters.type', 'bpjs')
            );

        $this->put(route('master.asuransi.update', $insurance), [
            'kode' => 'BPJS-001',
            'nama' => 'BPJS Kesehatan Nasional',
            'jenis' => 'bpjs',
            'alamat' => '',
            'telepon' => '021123456',
            'catatan' => '',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $this->post(route('master.asuransi.harga.store', $insurance), [
            'obat_id' => $medicine->id,
            'harga_khusus' => 8000,
        ])->assertSessionHasNoErrors();
        $this->post(route('master.asuransi.harga.store', $insurance), [
            'obat_id' => $medicine->id,
            'harga_khusus' => 7500,
        ])->assertSessionHasNoErrors();

        $this->get(route('master.asuransi.show', $insurance))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/asuransi/show')
                ->where('insurance.name', 'BPJS Kesehatan Nasional')
                ->has('insurance.prices', 1)
                ->where('insurance.prices.0.regularPrice', 10000)
                ->where('insurance.prices.0.specialPrice', 7500)
            );

        $price = AsuransiHarga::query()->firstOrFail();
        $this->delete(route('master.asuransi.harga.destroy', $price))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('asuransi_harga', ['id' => $price->id]);

        $this->post(route('master.asuransi.store'), [
            'kode' => 'PRSH-001',
            'nama' => 'Perusahaan Sehat',
            'jenis' => 'perusahaan',
            'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('asuransi', ['kode' => 'PRSH-001', 'nama' => 'Perusahaan Sehat']);
    }
}
