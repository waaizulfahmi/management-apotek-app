<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class InventoryController extends Controller
{
    public function movements()
    {
        $movements = DB::table('stock_movements')
            ->join('obats', 'stock_movements.medicine_id', '=', 'obats.kode')
            ->leftJoin('medicine_batches', 'stock_movements.batch_id', '=', 'medicine_batches.id')
            ->join('users', 'stock_movements.user_id', '=', 'users.id')
            ->select('stock_movements.*', 'obats.nama as medicine_name', 'medicine_batches.batch_number', 'users.name as user_name')
            ->orderBy('stock_movements.id', 'desc')
            ->paginate(15);

        return Inertia::render('Inventory/Movements', [
            'movements' => $movements,
        ]);
    }

    public function stockOpname()
    {
        $opnames = DB::table('stock_opnames')
            ->join('users', 'stock_opnames.user_id', '=', 'users.id')
            ->select('stock_opnames.*', 'users.name as user_name')
            ->orderBy('stock_opnames.id', 'desc')
            ->paginate(10);

        $medicines = DB::table('obats')->get();

        return Inertia::render('Inventory/StockOpname', [
            'opnames' => $opnames,
            'medicines' => $medicines,
        ]);
    }

    public function storeStockOpname(Request $request)
    {
        $request->validate([
            'opname_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:obats,kode',
            'items.*.physical_stock' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $opnameNumber = 'SOP-' . date('YmdHis') . '-' . rand(100, 999);
            $userId = auth()->id();

            $opnameId = DB::table('stock_opnames')->insertGetId([
                'opname_number' => $opnameNumber,
                'opname_date' => $request->opname_date,
                'user_id' => $userId,
                'status' => 'approved',
                'notes' => $request->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($request->items as $item) {
                $medicine = DB::table('obats')->where('kode', $item['medicine_id'])->first();
                $systemStock = $medicine->stok;
                $physicalStock = $item['physical_stock'];
                $difference = $physicalStock - $systemStock;

                DB::table('stock_opname_items')->insert([
                    'stock_opname_id' => $opnameId,
                    'medicine_id' => $item['medicine_id'],
                    'system_stock' => $systemStock,
                    'physical_stock' => $physicalStock,
                    'difference' => $difference,
                    'reason' => $item['reason'] ?? 'Stock Opname Fisik',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Adjust stock on medicine table
                DB::table('obats')->where('kode', $item['medicine_id'])->update(['stok' => $physicalStock]);

                // Create Stock Movement log (adjustment)
                DB::table('stock_movements')->insert([
                    'medicine_id' => $item['medicine_id'],
                    'type' => 'adjustment',
                    'quantity' => abs($difference),
                    'stock_before' => $systemStock,
                    'stock_after' => $physicalStock,
                    'reference_number' => $opnameNumber,
                    'user_id' => $userId,
                    'notes' => "Opname (Selisih: {$difference})",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return redirect()->route('inventory.opname')->with('success', 'Stock Opname berhasil diproses & stok fisik telah disesuaikan!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
