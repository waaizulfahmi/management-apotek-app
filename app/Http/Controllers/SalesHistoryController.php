<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Outlet;
use App\Models\User;
use App\Services\OutletService;
use Inertia\Inertia;

class SalesHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $accessibleOutlets = OutletService::getUserOutlets($user);

        $selectedOutletId = $request->input('outlet_id', OutletService::getActiveOutletId($user));
        
        // Ensure user has access to selected outlet
        if ($selectedOutletId && !OutletService::userCanAccessOutlet($user, $selectedOutletId)) {
            $selectedOutletId = OutletService::getActiveOutletId($user);
        }

        $query = Sale::with(['customer', 'user', 'items.medicine', 'outlet'])
            ->orderBy('id', 'desc');

        // Self-heal: sync any existing sales where outlet_id is null to the cashier's assigned outlet
        try {
            DB::table('sales')
                ->join('users', 'sales.cashier_id', '=', 'users.id')
                ->whereNull('sales.outlet_id')
                ->whereNotNull('users.outlet_id')
                ->update(['sales.outlet_id' => DB::raw('users.outlet_id')]);
        } catch (\Throwable $e) {
            // Ignore if in read-only mode
        }

        // Cashier role restriction flag
        $isCashier = $user->role === 'kasir' && !$user->can('sales.view_all_cashier');

        // Scoping by active/selected outlet
        if ($selectedOutletId) {
            $mainOutlet = Outlet::where('is_main', true)->first();
            $isMainOutlet = $mainOutlet && $mainOutlet->id == $selectedOutletId;

            $query->where(function ($q) use ($selectedOutletId, $isMainOutlet, $isCashier, $user) {
                $q->where('sales.outlet_id', $selectedOutletId)
                  ->orWhere(function ($sub) use ($selectedOutletId, $isMainOutlet, $isCashier, $user) {
                      $sub->whereNull('sales.outlet_id');
                      if ($isCashier && $user->outlet_id == $selectedOutletId) {
                          $sub->where('sales.cashier_id', $user->id);
                      } else {
                          $sub->whereExists(function ($uq) use ($selectedOutletId) {
                              $uq->select(DB::raw(1))
                                 ->from('users')
                                 ->whereColumn('users.id', 'sales.cashier_id')
                                 ->where('users.outlet_id', $selectedOutletId);
                          });
                          if ($isMainOutlet) {
                              $sub->orWhereNotExists(function ($uq) {
                                  $uq->select(DB::raw(1))
                                     ->from('users')
                                     ->whereColumn('users.id', 'sales.cashier_id')
                                     ->whereNotNull('users.outlet_id');
                              });
                          }
                      }
                  });
            });
        }
        if ($isCashier) {
            $query->where('cashier_id', $user->id);
        } elseif ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->cashier_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sale_date', [$request->start_date, $request->end_date]);
        }

        $sales = $query->paginate(15)->withQueryString();

        // For non-cashier users (e.g. Admin, Owner), provide the list of cashiers for filtering
        $cashiers = [];
        if (!$isCashier) {
            $cashiers = User::whereIn('id', function($sub) {
                    $sub->select('cashier_id')->from('sales')->whereNotNull('cashier_id');
                })
                ->orWhere('role', 'kasir')
                ->select('id', 'name', 'username')
                ->orderBy('name')
                ->get();
        }

        return Inertia::render('Sales/History/Index', [
            'sales' => $sales,
            'outlets' => $accessibleOutlets,
            'selectedOutletId' => (int)$selectedOutletId,
            'isCashier' => $isCashier,
            'cashiers' => $cashiers,
            'currentCashier' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
            'filters' => [
                'search' => $request->search ?? '',
                'payment_method' => $request->payment_method ?? '',
                'start_date' => $request->start_date ?? '',
                'end_date' => $request->end_date ?? '',
                'outlet_id' => (int)$selectedOutletId,
                'cashier_id' => $isCashier ? (string)$user->id : ($request->cashier_id ?? ''),
            ]
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = auth()->user();
        $sale = Sale::with(['customer', 'user', 'items.medicine', 'productReturns', 'outlet', 'shift'])->findOrFail($id);

        if ($sale->outlet_id && !OutletService::userCanAccessOutlet($user, $sale->outlet_id)) {
            if ($request->wantsJson() || $request->ajax() || $request->has('json')) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses ke transaksi outlet ini.'], 403);
            }
            abort(403, 'Anda tidak memiliki akses ke transaksi outlet ini.');
        }

        // Cashier role restriction: cashier can only view their own transactions
        $isCashier = $user->role === 'kasir' && !$user->can('sales.view_all_cashier');
        if ($isCashier && $sale->cashier_id != $user->id) {
            if ($request->wantsJson() || $request->ajax() || $request->has('json')) {
                return response()->json(['success' => false, 'message' => 'Anda hanya berhak melihat riwayat transaksi yang Anda layani sendiri.'], 403);
            }
            abort(403, 'Anda hanya berhak melihat riwayat transaksi yang Anda layani sendiri.');
        }

        if ($request->wantsJson() || $request->ajax() || $request->has('json')) {
            return response()->json([
                'success' => true,
                'sale' => $sale
            ]);
        }

        return Inertia::render('Sales/History/Show', [
            'sale' => $sale
        ]);
    }
}
