<?php

namespace Tests\Feature;

use App\Models\Obat;
use App\Models\Outlet;
use App\Models\ProductStock;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockOpnameManualTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
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

        $this->obat = Obat::create([
            'kode' => 'OBT-SO-01',
            'nama' => 'Paracetamol 500mg',
            'jenis_obat' => 'Tablet',
            'kategori' => 'Obat Bebas',
            'harga' => 5000,
            'harga_beli' => 3000,
            'stok' => 150,
            'satuan' => 'Strip',
        ]);

        ProductStock::create([
            'obat_id' => $this->obat->kode,
            'outlet_id' => $this->outletA->id,
            'stock' => 100,
        ]);

        ProductStock::create([
            'obat_id' => $this->obat->kode,
            'outlet_id' => $this->outletB->id,
            'stock' => 50,
        ]);
    }

    public function test_create_draft_stock_opname_takes_snapshot_without_altering_stock()
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_outlet_id' => $this->outletB->id])
            ->post(route('opname.store'), [
                'notes' => 'SO Bulanan Outlet B',
            ]);

        $response->assertStatus(302);

        $so = StockOpname::where('outlet_id', $this->outletB->id)->first();
        $this->assertNotNull($so);
        $this->assertEquals('DRAFT', $so->status);

        $item = StockOpnameItem::where('stock_opname_id', $so->id)
            ->where('medicine_id', $this->obat->kode)
            ->first();

        $this->assertNotNull($item);
        // System stock in snapshot should reflect Outlet B stock (50)
        $this->assertEquals(50, $item->system_stock);

        // Actual stock in DB must remain unchanged in draft state
        $this->assertEquals(50, ProductStock::where('obat_id', $this->obat->kode)->where('outlet_id', $this->outletB->id)->value('stock'));
        $this->assertEquals(100, ProductStock::where('obat_id', $this->obat->kode)->where('outlet_id', $this->outletA->id)->value('stock'));
    }

    public function test_update_item_physical_stock_calculates_difference()
    {
        $this->actingAs($this->admin)
            ->withSession(['active_outlet_id' => $this->outletB->id])
            ->post(route('opname.store'));

        $so = StockOpname::where('outlet_id', $this->outletB->id)->first();
        $item = StockOpnameItem::where('stock_opname_id', $so->id)->first();

        $response = $this->actingAs($this->admin)
            ->postJson(route('opname.update_item', $so->id), [
                'item_id' => $item->id,
                'physical_stock' => 45, // 5 less than system stock (50)
                'reason' => 'Rusak kemasan',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'physical_stock' => 45,
                'difference' => -5,
                'difference_value' => -25000, // -5 * 5000
                'is_counted' => true,
            ],
        ]);

        $freshItem = $item->fresh();
        $this->assertEquals(45, $freshItem->physical_stock);
        $this->assertEquals(-5, $freshItem->difference);
        $this->assertTrue((bool)$freshItem->is_counted);
    }

    public function test_finalize_stock_opname_updates_target_outlet_stock_and_preserves_other_outlets()
    {
        $this->actingAs($this->admin)
            ->withSession(['active_outlet_id' => $this->outletB->id])
            ->post(route('opname.store'));

        $so = StockOpname::where('outlet_id', $this->outletB->id)->first();
        $item = StockOpnameItem::where('stock_opname_id', $so->id)->first();

        // Count physically: 48 (loss of 2)
        $this->actingAs($this->admin)->postJson(route('opname.update_item', $so->id), [
            'item_id' => $item->id,
            'physical_stock' => 48,
            'reason' => 'Selisih fisik',
        ]);

        // Finalize
        $response = $this->actingAs($this->admin)->post(route('opname.finalize', $so->id));
        $response->assertStatus(302);

        $so->refresh();
        $this->assertEquals('COMPLETED', strtoupper($so->status));

        // Outlet B stock must now be 48
        $outletBStock = ProductStock::where('obat_id', $this->obat->kode)
            ->where('outlet_id', $this->outletB->id)
            ->value('stock');
        $this->assertEquals(48, $outletBStock);

        // Outlet A stock must REMAIN 100 (No cross-outlet pollution)
        $outletAStock = ProductStock::where('obat_id', $this->obat->kode)
            ->where('outlet_id', $this->outletA->id)
            ->value('stock');
        $this->assertEquals(100, $outletAStock);

        // Stock movement card should have recorded the adjustment with outlet_id
        $movement = DB::table('stock_movements')->where('medicine_id', $this->obat->kode)
            ->where('outlet_id', $this->outletB->id)
            ->where('movement_type', 'STOCK_OPNAME')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(48, $movement->stock_after);
        $this->assertEquals('out', $movement->type);
        $this->assertEquals(2, $movement->quantity);
    }

    public function test_cannot_finalize_already_completed_stock_opname()
    {
        $this->actingAs($this->admin)
            ->withSession(['active_outlet_id' => $this->outletB->id])
            ->post(route('opname.store'));

        $so = StockOpname::where('outlet_id', $this->outletB->id)->first();
        $this->actingAs($this->admin)->post(route('opname.finalize', $so->id));

        // Attempting second finalize should fail / be rejected
        $secondAttempt = $this->actingAs($this->admin)->post(route('opname.finalize', $so->id));
        $secondAttempt->assertSessionHas('error');
    }
}
