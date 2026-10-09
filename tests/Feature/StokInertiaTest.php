<?php

namespace Tests\Feature;

use App\Models\DepoObat;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StokInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_dispatch_and_receive_purchase_orders_without_over_receiving(): void
    {
        $admin = $this->admin();
        $depot = DepoObat::create(['kode' => 'DEP-01', 'nama' => 'Depo Utama', 'is_active' => true]);
        $medicine = $this->medicine();

        $this->actingAs($admin)->get(route('stok.purchase-order.index'))
            ->assertInertia(fn (Assert $page) => $page->component('stok/purchase-order/index')->has('orders.data', 0));

        $this->post(route('stok.purchase-order.store'), [
            'depo_id' => $depot->id,
            'supplier' => 'PBF Sehat',
            'tanggal' => today()->toDateString(),
            'items' => [['obat_id' => $medicine->id, 'jumlah' => 5, 'harga' => 5000]],
        ])->assertSessionHasNoErrors();

        $order = PurchaseOrder::query()->firstOrFail();
        $orderItem = $order->items()->firstOrFail();
        $this->assertSame('draft', $order->status);
        $this->post(route('stok.purchase-order.kirim', $order))->assertSessionHasNoErrors();

        $this->from(route('stok.purchase-order.terima.form', $order))
            ->post(route('stok.purchase-order.terima', $order), [
                'items' => [['id' => $orderItem->id, 'nomor_batch' => 'TEST-PO', 'expired_at' => today()->addYear()->toDateString(), 'jumlah_terima' => 6]],
            ])
            ->assertSessionHasErrors('items.0.jumlah_terima');
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 10]);
        $this->assertDatabaseCount('stok_mutasi', 0);

        $this->post(route('stok.purchase-order.terima', $order), [
            'items' => [['id' => $orderItem->id, 'nomor_batch' => 'TEST-PO-1', 'expired_at' => today()->addYear()->toDateString(), 'jumlah_terima' => 3]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_order', ['id' => $order->id, 'status' => 'sebagian']);
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 13]);

        $this->post(route('stok.purchase-order.terima', $order), [
            'items' => [['id' => $orderItem->id, 'nomor_batch' => 'TEST-PO-2', 'expired_at' => today()->addYear()->toDateString(), 'jumlah_terima' => 2]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('purchase_order', ['id' => $order->id, 'status' => 'diterima']);
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 15]);
        $this->assertDatabaseCount('stok_mutasi', 2);
        $this->get(route('stok.purchase-order.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('stok/purchase-order/show')
                ->where('order.items.0.received', 5)
            );
    }

    public function test_purchase_order_receipt_rejects_an_item_from_another_order(): void
    {
        $admin = $this->admin();
        $depot = DepoObat::create(['kode' => 'DEP-02', 'nama' => 'Depo Cabang', 'is_active' => true]);
        $medicine = $this->medicine();
        $otherMedicine = $this->medicine('OBT-002', 'Ibuprofen');
        $order = $this->order($depot, $medicine);
        $foreignOrder = $this->order($depot, $otherMedicine);
        $order->update(['status' => 'dikirim']);
        $foreignItem = $foreignOrder->items()->firstOrFail();

        $this->actingAs($admin)->from(route('stok.purchase-order.terima.form', $order))
            ->post(route('stok.purchase-order.terima', $order), [
                'items' => [['id' => $foreignItem->id, 'nomor_batch' => 'TEST-FOREIGN', 'expired_at' => today()->addYear()->toDateString(), 'jumlah_terima' => 1]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 10]);
        $this->assertDatabaseHas('obat', ['id' => $otherMedicine->id, 'stok' => 10]);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    public function test_direct_sale_is_retired_without_changing_inventory(): void
    {
        $admin = $this->admin();
        $medicine = $this->medicine();
        $this->actingAs($admin)->get(route('stok.penjualan-langsung.create'))->assertStatus(410);
        $this->post(route('stok.penjualan-langsung.store'), ['bayar' => 100000, 'items' => [['obat_id' => $medicine->id, 'jumlah' => 2]]])->assertStatus(410);
        $this->assertDatabaseCount('penjualan_langsung', 0);
        $this->assertDatabaseCount('stok_mutasi', 0);
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 10]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Administrator',
            'email' => 'admin-stock-'.uniqid().'@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    private function medicine(string $code = 'OBT-001', string $name = 'Paracetamol'): Obat
    {
        $medicine = Obat::create([
            'kode' => $code,
            'nama' => $name,
            'satuan_kecil' => 'tablet',
            'harga_beli' => 5000,
            'harga_jual' => 7000,
            'stok' => 10,
            'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 10]);

        return $medicine;
    }

    private function order(DepoObat $depot, Obat $medicine): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'no_po' => 'PO-TEST-'.uniqid(),
            'depo_id' => $depot->id,
            'supplier' => 'PBF Sehat',
            'tanggal' => today(),
            'status' => 'draft',
            'total' => 25000,
        ]);
        $order->items()->create(['obat_id' => $medicine->id, 'jumlah' => 5, 'harga' => 5000, 'total' => 25000]);

        return $order;
    }
}
