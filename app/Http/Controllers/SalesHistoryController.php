<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Outlet;
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

        // Scoping by active/selected outlet
        if ($selectedOutletId) {
            $mainOutlet = Outlet::where('is_main', true)->first();
            $isMainOutlet = $mainOutlet && $mainOutlet->id == $selectedOutletId;

            $query->where(function ($q) use ($selectedOutletId, $isMainOutlet) {
                $q->where('outlet_id', $selectedOutletId);
                if ($isMainOutlet) {
                    $q->orWhereNull('outlet_id');
                }
            });
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

        return Inertia::render('Sales/History/Index', [
            'sales' => $sales,
            'outlets' => $accessibleOutlets,
            'selectedOutletId' => (int)$selectedOutletId,
            'filters' => [
                'search' => $request->search ?? '',
                'payment_method' => $request->payment_method ?? '',
                'start_date' => $request->start_date ?? '',
                'end_date' => $request->end_date ?? '',
                'outlet_id' => (int)$selectedOutletId,
            ]
        ]);
    }

    public function show($id)
    {
        $user = auth()->user();
        $sale = Sale::with(['customer', 'user', 'items.medicine', 'productReturns', 'outlet'])->findOrFail($id);

        if ($sale->outlet_id && !OutletService::userCanAccessOutlet($user, $sale->outlet_id)) {
            abort(403, 'Anda tidak memiliki akses ke transaksi outlet ini.');
        }

        return Inertia::render('Sales/History/Show', [
            'sale' => $sale
        ]);
    }
}
