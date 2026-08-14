<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashierShift;
use App\Models\Sale;
use App\Models\User;
use App\Models\Outlet;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class SalesCashierController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = DB::table('sales')
            ->join('users', 'sales.cashier_id', '=', 'users.id')
            ->leftJoin('cashier_shifts', 'sales.shift_id', '=', 'cashier_shifts.id')
            ->leftJoin('outlets', 'sales.outlet_id', '=', 'outlets.id')
            ->select(
                'sales.cashier_id',
                'users.name as cashier_name',
                'sales.shift_id',
                'cashier_shifts.shift_name',
                'sales.outlet_id',
                'outlets.name as outlet_name',
                DB::raw('COUNT(sales.id) as total_transactions'),
                DB::raw('SUM(sales.grand_total) as total_sales'),
                DB::raw("SUM(CASE WHEN sales.payment_method = 'cash' THEN sales.grand_total ELSE 0 END) as cash_sales"),
                DB::raw("SUM(CASE WHEN sales.payment_method = 'qris' THEN sales.grand_total ELSE 0 END) as qris_sales"),
                DB::raw("SUM(CASE WHEN sales.payment_method = 'debit' THEN sales.grand_total ELSE 0 END) as debit_sales"),
                DB::raw("SUM(CASE WHEN sales.payment_method = 'transfer' THEN sales.grand_total ELSE 0 END) as transfer_sales")
            )
            ->where('sales.status', 'completed');

        // Cashier role restriction
        if (!$user->can('sales.view_all_cashier') && $user->role === 'kasir') {
            $query->where('sales.cashier_id', $user->id);
        }

        // Filters
        if ($request->filled('cashier_id')) {
            $query->where('sales.cashier_id', $request->cashier_id);
        }
        if ($request->filled('shift_name')) {
            $query->where('cashier_shifts.shift_name', $request->shift_name);
        }
        if ($request->filled('outlet_id')) {
            $query->where('sales.outlet_id', $request->outlet_id);
        }
        if ($request->filled('payment_method')) {
            $query->where('sales.payment_method', $request->payment_method);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween(DB::raw('DATE(sales.sale_date)'), [$request->start_date, $request->end_date]);
        }

        $query->groupBy(
            'sales.cashier_id',
            'users.name',
            'sales.shift_id',
            'cashier_shifts.shift_name',
            'sales.outlet_id',
            'outlets.name'
        );

        $results = $query->paginate(15)->withQueryString();

        // Calculate refunds per cashier/shift
        foreach ($results as $row) {
            $refundAmount = DB::table('product_returns')
                ->join('sales', 'product_returns.reference_id', '=', 'sales.id')
                ->where('sales.cashier_id', $row->cashier_id)
                ->where('sales.shift_id', $row->shift_id)
                ->where('product_returns.type', 'sale')
                ->where('product_returns.status', 'APPROVED')
                ->sum('product_returns.refund_amount');

            $row->refund_amount = (float)$refundAmount;
            $row->net_sales = (float)$row->total_sales - (float)$refundAmount;
        }

        $cashiers = User::all();
        $outlets = Outlet::where('is_active', true)->get();

        return Inertia::render('Sales/Cashier/Index', [
            'reports' => $results,
            'cashiers' => $cashiers,
            'outlets' => $outlets,
            'filters' => $request->only(['cashier_id', 'shift_name', 'outlet_id', 'payment_method', 'start_date', 'end_date'])
        ]);
    }
}
