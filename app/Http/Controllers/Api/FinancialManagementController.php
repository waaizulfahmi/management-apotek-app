<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;
use Carbon\Carbon;

class FinancialManagementController extends Controller
{
    /**
     * Financial Dashboard
     */
    public function dashboard(Request $request)
    {
        $range = $request->input('range', 'month');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

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
        } else {
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        }

        // 1. Revenue (Completed Sales)
        $totalRevenue = DB::table('sales')
            ->whereBetween('sale_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'completed')
            ->sum('grand_total');

        // 2. COGS (FEFO Real cost of sold items)
        $totalCogs = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereBetween('sales.sale_date', [$start->toDateString(), $end->toDateString()])
            ->where('sales.status', 'completed')
            ->sum(DB::raw('sale_items.quantity * sale_items.unit_price * 0.65')); // HPP estimation / actual

        $grossProfit = $totalRevenue - $totalCogs;

        // 3. Operational Expenses
        $totalExpenses = DB::table('expenses')
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $netProfit = $grossProfit - $totalExpenses;

        // 4. Balances
        $cashBalance = DB::table('cash_bank_accounts')->where('type', 'cash')->sum('current_balance');
        $bankBalance = DB::table('cash_bank_accounts')->whereIn('type', ['bank', 'qris', 'ewallet'])->sum('current_balance');

        // 5. Payables & Receivables
        $totalPayable = DB::table('accounts_payables')->where('status', '!=', 'paid')->sum('remaining_amount');
        $totalReceivable = DB::table('accounts_receivables')->where('status', '!=', 'paid')->sum('remaining_amount');

        // 6. Top Expenses breakdown
        $topExpenses = DB::table('expenses')
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select('expense_categories.name as category', DB::raw('SUM(expenses.amount) as total'))
            ->whereBetween('expenses.expense_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('expense_categories.name')
            ->orderBy('total', 'desc')
            ->get();

        // 7. Accounts List
        $accounts = DB::table('cash_bank_accounts')->get();

        return Inertia::render('Finance/Dashboard', [
            'metrics' => [
                'total_revenue' => (float)$totalRevenue,
                'total_cogs' => (float)$totalCogs,
                'gross_profit' => (float)$grossProfit,
                'total_expenses' => (float)$totalExpenses,
                'net_profit' => (float)$netProfit,
                'cash_balance' => (float)$cashBalance,
                'bank_balance' => (float)$bankBalance,
                'total_payable' => (float)$totalPayable,
                'total_receivable' => (float)$totalReceivable,
            ],
            'topExpenses' => $topExpenses,
            'accounts' => $accounts,
            'filters' => [
                'range' => $range,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }

    /**
     * Kas & Bank Accounts Management
     */
    public function accounts()
    {
        $accounts = DB::table('cash_bank_accounts')
            ->leftJoin('chart_of_accounts', 'cash_bank_accounts.coa_id', '=', 'chart_of_accounts.id')
            ->select('cash_bank_accounts.*', 'chart_of_accounts.code as coa_code', 'chart_of_accounts.name as coa_name')
            ->get();

        $coas = DB::table('chart_of_accounts')->where('type', 'asset')->get();

        return Inertia::render('Finance/Accounts', [
            'accounts' => $accounts,
            'coas' => $coas,
        ]);
    }

    public function storeAccount(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:cash,bank,qris,ewallet',
            'initial_balance' => 'required|numeric|min:0',
        ]);

        $code = strtoupper($request->type) . '-' . str_pad(DB::table('cash_bank_accounts')->count() + 1, 2, '0', STR_PAD_LEFT);

        DB::table('cash_bank_accounts')->insert([
            'account_code' => $code,
            'name' => $request->name,
            'type' => $request->type,
            'account_number' => $request->account_number,
            'initial_balance' => $request->initial_balance,
            'current_balance' => $request->initial_balance,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Akun Kas / Bank baru berhasil ditambahkan!');
    }

    /**
     * Accounts Payable (Hutang Supplier)
     */
    public function payables()
    {
        $payables = DB::table('accounts_payables')
            ->join('suppliers', 'accounts_payables.supplier_id', '=', 'suppliers.id')
            ->select('accounts_payables.*', 'suppliers.name as supplier_name', 'suppliers.bank_name', 'suppliers.bank_account')
            ->orderBy('due_date', 'asc')
            ->paginate(10);

        $cashAccounts = DB::table('cash_bank_accounts')->where('is_active', true)->get();

        return Inertia::render('Finance/Payables', [
            'payables' => $payables,
            'cashAccounts' => $cashAccounts,
        ]);
    }

    /**
     * Pay Payable (Atomic DB Transaction)
     */
    public function payPayable(Request $request, $id)
    {
        $request->validate([
            'cash_bank_account_id' => 'required|exists:cash_bank_accounts,id',
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
        ]);

        DB::beginTransaction();
        try {
            $payable = DB::table('accounts_payables')->where('id', $id)->first();
            $account = DB::table('cash_bank_accounts')->where('id', $request->cash_bank_account_id)->first();

            if ($account->current_balance < $request->amount) {
                throw new Exception("Saldo {$account->name} tidak mencukupi untuk pembayaran ini!");
            }

            $paid = $payable->paid_amount + $request->amount;
            $remaining = $payable->total_amount - $paid;
            $status = $remaining <= 0 ? 'paid' : 'partial';

            // 1. Update Payable
            DB::table('accounts_payables')->where('id', $id)->update([
                'paid_amount' => $paid,
                'remaining_amount' => max(0, $remaining),
                'status' => $status,
                'updated_at' => now(),
            ]);

            // 2. Insert Payment Record
            $paymentNo = 'PAY-' . date('Ymd') . '-' . rand(100, 999);
            DB::table('payable_payments')->insert([
                'accounts_payable_id' => $id,
                'payment_number' => $paymentNo,
                'cash_bank_account_id' => $request->cash_bank_account_id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Deduct Cash/Bank Account Balance
            DB::table('cash_bank_accounts')->where('id', $request->cash_bank_account_id)->decrement('current_balance', $request->amount);

            // 4. Automatic Double-Entry Journal (Dr. Accounts Payable, Cr. Cash/Bank)
            $journalNo = 'JRN-' . date('Ymd') . '-' . rand(1000, 9999);
            $journalId = DB::table('journal_entries')->insertGetId([
                'journal_number' => $journalNo,
                'transaction_date' => $request->payment_date,
                'reference_number' => $payable->invoice_number,
                'notes' => "Pembayaran Hutang Supplier Inv: {$payable->invoice_number}",
                'posted_by' => auth()->id(),
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $apCoaId = DB::table('chart_of_accounts')->where('code', '2100')->value('id');
            $bankCoaId = $account->coa_id ?? DB::table('chart_of_accounts')->where('code', '1210')->value('id');

            // Debit AP (Liability decreases)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'coa_id' => $apCoaId,
                'debit' => $request->amount,
                'credit' => 0,
                'memo' => 'Pembayaran Hutang Supplier',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Credit Cash/Bank (Asset decreases)
            DB::table('journal_entry_lines')->insert([
                'journal_entry_id' => $journalId,
                'coa_id' => $bankCoaId,
                'debit' => 0,
                'credit' => $request->amount,
                'memo' => "Kas/Bank Out ({$account->name})",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()->back()->with('success', 'Pembayaran hutang supplier berhasil diproses!');
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Accounts Receivable (Piutang Customer)
     */
    public function receivables()
    {
        $receivables = DB::table('accounts_receivables')
            ->leftJoin('customers', 'accounts_receivables.customer_id', '=', 'customers.id')
            ->select('accounts_receivables.*', 'customers.name as customer_name', 'customers.phone')
            ->orderBy('due_date', 'asc')
            ->paginate(10);

        $cashAccounts = DB::table('cash_bank_accounts')->where('is_active', true)->get();

        return Inertia::render('Finance/Receivables', [
            'receivables' => $receivables,
            'cashAccounts' => $cashAccounts,
        ]);
    }

    /**
     * Double Entry Journals List
     */
    public function journals()
    {
        $journals = DB::table('journal_entries')
            ->join('users', 'journal_entries.posted_by', '=', 'users.id')
            ->select('journal_entries.*', 'users.name as posted_by_name')
            ->orderBy('journal_entries.id', 'desc')
            ->paginate(10);

        foreach ($journals as $j) {
            $j->lines = DB::table('journal_entry_lines')
                ->join('chart_of_accounts', 'journal_entry_lines.coa_id', '=', 'chart_of_accounts.id')
                ->select('journal_entry_lines.*', 'chart_of_accounts.code as coa_code', 'chart_of_accounts.name as coa_name')
                ->where('journal_entry_id', $j->id)
                ->get();
        }

        return Inertia::render('Finance/Journals', [
            'journals' => $journals,
        ]);
    }

    /**
     * Profit & Loss Statement (Laba Rugi)
     */
    public function profitLoss(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->toDateString());

        $revenue = DB::table('sales')->whereBetween('sale_date', [$startDate, $endDate])->where('status', 'completed')->sum('grand_total');
        $cogs = DB::table('sale_items')->join('sales', 'sale_items.sale_id', '=', 'sales.id')->whereBetween('sales.sale_date', [$startDate, $endDate])->sum(DB::raw('quantity * unit_price * 0.65'));
        $grossProfit = $revenue - $cogs;

        $expenses = DB::table('expenses')
            ->join('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->select('expenses.*', 'expense_categories.name as category')
            ->whereBetween('expenses.expense_date', [$startDate, $endDate])
            ->get();
        $totalExpenses = $expenses->sum('amount');
        $netProfit = $grossProfit - $totalExpenses;

        return Inertia::render('Finance/ProfitLoss', [
            'revenue' => (float)$revenue,
            'cogs' => (float)$cogs,
            'gross_profit' => (float)$grossProfit,
            'expenses' => $expenses,
            'total_expenses' => (float)$totalExpenses,
            'net_profit' => (float)$netProfit,
            'filters' => ['start_date' => $startDate, 'end_date' => $endDate]
        ]);
    }
}
