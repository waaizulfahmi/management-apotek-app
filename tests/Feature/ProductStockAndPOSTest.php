<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\MedicineBatch;
use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductStock;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\OutletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductStockAndPOSTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outletA;
    protected Outlet $outletB;
    protected User $cashierA;
    protected User $cashierB;
    protected Obat $paracetamol;
    protected Obat $vitaminC;
    protected Obat $obh;
    protected CashierShift $shiftA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Outlets
        $this->outletA = Outlet::create([
            'code' => 'OUT-A',
            'name' => 'Outlet Farmasi A',
            'is_main' => true,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->outletB = Outlet::create([
            'code' => 'OUT-B',
            'name' => 'Outlet Farmasi B',
            'is_main' => false,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // 2. Create Cashiers
        $this->cashierA = User::create([
            'name' => 'Kasir Outlet A',
            'username' => 'kasir_a',
            'email' => 'kasir_a@apotek.com',
            'password' => Hash::make('secret'),
            'role' => 'kasir',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outletA->id,
            'access_all_outlets' => false,
        ]);
        DB::table('user_outlets')->insert([
            'user_id' => $this->cashierA->id,
            'outlet_id' => $this->outletA->id,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cashierB = User::create([
            'name' => 'Kasir Outlet B',
            'username' => 'kasir_b',
            'email' => 'kasir_b@apotek.com',
            'password' => Hash::make('secret'),
            'role' => 'kasir',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outletB->id,
            'access_all_outlets' => false,
        ]);
        DB::table('user_outlets')->insert([
            'user_id' => $this->cashierB->id,
            'outlet_id' => $this->outletB->id,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Create Products
        $this->paracetamol = Obat::create([
            'kode' => 'MED-PCT-01',
            'nama' => 'Paracetamol 500mg',
            'jenis_obat' => 'Tablet',
            'kategori' => 'Analgesik',
            'harga' => 5000,
            'stok' => 150,
            'min_stok' => 10,
        ]);

        $this->vitaminC = Obat::create([
            'kode' => 'MED-VITC-01',
            'nama' => 'Vitamin C 500mg',
            'jenis_obat' => 'Tablet',
            'kategori' => 'Vitamin',
            'harga' => 8000,
            'stok' => 80,
            'min_stok' => 10,
        ]);

        $this->obh = Obat::create([
            'kode' => 'MED-OBH-01',
            'nama' => 'OBH Sirup 100ml',
            'jenis_obat' => 'Sirup',
            'kategori' => 'Batuk',
            'harga' => 18000,
            'stok' => 30,
            'min_stok' => 5,
        ]);

        // 4. Map Products and Stocks as specified in prompt:
        // Paracetamol: 100 in A, 50 in B
        OutletService::attachProductToOutlet($this->paracetamol->kode, $this->outletA->id);
        ProductStock::updateOrCreate(
            ['obat_id' => $this->paracetamol->kode, 'outlet_id' => $this->outletA->id],
            ['stock' => 100]
        );

        OutletService::attachProductToOutlet($this->paracetamol->kode, $this->outletB->id, 5500); // Custom outlet B price 5,500
        ProductStock::updateOrCreate(
            ['obat_id' => $this->paracetamol->kode, 'outlet_id' => $this->outletB->id],
            ['stock' => 50]
        );

        // Vitamin C: 80 in A, unmapped in B
        OutletService::attachProductToOutlet($this->vitaminC->kode, $this->outletA->id);
        ProductStock::updateOrCreate(
            ['obat_id' => $this->vitaminC->kode, 'outlet_id' => $this->outletA->id],
            ['stock' => 80]
        );

        // OBH: unmapped in A, 30 in B
        OutletService::attachProductToOutlet($this->obh->kode, $this->outletB->id);
        ProductStock::updateOrCreate(
            ['obat_id' => $this->obh->kode, 'outlet_id' => $this->outletB->id],
            ['stock' => 30]
        );

        // Create FEFO Batches for Paracetamol
        MedicineBatch::create([
            'medicine_id' => $this->paracetamol->kode,
            'batch_number' => 'B-PCT-01',
            'expired_date' => now()->addMonths(6)->toDateString(),
            'stock' => 60,
            'buy_price' => 3000,
            'sell_price' => 5000,
            'is_active' => true,
        ]);
        MedicineBatch::create([
            'medicine_id' => $this->paracetamol->kode,
            'batch_number' => 'B-PCT-02',
            'expired_date' => now()->addMonths(12)->toDateString(),
            'stock' => 90,
            'buy_price' => 3200,
            'sell_price' => 5000,
            'is_active' => true,
        ]);

        // 5. Open shift for Cashier A
        $this->shiftA = CashierShift::create([
            'cashier_id' => $this->cashierA->id,
            'outlet_id' => $this->outletA->id,
            'shift_name' => 'Shift Pagi A',
            'opening_cash' => 200000,
            'expected_cash' => 200000,
            'actual_cash' => 0,
            'difference' => 0,
            'status' => 'OPEN',
            'opened_at' => now(),
        ]);
    }

    /**
     * 1. Outlet product visibility test
     */
    public function test_outlet_product_mapping_isolation()
    {
        // Outlet A has Paracetamol & Vitamin C
        $this->assertTrue(OutletService::isProductAvailableInOutlet($this->paracetamol->kode, $this->outletA->id));
        $this->assertTrue(OutletService::isProductAvailableInOutlet($this->vitaminC->kode, $this->outletA->id));
        $this->assertFalse(OutletService::isProductAvailableInOutlet($this->obh->kode, $this->outletA->id));

        // Outlet B has Paracetamol & OBH
        $this->assertTrue(OutletService::isProductAvailableInOutlet($this->paracetamol->kode, $this->outletB->id));
        $this->assertFalse(OutletService::isProductAvailableInOutlet($this->vitaminC->kode, $this->outletB->id));
        $this->assertTrue(OutletService::isProductAvailableInOutlet($this->obh->kode, $this->outletB->id));
    }

    /**
     * 2. Stock isolation per outlet
     */
    public function test_stock_per_outlet_isolation()
    {
        $this->assertEquals(100, OutletService::getStock($this->paracetamol->kode, $this->outletA->id));
        $this->assertEquals(50, OutletService::getStock($this->paracetamol->kode, $this->outletB->id));
        $this->assertEquals(80, OutletService::getStock($this->vitaminC->kode, $this->outletA->id));
        $this->assertEquals(30, OutletService::getStock($this->obh->kode, $this->outletB->id));
    }

    /**
     * 3. Outlet B custom price is respected
     */
    public function test_outlet_custom_pricing_is_respected()
    {
        // Outlet A uses default price 5000
        $this->assertEquals(5000, OutletService::getOutletPrice($this->paracetamol->kode, $this->outletA->id));
        // Outlet B uses custom price 5500
        $this->assertEquals(5500, OutletService::getOutletPrice($this->paracetamol->kode, $this->outletB->id));
    }

    /**
     * 4. POS Checkout cannot sell unmapped products
     */
    public function test_pos_checkout_rejects_unmapped_product()
    {
        session(['active_outlet_id' => $this->outletA->id]);

        // Attempt to sell OBH (unmapped in Outlet A) from Outlet A POS
        $response = $this->actingAs($this->cashierA)->postJson(route('pos.checkout'), [
            'items' => [
                ['kode' => $this->obh->kode, 'quantity' => 1]
            ],
            'payment_method' => 'cash',
            'paid_amount' => 50000,
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('tidak terdaftar atau tidak aktif', $response->json('message'));
    }

    /**
     * 5. Price manipulation prevention: frontend cannot override backend price
     */
    public function test_frontend_price_manipulation_prevented()
    {
        session(['active_outlet_id' => $this->outletA->id]);

        // Request sends fake unit_price = 100 instead of 5,000
        $response = $this->actingAs($this->cashierA)->postJson(route('pos.checkout'), [
            'items' => [
                ['kode' => $this->paracetamol->kode, 'quantity' => 2, 'unit_price' => 100]
            ],
            'payment_method' => 'cash',
            'paid_amount' => 10000, // Pays 10,000 for 2 x 5,000
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));

        // Grand total must be 10,000 (2 x 5,000), not 200 (2 x 100)
        $this->assertEquals(10000, $response->json('data.grand_total'));

        $sale = Sale::find($response->json('data.sale_id'));
        $this->assertEquals(10000, (float)$sale->grand_total);
    }

    /**
     * 6. Multi-batch allocation accuracy: line subtotals and quantities sum to exact transaction
     */
    public function test_multi_batch_allocation_records_exact_quantities_and_subtotals()
    {
        session(['active_outlet_id' => $this->outletA->id]);

        // Customer buys 70 tablets of Paracetamol
        // Batch 1 has 60 tablets, Batch 2 will provide the remaining 10 tablets
        $response = $this->actingAs($this->cashierA)->postJson(route('pos.checkout'), [
            'items' => [
                ['kode' => $this->paracetamol->kode, 'quantity' => 70]
            ],
            'payment_method' => 'cash',
            'paid_amount' => 350000, // 70 x 5,000 = 350,000
        ]);

        $response->assertStatus(200);
        $saleId = $response->json('data.sale_id');

        $saleItems = SaleItem::where('sale_id', $saleId)->get();
        $this->assertCount(2, $saleItems); // 2 batches allocated

        // Total quantities and subtotals must sum exactly to 70 and 350,000
        $this->assertEquals(70, $saleItems->sum('quantity'));
        $this->assertEquals(350000, (float)$saleItems->sum('subtotal'));

        // Batch 1 item
        $itemBatch1 = $saleItems->firstWhere('batch_id', 1);
        $this->assertNotNull($itemBatch1);
        $this->assertEquals(60, $itemBatch1->quantity);
        $this->assertEquals(300000, (float)$itemBatch1->subtotal);

        // Batch 2 item
        $itemBatch2 = $saleItems->firstWhere('batch_id', 2);
        $this->assertNotNull($itemBatch2);
        $this->assertEquals(10, $itemBatch2->quantity);
        $this->assertEquals(50000, (float)$itemBatch2->subtotal);

        // Outlet stock must be reduced from 100 to 30
        $newStockA = ProductStock::where('obat_id', $this->paracetamol->kode)
            ->where('outlet_id', $this->outletA->id)
            ->value('stock');
        $this->assertEquals(30, (float)$newStockA);

        // Outlet B stock must remain untouched (50)
        $stockB = ProductStock::where('obat_id', $this->paracetamol->kode)
            ->where('outlet_id', $this->outletB->id)
            ->value('stock');
        $this->assertEquals(50, (float)$stockB);
    }

    /**
     * 7. Idempotency test: duplicate click does not create duplicate transaction
     */
    public function test_pos_idempotency_prevents_duplicate_transactions()
    {
        session(['active_outlet_id' => $this->outletA->id]);
        $idempotencyKey = 'tx-client-uuid-999888';

        $payload = [
            'client_tx_id' => $idempotencyKey,
            'items' => [
                ['kode' => $this->paracetamol->kode, 'quantity' => 5]
            ],
            'payment_method' => 'cash',
            'paid_amount' => 25000,
        ];

        // 1st Click
        $res1 = $this->actingAs($this->cashierA)->postJson(route('pos.checkout'), $payload);
        $res1->assertStatus(200);
        $saleId1 = $res1->json('data.sale_id');

        // 2nd Click with same client_tx_id
        $res2 = $this->actingAs($this->cashierA)->postJson(route('pos.checkout'), $payload);
        $res2->assertStatus(200);
        $saleId2 = $res2->json('data.sale_id');

        // Both must return the same sale_id
        $this->assertEquals($saleId1, $saleId2);

        // Total sales in DB must be exactly 1
        $this->assertEquals(1, Sale::where('outlet_id', $this->outletA->id)->count());
    }

    /**
     * 8. Insufficient payment rejection
     */
    public function test_insufficient_payment_is_rejected()
    {
        session(['active_outlet_id' => $this->outletA->id]);

        $response = $this->actingAs($this->cashierA)->postJson(route('pos.checkout'), [
            'items' => [
                ['kode' => $this->paracetamol->kode, 'quantity' => 2] // 10,000
            ],
            'payment_method' => 'cash',
            'paid_amount' => 5000, // Insufficient (paid 5,000 vs 10,000)
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('kurang dari Grand Total', $response->json('message'));
    }

    /**
     * 9. Checkout without active shift is rejected
     */
    public function test_checkout_without_open_shift_is_rejected()
    {
        // Cashier B has no open shift
        session(['active_outlet_id' => $this->outletB->id]);

        $response = $this->actingAs($this->cashierB)->postJson(route('pos.checkout'), [
            'items' => [
                ['kode' => $this->paracetamol->kode, 'quantity' => 1]
            ],
            'payment_method' => 'cash',
            'paid_amount' => 10000,
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsString('Shift belum dibuka', $response->json('message'));
    }
}
