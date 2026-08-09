<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index()
    {
        $expenses = DB::table('expenses')
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->join('users', 'expenses.user_id', '=', 'users.id')
            ->select('expenses.*', 'expense_categories.name as category_name', 'users.name as user_name')
            ->orderBy('expenses.id', 'desc')
            ->paginate(10);

        $categories = DB::table('expense_categories')->get();

        // Calculate Revenue, COGS, Gross Profit, Expenses, Net Profit
        $totalSales = DB::table('sales')->where('status', 'completed')->sum('grand_total');
        
        // Exact COGS from sale_items buy_price
        $totalCogs = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->sum(DB::raw('sale_items.quantity * sale_items.buy_price'));

        $grossProfit = $totalSales - $totalCogs;
        $totalExpenseAmount = DB::table('expenses')->sum('amount');
        $netProfit = $grossProfit - $totalExpenseAmount;

        return Inertia::render('Finance/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'summary' => [
                'revenue' => (float)$totalSales,
                'cogs' => (float)$totalCogs,
                'gross_profit' => (float)$grossProfit,
                'total_expense' => (float)$totalExpenseAmount,
                'net_profit' => (float)$netProfit,
            ]
        ]);
    }

    public function storeExpense(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date',
            'description' => 'required|string',
        ]);

        DB::table('expenses')->insert([
            'category_id' => $request->category_id,
            'user_id' => auth()->id(),
            'amount' => $request->amount,
            'expense_date' => $request->expense_date,
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('finance.index')->with('success', 'Pengeluaran berhasil dicatat!');
    }
}
