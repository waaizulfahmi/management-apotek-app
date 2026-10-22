<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('stock_movements')) {
            return;
        }

        // 1. Fix movement_type = 'RETURN' to 'SALE_RETURN' or 'PURCHASE_RETURN'
        DB::table('stock_movements')
            ->where('movement_type', 'RETURN')
            ->where('reference_number', 'LIKE', 'RET-S%')
            ->update(['movement_type' => 'SALE_RETURN']);

        DB::table('stock_movements')
            ->where('movement_type', 'RETURN')
            ->where('reference_number', 'LIKE', 'RET-P%')
            ->update(['movement_type' => 'PURCHASE_RETURN']);

        // Default any remaining 'RETURN'
        DB::table('stock_movements')
            ->where('movement_type', 'RETURN')
            ->update(['movement_type' => 'SALE_RETURN']);

        // 2. Fix corrupted rows where stock_before == 0 and stock_after == 0 for returns
        $corrupted = DB::table('stock_movements')
            ->where('stock_before', 0)
            ->where('stock_after', 0)
            ->where(function ($q) {
                $q->where('notes', 'LIKE', '%RUSAK%')
                  ->orWhere('reference_number', 'LIKE', 'RET-%')
                  ->orWhereIn('movement_type', ['SALE_RETURN', 'PURCHASE_RETURN']);
            })
            ->orderBy('id', 'asc')
            ->get();

        foreach ($corrupted as $row) {
            $prev = DB::table('stock_movements')
                ->where('medicine_id', $row->medicine_id)
                ->when($row->outlet_id, fn($q) => $q->where('outlet_id', $row->outlet_id))
                ->where('id', '<', $row->id)
                ->orderBy('id', 'desc')
                ->first();

            $stock = $prev ? (float)$prev->stock_after : 0;
            if ($stock == 0 && Schema::hasTable('product_stocks')) {
                $pStock = DB::table('product_stocks')
                    ->where('obat_id', $row->medicine_id)
                    ->when($row->outlet_id, fn($q) => $q->where('outlet_id', $row->outlet_id))
                    ->value('stock');
                $stock = $pStock ? (float)$pStock : 0;
            }

            if ($stock == 0) {
                $medStock = DB::table('obats')->where('kode', $row->medicine_id)->value('stok');
                $stock = $medStock ? (float)$medStock : 0;
            }

            $isDamaged = stripos((string)$row->notes, 'RUSAK') !== false;

            DB::table('stock_movements')
                ->where('id', $row->id)
                ->update([
                    'stock_before' => $stock,
                    'stock_after' => $stock,
                    'type' => $isDamaged ? 'return' : $row->type,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
