<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfitLossService
{
    /**
     * Get complete Profit & Loss Report data
     */
    public function getProfitLossData(array $filters = [])
    {
        $period = $filters['period'] ?? 'this_month';
        $dates = $this->resolveDateRange($period, $filters['start_date'] ?? null, $filters['end_date'] ?? null);
        $startDate = $dates['start_date'];
        $endDate = $dates['end_date'];

        $prevDates = $this->resolvePreviousPeriodRange($startDate, $endDate);
        $prevStartDate = $prevDates['start_date'];
        $prevEndDate = $prevDates['end_date'];

        $outletId = $filters['outlet_id'] ?? null;
        $cashierId = $filters['cashier_id'] ?? null;

        // Current & Previous Core Metrics
        $currentMetrics = $this->calculateMetrics($startDate, $endDate, $outletId, $cashierId);
        $prevMetrics = $this->calculateMetrics($prevStartDate, $prevEndDate, $outletId, $cashierId);
        $comparisons = $this->calculateComparisons($currentMetrics, $prevMetrics);

        // Breakdowns
        $paymentMethods = $this->getPaymentMethodBreakdown($startDate, $endDate, $outletId, $cashierId);
        $productBreakdown = $this->getProductMarginBreakdown($startDate, $endDate, $outletId, $cashierId, $filters);
        $categoryBreakdown = $this->getCategoryBreakdown($startDate, $endDate, $outletId, $cashierId);
        $expenseBreakdown = $this->getExpenseBreakdown($startDate, $endDate, $outletId);
        $trendData = $this->getTrendData($startDate, $endDate, $outletId, $cashierId);
        $cashierBreakdown = $this->getCashierShiftBreakdown($startDate, $endDate, $outletId, $cashierId);
        $outletBreakdown = $this->getOutletBreakdown($startDate, $endDate);
        $financialPositions = $this->getFinancialPositionsSummary();

        return [
            'period' => $period,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'prev_start_date' => $prevStartDate,
            'prev_end_date' => $prevEndDate,
            'metrics' => $currentMetrics,
            'prev_metrics' => $prevMetrics,
            'comparisons' => $comparisons,
            'payment_methods' => $paymentMethods,
            'product_breakdown' => $productBreakdown['items'],
            'top_products_sales' => $productBreakdown['top_sales'],
            'top_products_profit' => $productBreakdown['top_profit'],
            'bottom_products_margin' => $productBreakdown['bottom_margin'],
            'category_breakdown' => $categoryBreakdown,
            'expense_breakdown' => $expenseBreakdown,
            'trend_data' => $trendData,
            'cashier_breakdown' => $cashierBreakdown,
            'outlet_breakdown' => $outletBreakdown,
            'financial_positions' => $financialPositions,
            'last_updated_at' => Carbon::now()->format('d F Y, H:i') . ' WIB',
        ];
    }

    private function resolveDateRange($period, $startDate = null, $endDate = null)
    {
        $now = Carbon::now();

        switch ($period) {
            case 'today':
                return [
                    'start_date' => $now->toDateString(),
                    'end_date' => $now->toDateString(),
                ];
            case 'yesterday':
                $y = $now->copy()->subDay();
                return [
                    'start_date' => $y->toDateString(),
                    'end_date' => $y->toDateString(),
                ];
            case 'this_week':
                return [
                    'start_date' => $now->copy()->startOfWeek()->toDateString(),
                    'end_date' => $now->copy()->endOfWeek()->toDateString(),
                ];
            case 'last_month':
                $lm = $now->copy()->subMonth();
                return [
                    'start_date' => $lm->copy()->startOfMonth()->toDateString(),
                    'end_date' => $lm->copy()->endOfMonth()->toDateString(),
                ];
            case 'this_year':
                return [
                    'start_date' => $now->copy()->startOfYear()->toDateString(),
                    'end_date' => $now->copy()->endOfYear()->toDateString(),
                ];
            case 'last_year':
                $ly = $now->copy()->subYear();
                return [
                    'start_date' => $ly->copy()->startOfYear()->toDateString(),
                    'end_date' => $ly->copy()->endOfYear()->toDateString(),
                ];
            case 'custom':
                return [
                    'start_date' => $startDate ?: $now->copy()->startOfMonth()->toDateString(),
                    'end_date' => $endDate ?: $now->copy()->endOfMonth()->toDateString(),
                ];
            case 'this_month':
            default:
                return [
                    'start_date' => $now->copy()->startOfMonth()->toDateString(),
                    'end_date' => $now->copy()->endOfMonth()->toDateString(),
                ];
        }
    }

    private function resolvePreviousPeriodRange($startDate, $endDate)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $diffDays = $start->diffInDays($end) + 1;

        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($diffDays - 1);

        return [
            'start_date' => $prevStart->toDateString(),
            'end_date' => $prevEnd->toDateString(),
        ];
    }

    private function calculateMetrics($startDate, $endDate, $outletId = null, $cashierId = null)
    {
        // 1. Sales Query (Completed Sales Only)
        $salesQuery = DB::table('sales')
            ->whereBetween('sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', 'completed')
            ->whereNull('deleted_at');

        if ($outletId) $salesQuery->where('outlet_id', $outletId);
        if ($cashierId) $salesQuery->where('cashier_id', $cashierId);

        $totalTransactions = (clone $salesQuery)->count();
        $totalDiscount = (float)(clone $salesQuery)->sum('discount');
        $grandTotalSum = (float)(clone $salesQuery)->sum('grand_total');
        $subtotalSum = (float)(clone $salesQuery)->sum('subtotal');
        $grossSales = $subtotalSum > 0 ? ($subtotalSum + $totalDiscount) : ($grandTotalSum + $totalDiscount);

        // 2. Sales Returns Query
        $returnsQuery = DB::table('product_returns')
            ->whereBetween('product_returns.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('product_returns.type', 'sale')
            ->whereIn('product_returns.status', ['APPROVED', 'COMPLETED', 'approved', 'completed']);

        if ($outletId) {
            $returnsQuery->join('sales', 'product_returns.reference_id', '=', 'sales.id')
                ->where('sales.outlet_id', $outletId);
        }
        $totalReturns = (float)$returnsQuery->sum('product_returns.total_amount');

        // 3. Net Sales
        $netSales = max(0, $grossSales - $totalDiscount - $totalReturns);

        // 4. COGS (HPP) Query
        $cogsQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('obats', 'sale_items.medicine_id', '=', 'obats.kode')
            ->whereBetween('sales.sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at');

        if ($outletId) $cogsQuery->where('sales.outlet_id', $outletId);
        if ($cashierId) $cogsQuery->where('sales.cashier_id', $cashierId);

        $cogs = (float)$cogsQuery->sum(DB::raw("
            CASE 
                WHEN sale_items.buy_price > 0 THEN (sale_items.quantity * sale_items.buy_price)
                WHEN obats.harga > 0 THEN (sale_items.quantity * obats.harga * 0.70)
                ELSE (sale_items.subtotal * 0.70)
            END
        "));

        $totalItemsSold = (int)(clone $cogsQuery)->sum('sale_items.quantity');

        // 5. Gross Profit
        $grossProfit = $netSales - $cogs;
        $grossMarginPercent = $netSales > 0 ? round(($grossProfit / $netSales) * 100, 2) : 0;

        // 6. Operating Expenses
        $expenseQuery = DB::table('expenses')
            ->whereBetween('expense_date', [$startDate, $endDate]);
        if ($outletId && \Illuminate\Support\Facades\Schema::hasColumn('expenses', 'outlet_id')) {
            $expenseQuery->where('outlet_id', $outletId);
        }
        $totalExpenses = (float)$expenseQuery->sum('amount');

        // 7. Net Profit
        $netProfit = $grossProfit - $totalExpenses;
        $netMarginPercent = $netSales > 0 ? round(($netProfit / $netSales) * 100, 2) : 0;

        $avgTransactionValue = $totalTransactions > 0 ? round($netSales / $totalTransactions, 0) : 0;

        return [
            'total_transactions' => $totalTransactions,
            'total_items_sold' => $totalItemsSold,
            'gross_sales' => $grossSales,
            'total_discount' => $totalDiscount,
            'total_returns' => $totalReturns,
            'net_sales' => $netSales,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMarginPercent,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'net_margin_percent' => $netMarginPercent,
            'avg_transaction_value' => $avgTransactionValue,
        ];
    }

    private function calculateComparisons($current, $prev)
    {
        $metricsToCompare = ['gross_sales', 'total_discount', 'net_sales', 'cogs', 'gross_profit', 'total_expenses', 'net_profit'];
        $res = [];

        foreach ($metricsToCompare as $key) {
            $curVal = $current[$key] ?? 0;
            $prevVal = $prev[$key] ?? 0;
            $diff = $curVal - $prevVal;

            $pct = 0;
            if ($prevVal != 0) {
                $pct = round(($diff / abs($prevVal)) * 100, 1);
            } else if ($curVal > 0) {
                $pct = 100;
            }

            $res[$key] = [
                'current' => $curVal,
                'previous' => $prevVal,
                'diff' => $diff,
                'percentage' => $pct,
                'direction' => $diff >= 0 ? 'up' : 'down',
            ];
        }

        return $res;
    }

    private function getPaymentMethodBreakdown($startDate, $endDate, $outletId = null, $cashierId = null)
    {
        $query = DB::table('sales')
            ->select('payment_method', DB::raw('COUNT(*) as total_count'), DB::raw('SUM(grand_total) as total_amount'))
            ->whereBetween('sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', 'completed')
            ->whereNull('deleted_at');

        if ($outletId) $query->where('outlet_id', $outletId);
        if ($cashierId) $query->where('cashier_id', $cashierId);

        $results = $query->groupBy('payment_method')->get();
        $grandTotal = $results->sum('total_amount');

        return $results->map(function ($r) use ($grandTotal) {
            $amt = (float)$r->total_amount;
            $pct = $grandTotal > 0 ? round(($amt / $grandTotal) * 100, 1) : 0;
            return [
                'method' => strtoupper($r->payment_method),
                'method_raw' => strtolower($r->payment_method),
                'count' => (int)$r->total_count,
                'total_amount' => $amt,
                'percentage' => $pct,
            ];
        })->values()->all();
    }

    private function getProductMarginBreakdown($startDate, $endDate, $outletId = null, $cashierId = null, array $filters = [])
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('obats', 'sale_items.medicine_id', '=', 'obats.kode')
            ->leftJoin('suppliers', 'obats.supplier_id', '=', 'suppliers.id')
            ->select(
                'obats.kode',
                'obats.nama',
                'obats.kategori',
                'obats.supplier_name',
                DB::raw('SUM(sale_items.quantity) as qty_sold'),
                DB::raw('SUM(sale_items.subtotal) as total_sales'),
                DB::raw('SUM(CASE WHEN sale_items.buy_price > 0 THEN (sale_items.quantity * sale_items.buy_price) ELSE (sale_items.quantity * obats.harga * 0.70) END) as total_cogs')
            )
            ->whereBetween('sales.sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at');

        if ($outletId) $query->where('sales.outlet_id', $outletId);
        if ($cashierId) $query->where('sales.cashier_id', $cashierId);

        if (!empty($filters['category'])) {
            $query->where('obats.kategori', $filters['category']);
        }

        $items = $query->groupBy('obats.kode', 'obats.nama', 'obats.kategori', 'obats.supplier_name')->get();

        $processed = $items->map(function ($i) {
            $sales = (float)$i->total_sales;
            $cogs = (float)$i->total_cogs;
            $profit = $sales - $cogs;
            $margin = $sales > 0 ? round(($profit / $sales) * 100, 2) : 0;

            return [
                'kode' => $i->kode,
                'nama' => $i->nama,
                'kategori' => $i->kategori || 'Umum',
                'supplier_name' => $i->supplier_name || 'Multi Supplier',
                'qty_sold' => (int)$i->qty_sold,
                'total_sales' => $sales,
                'cogs' => $cogs,
                'gross_profit' => $profit,
                'margin_percent' => $margin,
            ];
        });

        $topSales = $processed->sortByDesc('total_sales')->take(10)->values()->all();
        $topProfit = $processed->sortByDesc('gross_profit')->take(10)->values()->all();
        $bottomMargin = $processed->sortBy('margin_percent')->take(10)->values()->all();

        return [
            'items' => $processed->values()->all(),
            'top_sales' => $topSales,
            'top_profit' => $topProfit,
            'bottom_margin' => $bottomMargin,
        ];
    }

    private function getCategoryBreakdown($startDate, $endDate, $outletId = null, $cashierId = null)
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('obats', 'sale_items.medicine_id', '=', 'obats.kode')
            ->select(
                DB::raw("COALESCE(NULLIF(obats.kategori, ''), 'Bebas') as category_name"),
                DB::raw('SUM(sale_items.quantity) as qty_sold'),
                DB::raw('SUM(sale_items.subtotal) as total_sales'),
                DB::raw('SUM(CASE WHEN sale_items.buy_price > 0 THEN (sale_items.quantity * sale_items.buy_price) ELSE (sale_items.quantity * obats.harga * 0.70) END) as total_cogs')
            )
            ->whereBetween('sales.sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at');

        if ($outletId) $query->where('sales.outlet_id', $outletId);
        if ($cashierId) $query->where('sales.cashier_id', $cashierId);

        $categories = $query->groupBy(DB::raw("COALESCE(NULLIF(obats.kategori, ''), 'Bebas')"))->get();
        $grandTotalSales = $categories->sum('total_sales');

        return $categories->map(function ($c) use ($grandTotalSales) {
            $sales = (float)$c->total_sales;
            $cogs = (float)$c->total_cogs;
            $profit = $sales - $cogs;
            $margin = $sales > 0 ? round(($profit / $sales) * 100, 2) : 0;
            $pctTotal = $grandTotalSales > 0 ? round(($sales / $grandTotalSales) * 100, 1) : 0;

            return [
                'category' => $c->category_name,
                'qty_sold' => (int)$c->qty_sold,
                'total_sales' => $sales,
                'cogs' => $cogs,
                'gross_profit' => $profit,
                'margin_percent' => $margin,
                'percentage_total' => $pctTotal,
            ];
        })->sortByDesc('total_sales')->values()->all();
    }

    private function getExpenseBreakdown($startDate, $endDate, $outletId = null)
    {
        $query = DB::table('expenses')
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select(
                'expense_categories.name as category_name',
                DB::raw('COUNT(expenses.id) as total_count'),
                DB::raw('SUM(expenses.amount) as total_amount')
            )
            ->whereBetween('expenses.expense_date', [$startDate, $endDate]);

        if ($outletId && \Illuminate\Support\Facades\Schema::hasColumn('expenses', 'outlet_id')) {
            $query->where('expenses.outlet_id', $outletId);
        }

        $results = $query->groupBy('expense_categories.name')->get();
        $totalExp = $results->sum('total_amount');

        return $results->map(function ($e) use ($totalExp) {
            $amt = (float)$e->total_amount;
            $pct = $totalExp > 0 ? round(($amt / $totalExp) * 100, 1) : 0;

            return [
                'category' => $e->category_name,
                'count' => (int)$e->total_count,
                'total_amount' => $amt,
                'percentage' => $pct,
            ];
        })->sortByDesc('total_amount')->values()->all();
    }

    private function getTrendData($startDate, $endDate, $outletId = null, $cashierId = null)
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $salesDaily = DB::table('sales')
            ->select(
                DB::raw('DATE(sale_date) as date'),
                DB::raw('SUM(grand_total) as net_sales')
            )
            ->whereBetween('sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', 'completed')
            ->whereNull('deleted_at');

        if ($outletId) $salesDaily->where('outlet_id', $outletId);
        if ($cashierId) $salesDaily->where('cashier_id', $cashierId);

        $salesMap = $salesDaily->groupBy(DB::raw('DATE(sale_date)'))->pluck('net_sales', 'date')->all();

        $cogsDaily = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('obats', 'sale_items.medicine_id', '=', 'obats.kode')
            ->select(
                DB::raw('DATE(sales.sale_date) as date'),
                DB::raw('SUM(CASE WHEN sale_items.buy_price > 0 THEN (sale_items.quantity * sale_items.buy_price) ELSE (sale_items.quantity * obats.harga * 0.70) END) as cogs')
            )
            ->whereBetween('sales.sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at');

        if ($outletId) $cogsDaily->where('sales.outlet_id', $outletId);
        if ($cashierId) $cogsDaily->where('sales.cashier_id', $cashierId);

        $cogsMap = $cogsDaily->groupBy(DB::raw('DATE(sales.sale_date)'))->pluck('cogs', 'date')->all();

        $expensesDaily = DB::table('expenses')
            ->select(
                DB::raw('DATE(expense_date) as date'),
                DB::raw('SUM(amount) as expenses')
            )
            ->whereBetween('expense_date', [$startDate, $endDate]);

        if ($outletId && \Illuminate\Support\Facades\Schema::hasColumn('expenses', 'outlet_id')) {
            $expensesDaily->where('outlet_id', $outletId);
        }

        $expensesMap = $expensesDaily->groupBy(DB::raw('DATE(expense_date)'))->pluck('expenses', 'date')->all();

        $labels = [];
        $salesArr = [];
        $cogsArr = [];
        $grossProfitArr = [];
        $expensesArr = [];
        $netProfitArr = [];

        $curr = $start->copy();
        while ($curr->lte($end)) {
            $dStr = $curr->toDateString();
            $labels[] = $curr->format('d M');

            $s = (float)($salesMap[$dStr] ?? 0);
            $c = (float)($cogsMap[$dStr] ?? 0);
            $gp = $s - $c;
            $e = (float)($expensesMap[$dStr] ?? 0);
            $np = $gp - $e;

            $salesArr[] = $s;
            $cogsArr[] = $c;
            $grossProfitArr[] = $gp;
            $expensesArr[] = $e;
            $netProfitArr[] = $np;

            $curr->addDay();
        }

        return [
            'labels' => $labels,
            'sales' => $salesArr,
            'cogs' => $cogsArr,
            'gross_profit' => $grossProfitArr,
            'expenses' => $expensesArr,
            'net_profit' => $netProfitArr,
        ];
    }

    private function getCashierShiftBreakdown($startDate, $endDate, $outletId = null, $cashierId = null)
    {
        $query = DB::table('sales')
            ->join('users', 'sales.cashier_id', '=', 'users.id')
            ->select(
                'users.name as cashier_name',
                DB::raw('COUNT(sales.id) as total_transactions'),
                DB::raw('SUM(sales.subtotal + sales.discount) as gross_sales'),
                DB::raw('SUM(sales.discount) as total_discount'),
                DB::raw('SUM(sales.grand_total) as net_sales')
            )
            ->whereBetween('sales.sale_date', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at');

        if ($outletId) $query->where('sales.outlet_id', $outletId);
        if ($cashierId) $query->where('sales.cashier_id', $cashierId);

        return $query->groupBy('users.name')->get()->map(function ($r) {
            return [
                'cashier_name' => $r->cashier_name,
                'total_transactions' => (int)$r->total_transactions,
                'gross_sales' => (float)$r->gross_sales,
                'total_discount' => (float)$r->total_discount,
                'net_sales' => (float)$r->net_sales,
            ];
        })->sortByDesc('net_sales')->values()->all();
    }

    private function getOutletBreakdown($startDate, $endDate)
    {
        $outlets = DB::table('outlets')->whereNull('deleted_at')->get();
        if ($outlets->isEmpty()) {
            return [];
        }

        return $outlets->map(function ($out) use ($startDate, $endDate) {
            $m = $this->calculateMetrics($startDate, $endDate, $out->id);
            return [
                'outlet_id' => $out->id,
                'outlet_name' => $out->name,
                'net_sales' => $m['net_sales'],
                'cogs' => $m['cogs'],
                'gross_profit' => $m['gross_profit'],
                'total_expenses' => $m['total_expenses'],
                'net_profit' => $m['net_profit'],
            ];
        })->sortByDesc('net_sales')->values()->all();
    }

    private function getFinancialPositionsSummary()
    {
        $cashBankAccounts = DB::table('cash_bank_accounts')
            ->select('type', DB::raw('SUM(current_balance) as balance'))
            ->groupBy('type')
            ->pluck('balance', 'type')
            ->all();

        $totalCash = (float)($cashBankAccounts['cash'] ?? 0);
        $totalBank = (float)($cashBankAccounts['bank'] ?? 0);
        $totalQris = (float)($cashBankAccounts['qris'] ?? 0);
        $totalEwallet = (float)($cashBankAccounts['ewallet'] ?? 0);
        $totalCashBank = $totalCash + $totalBank + $totalQris + $totalEwallet;

        $payablesTotal = (float)DB::table('accounts_payables')->where('status', '!=', 'paid')->sum('remaining_amount');
        $payablesOverdue = (float)DB::table('accounts_payables')->where('status', '!=', 'paid')->where('due_date', '<', Carbon::now()->toDateString())->sum('remaining_amount');

        $receivablesTotal = (float)DB::table('accounts_receivables')->where('status', '!=', 'paid')->sum('remaining_amount');
        $receivablesOverdue = (float)DB::table('accounts_receivables')->where('status', '!=', 'paid')->where('due_date', '<', Carbon::now()->toDateString())->sum('remaining_amount');

        return [
            'total_cash_bank' => $totalCashBank,
            'total_cash' => $totalCash,
            'total_bank' => $totalBank,
            'total_qris' => $totalQris,
            'total_ewallet' => $totalEwallet,
            'total_payables' => $payablesTotal,
            'overdue_payables' => $payablesOverdue,
            'total_receivables' => $receivablesTotal,
            'overdue_receivables' => $receivablesOverdue,
        ];
    }
}
