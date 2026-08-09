<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        // Sales report
        $sales = DB::table('sales')
            ->join('users', 'sales.cashier_id', '=', 'users.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->select('sales.*', 'users.name as cashier_name', 'customers.name as customer_name')
            ->whereBetween('sale_date', [$startDate, $endDate])
            ->orderBy('id', 'desc')
            ->get();

        // Expired Warning Alerts (< 60 days)
        $expiredAlerts = DB::table('medicine_batches')
            ->join('obats', 'medicine_batches.medicine_id', '=', 'obats.kode')
            ->select('medicine_batches.*', 'obats.nama as medicine_name')
            ->where('medicine_batches.stock', '>', 0)
            ->where('medicine_batches.expired_date', '<=', Carbon::now()->addDays(60)->toDateString())
            ->orderBy('expired_date', 'asc')
            ->get();

        // Low Stock Alerts
        $lowStockAlerts = DB::table('obats')
            ->where('stok', '<=', 10)
            ->get();

        return Inertia::render('Reports/Index', [
            'sales' => $sales,
            'expiredAlerts' => $expiredAlerts,
            'lowStockAlerts' => $lowStockAlerts,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }
}
