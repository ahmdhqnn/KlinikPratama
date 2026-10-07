<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KasirInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cashier_can_view_queue_process_payment_and_open_receipt(): void
    {
        $cashier = User::create([
            'name' => 'Petugas Kasir',
            'email' => 'kasir-test@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'kasir',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create([
            'no_rm' => 'RM-000001',
            'nama' => 'Pasien Kasir',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1990-01-01',
        ]);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-20261007-0001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'kasir',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);

        $this->actingAs($cashier)->get(route('pelayanan.kasir.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/kasir/index')
                ->has('visits.data', 1)
                ->where('visits.data.0.patient', 'Pasien Kasir')
            );

        $this->get(route('pelayanan.kasir.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/kasir/show')
                ->where('visit.patient.name', 'Pasien Kasir')
                ->where('subtotal', 25000)
            );

        $payload = [
            'metode_bayar' => 'tunai',
            'bayar' => 20000,
            'diskon' => 5000,
            'items' => [
                ['nama' => 'Biaya Pendaftaran', 'jenis' => 'pendaftaran', 'referensi_id' => 0, 'jumlah' => 1, 'tarif' => 25000],
            ],
        ];

        $this->post(route('pelayanan.kasir.store', $visit), array_replace($payload, ['diskon' => 25001]))
            ->assertSessionHasErrors('diskon');
        $this->assertDatabaseMissing('tagihan', ['kunjungan_id' => $visit->id]);

        $this->post(route('pelayanan.kasir.store', $visit), $payload)
            ->assertRedirect();

        $invoice = Tagihan::query()->where('kunjungan_id', $visit->id)->firstOrFail();
        $this->assertSame('lunas', $invoice->status);
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->assertSame('20000.00', $invoice->total);
        $this->assertSame('0.00', $invoice->kembalian);

        $this->get(route('pelayanan.kasir.kuitansi', $invoice))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/kasir/kuitansi')
                ->where('receipt.number', $invoice->no_tagihan)
                ->where('receipt.total', 20000)
                ->where('receipt.items.0.name', 'Biaya Pendaftaran')
            );

        $this->post(route('pelayanan.kasir.store', $visit), $payload)
            ->assertSessionHasErrors('kunjungan');
        $this->assertDatabaseCount('tagihan', 1);
    }
}
