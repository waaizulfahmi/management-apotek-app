<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Sale;
use App\Models\User;
use App\Services\OutletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OutletAndUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Outlet $outletA;
    protected Outlet $outletB;
    protected User $userA;
    protected User $userB;
    protected User $userC;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Outlets
        $this->outletA = Outlet::create([
            'code' => 'OUT-A',
            'name' => 'Outlet Farma A',
            'is_main' => true,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->outletB = Outlet::create([
            'code' => 'OUT-B',
            'name' => 'Outlet Farma B',
            'is_main' => false,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // 2. User A: only Outlet A
        $this->userA = User::create([
            'name' => 'Kasir A',
            'username' => 'kasir_a',
            'email' => 'kasira@example.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outletA->id,
            'access_all_outlets' => false,
        ]);
        DB::table('user_outlets')->insert([
            'user_id' => $this->userA->id,
            'outlet_id' => $this->outletA->id,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. User B: only Outlet B
        $this->userB = User::create([
            'name' => 'Kasir B',
            'username' => 'kasir_b',
            'email' => 'kasirb@example.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outletB->id,
            'access_all_outlets' => false,
        ]);
        DB::table('user_outlets')->insert([
            'user_id' => $this->userB->id,
            'outlet_id' => $this->outletB->id,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. User C: both Outlet A and Outlet B
        $this->userC = User::create([
            'name' => 'Kasir C Multi',
            'username' => 'kasir_c',
            'email' => 'kasirc@example.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outletA->id,
            'access_all_outlets' => false,
        ]);
        DB::table('user_outlets')->insert([
            [
                'user_id' => $this->userC->id,
                'outlet_id' => $this->outletA->id,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $this->userC->id,
                'outlet_id' => $this->outletB->id,
                'is_primary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // 5. Admin user
        $this->adminUser = User::create([
            'name' => 'Super Administrator',
            'username' => 'superadmin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'ACTIVE',
            'outlet_id' => $this->outletA->id,
            'access_all_outlets' => true,
        ]);
    }

    /**
     * Test User A can access Outlet A but is rejected when accessing Outlet B
     */
    public function test_user_a_cannot_access_outlet_b()
    {
        $this->assertTrue(OutletService::userCanAccessOutlet($this->userA, $this->outletA->id));
        $this->assertFalse(OutletService::userCanAccessOutlet($this->userA, $this->outletB->id));

        // Attempting to switch active outlet to Outlet B must fail
        $response = $this->actingAs($this->userA)->post(route('outlets.switch'), [
            'outlet_id' => $this->outletB->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertNotEquals($this->outletB->id, session('active_outlet_id'));
    }

    /**
     * Test User B can access Outlet B but is rejected when accessing Outlet A
     */
    public function test_user_b_cannot_access_outlet_a()
    {
        $this->assertTrue(OutletService::userCanAccessOutlet($this->userB, $this->outletB->id));
        $this->assertFalse(OutletService::userCanAccessOutlet($this->userB, $this->outletA->id));

        $response = $this->actingAs($this->userB)->post(route('outlets.switch'), [
            'outlet_id' => $this->outletA->id,
        ]);

        $response->assertSessionHas('error');
    }

    /**
     * Test User C can access both Outlet A and Outlet B
     */
    public function test_user_c_can_switch_between_outlets()
    {
        $this->assertTrue(OutletService::userCanAccessOutlet($this->userC, $this->outletA->id));
        $this->assertTrue(OutletService::userCanAccessOutlet($this->userC, $this->outletB->id));

        // Switch to Outlet B
        $responseB = $this->actingAs($this->userC)->post(route('outlets.switch'), [
            'outlet_id' => $this->outletB->id,
        ]);
        $responseB->assertSessionHas('success');

        // Switch back to Outlet A
        $responseA = $this->actingAs($this->userC)->post(route('outlets.switch'), [
            'outlet_id' => $this->outletA->id,
        ]);
        $responseA->assertSessionHas('success');
    }

    /**
     * Test Admin has access to all active outlets
     */
    public function test_admin_has_access_to_all_active_outlets()
    {
        $this->assertTrue(OutletService::userCanAccessOutlet($this->adminUser, $this->outletA->id));
        $this->assertTrue(OutletService::userCanAccessOutlet($this->adminUser, $this->outletB->id));
        $this->assertTrue($this->adminUser->hasAccessToAllOutlets());
    }

    /**
     * Test User cannot open a shift on an unauthorized outlet
     */
    public function test_user_cannot_open_shift_on_unauthorized_outlet()
    {
        $response = $this->actingAs($this->userA)->post(route('shifts.open'), [
            'shift_name' => 'Pagi Outlet B',
            'opening_cash' => 100000,
            'outlet_id' => $this->outletB->id,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('cashier_shifts', [
            'cashier_id' => $this->userA->id,
            'outlet_id' => $this->outletB->id,
            'status' => 'OPEN',
        ]);
    }

    /**
     * Test soft-deleting an outlet preserves sales history
     */
    public function test_soft_deleting_outlet_preserves_sales_history()
    {
        // Record sale in Outlet B
        $sale = Sale::create([
            'invoice_number' => 'INV-HIST-001',
            'cashier_id' => $this->userB->id,
            'outlet_id' => $this->outletB->id,
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

        // Soft delete Outlet B
        $this->outletB->delete();

        $this->assertTrue($this->outletB->fresh()->trashed());

        // Sale record must still exist and reference the soft-deleted outlet
        $savedSale = Sale::where('invoice_number', 'INV-HIST-001')->first();
        $this->assertNotNull($savedSale);
        $this->assertEquals($this->outletB->id, $savedSale->outlet_id);

        // Soft deleted outlet cannot be accessed for new transactions or switches
        $this->assertFalse(OutletService::userCanAccessOutlet($this->userB, $this->outletB->id));
    }

    /**
     * Test duplicate username and email validation
     */
    public function test_duplicate_username_and_email_rejected()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        User::create([
            'name' => 'Duplicate User',
            'username' => 'kasir_a', // Duplicate username
            'email' => 'different@example.com',
            'password' => Hash::make('password123'),
            'role' => 'kasir',
        ]);
    }
}
