<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductReturn;
use App\Models\ProductReturnItem;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductReturnOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $kasir;
    protected $outlet;
    protected $obat;
    protected $customer;
    protected $supplier;
    protected $sale;
    protected $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create([
            'name' => 'Apotek Utama',
            'code' => 'OUT-UTAMA',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->admin->outlets()->attach($this->outlet->id);

        $this->kasir = User::factory()->create([
            'role' => 'kasir',
            'status' => 'active',
        ]);
        $this->kasir->outlets()->attach($this->outlet->id);

        $this->customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'points_balance' => 100,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'PBF Kimia Farma',
            'code' => 'PBF-KF',
        ]);

        $this->obat = Obat::create([
            'kode' => 'OBT-RET-01',
            'nama' => 'Amoxicillin 500mg',
            'jenis_obat' => 'Kapsul',
            'kategori' => 'Obat Keras',
            'harga' => 10000,
            'harga_beli' => 6000,
            'stok' => 50,
            'satuan' => 'Strip',
        ]);

        ProductStock::create([
            'obat_id' => $this->obat->kode,
            'outlet_id' => $this->outlet->id,
            'stock' => 50,
        ]);

        // Create a completed Sale (10 items bought)
        $this->sale = Sale::create([
            'invoice_number' => 'INV-TEST-001',
            'cashier_id' => $this->kasir->id,
            'customer_id' => $this->customer->id,
            'outlet_id' => $this->outlet->id,
            'sale_date' => now()->toDateString(),
            'subtotal' => 100000,
            'discount' => 0,
            'grand_total' => 100000,
            'paid_amount' => 100000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $this->sale->id,
            'medicine_id' => $this->obat->kode,
            'quantity' => 10,
            'unit_price' => 10000,
            'buy_price' => 6000,
            'subtotal' => 100000,
        ]);

        // Create a completed Purchase (20 items received)
        $this->purchase = Purchase::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->admin->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'grand_total' => 120000,
            'status' => 'received',
        ]);

        PurchaseItem::create([
            'purchase_id' => $this->purchase->id,
            'medicine_id' => $this->obat->kode,
            'quantity_ordered' => 20,
            'quantity_received' => 20,
            'unit_price' => 6000,
            'subtotal' => 120000,
        ]);
    }

    public function test_cannot_return_more_quantity_than_purchased_in_sale()
    {
        $service = app(ReturnService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Kuantitas retur melebihi batas. Maksimal yang bisa diretur: 10');

        $service->createReturn('sale', $this->sale->id, [
            'total_amount' => 150000,
            'items' => [
                [
                    'medicine_id' => $this->obat->kode,
                    'quantity' => 15, // Bought only 10
                    'unit_price' => 10000,
                    'reason' => 'Kelebihan beli',
                    'condition' => 'Baik',
                ]
            ]
        ], $this->admin->id);
    }

    public function test_partial_returns_track_remaining_limit()
    {
        $service = app(ReturnService::class);

        // First return 4 items
        $ret1 = $service->createReturn('sale', $this->sale->id, [
            'total_amount' => 40000,
            'items' => [
                [
                    'medicine_id' => $this->obat->kode,
                    'quantity' => 4,
                    'unit_price' => 10000,
                    'reason' => 'Salah beli',
                    'condition' => 'Baik',
                ]
            ]
        ], $this->admin->id);
        $this->assertNotNull($ret1);

        // Attempting to return 7 more (4 + 7 = 11 > 10) must fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Kuantitas retur melebihi batas. Maksimal yang bisa diretur: 6');

        $service->createReturn('sale', $this->sale->id, [
            'total_amount' => 70000,
            'items' => [
                [
                    'medicine_id' => $this->obat->kode,
                    'quantity' => 7,
                    'unit_price' => 10000,
                    'reason' => 'Salah beli lagi',
                    'condition' => 'Baik',
                ]
            ]
        ], $this->admin->id);
    }

    public function test_approving_sales_return_with_good_condition_restocks_inventory()
    {
        $service = app(ReturnService::class);

        $initialStock = ProductStock::where('obat_id', $this->obat->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $this->assertEquals(50, $initialStock);

        $ret = $service->createReturn('sale', $this->sale->id, [
            'total_amount' => 30000,
            'refund_method' => 'Cash',
            'refund_amount' => 30000,
            'items' => [
                [
                    'medicine_id' => $this->obat->kode,
                    'quantity' => 3,
                    'unit_price' => 10000,
                    'reason' => 'Ganti obat',
                    'condition' => 'Baik', // Good condition: should restock
                ]
            ]
        ], $this->admin->id);

        $this->assertEquals('PENDING', $ret->status);

        // Approve
        $service->approveReturn($ret->id, $this->admin->id);

        $ret->refresh();
        $this->assertEquals('APPROVED', $ret->status);

        // Stock should now be 50 + 3 = 53
        $updatedStock = ProductStock::where('obat_id', $this->obat->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $this->assertEquals(53, $updatedStock);

        // Master stock should also increment
        $this->assertEquals(53, $this->obat->fresh()->stok);
    }

    public function test_approving_sales_return_with_damaged_condition_does_not_restock()
    {
        $service = app(ReturnService::class);

        $initialStock = ProductStock::where('obat_id', $this->obat->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $ret = $service->createReturn('sale', $this->sale->id, [
            'total_amount' => 20000,
            'refund_method' => 'Cash',
            'refund_amount' => 20000,
            'items' => [
                [
                    'medicine_id' => $this->obat->kode,
                    'quantity' => 2,
                    'unit_price' => 10000,
                    'reason' => 'Kemasan pecah saat dibawa pulang',
                    'condition' => 'Rusak', // Damaged: MUST NOT RESTOCK
                ]
            ]
        ], $this->admin->id);

        $service->approveReturn($ret->id, $this->admin->id);

        // Stock must remain unchanged at 50
        $currentStock = ProductStock::where('obat_id', $this->obat->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $this->assertEquals(50, $currentStock);
        $this->assertEquals(50, $this->obat->fresh()->stok);

        // Check stock movement audit log
        $sm = \Illuminate\Support\Facades\DB::table('stock_movements')
            ->where('reference_number', $ret->return_number)
            ->first();
        $this->assertNotNull($sm);
        $this->assertEquals(50, (float)$sm->stock_before);
        $this->assertEquals(50, (float)$sm->stock_after);
        $this->assertEquals('SALE_RETURN', $sm->movement_type);

        // Check ledger API: closing_stock must NEVER be 0
        $ledgerRes = $this->actingAs($this->admin)->getJson(route('inventory.movements.medicine-ledger', [
            'medicine_id' => $this->obat->kode,
            'outlet_id' => $this->outlet->id,
        ]));
        $ledgerRes->assertOk();
        $this->assertEquals(50, $ledgerRes->json('summary.closing_stock'));
        $this->assertEquals(0, $ledgerRes->json('summary.total_in'));
    }

    public function test_cannot_approve_already_approved_return()
    {
        $service = app(ReturnService::class);

        $ret = $service->createReturn('sale', $this->sale->id, [
            'total_amount' => 10000,
            'items' => [
                [
                    'medicine_id' => $this->obat->kode,
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'reason' => 'Retur',
                    'condition' => 'Baik',
                ]
            ]
        ], $this->admin->id);

        $service->approveReturn($ret->id, $this->admin->id);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Hanya retur berstatus PENDING yang dapat disetujui.');

        $service->approveReturn($ret->id, $this->admin->id);
    }
}
