<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MASTER SHIFTS ===\n";
$masters = DB::table('master_shifts')->get();
foreach ($masters as $m) {
    echo "ID: {$m->id} | Name: {$m->name} | Start: {$m->start_time} | End: {$m->end_time}\n";
}

echo "\n=== ACTIVE CASHIER SHIFTS ===\n";
$shifts = DB::table('cashier_shifts')->where('status', 'OPEN')->get();
foreach ($shifts as $s) {
    echo "ID: {$s->id} | Shift Name: {$s->shift_name} | Master Shift ID: {$s->master_shift_id} | Cashier ID: {$s->cashier_id}\n";
}
