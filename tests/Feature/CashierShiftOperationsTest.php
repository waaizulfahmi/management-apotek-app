<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\Sale;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Exception;

class CashierShiftOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outlet;
    protected User $cashier1;
    protected User $cashier2;
    protected ShiftService $shiftService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shiftService = app(ShiftService::class);

        $this->outlet = Outlet::create([
            'code' => 'OUT-MAIN',
            'name' => 'Outlet Farmasi Utama',
            'is_main' => true,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->cashier1 = User::create([
            'name' => 'Budi Kasir 1',
            'username' => 'budi_kasir',
            'email' => 'budi@example.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outlet->id,
            'access_all_outlets' => false,
        ]);
        DB::table('user_outlets')->insert([
            'user_id' => $this->cashier1->id,
            'outlet_id' => $this->outlet->id,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cashier2 = User::create([
            'name' => 'Siti Kasir 2',
            'username' => 'siti_kasir',
            'email' => 'siti@example.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outlet->id,
            'access_all_outlets' => false,
        ]);
        DB::table('user_outlets')->insert([
            'user_id' => $this->cashier2->id,
            'outlet_id' => $this->outlet->id,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * 1. Test opening shift records initial cash accurately
     */
    public function test_open_shift_records_initial_cash()
    {
        $shift = $this->shiftService->openShift(
            $this->cashier1->id,
            $this->outlet->id,
            'Shift Pagi 1',
            150000
        );

        $this->assertEquals('OPEN', $shift->status);
        $this->assertEquals(150000, (float)$shift->opening_cash);
        $this->assertEquals(150000, (float)$shift->expected_cash);
        $this->assertEquals($this->cashier1->id, $shift->cashier_id);
    }

    /**
     * 2. Test expected cash only accumulates cash sales, not QRIS or Transfer
     */
    public function test_expected_cash_separates_cash_from_non_cash_sales()
    {
        $shift = $this->shiftService->openShift(
            $this->cashier1->id,
            $this->outlet->id,
            'Shift Pagi 1',
            100000
        );

        // Transaction 1: Cash Rp 50,000
        Sale::create([
            'invoice_number' => 'INV-TX-001',
            'cashier_id' => $this->cashier1->id,
            'shift_id' => $shift->id,
            'outlet_id' => $this->outlet->id,
            'sale_date' => now(),
            'subtotal' => 50000,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 50000,
            'paid_amount' => 50000,
            'change_amount' => 0,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        // Transaction 2: QRIS Rp 75,000
        Sale::create([
            'invoice_number' => 'INV-TX-002',
            'cashier_id' => $this->cashier1->id,
            'shift_id' => $shift->id,
            'outlet_id' => $this->outlet->id,
            'sale_date' => now(),
            'subtotal' => 75000,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 75000,
            'paid_amount' => 75000,
            'change_amount' => 0,
            'payment_method' => 'qris',
            'status' => 'completed',
        ]);

        // Transaction 3: Transfer Bank Rp 120,000
        Sale::create([
            'invoice_number' => 'INV-TX-003',
            'cashier_id' => $this->cashier1->id,
            'shift_id' => $shift->id,
            'outlet_id' => $this->outlet->id,
            'sale_date' => now(),
            'subtotal' => 120000,
            'discount' => 0,
            'tax' => 0,
            'grand_total' => 120000,
            'paid_amount' => 120000,
            'change_amount' => 0,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);

        $updatedShift = $this->shiftService->recalculateShiftMetrics($shift->id);

        $this->assertEquals(50000, (float)$updatedShift->cash_sales);
        $this->assertEquals(195000, (float)$updatedShift->non_cash_sales); // 75,000 + 120,000
        // Expected cash in drawer must be 100,000 opening + 50,000 cash = 150,000 (QRIS and Transfer are NOT in drawer)
        $this->assertEquals(150000, (float)$updatedShift->expected_cash);
    }

    /**
     * 3. Test cash movements (kas masuk / kas keluar) update expected cash
     */
    public function test_cash_movements_update_expected_cash()
    {
        $shift = $this->shiftService->openShift(
            $this->cashier1->id,
            $this->outlet->id,
            'Shift Pagi 1',
            100000
        );

        // Kas Masuk (e.g. tambah modal kecil) Rp 50,000
        $this->shiftService->addCashMovement($shift->id, 'in', 50000, 'Tambah modal kembalian', $this->cashier1->id);

        // Kas Keluar (e.g. beli plastik/konsumsi) Rp 20,000
        $this->shiftService->addCashMovement($shift->id, 'out', 20000, 'Beli plastik obat', $this->cashier1->id);

        $shift->refresh();

        $this->assertEquals(30000, (float)$shift->cash_adjustments); // 50,000 - 20,000
        $this->assertEquals(130000, (float)$shift->expected_cash); // 100,000 + 30,000
    }

    /**
     * 4. Test closing shift calculates cash difference and seals the shift
     */
    public function test_close_shift_calculates_difference()
    {
        $shift = $this->shiftService->openShift(
            $this->cashier1->id,
            $this->outlet->id,
            'Shift Pagi 1',
            100000
        );

        // Expected cash is 100,000. Cashier counts physical cash = 95,000 (selisih minus 5,000)
        $closedShift = $this->shiftService->closeShift($shift->id, 95000, 'Selisih kurang 5rb karena salah kembalian', $this->cashier1->id);

        $this->assertEquals('CLOSED', $closedShift->status);
        $this->assertEquals(95000, (float)$closedShift->actual_cash);
        $this->assertEquals(-5000, (float)$closedShift->difference);
        $this->assertNotNull($closedShift->closed_at);
    }

    /**
     * 5. Test preventing double closing of a shift
     */
    public function test_prevent_double_shift_close()
    {
        $shift = $this->shiftService->openShift(
            $this->cashier1->id,
            $this->outlet->id,
            'Shift Pagi 1',
            100000
        );

        $this->shiftService->closeShift($shift->id, 100000, 'Tutup shift 1', $this->cashier1->id);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('sudah ditutup');

        // Second close must throw exception
        $this->shiftService->closeShift($shift->id, 100000, 'Tutup kedua kali', $this->cashier1->id);
    }

    /**
     * 6. Cashier cannot close another cashier's shift (IDOR)
     */
    public function test_cashier_cannot_close_another_cashiers_shift()
    {
        $shift = $this->shiftService->openShift(
            $this->cashier1->id,
            $this->outlet->id,
            'Shift Kasir 1',
            100000
        );

        // Cashier 2 tries to close Cashier 1's shift via controller endpoint
        $response = $this->actingAs($this->cashier2)->post(route('shifts.close', $shift->id), [
            'actual_cash' => 100000,
            'notes' => 'Tutup paksa punya orang lain',
        ]);

        $response->assertStatus(403);
    }
}
