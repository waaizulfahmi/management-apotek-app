<?php

namespace App\Services;

use App\Models\Obat;
use Illuminate\Support\Facades\DB;
use Exception;

class FefoService
{
    /**
     * Deduct stock for a medicine using FEFO (First Expired First Out)
     *
     * @param string $medicineKode
     * @param int $qtyNeeded
     * @param int $userId
     * @param string $referenceNumber
     * @return array Array of allocated batch items [{batch_id, quantity, buy_price, unit_price}]
     * @throws Exception
     */
    public static function deductStock(
        string $medicineKode, 
        int $qtyNeeded, 
        int $userId, 
        string $referenceNumber = '',
        ?int $unitId = null,
        ?string $unitName = null,
        ?int $transactionQty = null,
        float $conversionFactor = 1.0000,
        ?int $outletId = null
    ): array
    {
        $medicine = Obat::findOrFail($medicineKode);

        // Fetch active batches sorted by FEFO (Expired Date ASC)
        $batches = DB::table('medicine_batches')
            ->where('medicine_id', $medicineKode)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('expired_date', 'asc')
            ->get();

        $totalAvailableStock = $batches->sum('stock');

        // If batch stock is less than requested quantity BUT main stock is sufficient, auto-sync default batch
        if ($totalAvailableStock < $qtyNeeded && $medicine->stok >= $qtyNeeded) {
            DB::table('medicine_batches')->insert([
                'medicine_id' => $medicineKode,
                'batch_number' => 'BATCH-SYS-' . date('Ym'),
                'expired_date' => now()->addYears(2)->toDateString(),
                'stock' => $medicine->stok,
                'buy_price' => $medicine->harga * 0.7,
                'sell_price' => $medicine->harga,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $batches = DB::table('medicine_batches')
                ->where('medicine_id', $medicineKode)
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->orderBy('expired_date', 'asc')
                ->get();

            $totalAvailableStock = $batches->sum('stock');
        }

        if ($totalAvailableStock < $qtyNeeded) {
            throw new Exception("Stok obat {$medicine->nama} tidak mencukupi. Dibutuhkan: {$qtyNeeded}, Tersedia: {$totalAvailableStock}");
        }

        // Validate outlet-specific stock if outlet context is provided
        if ($outletId && \Illuminate\Support\Facades\Schema::hasTable('product_stocks')) {
            $pStock = DB::table('product_stocks')
                ->where('obat_id', $medicineKode)
                ->where('outlet_id', $outletId)
                ->first();
            $outletStock = $pStock ? (float)$pStock->stock : 0;
            if ($outletStock < $qtyNeeded) {
                throw new Exception("Stok obat {$medicine->nama} di outlet tidak mencukupi. Tersedia: {$outletStock}, Dibutuhkan: {$qtyNeeded}");
            }
        }

        $allocatedBatches = [];
        $remainingQty = $qtyNeeded;

        foreach ($batches as $batch) {
            if ($remainingQty <= 0) break;

            $deductQty = min($batch->stock, $remainingQty);
            $newStock = $batch->stock - $deductQty;

            // Update batch stock
            DB::table('medicine_batches')
                ->where('id', $batch->id)
                ->update(['stock' => $newStock, 'updated_at' => now()]);

            $smNo = 'SM-' . date('Ymd') . '-' . rand(10000, 99999);

            // Create stock movement record with unit snapshot
            DB::table('stock_movements')->insert([
                'movement_number' => $smNo,
                'medicine_id' => $medicineKode,
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number ?? 'BATCH-SYS',
                'expired_date' => $batch->expired_date ?? now()->addYears(2)->toDateString(),
                'type' => 'out',
                'movement_type' => 'SALE',
                'quantity' => $deductQty,
                'stock_before' => $batch->stock,
                'stock_after' => $newStock,
                'reference_number' => $referenceNumber,
                'user_id' => $userId,
                'outlet_id' => $outletId,
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'transaction_quantity' => $transactionQty ?? $deductQty,
                'conversion_to_base' => $conversionFactor,
                'notes' => 'Penjualan POS (FEFO)',
                'status' => 'POSTED',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $allocatedBatches[] = [
                'batch_id' => $batch->id,
                'quantity' => $deductQty,
                'buy_price' => $batch->buy_price,
                'sell_price' => $batch->sell_price,
            ];

            $remainingQty -= $deductQty;
        }

        // Update total stock on medicines table
        $medicine->decrement('stok', $qtyNeeded);

        // Update product_stocks table (outlet stock)
        if ($outletId && \Illuminate\Support\Facades\Schema::hasTable('product_stocks')) {
            $pStock = DB::table('product_stocks')
                ->where('obat_id', $medicineKode)
                ->where('outlet_id', $outletId)
                ->first();

            if ($pStock) {
                DB::table('product_stocks')
                    ->where('id', $pStock->id)
                    ->decrement('stock', $qtyNeeded);
            } else {
                DB::table('product_stocks')->insert([
                    'obat_id' => $medicineKode,
                    'outlet_id' => $outletId,
                    'stock' => max(0, (float)($medicine->fresh()->stok ?? 0)),
                    'min_stock' => (float)($medicine->min_stok ?? 5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return $allocatedBatches;
    }
}
