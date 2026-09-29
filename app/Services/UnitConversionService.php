<?php

namespace App\Services;

use App\Models\Obat;
use App\Models\ProductUnit;
use App\Models\UnitConversion;
use App\Models\ProductPrice;
use Illuminate\Support\Facades\DB;
use Exception;

class UnitConversionService
{
    /**
     * Recalculate and update conversion_factor for all product_units of a given product.
     * The base_unit has a conversion_factor of 1.0000.
     * Conversion rules define: 1 ParentUnit = conversion_rate * ChildUnit.
     * Therefore: multiplier(Parent) = multiplier(Child) * conversion_rate.
     */
    public function recalculateProductUnitFactors(string $productId): void
    {
        $product = Obat::where('kode', $productId)->first();
        if (!$product || !$product->satuan_dasar_id) {
            return;
        }

        $baseUnitId = $product->satuan_dasar_id;

        // Fetch all conversion rules for this product
        $conversions = UnitConversion::where('product_id', $productId)->get();

        // Multipliers relative to base unit (base unit = 1)
        $multipliers = [
            $baseUnitId => 1.0000,
        ];

        // Iterative pass to resolve parent multipliers from child multipliers
        $changed = true;
        $maxPasses = 20;
        $pass = 0;

        while ($changed && $pass < $maxPasses) {
            $changed = false;
            $pass++;

            foreach ($conversions as $conv) {
                $pId = $conv->parent_unit_id;
                $cId = $conv->child_unit_id;
                $rate = (float) $conv->conversion_rate;

                // Case 1: Child multiplier is known, calculate Parent multiplier
                if (isset($multipliers[$cId]) && !isset($multipliers[$pId])) {
                    $multipliers[$pId] = $multipliers[$cId] * $rate;
                    $changed = true;
                }
                // Case 2: Parent multiplier is known, calculate Child multiplier
                elseif (isset($multipliers[$pId]) && !isset($multipliers[$cId]) && $rate > 0) {
                    $multipliers[$cId] = $multipliers[$pId] / $rate;
                    $changed = true;
                }
            }
        }

        // Update product_units table
        foreach ($multipliers as $unitId => $factor) {
            ProductUnit::updateOrCreate(
                ['product_id' => $productId, 'unit_id' => $unitId],
                [
                    'is_base_unit' => ($unitId == $baseUnitId),
                    'conversion_factor' => $factor,
                ]
            );
        }
    }

    /**
     * Check if adding parentUnitId -> childUnitId creates a cycle for this product.
     */
    public function detectCycle(string $productId, int $parentUnitId, int $childUnitId): bool
    {
        if ($parentUnitId === $childUnitId) {
            return true;
        }

        // Build adjacency graph child -> parent (if child is already a parent of parentUnitId, cycle exists)
        $conversions = UnitConversion::where('product_id', $productId)->get();

        // Direct check: if childUnitId can reach parentUnitId through existing links (parent -> child)
        $visited = [];
        $queue = [$childUnitId];

        while (!empty($queue)) {
            $current = array_shift($queue);
            if ($current == $parentUnitId) {
                return true; // Cycle detected!
            }
            if (!in_array($current, $visited)) {
                $visited[] = $current;
                foreach ($conversions as $conv) {
                    if ($conv->parent_unit_id == $current) {
                        $queue[] = $conv->child_unit_id;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get conversion factor (multiplier to base unit) for a specific product and unit.
     */
    public function getConversionFactor(string $productId, int $unitId): float
    {
        $pu = ProductUnit::where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if ($pu && $pu->conversion_factor > 0) {
            return (float) $pu->conversion_factor;
        }

        // Fallback to 1.0 if base unit or unknown
        return 1.0000;
    }

    /**
     * Get detailed price list per unit for a product including calculated prices, custom prices, margins, and margin percentage.
     */
    public function getCalculatedPrices(string $productId): array
    {
        $product = Obat::with(['satuanDasar', 'satuanPembelian', 'satuanPenjualan'])->where('kode', $productId)->first();
        if (!$product) {
            return [];
        }

        $this->recalculateProductUnitFactors($productId);

        $productUnits = ProductUnit::with('unit')
            ->where('product_id', $productId)
            ->get();

        $customPrices = ProductPrice::where('product_id', $productId)->get();

        $conversions = UnitConversion::with(['parentUnit', 'childUnit'])
            ->where('product_id', $productId)
            ->get();

        // Default base/master prices from product table if present
        $masterHarga = (float) $product->harga; // Default master price

        $unitPriceData = [];

        foreach ($productUnits as $pu) {
            $unitId = $pu->unit_id;
            $unitName = $pu->unit ? $pu->unit->name : 'Satuan';
            $factor = (float) $pu->conversion_factor;

            // Fetch explicit purchase & selling prices from product_prices table
            $customPurchase = $customPrices->where('unit_id', $unitId)->where('price_type', 'PURCHASE')->first();
            $customSelling = $customPrices->where('unit_id', $unitId)->where('price_type', 'SELLING')->first();

            // Computed prices if not specified explicitly:
            // Base purchase price estimate = masterHarga / purchase_unit_factor (or masterHarga * factor)
            $calcPurchase = $customPurchase ? (float)$customPurchase->price : ($masterHarga * $factor);
            $calcSelling = $customSelling ? (float)$customSelling->price : ($calcPurchase > 0 ? $calcPurchase * 1.20 : 0); // Default 20% markup if missing

            $margin = $calcSelling - $calcPurchase;
            $marginPercent = $calcPurchase > 0 ? ($margin / $calcPurchase) * 100 : 0;

            $unitPriceData[] = [
                'unit_id' => $unitId,
                'unit_name' => $unitName,
                'is_base_unit' => $pu->is_base_unit,
                'is_purchase_unit' => ($product->satuan_pembelian_id == $unitId || $pu->is_purchase_unit),
                'is_selling_unit' => ($product->satuan_penjualan_id == $unitId || $pu->is_selling_unit),
                'conversion_factor' => $factor,
                'purchase_price' => round($calcPurchase, 2),
                'selling_price' => round($calcSelling, 2),
                'is_custom_purchase' => !empty($customPurchase),
                'is_custom_selling' => !empty($customSelling),
                'margin' => round($margin, 2),
                'margin_percent' => round($marginPercent, 2),
                'purchase_price_formatted' => 'Rp ' . number_format($calcPurchase, 0, ',', '.') . ' / ' . $unitName,
                'selling_price_formatted' => 'Rp ' . number_format($calcSelling, 0, ',', '.') . ' / ' . $unitName,
                'margin_formatted' => 'Rp ' . number_format($margin, 0, ',', '.') . ' / ' . $unitName,
            ];
        }

        return [
            'product' => [
                'kode' => $product->kode,
                'nama' => $product->nama,
                'stok' => $product->stok,
                'satuan_dasar' => $product->satuanDasar ? $product->satuanDasar->name : null,
                'satuan_pembelian' => $product->satuanPembelian ? $product->satuanPembelian->name : null,
                'satuan_penjualan' => $product->satuanPenjualan ? $product->satuanPenjualan->name : null,
            ],
            'conversions' => $conversions->map(function ($c) {
                return [
                    'id' => $c->id,
                    'parent_unit_id' => $c->parent_unit_id,
                    'parent_unit_name' => $c->parentUnit ? $c->parentUnit->name : '',
                    'child_unit_id' => $c->child_unit_id,
                    'child_unit_name' => $c->childUnit ? $c->childUnit->name : '',
                    'conversion_rate' => (float) $c->conversion_rate,
                    'description' => "1 " . ($c->parentUnit ? $c->parentUnit->name : '') . " = " . (float)$c->conversion_rate . " " . ($c->childUnit ? $c->childUnit->name : ''),
                ];
            }),
            'unit_prices' => $unitPriceData,
        ];
    }
}
