<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\ProductStock;
use App\Models\Obat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Exception;

class OutletService
{
    /**
     * Safe permission check without throwing Spatie PermissionDoesNotExist exception
     */
    private static function checkPermission(?User $user, string $perm): bool
    {
        if (!$user) return false;
        try {
            return method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo($perm, 'web');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get active outlet ID for current user context
     */
    public static function getActiveOutletId(?User $user = null): ?int
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            $main = DB::table('outlets')->where('is_main', true)->first() ?: DB::table('outlets')->first();
            return $main ? $main->id : null;
        }

        $sessionOutletId = session('active_outlet_id');
        if ($sessionOutletId && self::userCanAccessOutlet($user, $sessionOutletId)) {
            return (int) $sessionOutletId;
        }

        // Default to user's primary outlet if set and accessible
        if ($user->outlet_id && self::userCanAccessOutlet($user, $user->outlet_id)) {
            session(['active_outlet_id' => $user->outlet_id]);
            return (int) $user->outlet_id;
        }

        // Fallback to Main Outlet or first available outlet
        $mainOutlet = DB::table('outlets')->where('is_main', true)->first() 
            ?: DB::table('outlets')->first();

        $outletId = $mainOutlet ? $mainOutlet->id : null;
        if ($outletId) {
            session(['active_outlet_id' => $outletId]);
        }
        return $outletId;
    }

    /**
     * Get active outlet instance
     */
    public static function getActiveOutlet(?User $user = null): ?Outlet
    {
        $id = self::getActiveOutletId($user);
        return $id ? Outlet::find($id) : null;
    }

    /**
     * Get all outlets accessible by user
     */
    public static function getUserOutlets(?User $user = null)
    {
        $user = $user ?: auth()->user();
        if (!$user) {
            return Outlet::where('is_active', true)->whereNull('deleted_at')->get();
        }

        // Users with access_all_outlets or Admin/Owner see all active outlets
        if ($user->hasAccessToAllOutlets() || self::checkPermission($user, 'outlet.view_all')) {
            return Outlet::where('is_active', true)->whereNull('deleted_at')->orderBy('is_main', 'desc')->orderBy('name', 'asc')->get();
        }

        // Regular users see assigned active outlets + primary outlet
        $outletIds = DB::table('user_outlets')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->pluck('outlet_id')
            ->toArray();

        if ($user->outlet_id && !in_array($user->outlet_id, $outletIds)) {
            $outletIds[] = $user->outlet_id;
        }

        return Outlet::whereIn('id', $outletIds)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('is_main', 'desc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Check if user can access specific outlet ID
     */
    public static function userCanAccessOutlet(?User $user, int $outletId): bool
    {
        if (!$user) return false;

        $targetOutlet = Outlet::find($outletId);
        if (!$targetOutlet || $targetOutlet->status === 'INACTIVE' || $targetOutlet->deleted_at !== null) {
            return false;
        }

        if ($user->hasAccessToAllOutlets() || self::checkPermission($user, 'outlet.view_all')) {
            return true;
        }

        if ($user->outlet_id == $outletId) return true;

        return DB::table('user_outlets')
            ->where('user_id', $user->id)
            ->where('outlet_id', $outletId)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Switch user's active outlet
     */
    public static function switchActiveOutlet(int $outletId, ?User $user = null): bool
    {
        $user = $user ?: auth()->user();
        if (!self::userCanAccessOutlet($user, $outletId)) {
            return false;
        }

        $outlet = Outlet::find($outletId);
        if (!$outlet) return false;

        session(['active_outlet_id' => $outlet->id]);
        return true;
    }

    /**
     * Get stock for specific product & outlet
     */
    public static function getStock(string $obatKode, int $outletId): float
    {
        $stockRecord = ProductStock::where('obat_id', $obatKode)->where('outlet_id', $outletId)->first();
        if ($stockRecord) {
            return (float) $stockRecord->stock;
        }

        // Fallback to obat main stock if this is main outlet and no record created yet
        $mainOutlet = Outlet::where('is_main', true)->first();
        if ($mainOutlet && $mainOutlet->id == $outletId) {
            $obat = Obat::where('kode', $obatKode)->first();
            if ($obat) {
                ProductStock::create([
                    'obat_id' => $obatKode,
                    'outlet_id' => $outletId,
                    'stock' => $obat->stok ?? 0,
                    'min_stock' => $obat->min_stok ?? 5,
                ]);
                return (float) ($obat->stok ?? 0);
            }
        }

        return 0.0;
    }

    /**
     * Adjust stock for specific outlet
     */
    public static function adjustStock(string $obatKode, int $outletId, float $qtyDelta, string $type = 'ADJUSTMENT', ?string $reference = null, ?int $userId = null): float
    {
        $stockRecord = ProductStock::firstOrCreate(
            ['obat_id' => $obatKode, 'outlet_id' => $outletId],
            ['stock' => 0, 'min_stock' => 5]
        );

        $stockBefore = (float) $stockRecord->stock;
        $newStock = $stockBefore + $qtyDelta;
        $stockRecord->stock = $newStock;
        $stockRecord->save();

        // Record in stock_movements table with correct schema column names
        if (Schema::hasTable('stock_movements')) {
            $typeUpper = strtoupper($type);
            $enumType = 'adjustment';
            if (str_contains($typeUpper, 'IN') || $qtyDelta > 0) {
                $enumType = 'in';
            } elseif (str_contains($typeUpper, 'OUT') || $qtyDelta < 0) {
                $enumType = 'out';
            }

            $movementNo = 'SM-' . date('Ymd') . '-' . rand(10000, 99999);

            $insertData = [
                'movement_number' => $movementNo,
                'outlet_id' => $outletId,
                'type' => $enumType,
                'movement_type' => $type,
                'quantity' => (int) round(abs($qtyDelta)),
                'stock_before' => (int) round($stockBefore),
                'stock_after' => (int) round($newStock),
                'user_id' => $userId ?: (auth()->id() ?: 1),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (Schema::hasColumn('stock_movements', 'medicine_id')) {
                $insertData['medicine_id'] = $obatKode;
            } elseif (Schema::hasColumn('stock_movements', 'obat_id')) {
                $insertData['obat_id'] = $obatKode;
            }

            if (Schema::hasColumn('stock_movements', 'reference_number')) {
                $insertData['reference_number'] = $reference;
            } elseif (Schema::hasColumn('stock_movements', 'reference')) {
                $insertData['reference'] = $reference;
            }

            DB::table('stock_movements')->insert($insertData);
        }

        // If main outlet, sync obats.stok table for backward compatibility
        $mainOutlet = Outlet::where('is_main', true)->first();
        if ($mainOutlet && $mainOutlet->id == $outletId) {
            DB::table('obats')->where('kode', $obatKode)->update(['stok' => DB::raw("stok + ({$qtyDelta})")]);
        }

        return $newStock;
    }

    /**
     * Attach a global product to an outlet
     */
    public static function attachProductToOutlet(string $obatKode, int $outletId, ?float $price = null, bool $isActive = true): bool
    {
        $existing = DB::table('product_outlets')
            ->where('obat_id', $obatKode)
            ->where('outlet_id', $outletId)
            ->first();

        if ($existing) {
            DB::table('product_outlets')
                ->where('id', $existing->id)
                ->update([
                    'is_active' => $isActive,
                    'price' => $price !== null ? $price : $existing->price,
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('product_outlets')->insert([
                'obat_id' => $obatKode,
                'outlet_id' => $outletId,
                'is_active' => $isActive,
                'price' => $price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Also ensure a product_stocks record exists
        ProductStock::firstOrCreate(
            ['obat_id' => $obatKode, 'outlet_id' => $outletId],
            ['stock' => 0, 'min_stock' => 5]
        );

        return true;
    }

    /**
     * Check if product is available & active in outlet
     */
    public static function isProductAvailableInOutlet(string $obatKode, int $outletId): bool
    {
        return DB::table('product_outlets')
            ->where('obat_id', $obatKode)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Get selling price for specific outlet (custom price if set, otherwise default obat price)
     */
    public static function getOutletPrice(string $obatKode, int $outletId, ?float $defaultPrice = null): float
    {
        $po = DB::table('product_outlets')
            ->where('obat_id', $obatKode)
            ->where('outlet_id', $outletId)
            ->whereNull('deleted_at')
            ->first();

        if ($po && $po->price !== null) {
            return (float) $po->price;
        }

        if ($defaultPrice !== null) {
            return (float) $defaultPrice;
        }

        $obat = Obat::where('kode', $obatKode)->first();
        return $obat ? (float) $obat->harga : 0.0;
    }
}
