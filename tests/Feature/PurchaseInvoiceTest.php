<?php

namespace Tests\Feature;

use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductStock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Exception;

class PurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Outlet $outlet;
    protected Obat $medicineA;
    protected Obat $medicineB;
    protected PurchaseInvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PurchaseInvoiceService::class);

        // Siapkan user, supplier, outlet & obat untuk testing
        $this->user = User::first() ?: User::factory()->create();

        $this->supplier = Supplier::firstOrCreate(
            ['code' => 'SUP-TEST-01'],
            ['name' => 'PT Test Farmasi Medika', 'is_active' => true]
        );

        $this->outlet = Outlet::firstOrCreate(
            ['code' => 'OUT-TEST-01'],
            ['name' => 'Outlet Farmasi Utama']
        );

        $this->medicineA = Obat::firstOrCreate(
            ['kode' => 'MED-TEST-001'],
            [
                'nama' => 'Paracetamol 500mg Test',
                'stok' => 50,
                'harga' => 10000,
                'harga_beli' => 7000,
                'min_stok' => 10,
            ]
        );

        $this->medicineB = Obat::firstOrCreate(
            ['kode' => 'MED-TEST-002'],
            [
                'nama' => 'Amoxicillin 500mg Test',
                'stok' => 30,
                'harga' => 25000,
                'harga_beli' => 18000,
                'min_stok' => 10,
            ]
        );

        // Pastikan product_stocks ada untuk outlet
        ProductStock::updateOrCreate(
            ['obat_id' => $this->medicineA->kode, 'outlet_id' => $this->outlet->id],
            ['stock' => 50, 'min_stock' => 10]
        );

        ProductStock::updateOrCreate(
            ['obat_id' => $this->medicineB->kode, 'outlet_id' => $this->outlet->id],
            ['stock' => 30, 'min_stock' => 10]
        );
    }

    /**
     * 1. Faktur draft tidak menambah stok
     */
    public function test_draft_invoice_does_not_modify_stock()
    {
        $initialStock = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $initialGlobalStock = (float)Obat::where('kode', $this->medicineA->kode)->value('stok');

        $header = [
            'invoice_number' => 'INV-TEST-DRAFT-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => 10,
                'unit_price' => 7000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-DRAFT-01',
                'expired_date' => now()->addYear()->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);

        $this->assertEquals('DRAFT', $purchase->status);

        // Pastikan stok fisik outlet dan stok global TIDAK bertambah saat masih DRAFT
        $currentStock = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $currentGlobalStock = (float)Obat::where('kode', $this->medicineA->kode)->value('stok');

        $this->assertEquals($initialStock, $currentStock);
        $this->assertEquals($initialGlobalStock, $currentGlobalStock);
    }

    /**
     * 2. Faktur satu obat berhasil difinalisasi dan stok bertambah tepat satu kali
     */
    public function test_single_medicine_finalization_increases_stock_and_records_movement()
    {
        $stockBefore = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $header = [
            'invoice_number' => 'INV-TEST-FIN-1-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        $qtyToAdd = 25;
        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => $qtyToAdd,
                'unit_price' => 7500,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-SINGLE-01',
                'expired_date' => now()->addMonths(18)->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);
        $finalized = $this->service->finalizeInvoice($purchase->id, $this->user);

        $this->assertEquals('FINAL', $finalized->status);
        $this->assertNotNull($finalized->finalized_at);

        // Verifikasi stok outlet bertambah
        $stockAfter = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $this->assertEquals($stockBefore + $qtyToAdd, $stockAfter);

        // Verifikasi mutasi kartu stok tercatat
        $hasMovement = DB::table('stock_movements')
            ->where('medicine_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->where('type', 'in')
            ->where('movement_type', 'PURCHASE_INVOICE')
            ->where('quantity', $qtyToAdd)
            ->exists();
        $this->assertTrue($hasMovement);

        // Verifikasi batch tersimpan
        $batch = DB::table('medicine_batches')
            ->where('medicine_id', $this->medicineA->kode)
            ->where('batch_number', 'BATCH-SINGLE-01')
            ->first();
        $this->assertNotNull($batch);
        $this->assertGreaterThanOrEqual($qtyToAdd, (float)$batch->stock);
    }

    /**
     * 3. Pembelian dalam satuan Box dikonversi ke satuan dasar dengan benar
     * Contoh: 5 box x 100 tablet = 500 tablet stok dasar masuk
     */
    public function test_purchase_in_box_converts_to_base_stock_accurately()
    {
        $stockBefore = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $header = [
            'invoice_number' => 'INV-CONV-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        $boxesBought = 5;
        $conversionRate = 100; // 1 Box = 100 Tablet
        $expectedBaseAdded = 500; // 5 x 100

        $unitBox = \App\Models\Unit::firstOrCreate(['name' => 'Box'], ['symbol' => 'box']);

        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => $boxesBought,
                'unit_id' => $unitBox->id,
                'unit_name' => 'Box',
                'conversion_factor' => $conversionRate,
                'unit_price' => 200000, // Rp 200.000 per Box
                'batch_number' => 'BATCH-BOX-100',
                'expired_date' => now()->addMonths(24)->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);
        $this->service->finalizeInvoice($purchase->id, $this->user);

        $stockAfter = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $this->assertEquals($stockBefore + $expectedBaseAdded, $stockAfter);
    }

    /**
     * 4. Obat dengan batch berbeda disimpan secara terpisah di medicine_batches
     */
    public function test_different_batches_stored_separately()
    {
        $batch1 = 'BATCH-ALPHA-' . rand(100, 999);
        $batch2 = 'BATCH-BETA-' . rand(100, 999);

        $header = [
            'invoice_number' => 'INV-BATCH-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'cash',
        ];

        $items = [
            [
                'medicine_id' => $this->medicineB->kode,
                'quantity' => 10,
                'unit_price' => 15000,
                'conversion_factor' => 1,
                'batch_number' => $batch1,
                'expired_date' => '2028-06-30',
            ],
            [
                'medicine_id' => $this->medicineB->kode,
                'quantity' => 15,
                'unit_price' => 15000,
                'conversion_factor' => 1,
                'batch_number' => $batch2,
                'expired_date' => '2028-12-31',
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);
        $this->service->finalizeInvoice($purchase->id, $this->user);

        $b1 = DB::table('medicine_batches')->where('batch_number', $batch1)->first();
        $b2 = DB::table('medicine_batches')->where('batch_number', $batch2)->first();

        $this->assertNotNull($b1);
        $this->assertNotNull($b2);
        $this->assertEquals(10, (float)$b1->stock);
        $this->assertEquals(15, (float)$b2->stock);
        $this->assertStringStartsWith('2028-06-30', (string)$b1->expired_date);
        $this->assertStringStartsWith('2028-12-31', (string)$b2->expired_date);
    }

    /**
     * 5. Idempotensi: Faktur yang sudah FINAL tidak bisa difinalisasi dua kali
     */
    public function test_idempotency_prevents_double_finalization()
    {
        $header = [
            'invoice_number' => 'INV-IDEM-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => 10,
                'unit_price' => 7000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-IDEM-01',
                'expired_date' => now()->addYear()->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);
        $this->service->finalizeInvoice($purchase->id, $this->user);

        // Coba finalisasi kedua kali harus melempar exception
        $this->expectException(Exception::class);
        $this->service->finalizeInvoice($purchase->id, $this->user);
    }

    /**
     * 6. Pembatalan faktur final menghasilkan transaksi mutasi pembalik (reversal)
     */
    public function test_cancellation_reverses_stock_and_records_reversal()
    {
        $stockBefore = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $header = [
            'invoice_number' => 'INV-REV-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        $qty = 20;
        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => $qty,
                'unit_price' => 7000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-REV-01',
                'expired_date' => now()->addYear()->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);
        $this->service->finalizeInvoice($purchase->id, $this->user);

        // Batalkan faktur yang sudah final
        $cancelled = $this->service->cancelInvoice($purchase->id, "Barang rusak saat dibongkar", $this->user);

        $this->assertEquals('CANCELLED', $cancelled->status);

        // Stok outlet harus kembali seperti sebelum difinalisasi
        $stockAfterCancel = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $this->assertEquals($stockBefore, $stockAfterCancel);

        // Harus ada mutasi reversal keluar (type = out, movement_type = PURCHASE_CANCELLATION_REVERSAL)
        $hasReversal = DB::table('stock_movements')
            ->where('medicine_id', $this->medicineA->kode)
            ->where('type', 'out')
            ->where('movement_type', 'PURCHASE_CANCELLATION_REVERSAL')
            ->where('quantity', $qty)
            ->exists();
        $this->assertTrue($hasReversal);
    }

    /**
     * 7. Perhitungan diskon, pajak (PPN), dan total faktur akurat
     */
    public function test_calculations_accuracy_with_tax_and_discounts()
    {
        $header = [
            'discount_type' => 'percentage',
            'discount_rate' => 10, // 10% diskon faktur
            'tax_type' => 'ppn_11', // PPN 11%
            'shipping_cost' => 50000,
            'paid_amount' => 200000,
        ];

        $items = [
            [
                'quantity' => 10,
                'unit_price' => 100000, // 10 x 100.000 = 1.000.000
                'discount_percent' => 5, // 5% = 50.000
                'tax_percent' => 0,
            ],
            [
                'quantity' => 2,
                'unit_price' => 50000, // 2 x 50.000 = 100.000
                'discount_percent' => 0,
                'tax_percent' => 0,
            ]
        ];

        $calc = $this->service->calculateTotals($header, $items);

        // Subtotal bruto = 1.000.000 + 100.000 = 1.100.000
        $this->assertEquals(1100000, $calc['subtotal']);

        // Item net = (1.000.000 - 50.000) + 100.000 = 1.050.000
        $this->assertEquals(1050000, $calc['total_items_net']);

        // Diskon faktur 10% dari 1.050.000 = 105.000
        $this->assertEquals(105000, $calc['discount_amount']);

        // DPP setelah diskon = 1.050.000 - 105.000 = 945.000
        // PPN 11% dari 945.000 = 103.950
        $this->assertEquals(103950, $calc['tax_amount']);

        // Grand Total = 945.000 + 103.950 (PPN) + 50.000 (Ongkir) = 1.098.950
        $this->assertEquals(1098950, $calc['grand_total']);

        // Sisa hutang = 1.098.950 - 200.000 = 898.950
        $this->assertEquals(898950, $calc['remaining_amount']);
        $this->assertEquals('partial', $calc['payment_status']);
    }

    /**
     * 8. Pembayaran sebagian atau belum lunas TIDAK menghalangi penambahan stok
     */
    public function test_unpaid_status_does_not_block_stock_entry()
    {
        $stockBefore = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $header = [
            'invoice_number' => 'INV-UNPAID-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
            'paid_amount' => 0, // Belum dibayar sama sekali (Hutang 100%)
        ];

        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => 15,
                'unit_price' => 7000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-UNPAID-01',
                'expired_date' => now()->addYear()->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);
        $finalized = $this->service->finalizeInvoice($purchase->id, $this->user);

        // Status pembayaran = unpaid
        $this->assertEquals('unpaid', $finalized->payment_status);

        // Namun stok fisik tetap bertambah karena penerimaan barang telah difinalisasi
        $stockAfter = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $this->assertEquals($stockBefore + 15, $stockAfter);
    }

    /**
     * 9. Faktur berisi banyak obat berhasil difinalisasi sekaligus
     */
    public function test_multi_medicine_invoice_finalization()
    {
        $stockA_before = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $stockB_before = (float)ProductStock::where('obat_id', $this->medicineB->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $header = [
            'invoice_number' => 'INV-MULTI-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => 12,
                'unit_price' => 7000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-MA-01',
                'expired_date' => now()->addYear()->toDateString(),
            ],
            [
                'medicine_id' => $this->medicineB->kode,
                'quantity' => 8,
                'unit_price' => 18000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-MB-01',
                'expired_date' => now()->addYears(2)->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);
        $this->service->finalizeInvoice($purchase->id, $this->user);

        $stockA_after = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $stockB_after = (float)ProductStock::where('obat_id', $this->medicineB->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $this->assertEquals($stockA_before + 12, $stockA_after);
        $this->assertEquals($stockB_before + 8, $stockB_after);
    }

    /**
     * 10. Kegagalan pada salah satu item menyebabkan seluruh proses rollback
     */
    public function test_atomic_rollback_on_failed_item()
    {
        $stockA_before = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');

        $header = [
            'invoice_number' => 'INV-FAIL-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        // Item 1 valid, Item 2 batch sengaja dikosongkan untuk memicu exception pada finalisasi
        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => 30,
                'unit_price' => 7000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-OK',
                'expired_date' => now()->addYear()->toDateString(),
            ],
            [
                'medicine_id' => $this->medicineB->kode,
                'quantity' => 10,
                'unit_price' => 18000,
                'conversion_factor' => 1,
                'batch_number' => '', // KOSONG -> akan memicu exception
                'expired_date' => now()->addYear()->toDateString(),
            ]
        ];

        $purchase = $this->service->saveInvoice($header, $items, null, $this->user);

        try {
            $this->service->finalizeInvoice($purchase->id, $this->user);
            $this->fail("Harusnya memicu exception karena nomor batch kosong.");
        } catch (Exception $e) {
            // Exception tertangkap
        }

        // Pastikan stok Item 1 TIDAK bertambah karena rollback
        $stockA_after = (float)ProductStock::where('obat_id', $this->medicineA->kode)
            ->where('outlet_id', $this->outlet->id)
            ->value('stock');
        $this->assertEquals($stockA_before, $stockA_after);

        // Faktur tetap berstatus DRAFT, tidak berubah jadi FINAL
        $freshPurchase = Purchase::find($purchase->id);
        $this->assertEquals('DRAFT', $freshPurchase->status);
    }

    /**
     * 11. Validasi duplikasi nomor faktur supplier untuk supplier yang sama
     */
    public function test_duplicate_supplier_invoice_number_throws_exception()
    {
        $duplicateInvNumber = 'INV-DUP-' . rand(1000, 9999);

        $header = [
            'invoice_number' => $duplicateInvNumber,
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'purchase_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'payment_type' => 'credit',
        ];

        $items = [
            [
                'medicine_id' => $this->medicineA->kode,
                'quantity' => 5,
                'unit_price' => 7000,
                'conversion_factor' => 1,
                'batch_number' => 'BATCH-D1',
                'expired_date' => now()->addYear()->toDateString(),
            ]
        ];

        // Buat faktur pertama dengan nomor faktur supplier tsb
        $this->service->saveInvoice($header, $items, null, $this->user);

        // Coba buat faktur kedua dengan supplier yang sama dan nomor faktur yang sama
        $this->expectException(Exception::class);
        $this->service->saveInvoice($header, $items, null, $this->user);
    }

    /**
     * 12. Halaman buat faktur dari PO (prefilled PO) berhasil dimuat tanpa error obats.barcode
     */
    public function test_create_invoice_page_with_prefilled_po_loads_successfully()
    {
        $poId = DB::table('purchase_orders')->insertGetId([
            'po_number' => 'PO-TEST-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'outlet_id' => $this->outlet->id,
            'created_by' => $this->user->id,
            'order_date' => now()->toDateString(),
            'status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_order_items')->insert([
            'purchase_order_id' => $poId,
            'medicine_id' => $this->medicineA->kode,
            'order_quantity' => 10,
            'unit_price' => 7000,
            'subtotal' => 70000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('purchases.invoices.create', ['po_id' => $poId]));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Purchases/Invoices/Create')
            ->has('prefilled_po')
            ->where('prefilled_po.id', $poId)
        );
    }
}
