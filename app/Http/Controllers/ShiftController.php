<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CashierShift;
use App\Models\Outlet;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class ShiftController extends Controller
{
    protected $shiftService;

    public function __construct(ShiftService $shiftService)
    {
        $this->shiftService = $shiftService;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = CashierShift::with(['user', 'outlet', 'closedBy'])
            ->orderBy('id', 'desc');

        // Cashiers can only view their own shifts unless they have view_all permission
        if (!$user->can('shift.view_all_cashier') && $user->role === 'kasir') {
            $query->where('cashier_id', $user->id);
        }

        if ($request->filled('cashier_id')) {
            $query->where('cashier_id', $request->cashier_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween(DB::raw('DATE(opened_at)'), [$request->start_date, $request->end_date]);
        }

        $shifts = $query->paginate(15)->withQueryString();
        $outlets = Outlet::where('is_active', true)->get();
        $cashiers = User::all();
        $activeShift = $this->shiftService->getActiveShift($user->id);
        $masterShifts = \App\Models\MasterShift::orderBy('start_time', 'asc')->get();

        return Inertia::render('Shifts/Index', [
            'shifts' => $shifts,
            'outlets' => $outlets,
            'cashiers' => $cashiers,
            'activeShift' => $activeShift,
            'masterShifts' => $masterShifts,
            'filters' => $request->only(['cashier_id', 'status', 'start_date', 'end_date'])
        ]);
    }

    public function show($id)
    {
        $user = auth()->user();
        $shift = CashierShift::with(['user', 'outlet', 'closedBy', 'cashMovements.user', 'sales.customer', 'masterShift'])
            ->findOrFail($id);

        if (!$user->can('shift.view_all_cashier') && $user->role === 'kasir' && $shift->cashier_id !== $user->id) {
            abort(403, 'Unauthorized access.');
        }

        // Recalculate metrics for up-to-date figures
        if ($shift->status === 'OPEN') {
            $shift = $this->shiftService->recalculateShiftMetrics($id);
            $shift->load(['user', 'outlet', 'closedBy', 'cashMovements.user', 'sales.customer', 'masterShift']);
        }

        return Inertia::render('Shifts/Show', [
            'shift' => $shift,
            'canForceClose' => $user->can('shift.force_close') || in_array($user->role, ['admin', 'owner'])
        ]);
    }

    public function open(Request $request)
    {
        $request->validate([
            'shift_name' => 'required|string|max:50',
            'opening_cash' => 'required|numeric|min:0',
            'outlet_id' => 'nullable|exists:outlets,id',
            'master_shift_id' => 'nullable|exists:master_shifts,id',
        ]);

        try {
            $shift = $this->shiftService->openShift(
                auth()->id(),
                $request->outlet_id,
                $request->shift_name,
                $request->opening_cash,
                $request->master_shift_id
            );

            return back()->with('success', "Shift {$shift->shift_name} berhasil dibuka!");
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function close(Request $request, $id)
    {
        $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        try {
            $this->shiftService->closeShift(
                $id,
                $request->actual_cash,
                $request->notes,
                auth()->id()
            );

            return redirect()->route('shifts.show', $id)->with('success', 'Shift berhasil ditutup!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function forceClose(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5',
        ]);

        try {
            $this->shiftService->forceCloseShift(
                $id,
                $request->reason,
                auth()->id()
            );

            return back()->with('success', 'Shift berhasil di-Force Close!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function adjustCash(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|min:3',
        ]);

        try {
            $this->shiftService->addCashMovement(
                $id,
                $request->type,
                $request->amount,
                $request->reason,
                auth()->id()
            );

            return back()->with('success', 'Penyesuaian kas berhasil dicatat!');
        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function activeShift()
    {
        $activeShift = $this->shiftService->getActiveShift(auth()->id());
        if ($activeShift) {
            $this->shiftService->recalculateShiftMetrics($activeShift->id);
            $activeShift = $activeShift->fresh(['outlet']);
        }

        return response()->json([
            'active_shift' => $activeShift
        ]);
    }
}
