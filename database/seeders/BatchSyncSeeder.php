<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BatchSyncSeeder extends Seeder
{
    public function run()
    {
        $obats = DB::table('obats')->get();
        foreach ($obats as $o) {
            $sum = DB::table('medicine_batches')->where('medicine_id', $o->kode)->where('is_active', true)->sum('stock');
            if ($sum < $o->stok) {
                $needed = $o->stok - $sum;
                DB::table('medicine_batches')->insert([
                    'medicine_id' => $o->kode,
                    'batch_number' => 'BATCH-INIT-' . rand(100, 999),
                    'expired_date' => now()->addYears(2)->toDateString(),
                    'stock' => $needed,
                    'buy_price' => $o->harga * 0.7,
                    'sell_price' => $o->harga,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }
}
