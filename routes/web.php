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
    
    // Import Obat Routes
    Route::get('/obat/import-template', [\App\Http\Controllers\Admin\ObatController::class, 'downloadTemplate'])->name('obat.import-template');
    Route::post('/obat/import', [\App\Http\Controllers\Admin\ObatController::class, 'import'])->name('obat.import');

    Route::resource('obat', \App\Http\Controllers\Admin\ObatController::class);
    Route::resource('kasir', \App\Http\Controllers\Admin\KasirController::class);

    // Master Satuan Module
    Route::get('/units', [\App\Http\Controllers\Admin\UnitController::class, 'index'])->name('units.index');
    Route::post('/units', [\App\Http\Controllers\Admin\UnitController::class, 'store'])->name('units.store');
    Route::put('/units/{id}', [\App\Http\Controllers\Admin\UnitController::class, 'update'])->name('units.update');
    Route::post('/units/{id}/toggle-status', [\App\Http\Controllers\Admin\UnitController::class, 'toggleStatus'])->name('units.toggle-status');
    Route::delete('/units/{id}', [\App\Http\Controllers\Admin\UnitController::class, 'destroy'])->name('units.destroy');

    // Product Unit & Price Management Module
    Route::get('/obat/{kode}/units-prices', [\App\Http\Controllers\Admin\ProductUnitPriceController::class, 'getDetails'])->name('obat.units-prices.get');
    Route::post('/obat/{kode}/primary-units', [\App\Http\Controllers\Admin\ProductUnitPriceController::class, 'setPrimaryUnits'])->name('obat.primary-units.store');
    Route::post('/obat/{kode}/conversion', [\App\Http\Controllers\Admin\ProductUnitPriceController::class, 'saveConversion'])->name('obat.conversion.store');
    Route::delete('/obat/{kode}/conversion/{id}', [\App\Http\Controllers\Admin\ProductUnitPriceController::class, 'deleteConversion'])->name('obat.conversion.destroy');
    Route::post('/obat/{kode}/prices', [\App\Http\Controllers\Admin\ProductUnitPriceController::class, 'updatePrices'])->name('obat.prices.store');

    // User Management & RBAC Module
    Route::get('/users/dashboard', [UserController::class, 'dashboard'])->name('users.dashboard');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
    Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('/users/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{id}/force-logout', [UserController::class, 'forceLogout'])->name('users.force-logout');
    Route::post('/users/{id}/outlets', [UserController::class, 'updateOutlets'])->name('users.update-outlets');

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
    Route::get('/pos/search-medicines', [\App\Http\Controllers\Api\PosController::class, 'searchMedicines'])->name('pos.search');
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
    Route::get('/opname/{id}/print', [\App\Http\Controllers\StockOpnameManualController::class, 'print'])->name('opname.print');
    Route::get('/opname/{id}/export-excel', [\App\Http\Controllers\StockOpnameManualController::class, 'exportExcel'])->name('opname.export_excel');

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
    Route::put('/finance/accounts/{id}', [FinancialManagementController::class, 'updateAccount'])->name('finance.accounts.update');
    Route::delete('/finance/accounts/{id}', [FinancialManagementController::class, 'destroyAccount'])->name('finance.accounts.destroy');

    // Full-Feature Membership & Customer Loyalty Module Routes
    Route::get('/membership', [\App\Http\Controllers\Membership\MembershipController::class, 'dashboard'])->name('membership.dashboard');
    Route::get('/membership/members', [\App\Http\Controllers\Membership\MembershipController::class, 'members'])->name('membership.members.index');
    Route::post('/membership/members', [\App\Http\Controllers\Membership\MembershipController::class, 'storeMember'])->name('membership.members.store');
    Route::get('/membership/members/{id}', [\App\Http\Controllers\Membership\MembershipController::class, 'memberShow'])->name('membership.members.show');
    Route::put('/membership/members/{id}', [\App\Http\Controllers\Membership\MembershipController::class, 'updateMember'])->name('membership.members.update');
    Route::delete('/membership/members/{id}', [\App\Http\Controllers\Membership\MembershipController::class, 'destroyMember'])->name('membership.members.destroy');
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
    Route::get('/about', [\App\Http\Controllers\AboutController::class, 'index'])->name('about.index');

    // Returns / Retur Barang Routes
    Route::get('/returns', [\App\Http\Controllers\ReturnController::class, 'index'])->name('returns.index');
    Route::get('/returns/create', [\App\Http\Controllers\ReturnController::class, 'create'])->name('returns.create');
    Route::post('/returns', [\App\Http\Controllers\ReturnController::class, 'store'])->name('returns.store');
    Route::get('/returns/{id}', [\App\Http\Controllers\ReturnController::class, 'show'])->name('returns.show');
    Route::post('/returns/{id}/approve', [\App\Http\Controllers\ReturnController::class, 'approve'])->name('returns.approve');
    Route::post('/returns/{id}/cancel', [\App\Http\Controllers\ReturnController::class, 'cancel'])->name('returns.cancel');
    Route::post('/returns/{id}/log-print', [\App\Http\Controllers\ReturnController::class, 'logPrint'])->name('returns.log-print');

    // Outlet Management & Switch Routes
    Route::post('/outlets/switch', [\App\Http\Controllers\Admin\OutletController::class, 'switchOutlet'])->name('outlets.switch');
    Route::get('/outlets', [\App\Http\Controllers\Admin\OutletController::class, 'index'])->name('outlets.index');
    Route::post('/outlets', [\App\Http\Controllers\Admin\OutletController::class, 'store'])->name('outlets.store');
    Route::put('/outlets/{id}', [\App\Http\Controllers\Admin\OutletController::class, 'update'])->name('outlets.update');
    Route::delete('/outlets/{id}', [\App\Http\Controllers\Admin\OutletController::class, 'destroy'])->name('outlets.destroy');
    Route::post('/outlets/{id}/restore', [\App\Http\Controllers\Admin\OutletController::class, 'restore'])->name('outlets.restore');
    Route::post('/outlets/{id}/set-main', [\App\Http\Controllers\Admin\OutletController::class, 'setMain'])->name('outlets.set-main');
    Route::post('/outlets/{id}/toggle-status', [\App\Http\Controllers\Admin\OutletController::class, 'toggleStatus'])->name('outlets.toggle-status');

    // Stock per Outlet & Stock Transfer Routes
    Route::get('/inventory/stocks-per-outlet', [\App\Http\Controllers\Inventory\StockTransferController::class, 'stockPerOutlet'])->name('inventory.stocks-per-outlet');
    Route::get('/inventory/stock-transfers', [\App\Http\Controllers\Inventory\StockTransferController::class, 'index'])->name('stock-transfers.index');
    Route::get('/inventory/stock-transfers/create', [\App\Http\Controllers\Inventory\StockTransferController::class, 'create'])->name('stock-transfers.create');
    Route::post('/inventory/stock-transfers', [\App\Http\Controllers\Inventory\StockTransferController::class, 'store'])->name('stock-transfers.store');
    Route::post('/inventory/stock-transfers/{id}/send', [\App\Http\Controllers\Inventory\StockTransferController::class, 'send'])->name('stock-transfers.send');
    Route::post('/inventory/stock-transfers/{id}/receive', [\App\Http\Controllers\Inventory\StockTransferController::class, 'receive'])->name('stock-transfers.receive');
    Route::post('/inventory/stock-transfers/{id}/cancel', [\App\Http\Controllers\Inventory\StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');

    // Outlet Product Management Routes
    Route::get('/inventory/outlet-products', [\App\Http\Controllers\Inventory\OutletProductController::class, 'index'])->name('inventory.outlet-products.index');
    Route::post('/inventory/outlet-products/attach', [\App\Http\Controllers\Inventory\OutletProductController::class, 'attach'])->name('inventory.outlet-products.attach');
    Route::put('/inventory/outlet-products/{id}/price', [\App\Http\Controllers\Inventory\OutletProductController::class, 'updatePrice'])->name('inventory.outlet-products.update-price');
    Route::post('/inventory/outlet-products/{id}/toggle-status', [\App\Http\Controllers\Inventory\OutletProductController::class, 'toggleStatus'])->name('inventory.outlet-products.toggle-status');
    Route::delete('/inventory/outlet-products/{id}', [\App\Http\Controllers\Inventory\OutletProductController::class, 'detach'])->name('inventory.outlet-products.detach');
});

Route::middleware(['auth', 'role:kasir'])->prefix('kasir')->name('kasir.')->group(function () {
    Route::get('/dashboard', [KasirController::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:owner'])->prefix('owner')->name('owner.')->group(function () {
    Route::get('/dashboard', [OwnerController::class, 'index'])->name('dashboard');
});

require __DIR__.'/auth.php';
