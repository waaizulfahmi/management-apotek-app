<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->input('range', 'month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $queryDate = Carbon::now();

        if ($range === 'today') {
            $start = Carbon::today()->startOfDay();
            $end = Carbon::today()->endOfDay();
        } elseif ($range === 'week') {
            $start = Carbon::now()->startOfWeek();
            $end = Carbon::now()->endOfWeek();
        } elseif ($range === 'year') {
            $start = Carbon::now()->startOfYear();
            $end = Carbon::now()->endOfYear();
        } elseif ($range === 'custom' && $startDate && $endDate) {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->endOfDay();
        } else { // default 'month'
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        }

        // Summary Cards
        $total_kasir = DB::table('users')->where('role', 'kasir')->count();
        $total_obat = DB::table('obats')->count();
        $total_transaksi = DB::table('sales')->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])->count();
        $total_pendapatan = DB::table('sales')->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])->where('status', 'completed')->sum('grand_total');

        // Sales Trend Chart (Grouped by Date)
        $salesTrend = DB::table('sales')
            ->select(DB::raw('sale_date as date'), DB::raw('SUM(grand_total) as total'))
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'completed')
            ->groupBy('sale_date')
            ->orderBy('sale_date', 'asc')
            ->get();

        $salesChartLabels = $salesTrend->pluck('date')->toArray();
        $salesChartData = $salesTrend->pluck('total')->map(fn($v) => (float)$v)->toArray();

        // Top Selling Medicines Chart
        $topSelling = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('obats', 'sale_items.medicine_id', '=', 'obats.kode')
            ->select('obats.nama', DB::raw('SUM(sale_items.quantity) as total_qty'))
            ->whereBetween('sales.sale_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('obats.nama')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get();

        $topSellingLabels = $topSelling->pluck('nama')->toArray();
        $topSellingData = $topSelling->pluck('total_qty')->map(fn($v) => (int)$v)->toArray();

        // Category Distribution Chart
        $categoryDist = DB::table('obats')
            ->select('kategori', DB::raw('COUNT(*) as count'))
            ->groupBy('kategori')
            ->get();

        $categoryLabels = $categoryDist->pluck('kategori')->toArray();
        $categoryData = $categoryDist->pluck('count')->toArray();

        // Recent Transactions
        $recentSales = DB::table('sales')
            ->join('users', 'sales.cashier_id', '=', 'users.id')
            ->select('sales.*', 'users.name as cashier_name')
            ->orderBy('sales.id', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'total_kasir' => $total_kasir,
            'total_obat' => $total_obat,
            'total_transaksi' => $total_transaksi,
            'total_pendapatan' => (float)$total_pendapatan,
            'salesChart' => [
                'labels' => $salesChartLabels,
                'data' => $salesChartData,
            ],
            'topSellingChart' => [
                'labels' => $topSellingLabels,
                'data' => $topSellingData,
            ],
            'categoryChart' => [
                'labels' => $categoryLabels,
                'data' => $categoryData,
            ],
            'recentSales' => $recentSales,
            'filters' => [
                'range' => $range,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }
}
