<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\ShiftCashMovement;
use App\Models\Sale;
use App\Models\ProductReturn;
use Illuminate\Support\Facades\DB;
use Exception;

class ShiftService
{
    /**
     * Get active shift for a given cashier
     */
    public function getActiveShift(int $userId): ?CashierShift
    {
        return CashierShift::with(['outlet', 'user', 'masterShift'])
            ->where('cashier_id', $userId)
            ->where('status', 'OPEN')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Open a new shift for a cashier
     */
    public function openShift(int $userId, ?int $outletId, string $shiftName, float $initialCash, ?int $masterShiftId = null): CashierShift
    {
        return DB::transaction(function () use ($userId, $outletId, $shiftName, $initialCash, $masterShiftId) {
            // Check if cashier already has an active OPEN shift
            $activeShift = $this->getActiveShift($userId);
            if ($activeShift) {
                throw new Exception("Kasir masih memiliki Shift Aktif ({$activeShift->shift_name} - dibuka pada {$activeShift->opened_at->format('d/m/Y H:i')}). Silakan tutup shift terlebih dahulu.");
            }

            $isOutOfSchedule = false;
            if ($masterShiftId) {
                $masterShift = \App\Models\MasterShift::find($masterShiftId);
                if ($masterShift) {
                    $isOutOfSchedule = !$masterShift->isWithinSchedule(now());
                }
            }

            $shift = CashierShift::create([
                'cashier_id' => $userId,
                'outlet_id' => $outletId,
                'master_shift_id' => $masterShiftId,
                'shift_name' => $shiftName,
                'opening_cash' => $initialCash,
                'expected_cash' => $initialCash,
                'actual_cash' => 0,
                'difference' => 0,
                'status' => 'OPEN',
                'is_out_of_schedule' => $isOutOfSchedule,
                'opened_at' => now(),
            ]);

            app(AuditLogService::class)->log(
                'OPEN_SHIFT',
                'SHIFT',
                null,
                [
                    'shift_id' => $shift->id,
                    'cashier_id' => $userId,
                    'shift_name' => $shiftName,
                    'opening_cash' => $initialCash,
                    'is_out_of_schedule' => $isOutOfSchedule,
                ]
            );

            return $shift;
        });
    }

    /**
     * Recalculate sales, refunds, expected cash, and differences for a shift
     */
    public function recalculateShiftMetrics(int $shiftId): CashierShift
    {
        $shift = CashierShift::findOrFail($shiftId);

        // 1. Calculate Sales per payment type
        $cashSales = Sale::where('shift_id', $shiftId)
            ->where('status', 'completed')
            ->where('payment_method', 'cash')
            ->sum('grand_total');

        $nonCashSales = Sale::where('shift_id', $shiftId)
            ->where('status', 'completed')
            ->where('payment_method', '!=', 'cash')
            ->sum('grand_total');

        // 2. Calculate Cash Refunds from returns linked to sales of this shift or cashier
        $cashRefunds = DB::table('product_returns')
            ->join('sales', 'product_returns.reference_id', '=', 'sales.id')
            ->where('sales.shift_id', $shiftId)
            ->where('product_returns.type', 'sale')
            ->where('product_returns.status', 'APPROVED')
            ->where('product_returns.refund_method', 'Cash')
            ->sum('product_returns.refund_amount');

        // 3. Calculate Cash Movements (Penyesuaian Kas Masuk / Keluar)
        $cashIn = ShiftCashMovement::where('shift_id', $shiftId)->where('type', 'in')->sum('amount');
        $cashOut = ShiftCashMovement::where('shift_id', $shiftId)->where('type', 'out')->sum('amount');
        $cashAdjustments = $cashIn - $cashOut;

        // 4. Calculate Expected Cash
        // Formula: Expected Cash = Opening Cash + Cash Sales - Cash Refunds + Cash Adjustments
        $expectedCash = $shift->opening_cash + $cashSales - $cashRefunds + $cashAdjustments;

        $shift->update([
            'cash_sales' => $cashSales,
            'non_cash_sales' => $nonCashSales,
            'cash_refunds' => $cashRefunds,
            'cash_adjustments' => $cashAdjustments,
            'expected_cash' => $expectedCash,
        ]);

        return $shift->fresh();
    }

    /**
     * Close an active shift
     */
    public function closeShift(int $shiftId, float $actualCash, ?string $notes, int $closedByUserId): CashierShift
    {
        return DB::transaction(function () use ($shiftId, $actualCash, $notes, $closedByUserId) {
            $shift = $this->recalculateShiftMetrics($shiftId);

            if ($shift->status !== 'OPEN') {
                throw new Exception("Shift ini sudah ditutup atau force closed.");
            }

            $difference = $actualCash - $shift->expected_cash;

            $shift->update([
                'actual_cash' => $actualCash,
                'difference' => $difference,
                'difference_reason' => $notes,
                'status' => 'CLOSED',
                'closed_at' => now(),
                'closed_by' => $closedByUserId,
            ]);

            app(AuditLogService::class)->log(
                'CLOSE_SHIFT',
                'SHIFT',
                ['expected_cash' => $shift->expected_cash],
                [
                    'shift_id' => $shift->id,
                    'actual_cash' => $actualCash,
                    'difference' => $difference,
                    'status' => 'CLOSED',
                    'notes' => $notes,
                ]
            );

            return $shift;
        });
    }

    /**
     * Force close a shift (Admin / Manager)
     */
    public function forceCloseShift(int $shiftId, string $reason, int $closedByUserId): CashierShift
    {
        return DB::transaction(function () use ($shiftId, $reason, $closedByUserId) {
            $shift = $this->recalculateShiftMetrics($shiftId);

            if ($shift->status !== 'OPEN') {
                throw new Exception("Shift ini sudah tidak aktif.");
            }

            $shift->update([
                'actual_cash' => $shift->expected_cash, // Fallback to expected if force closed
                'difference' => 0,
                'force_close_reason' => $reason,
                'status' => 'FORCE CLOSED',
                'closed_at' => now(),
                'closed_by' => $closedByUserId,
            ]);

            app(AuditLogService::class)->log(
                'FORCE_CLOSE_SHIFT',
                'SHIFT',
                ['status' => 'OPEN'],
                [
                    'shift_id' => $shift->id,
                    'force_close_reason' => $reason,
                    'status' => 'FORCE CLOSED',
                    'closed_by' => $closedByUserId,
                ]
            );

            return $shift;
        });
    }

    /**
     * Record a cash movement (Penyesuaian Kas) during shift
     */
    public function addCashMovement(int $shiftId, string $type, float $amount, string $reason, int $userId): ShiftCashMovement
    {
        return DB::transaction(function () use ($shiftId, $type, $amount, $reason, $userId) {
            $shift = CashierShift::findOrFail($shiftId);
            if ($shift->status !== 'OPEN') {
                throw new Exception("Penyesuaian kas hanya bisa dilakukan pada shift yang aktif (OPEN).");
            }

            $movement = ShiftCashMovement::create([
                'shift_id' => $shiftId,
                'type' => $type,
                'amount' => $amount,
                'reason' => $reason,
                'user_id' => $userId,
            ]);

            $this->recalculateShiftMetrics($shiftId);

            app(AuditLogService::class)->log(
                'CASH_ADJUSTMENT',
                'SHIFT',
                null,
                [
                    'shift_id' => $shiftId,
                    'type' => $type,
                    'amount' => $amount,
                    'reason' => $reason,
                ]
            );

            return $movement;
        });
    }
}
