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
            $refundQuery = DB::table('product_returns')
                ->join('sales', 'product_returns.reference_id', '=', 'sales.id')
                ->where('sales.cashier_id', $row->cashier_id);

            if (is_null($row->shift_id)) {
                $refundQuery->whereNull('sales.shift_id');
            } else {
                $refundQuery->where('sales.shift_id', $row->shift_id);
            }

            $refundAmount = $refundQuery->where('product_returns.type', 'sale')
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

    public function transactions(Request $request)
    {
        $user = auth()->user();

        $query = Sale::with([
            'customer',
            'user:id,name,email',
            'shift:id,shift_name',
            'outlet:id,name,code',
            'items.medicine:kode,nama,satuan_dasar_id',
            'productReturns'
        ])
        ->where('status', 'completed')
        ->orderBy('sale_date', 'desc')
        ->orderBy('id', 'desc');

        // Cashier role restriction
        if (!$user->can('sales.view_all_cashier') && $user->role === 'kasir') {
            $query->where('cashier_id', $user->id);
        } elseif ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->cashier_id);
        }

        // Shift filter
        if ($request->has('shift_id') && $request->shift_id !== '' && $request->shift_id !== 'all') {
            if ($request->shift_id === 'null' || is_null($request->shift_id)) {
                $query->whereNull('shift_id');
            } elseif (is_numeric($request->shift_id)) {
                $query->where('shift_id', $request->shift_id);
            }
        } elseif ($request->filled('shift_name')) {
            $query->whereHas('shift', function ($q) use ($request) {
                $q->where('shift_name', $request->shift_name);
            });
        }

        // Outlet filter
        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Date range filter
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween(DB::raw('DATE(sale_date)'), [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where(DB::raw('DATE(sale_date)'), '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where(DB::raw('DATE(sale_date)'), '<=', $request->end_date);
        }

        // Search invoice or customer name
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 10);
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 10;
        }

        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
}
