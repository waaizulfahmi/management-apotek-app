<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use Inertia\Inertia;

class SalesHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'user', 'items.medicine'])
            ->orderBy('id', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
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
            'filters' => $request->only(['search', 'payment_method', 'start_date', 'end_date'])
        ]);
    }

    public function show($id)
    {
        $sale = Sale::with(['customer', 'user', 'items.medicine', 'productReturns'])->findOrFail($id);

        return Inertia::render('Sales/History/Show', [
            'sale' => $sale
        ]);
    }
}
