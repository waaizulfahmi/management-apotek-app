<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Unit;
use App\Models\Obat;
use App\Models\ProductUnit;
use App\Models\UnitConversion;
use App\Models\ProductPrice;
use App\Services\UnitConversionService;
use Illuminate\Support\Facades\DB;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Master Units
        $defaultUnits = [
            'Tablet',
            'Kapsul',
            'Strip',
            'Box',
            'Botol',
            'Tube',
            'Sachet',
            'Pcs',
            'Ampul',
            'Vial',
        ];

        $unitMap = [];
        foreach ($defaultUnits as $uName) {
            $u = Unit::firstOrCreate(
                ['name' => $uName],
                ['is_active' => true]
            );
            $unitMap[$uName] = $u->id;
        }

        // 2. Sample Product: Paracetamol 500mg (MED-001)
        $para = Obat::firstOrCreate(
            ['kode' => 'MED-001'],
            [
                'nama' => 'Paracetamol 500mg',
                'gambar' => 'paracetamol.jpg',
                'jenis_obat' => 'Tablet',
                'kategori' => 'Antipiretik',
                'harga' => 5000.00,
                'stok' => 1000, // 1000 Tablet
                'min_stok' => 100,
            ]
        );

        $tabletId = $unitMap['Tablet'];
        $stripId = $unitMap['Strip'];
        $boxId = $unitMap['Box'];

        // Assign Primary Units to Paracetamol
        $para->update([
            'satuan_dasar_id' => $tabletId,
            'satuan_pembelian_id' => $boxId,
            'satuan_penjualan_id' => $stripId,
        ]);

        // Register Product Units
        ProductUnit::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $tabletId],
            ['is_base_unit' => true, 'is_purchase_unit' => false, 'is_selling_unit' => true, 'conversion_factor' => 1.0000]
        );

        ProductUnit::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $stripId],
            ['is_base_unit' => false, 'is_purchase_unit' => false, 'is_selling_unit' => true, 'conversion_factor' => 10.0000]
        );

        ProductUnit::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $boxId],
            ['is_base_unit' => false, 'is_purchase_unit' => true, 'is_selling_unit' => true, 'conversion_factor' => 100.0000]
        );

        // Register Unit Conversions
        // 1 Box = 10 Strip
        UnitConversion::updateOrCreate(
            ['product_id' => 'MED-001', 'parent_unit_id' => $boxId, 'child_unit_id' => $stripId],
            ['conversion_rate' => 10.0000]
        );

        // 1 Strip = 10 Tablet
        UnitConversion::updateOrCreate(
            ['product_id' => 'MED-001', 'parent_unit_id' => $stripId, 'child_unit_id' => $tabletId],
            ['conversion_rate' => 10.0000]
        );

        // Recalculate multipliers
        app(UnitConversionService::class)->recalculateProductUnitFactors('MED-001');

        // Register Custom Prices for Paracetamol
        // Box: Purchase Rp40.000, Selling Rp48.000
        ProductPrice::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $boxId, 'price_type' => 'PURCHASE'],
            ['price' => 40000.00]
        );
        ProductPrice::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $boxId, 'price_type' => 'SELLING'],
            ['price' => 48000.00]
        );

        // Strip: Purchase Rp4.000, Selling Rp5.000
        ProductPrice::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $stripId, 'price_type' => 'PURCHASE'],
            ['price' => 4000.00]
        );
        ProductPrice::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $stripId, 'price_type' => 'SELLING'],
            ['price' => 5000.00]
        );

        // Tablet: Purchase Rp400, Selling Rp500
        ProductPrice::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $tabletId, 'price_type' => 'PURCHASE'],
            ['price' => 400.00]
        );
        ProductPrice::updateOrCreate(
            ['product_id' => 'MED-001', 'unit_id' => $tabletId, 'price_type' => 'SELLING'],
            ['price' => 500.00]
        );
    }
}
