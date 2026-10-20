<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductReturn;
use App\Models\Sale;
use App\Models\Purchase;
use App\Services\ReturnService;
use App\Services\AuditLogService;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Exception;

class ReturnController extends Controller
{
    protected $returnService;
    protected $auditLogService;

    public function __construct(ReturnService $returnService, AuditLogService $auditLogService)
    {
        $this->returnService = $returnService;
        $this->auditLogService = $auditLogService;
    }

    public function index(Request $request)
    {
        // $this->authorize('view', ProductReturn::class);
        if (!auth()->user()->can('retur.view')) {
            abort(403, 'Unauthorized access.');
        }

        $query = ProductReturn::with(['user', 'customer', 'supplier', 'approver'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('return_number')) {
            $query->where('return_number', 'like', '%' . $request->return_number . '%');
        }

        $returns = $query->paginate(15)->withQueryString();

        return Inertia::render('Return/Index', [
            'returns' => $returns,
            'filters' => $request->only(['type', 'status', 'return_number'])
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        if (!$user->can('retur.create')) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin membuat retur.');
        }

        $type = $request->get('type', 'sale');
        $referenceId = $request->get('reference_id');

        $transaction = null;

        if ($referenceId) {
            if ($type === 'sale') {
                $transaction = Sale::with(['items.medicine', 'customer', 'user'])->findOrFail($referenceId);
                // IDOR check: Cashier can only return their own sales
                if ($user->role === 'kasir' && !$user->can('retur.view_all_cashier') && $transaction->cashier_id !== $user->id) {
                    abort(403, 'Akses ditolak. Anda hanya berhak memproses retur untuk transaksi yang Anda layani sendiri.');
                }
            } else {
                // Purchase return is strictly for admin/owner
                if ($user->role === 'kasir') {
                    abort(403, 'Akses ditolak. Kasir tidak memiliki akses retur pembelian.');
                }
                $transaction = Purchase::with(['items.medicine', 'supplier'])->findOrFail($referenceId);
            }
        }

        return Inertia::render('Return/Create', [
            'type' => $type,
            'transaction' => $transaction,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user->can('retur.create')) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin membuat retur.');
        }

        $request->validate([
            'type' => 'required|in:sale,purchase',
            'reference_id' => 'required|integer',
            'total_amount' => 'required|numeric',
            'refund_method' => 'nullable|string',
            'refund_amount' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric',
            'items.*.reason' => 'required|string',
            'items.*.condition' => 'nullable|string',
        ]);

        if ($request->type === 'sale') {
            $sale = Sale::findOrFail($request->reference_id);
            // IDOR Protection on store
            if ($user->role === 'kasir' && !$user->can('retur.view_all_cashier') && $sale->cashier_id !== $user->id) {
                abort(403, 'Akses ditolak. Anda hanya berhak memproses retur untuk transaksi yang Anda layani sendiri.');
            }
        } elseif ($request->type === 'purchase' && $user->role === 'kasir') {
            abort(403, 'Akses ditolak. Kasir tidak memiliki akses retur pembelian.');
        }

        try {
            $productReturn = $this->returnService->createReturn(
                $request->type,
                $request->reference_id,
                $request->all(),
                $user->id
            );

            return redirect()->route('returns.show', $productReturn->id)->with('success', 'Retur berhasil dibuat (Status: PENDING).');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show($id)
    {
        $user = auth()->user();
        if (!$user->can('retur.view')) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin melihat retur.');
        }

        $productReturn = ProductReturn::with([
            'items.medicine', 'items.batch', 'user', 'approver', 'customer', 'supplier',
            'sale', 'purchase'
        ])->findOrFail($id);

        // IDOR Protection: Cashier can only view their own return records
        if ($user->role === 'kasir' && !$user->can('retur.view_all_cashier') && $productReturn->user_id !== $user->id) {
            abort(403, 'Akses ditolak. Anda tidak berhak melihat detail retur pengguna lain.');
        }

        return Inertia::render('Return/Show', [
            'productReturn' => $productReturn,
            'canApprove' => $user->can('retur.approve')
        ]);
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (!$user->can('retur.approve') || $user->role === 'kasir') {
            abort(403, 'Akses ditolak. Hanya Administrator atau Owner yang dapat menyetujui retur.');
        }

        try {
            $this->returnService->approveReturn($id, $user->id);
            return back()->with('success', 'Retur berhasil disetujui.');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel($id)
    {
        $user = auth()->user();
        if (!$user->can('retur.cancel')) {
            abort(403, 'Akses ditolak. Anda tidak memiliki izin membatalkan retur.');
        }

        $productReturn = ProductReturn::findOrFail($id);

        // IDOR Protection: Cashier can only cancel their own pending return
        if ($user->role === 'kasir' && !$user->can('retur.view_all_cashier') && $productReturn->user_id !== $user->id) {
            abort(403, 'Akses ditolak. Anda tidak berhak membatalkan retur pengguna lain.');
        }
        
        if ($productReturn->status !== 'PENDING') {
            return back()->with('error', 'Hanya retur berstatus PENDING yang bisa dibatalkan.');
        }

        $productReturn->update(['status' => 'CANCELLED']);

        return back()->with('success', 'Retur dibatalkan.');
    }

    public function logPrint(Request $request, $id)
    {
        $productReturn = ProductReturn::findOrFail($id);

        $this->auditLogService->log(
            'CETAK_BUKTI_RETUR',
            'RETUR',
            null,
            [
                'return_number' => $productReturn->return_number,
                'type' => $productReturn->type,
                'format' => $request->input('format', 'A4'),
                'printed_at' => now()->toDateTimeString(),
            ]
        );

        return response()->json(['success' => true]);
    }
}
