<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Obat;
use App\Models\Unit;
use App\Models\ProductUnit;
use App\Models\UnitConversion;
use App\Models\ProductPrice;
use App\Models\PriceHistory;
use App\Services\UnitConversionService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class ProductUnitPriceController extends Controller
{
    protected $conversionService;
    protected $auditLogService;

    public function __construct(UnitConversionService $conversionService, AuditLogService $auditLogService)
    {
        $this->conversionService = $conversionService;
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get detail of product units, conversions, calculated prices, and price history.
     */
    public function getDetails(string $kode)
    {
        $product = Obat::with(['satuanDasar', 'satuanPembelian', 'satuanPenjualan'])->where('kode', $kode)->firstOrFail();
        $details = $this->conversionService->getCalculatedPrices($kode);

        $priceHistories = PriceHistory::with(['unit', 'user'])
            ->where('product_id', $kode)
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get();

        $allUnits = Unit::where('is_active', true)->orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'details' => $details,
                'price_histories' => $priceHistories,
                'all_units' => $allUnits,
                'satuan_dasar_id' => $product->satuan_dasar_id,
                'satuan_pembelian_id' => $product->satuan_pembelian_id,
                'satuan_penjualan_id' => $product->satuan_penjualan_id,
            ]
        ]);
    }

    /**
     * Set Primary Product Units (Satuan Dasar, Satuan Pembelian, Satuan Penjualan).
     */
    public function setPrimaryUnits(Request $request, string $kode)
    {
        $product = Obat::where('kode', $kode)->firstOrFail();

        $request->validate([
            'satuan_dasar_id' => 'required|exists:units,id',
            'satuan_pembelian_id' => 'required|exists:units,id',
            'satuan_penjualan_id' => 'required|exists:units,id',
        ]);

        DB::beginTransaction();
        try {
            $product->update([
                'satuan_dasar_id' => $request->satuan_dasar_id,
                'satuan_pembelian_id' => $request->satuan_pembelian_id,
                'satuan_penjualan_id' => $request->satuan_penjualan_id,
            ]);

            // Ensure ProductUnit records exist
            $unitIds = array_unique([$request->satuan_dasar_id, $request->satuan_pembelian_id, $request->satuan_penjualan_id]);
            foreach ($unitIds as $uId) {
                ProductUnit::updateOrCreate(
                    ['product_id' => $kode, 'unit_id' => $uId],
                    [
                        'is_base_unit' => ($uId == $request->satuan_dasar_id),
                        'is_purchase_unit' => ($uId == $request->satuan_pembelian_id),
                        'is_selling_unit' => ($uId == $request->satuan_penjualan_id),
                    ]
                );
            }

            $this->conversionService->recalculateProductUnitFactors($kode);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Satuan utama produk berhasil diperbarui!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Save conversion rule (e.g. 1 Box = 10 Strip).
     */
    public function saveConversion(Request $request, string $kode)
    {
        $product = Obat::where('kode', $kode)->firstOrFail();

        $request->validate([
            'parent_unit_id' => 'required|exists:units,id',
            'child_unit_id' => 'required|exists:units,id|different:parent_unit_id',
            'conversion_rate' => 'required|numeric|gt:0',
        ]);

        $parentUnitId = (int)$request->parent_unit_id;
        $childUnitId = (int)$request->child_unit_id;
        $rate = (float)$request->conversion_rate;

        // Check for conversion cycle
        if ($this->conversionService->detectCycle($kode, $parentUnitId, $childUnitId)) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: Konversi ini membentuk perulangan (loop cycle) yang tidak valid!',
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Save or update conversion rule
            UnitConversion::updateOrCreate(
                [
                    'product_id' => $kode,
                    'parent_unit_id' => $parentUnitId,
                    'child_unit_id' => $childUnitId,
                ],
                [
                    'conversion_rate' => $rate,
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]
            );

            // Ensure ProductUnit entries exist for both parent and child units
            ProductUnit::firstOrCreate(
                ['product_id' => $kode, 'unit_id' => $parentUnitId],
                ['is_base_unit' => false, 'is_purchase_unit' => false, 'is_selling_unit' => true]
            );

            ProductUnit::firstOrCreate(
                ['product_id' => $kode, 'unit_id' => $childUnitId],
                ['is_base_unit' => false, 'is_purchase_unit' => false, 'is_selling_unit' => true]
            );

            // Recalculate factors
            $this->conversionService->recalculateProductUnitFactors($kode);

            $this->auditLogService->log(
                'ADD_UNIT_CONVERSION',
                'Konversi Satuan',
                null,
                ['product_id' => $kode, 'parent_unit_id' => $parentUnitId, 'child_unit_id' => $childUnitId, 'rate' => $rate]
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Konversi satuan berhasil disimpan!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Delete conversion rule.
     */
    public function deleteConversion(string $kode, string $id)
    {
        $conv = UnitConversion::where('product_id', $kode)->where('id', $id)->firstOrFail();
        $conv->delete();

        $this->conversionService->recalculateProductUnitFactors($kode);

        return response()->json([
            'success' => true,
            'message' => 'Konversi satuan berhasil dihapus!',
        ]);
    }

    /**
     * Save/Update multi-unit prices for purchase & selling, with price history tracking.
     */
    public function updatePrices(Request $request, string $kode)
    {
        $product = Obat::where('kode', $kode)->firstOrFail();

        $request->validate([
            'prices' => 'required|array|min:1',
            'prices.*.unit_id' => 'required|exists:units,id',
            'prices.*.purchase_price' => 'required|numeric|min:0',
            'prices.*.selling_price' => 'required|numeric|min:0',
        ]);

        $userId = auth()->id();

        DB::beginTransaction();
        try {
            foreach ($request->prices as $pData) {
                $unitId = $pData['unit_id'];
                $newPurchase = (float)$pData['purchase_price'];
                $newSelling = (float)$pData['selling_price'];

                // 1. Purchase Price Update & History
                $existingPurchase = ProductPrice::where('product_id', $kode)
                    ->where('unit_id', $unitId)
                    ->where('price_type', 'PURCHASE')
                    ->first();

                $oldPurchase = $existingPurchase ? (float)$existingPurchase->price : 0.00;

                if (!$existingPurchase || $oldPurchase != $newPurchase) {
                    ProductPrice::updateOrCreate(
                        ['product_id' => $kode, 'unit_id' => $unitId, 'price_type' => 'PURCHASE'],
                        ['price' => $newPurchase, 'updated_by' => $userId, 'created_by' => $userId]
                    );

                    PriceHistory::create([
                        'product_id' => $kode,
                        'unit_id' => $unitId,
                        'price_type' => 'PURCHASE',
                        'old_price' => $oldPurchase,
                        'new_price' => $newPurchase,
                        'user_id' => $userId,
                    ]);
                }

                // 2. Selling Price Update & History
                $existingSelling = ProductPrice::where('product_id', $kode)
                    ->where('unit_id', $unitId)
                    ->where('price_type', 'SELLING')
                    ->first();

                $oldSelling = $existingSelling ? (float)$existingSelling->price : 0.00;

                if (!$existingSelling || $oldSelling != $newSelling) {
                    ProductPrice::updateOrCreate(
                        ['product_id' => $kode, 'unit_id' => $unitId, 'price_type' => 'SELLING'],
                        ['price' => $newSelling, 'updated_by' => $userId, 'created_by' => $userId]
                    );

                    PriceHistory::create([
                        'product_id' => $kode,
                        'unit_id' => $unitId,
                        'price_type' => 'SELLING',
                        'old_price' => $oldSelling,
                        'new_price' => $newSelling,
                        'user_id' => $userId,
                    ]);
                }

                // If this is default selling unit or selling unit of product, sync main product harga
                if ($product->satuan_penjualan_id == $unitId) {
                    $product->update(['harga' => $newSelling]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Harga per satuan berhasil diperbarui & riwayat harga telah dicatat!',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
