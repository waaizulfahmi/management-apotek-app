<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Outlet;
use App\Models\Obat;
use App\Models\User;

class ExistingMasterStockPriceSeeder extends Seeder
{
    /**
     * Seed stocks, purchase prices, selling prices, and per-outlet product prices 
     * based on EXISTING mapped products and outlets in the database.
     */
    public function run(): void
    {
        // 1. Fetch all existing active outlets (or create default if empty)
        $outlets = Outlet::all();

        if ($outlets->isEmpty()) {
            $mainId = DB::table('outlets')->insertGetId([
                'code' => 'OUT-001',
                'name' => 'Apotek Medika Utama - Pusat',
                'is_main' => true,
                'status' => 'ACTIVE',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $branchId = DB::table('outlets')->insertGetId([
                'code' => 'OUT-002',
                'name' => 'Apotek Medika Cabang 1',
                'is_main' => false,
                'status' => 'ACTIVE',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $outlets = Outlet::all();
        }

        // 2. Ensure all users are mapped to outlets in user_outlets table
        $users = User::all();
        $firstOutletId = $outlets->first()->id;

        foreach ($users as $u) {
            if (!$u->outlet_id) {
                $u->update(['outlet_id' => $firstOutletId]);
            }

            // If user is admin / owner / superadmin, set access_all_outlets = true
            $userRole = strtolower($u->role ?? '');
            if (in_array($userRole, ['admin', 'owner', 'superadmin', 'super admin']) || $u->hasRole('admin')) {
                $u->update(['access_all_outlets' => true]);
            }

            // Ensure pivot mapping in user_outlets for primary outlet
            $hasPivot = DB::table('user_outlets')
                ->where('user_id', $u->id)
                ->where('outlet_id', $u->outlet_id)
                ->whereNull('deleted_at')
                ->exists();

            if (!$hasPivot) {
                DB::table('user_outlets')->insert([
                    'user_id' => $u->id,
                    'outlet_id' => $u->outlet_id,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 3. Fetch all existing master medicines
        $obats = Obat::all();

        if ($obats->isEmpty()) {
            $this->command->info('Tidak ada data master obat pada database. Silakan tambahkan obat master lebih dahulu.');
            return;
        }

        $racks = ['Rak A-1', 'Rak A-2', 'Rak B-1', 'Rak B-3', 'Rak C-2', 'Rak D-4', 'Etalase Depan', 'Kulkas Obat'];
        $units = Schema::hasTable('units') ? DB::table('units')->pluck('id', 'name')->toArray() : [];
        $defaultUnitId = !empty($units) ? reset($units) : null;

        // 4. Update Master Prices (Harga Beli & Harga Jual Global)
        foreach ($obats as $obat) {
            // Determine Selling Price (Harga Jual Master)
            $sellingPrice = (float) $obat->harga;
            if ($sellingPrice <= 0) {
                $sellingPrice = rand(5, 100) * 1000;
            }

            // Determine Purchase Price (Harga Beli / HPP: 75% to 85% of selling price)
            $marginPercent = rand(15, 25) / 100;
            $purchasePrice = round(($sellingPrice * (1 - $marginPercent)) / 100) * 100;

            // Update Master Obat table
            $updateData = ['harga' => $sellingPrice];
            if (Schema::hasColumn('obats', 'harga_beli')) {
                $updateData['harga_beli'] = $purchasePrice;
            }
            if (Schema::hasColumn('obats', 'hpp')) {
                $updateData['hpp'] = $purchasePrice;
            }
            DB::table('obats')->where('kode', $obat->kode)->update($updateData);

            // Update/Insert into product_prices table if available
            if (Schema::hasTable('product_prices')) {
                $unitId = $obat->satuan_penjualan_id ?: ($obat->satuan_dasar_id ?: $defaultUnitId);

                if ($unitId) {
                    // Purchase Price (Harga Beli)
                    DB::table('product_prices')->updateOrInsert(
                        [
                            'product_id' => $obat->kode,
                            'unit_id' => $unitId,
                            'price_type' => 'PURCHASE',
                        ],
                        [
                            'price' => $purchasePrice,
                            'is_default' => true,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );

                    // Selling Price (Harga Jual)
                    DB::table('product_prices')->updateOrInsert(
                        [
                            'product_id' => $obat->kode,
                            'unit_id' => $unitId,
                            'price_type' => 'SELLING',
                        ],
                        [
                            'price' => $sellingPrice,
                            'is_default' => true,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }

        // Refresh obats list with updated prices
        $obats = Obat::all();

        // 5. Process Outlet-Specific Stock & Custom Prices based on MAPPED products per outlet
        foreach ($outlets as $outlet) {
            // Check existing product mappings for this outlet in product_outlets
            $existingMappedKodes = DB::table('product_outlets')
                ->where('outlet_id', $outlet->id)
                ->whereNull('deleted_at')
                ->pluck('obat_id')
                ->toArray();

            if (!empty($existingMappedKodes)) {
                // Respect existing product_outlets mappings
                $selectedObats = $obats->whereIn('kode', $existingMappedKodes);
            } else {
                // If no products mapped yet to this outlet:
                // Main Outlet gets ALL master products.
                // Branch Outlets get a random unique subset (between 60% and 90% of total master products)
                if ($outlet->is_main) {
                    $selectedObats = $obats;
                } else {
                    $countToPick = max(1, (int) round($obats->count() * (rand(60, 90) / 100)));
                    $selectedObats = $obats->random(min($countToPick, $obats->count()));
                }
            }

            foreach ($selectedObats as $obat) {
                // Check if this product already has a custom price set in product_outlets
                $existingPO = DB::table('product_outlets')
                    ->where('outlet_id', $outlet->id)
                    ->where('obat_id', $obat->kode)
                    ->first();

                $customSellingPrice = $existingPO ? $existingPO->price : null;

                // If not set yet, 25% chance to set a custom outlet price
                if ($customSellingPrice === null && rand(1, 100) <= 25 && $obat->harga > 0) {
                    $markup = rand(500, 2500);
                    $customSellingPrice = ceil(($obat->harga + $markup) / 500) * 500;
                }

                // Determine realistic stock for this outlet product
                $randStockPercent = rand(1, 100);
                if ($randStockPercent <= 5) {
                    $stockQty = 0; // Out of stock
                } elseif ($randStockPercent <= 20) {
                    $stockQty = rand(1, 5); // Low stock
                } else {
                    $stockQty = rand(15, 200); // Normal stock
                }

                $minStock = $obat->min_stok ?: 10;
                $rack = $racks[array_rand($racks)];

                // Upsert product_outlets
                $poData = [
                    'is_active' => true,
                    'price' => $customSellingPrice,
                    'updated_at' => now(),
                    'created_at' => now(),
                ];
                if (Schema::hasColumn('product_outlets', 'harga_beli')) {
                    $marginPercent = rand(15, 25) / 100;
                    $refPrice = $customSellingPrice ?: $obat->harga;
                    $poData['harga_beli'] = round(($refPrice * (1 - $marginPercent)) / 100) * 100;
                }

                DB::table('product_outlets')->updateOrInsert(
                    [
                        'obat_id' => $obat->kode,
                        'outlet_id' => $outlet->id,
                    ],
                    $poData
                );

                // Upsert product_stocks (Stock per Outlet)
                DB::table('product_stocks')->updateOrInsert(
                    [
                        'obat_id' => $obat->kode,
                        'outlet_id' => $outlet->id,
                    ],
                    [
                        'stock' => $stockQty,
                        'min_stock' => $minStock,
                        'rack_location' => $rack,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        // 6. Update master legacy stock as sum across all outlets
        foreach ($obats as $obat) {
            $totalStock = DB::table('product_stocks')
                ->where('obat_id', $obat->kode)
                ->sum('stock');

            DB::table('obats')
                ->where('kode', $obat->kode)
                ->update(['stok' => $totalStock]);
        }

        $this->command->info('Seeder berhasil! Data pemetaan outlet, harga beli, harga jual, dan stok produk per outlet yang dimapping telah berhasil diperbarui.');
    }
}
