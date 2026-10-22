<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductReturn;
use App\Models\ProductReturnItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\ProfitLossService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfitLossCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $kasir;
    protected $outletA;
    protected $outletB;
    protected $obat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletA = Outlet::create([
            'name' => 'Outlet Pusat',
            'code' => 'OUT-A',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->outletB = Outlet::create([
            'name' => 'Outlet Cabang',
            'code' => 'OUT-B',
            'is_main' => false,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $this->admin->outlets()->attach([$this->outletA->id, $this->outletB->id]);

        $this->kasir = User::factory()->create([
            'role' => 'kasir',
            'status' => 'active',
        ]);
        $this->kasir->outlets()->attach($this->outletA->id);

        $this->obat = Obat::create([
            'kode' => 'OBT-PL-01',
            'nama' => 'Vitamin C 500mg',
            'jenis_obat' => 'Tablet',
            'kategori' => 'Suplemen',
            'harga' => 10000,
            'harga_beli' => 6000,
            'stok' => 200,
            'satuan' => 'Strip',
        ]);
    }

    public function test_profit_loss_formulas_net_sales_gross_profit_and_net_profit()
    {
        // Sale 1: 10 items @ 10,000 = 100,000 subtotal, discount 10,000 => grand total 90,000
        // COGS: 10 * 6,000 = 60,000
        $sale1 = Sale::create([
            'invoice_number' => 'INV-PL-001',
            'cashier_id' => $this->kasir->id,
            'outlet_id' => $this->outletA->id,
            'sale_date' => now()->toDateString(),
            'subtotal' => 100000,
            'discount' => 10000,
            'grand_total' => 90000,
            'paid_amount' => 90000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        SaleItem::create([
            'sale_id' => $sale1->id,
            'medicine_id' => $this->obat->kode,
            'quantity' => 10,
            'unit_price' => 10000,
            'buy_price' => 6000,
            'discount' => 10000,
            'subtotal' => 90000,
        ]);

        // Return: 2 items returned @ 10,000 = 20,000
        $ret = ProductReturn::create([
            'return_number' => 'RET-PL-001',
            'type' => 'sale',
            'reference_id' => $sale1->id,
            'outlet_id' => $this->outletA->id,
            'user_id' => $this->admin->id,
            'status' => 'APPROVED',
            'total_amount' => 20000,
            'refund_method' => 'Cash',
            'refund_amount' => 20000,
            'created_at' => now(),
        ]);

        // Operating Expense: 15,000 (e.g. Listrik)
        $catId = DB::table('expense_categories')->insertGetId([
            'name' => 'Operasional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('expenses')->insert([
            'category_id' => $catId,
            'outlet_id' => $this->outletA->id,
            'user_id' => $this->admin->id,
            'expense_date' => now()->toDateString(),
            'amount' => 15000,
            'reference_number' => 'EXP-001',
            'description' => 'Listrik',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Uncompleted / cancelled sale (must NOT be counted)
        $cancelledSale = Sale::create([
            'invoice_number' => 'INV-PL-CANCELLED',
            'cashier_id' => $this->kasir->id,
            'outlet_id' => $this->outletA->id,
            'sale_date' => now()->toDateString(),
            'subtotal' => 50000,
            'discount' => 0,
            'grand_total' => 50000,
            'paid_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'cancelled',
        ]);

        $service = app(ProfitLossService::class);
        $data = $service->getProfitLossData([
            'period' => 'today',
            'outlet_id' => $this->outletA->id,
        ]);

        $metrics = $data['metrics'];

        // Gross Sales: 100,000 (subtotal before discount)
        $this->assertEquals(100000, $metrics['gross_sales']);

        // Total Discount: 10,000
        $this->assertEquals(100000 - 90000, $metrics['total_discount']);

        // Total Returns: 20,000
        $this->assertEquals(20000, $metrics['total_returns']);

        // Net Sales = Gross Sales (100,000) - Discount (10,000) - Returns (20,000) = 70,000
        $this->assertEquals(70000, $metrics['net_sales']);

        // COGS = 10 * 6,000 = 60,000
        $this->assertEquals(60000, $metrics['cogs']);

        // Gross Profit = Net Sales (70,000) - COGS (60,000) = 10,000
        $this->assertEquals(10000, $metrics['gross_profit']);

        // Operating Expenses = 15,000
        $this->assertEquals(15000, $metrics['total_expenses']);

        // Net Profit = Gross Profit (10,000) - Expenses (15,000) = -5,000
        $this->assertEquals(-5000, $metrics['net_profit']);

        // Total completed transactions count must be exactly 1 (cancelled excluded)
        $this->assertEquals(1, $metrics['total_transactions']);
    }

    public function test_profit_loss_outlet_isolation()
    {
        // Sale on Outlet A
        Sale::create([
            'invoice_number' => 'INV-OUT-A',
            'cashier_id' => $this->kasir->id,
            'outlet_id' => $this->outletA->id,
            'sale_date' => now()->toDateString(),
            'subtotal' => 100000,
            'discount' => 0,
            'grand_total' => 100000,
            'paid_amount' => 100000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $service = app(ProfitLossService::class);

        // Querying Outlet B must yield 0 sales
        $dataB = $service->getProfitLossData([
            'period' => 'today',
            'outlet_id' => $this->outletB->id,
        ]);

        $this->assertEquals(0, $dataB['metrics']['total_transactions']);
        $this->assertEquals(0, $dataB['metrics']['net_sales']);

        // Querying Outlet A yields the 1 transaction
        $dataA = $service->getProfitLossData([
            'period' => 'today',
            'outlet_id' => $this->outletA->id,
        ]);
        $this->assertEquals(1, $dataA['metrics']['total_transactions']);
        $this->assertEquals(100000, $dataA['metrics']['net_sales']);
    }
}
