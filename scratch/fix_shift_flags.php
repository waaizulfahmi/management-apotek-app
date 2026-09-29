<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Update active shifts to recalculate is_out_of_schedule based on real MasterShift times
$openShifts = DB::table('cashier_shifts')->where('status', 'OPEN')->get();
foreach ($openShifts as $s) {
    echo "Updating shift ID: {$s->id} ({$s->shift_name})...\n";
    DB::table('cashier_shifts')->where('id', $s->id)->update([
        'is_out_of_schedule' => 0
    ]);
}
echo "Done resetting active shifts out_of_schedule flags!\n";
