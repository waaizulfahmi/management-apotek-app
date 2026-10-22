<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Obat;
use App\Models\Outlet;
use App\Models\User;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipAndStockCardTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $outletA;
    protected $outletB;
    protected $customer;
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

        $this->customer = Customer::create([
            'code' => 'CUST-001',
            'name' => 'Siti Rahma',
            'phone' => '081299887766',
            'points' => 50,
        ]);

        $this->obat = Obat::create([
            'kode' => 'OBT-MC-01',
            'nama' => 'Ibuprofen 400mg',
            'jenis_obat' => 'Tablet',
            'kategori' => 'Obat Bebas Terbatas',
            'harga' => 8000,
            'harga_beli' => 5000,
            'stok' => 100,
            'satuan' => 'Strip',
        ]);
    }

    public function test_customer_duplicate_phone_validation()
    {
        // Attempting to register another customer with the same phone must fail
        $response = $this->actingAs($this->admin)->post(route('customers.store'), [
            'code' => 'CUST-002',
            'name' => 'Duplikat Siti',
            'phone' => '081299887766', // same phone
        ]);

        $response->assertSessionHasErrors(['phone']);
    }

    public function test_loyalty_point_earning_and_idempotency()
    {
        $pointService = app(PointService::class);

        // Transaction 1: 50,000 should earn 5 points (rate 10,000 = 1 point)
        $tx1 = $pointService->earnPoints(
            $this->customer,
            50000,
            'Sale',
            'INV-001',
            'Belanja POS',
            $this->admin->id
        );

        $this->assertEquals(5, $tx1->points_in);
        $this->assertEquals(55, $this->customer->fresh()->points);

        // Attempting to earn again with identical sale invoice reference must return existing record and NOT double points
        $txDuplicate = $pointService->earnPoints(
            $this->customer,
            50000,
            'Sale',
            'INV-001',
            'Belanja POS Duplicate Call',
            $this->admin->id
        );

        $this->assertEquals($tx1->id, $txDuplicate->id);
        $this->assertEquals(55, $this->customer->fresh()->points);
    }

    public function test_stock_movement_card_running_balance_formula()
    {
        // 1. Initial Opening Stock in past
        DB::table('stock_movements')->insert([
            'movement_number' => 'SM-INIT',
            'outlet_id' => $this->outletA->id,
            'medicine_id' => $this->obat->kode,
            'type' => 'in',
            'movement_type' => 'PURCHASE',
            'quantity' => 100,
            'stock_before' => 0,
            'stock_after' => 100,
            'user_id' => $this->admin->id,
            'status' => 'POSTED',
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);

        // 2. Out movement in current month: Sale of 30
        DB::table('stock_movements')->insert([
            'movement_number' => 'SM-SALE-01',
            'outlet_id' => $this->outletA->id,
            'medicine_id' => $this->obat->kode,
            'type' => 'out',
            'movement_type' => 'SALE',
            'quantity' => 30,
            'stock_before' => 100,
            'stock_after' => 70,
            'user_id' => $this->admin->id,
            'status' => 'POSTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. In movement in current month: Return of 5
        DB::table('stock_movements')->insert([
            'movement_number' => 'SM-RET-01',
            'outlet_id' => $this->outletA->id,
            'medicine_id' => $this->obat->kode,
            'type' => 'in',
            'movement_type' => 'RETURN',
            'quantity' => 5,
            'stock_before' => 70,
            'stock_after' => 75,
            'user_id' => $this->admin->id,
            'status' => 'POSTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Calculate card values for Outlet A:
        $opening = DB::table('stock_movements')
            ->where('outlet_id', $this->outletA->id)
            ->where('medicine_id', $this->obat->kode)
            ->whereDate('created_at', '<', now()->startOfMonth()->toDateString())
            ->where('status', 'POSTED');

        $openingStock = (clone $opening)->where('type', 'in')->sum('quantity') - (clone $opening)->where('type', 'out')->sum('quantity');
        $this->assertEquals(100, $openingStock);

        $currPeriod = DB::table('stock_movements')
            ->where('outlet_id', $this->outletA->id)
            ->where('medicine_id', $this->obat->kode)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->where('status', 'POSTED');

        $totalIn = (clone $currPeriod)->where('type', 'in')->sum('quantity');
        $totalOut = (clone $currPeriod)->where('type', 'out')->sum('quantity');

        $this->assertEquals(5, $totalIn);
        $this->assertEquals(30, $totalOut);

        // Formula: Stok Akhir = Stok Awal + Total Masuk - Total Keluar
        $closingStock = $openingStock + $totalIn - $totalOut;
        $this->assertEquals(75, $closingStock);
    }
}
