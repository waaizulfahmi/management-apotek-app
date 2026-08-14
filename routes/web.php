<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\OwnerController;

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ApprovalController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    if (auth()->user()->role === 'admin') {
        return redirect()->route('admin.dashboard');
    } elseif (auth()->user()->role === 'kasir') {
        return redirect()->route('kasir.dashboard');
    } elseif (auth()->user()->role === 'owner') {
        return redirect()->route('owner.dashboard');
    }
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::resource('obat', \App\Http\Controllers\Admin\ObatController::class);
    Route::resource('kasir', \App\Http\Controllers\Admin\KasirController::class);

    // User Management & RBAC Module
    Route::get('/users/dashboard', [UserController::class, 'dashboard'])->name('users.dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{id}/force-logout', [UserController::class, 'forceLogout'])->name('users.force-logout');

    // Role & Permission Matrix
    Route::get('/roles', [RolePermissionController::class, 'index'])->name('roles.index');
    Route::post('/roles', [RolePermissionController::class, 'store'])->name('roles.store');
    Route::put('/roles/{id}/permissions', [RolePermissionController::class, 'updatePermissions'])->name('roles.permissions.update');
    Route::delete('/roles/{id}', [RolePermissionController::class, 'destroy'])->name('roles.destroy');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Trash Manager (Soft Delete Recovery)
    Route::get('/trash', [\App\Http\Controllers\TrashManagerController::class, 'index'])->name('trash.index');
    Route::post('/trash/restore/{module}/{id}', [\App\Http\Controllers\TrashManagerController::class, 'restore'])->name('trash.restore');
    Route::delete('/trash/force-delete/{module}/{id}', [\App\Http\Controllers\TrashManagerController::class, 'forceDelete'])->name('trash.force_delete');

    // Approvals
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals.index');
    Route::post('/approvals/{id}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{id}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
});

use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PrescriptionController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettingController;

use App\Http\Controllers\Api\StockOpnameController;

use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DoctorController;

use App\Http\Controllers\Api\FinancialManagementController;

use App\Http\Controllers\Api\PurchaseOrderController;

Route::middleware(['auth'])->group(function () {
    Route::get('/pos', [\App\Http\Controllers\Api\PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [\App\Http\Controllers\Api\PosController::class, 'checkout'])->name('pos.checkout');

    // Sales History & Cashier Reports Routes
    Route::get('/sales/history', [\App\Http\Controllers\SalesHistoryController::class, 'index'])->name('sales.history.index');
    Route::get('/sales/history/{id}', [\App\Http\Controllers\SalesHistoryController::class, 'show'])->name('sales.history.show');
    Route::get('/sales/cashier', [\App\Http\Controllers\SalesCashierController::class, 'index'])->name('sales.cashier.index');

    // Shift Management Routes
    Route::get('/shifts', [\App\Http\Controllers\ShiftController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/active', [\App\Http\Controllers\ShiftController::class, 'activeShift'])->name('shifts.active');
    Route::post('/shifts/open', [\App\Http\Controllers\ShiftController::class, 'open'])->name('shifts.open');
    Route::get('/shifts/{id}', [\App\Http\Controllers\ShiftController::class, 'show'])->name('shifts.show');
    Route::post('/shifts/{id}/close', [\App\Http\Controllers\ShiftController::class, 'close'])->name('shifts.close');
    Route::post('/shifts/{id}/force-close', [\App\Http\Controllers\ShiftController::class, 'forceClose'])->name('shifts.force_close');
    Route::post('/shifts/{id}/adjust-cash', [\App\Http\Controllers\ShiftController::class, 'adjustCash'])->name('shifts.adjust_cash');

    // Master Shift Management Routes
    Route::get('/master-shifts', [\App\Http\Controllers\MasterShiftController::class, 'index'])->name('master-shifts.index');
    Route::post('/master-shifts', [\App\Http\Controllers\MasterShiftController::class, 'store'])->name('master-shifts.store');
    Route::put('/master-shifts/{id}', [\App\Http\Controllers\MasterShiftController::class, 'update'])->name('master-shifts.update');
    Route::post('/master-shifts/{id}/toggle', [\App\Http\Controllers\MasterShiftController::class, 'toggleActive'])->name('master-shifts.toggle');
    Route::delete('/master-shifts/{id}', [\App\Http\Controllers\MasterShiftController::class, 'destroy'])->name('master-shifts.destroy');

    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');

    Route::get('/doctors', [DoctorController::class, 'index'])->name('doctors.index');
    Route::post('/doctors', [DoctorController::class, 'store'])->name('doctors.store');

    // Enhanced Purchase Order (PO) & Goods Receipt Routes
    Route::get('/purchases', [PurchaseOrderController::class, 'index'])->name('purchases.index');
    Route::get('/purchases/po', [PurchaseOrderController::class, 'index'])->name('purchases.po.index');
    Route::get('/purchases/po/create', [PurchaseOrderController::class, 'create'])->name('purchases.po.create');
    Route::post('/purchases/po', [PurchaseOrderController::class, 'store'])->name('purchases.po.store');
    Route::get('/purchases/po/recommendations', [PurchaseOrderController::class, 'recommendations'])->name('purchases.po.recommendations');
    Route::get('/purchases/po/{id}', [PurchaseOrderController::class, 'show'])->name('purchases.po.show');
    Route::post('/purchases/po/{id}/approve', [PurchaseOrderController::class, 'approve'])->name('purchases.po.approve');
    Route::post('/purchases/po/{id}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchases.po.cancel');
    Route::get('/purchases/po/{id}/receive', [PurchaseOrderController::class, 'receiveForm'])->name('purchases.po.receive');
    Route::post('/purchases/po/{id}/receive', [PurchaseOrderController::class, 'receiveStore'])->name('purchases.po.receive.store');

    Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
    Route::get('/prescriptions/{id}', [PrescriptionController::class, 'show'])->name('prescriptions.show');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
    Route::put('/prescriptions/{id}', [PrescriptionController::class, 'update'])->name('prescriptions.update');
    Route::delete('/prescriptions/{id}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');
    Route::post('/prescriptions/{id}/status', [PrescriptionController::class, 'updateStatus'])->name('prescriptions.status');

    // Inventory Movement & Kartu Stok Routes
    Route::get('/inventory/stocks', [\App\Http\Controllers\Api\StockMovementController::class, 'realStock'])->name('inventory.stocks');
    Route::get('/inventory/movements', [\App\Http\Controllers\Api\StockMovementController::class, 'index'])->name('inventory.movements');
    Route::get('/inventory/movements/reconcile', [\App\Http\Controllers\Api\StockMovementController::class, 'reconcile'])->name('inventory.movements.reconcile');
    Route::post('/inventory/movements/adjustment', [\App\Http\Controllers\Api\StockMovementController::class, 'storeAdjustment'])->name('inventory.movements.adjustment');
    
    // Stock Opname Manual Module Routes
    Route::get('/opname', [\App\Http\Controllers\StockOpnameManualController::class, 'index'])->name('opname.index');
    Route::post('/opname/create', [\App\Http\Controllers\StockOpnameManualController::class, 'store'])->name('opname.store');
    Route::get('/opname/{id}', [\App\Http\Controllers\StockOpnameManualController::class, 'show'])->name('opname.show');
    Route::post('/opname/{id}/item', [\App\Http\Controllers\StockOpnameManualController::class, 'updateItem'])->name('opname.update_item');
    Route::post('/opname/{id}/mark-all-counted', [\App\Http\Controllers\StockOpnameManualController::class, 'markAllCounted'])->name('opname.mark_all_counted');
    Route::post('/opname/{id}/finalize', [\App\Http\Controllers\StockOpnameManualController::class, 'finalize'])->name('opname.finalize');
    Route::post('/opname/{id}/cancel', [\App\Http\Controllers\StockOpnameManualController::class, 'cancel'])->name('opname.cancel');
    Route::delete('/opname/{id}', [\App\Http\Controllers\StockOpnameManualController::class, 'destroy'])->name('opname.destroy');

    // Enhanced Stock Opname Module Routes
    Route::get('/inventory/opname', [StockOpnameController::class, 'index'])->name('inventory.opname');
    Route::post('/inventory/opname', [StockOpnameController::class, 'store'])->name('inventory.opname.store');
    Route::get('/inventory/opname/{id}/counting', [StockOpnameController::class, 'countingMode'])->name('inventory.opname.counting');
    Route::get('/inventory/opname/{id}/scan/{barcode}', [StockOpnameController::class, 'scanBarcode'])->name('inventory.opname.scan');
    Route::post('/inventory/opname/{id}/count', [StockOpnameController::class, 'countItem'])->name('inventory.opname.count');
    Route::post('/inventory/opname/{id}/submit', [StockOpnameController::class, 'submitApproval'])->name('inventory.opname.submit');
    Route::get('/inventory/opname/{id}/review', [StockOpnameController::class, 'reviewMode'])->name('inventory.opname.review');
    Route::post('/inventory/opname/{id}/approve', [StockOpnameController::class, 'approve'])->name('inventory.opname.approve');

    // Financial Management Module Routes
    Route::get('/finance', [FinancialManagementController::class, 'dashboard'])->name('finance.index');
    Route::get('/finance/accounts', [FinancialManagementController::class, 'accounts'])->name('finance.accounts');
    Route::post('/finance/accounts', [FinancialManagementController::class, 'storeAccount'])->name('finance.accounts.store');

    // Full-Feature Membership & Customer Loyalty Module Routes
    Route::get('/membership', [\App\Http\Controllers\Membership\MembershipController::class, 'dashboard'])->name('membership.dashboard');
    Route::get('/membership/members', [\App\Http\Controllers\Membership\MembershipController::class, 'members'])->name('membership.members.index');
    Route::post('/membership/members', [\App\Http\Controllers\Membership\MembershipController::class, 'storeMember'])->name('membership.members.store');
    Route::get('/membership/members/{id}', [\App\Http\Controllers\Membership\MembershipController::class, 'memberShow'])->name('membership.members.show');
    Route::get('/membership/points', [\App\Http\Controllers\Membership\MembershipController::class, 'points'])->name('membership.points.index');
    Route::get('/membership/rewards', [\App\Http\Controllers\Membership\MembershipController::class, 'rewards'])->name('membership.rewards.index');
    Route::post('/membership/rewards/redeem', [\App\Http\Controllers\Membership\MembershipController::class, 'redeemReward'])->name('membership.rewards.redeem');
    Route::get('/membership/vouchers', [\App\Http\Controllers\Membership\MembershipController::class, 'vouchers'])->name('membership.vouchers.index');
    Route::get('/membership/promos', [\App\Http\Controllers\Membership\MembershipController::class, 'promos'])->name('membership.promos.index');
    Route::get('/membership/tiers', [\App\Http\Controllers\Membership\MembershipController::class, 'tiers'])->name('membership.tiers.index');
    Route::get('/membership/purchases', [\App\Http\Controllers\Membership\MembershipController::class, 'purchases'])->name('membership.purchases.index');
    Route::get('/membership/settings', [\App\Http\Controllers\Membership\MembershipController::class, 'settings'])->name('membership.settings.index');
    Route::post('/membership/settings', [\App\Http\Controllers\Membership\MembershipController::class, 'updateSettings'])->name('membership.settings.update');
    Route::get('/finance/payables', [FinancialManagementController::class, 'payables'])->name('finance.payables');
    Route::post('/finance/payables/{id}/pay', [FinancialManagementController::class, 'payPayable'])->name('finance.payables.pay');
    Route::get('/finance/receivables', [FinancialManagementController::class, 'receivables'])->name('finance.receivables');
    Route::get('/finance/journals', [FinancialManagementController::class, 'journals'])->name('finance.journals');
    Route::get('/finance/profit-loss', [FinancialManagementController::class, 'profitLoss'])->name('finance.profit_loss');
    Route::post('/finance/expense', [FinanceController::class, 'storeExpense'])->name('finance.expense.store');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/reset-logo', [SettingController::class, 'resetLogo'])->name('settings.reset-logo');

    // Returns / Retur Barang Routes
    Route::get('/returns', [\App\Http\Controllers\ReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/create', [\App\Http\Controllers\ReturnController::class, 'create'])->name('returns.create');
    Route::post('/returns', [\App\Http\Controllers\ReturnController::class, 'store'])->name('returns.store');
    Route::get('/returns/{id}', [\App\Http\Controllers\ReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{id}/approve', [\App\Http\Controllers\ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('/returns/{id}/cancel', [\App\Http\Controllers\ReturnController::class, 'cancel'])->name('returns.cancel');
    Route::post('/returns/{id}/log-print', [\App\Http\Controllers\ReturnController::class, 'logPrint'])->name('returns.log-print');
});

Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    Route::get('/dashboard', [KasirController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', [OwnerController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';
