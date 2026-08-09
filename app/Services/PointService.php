<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PointTransaction;
use App\Models\PointLot;
use Illuminate\Support\Facades\DB;
use Exception;

class PointService
{
    /**
     * Calculate potential points earned from a purchase amount
     */
    public function calculatePotentialPoints(Customer $customer, float $grandTotal): int
    {
        $pointRate = (float) (DB::table('membership_rules')->where('key_name', 'point_rate')->value('value') ?? 10000);
        if ($pointRate <= 0) $pointRate = 10000;

        $basePoints = floor($grandTotal / $pointRate);

        // Get tier multiplier
        $multiplier = 1.0;
        if ($customer->tier_id) {
            $tier = DB::table('membership_tiers')->where('id', $customer->tier_id)->first();
            if ($tier) {
                $multiplier = (float) $tier->point_multiplier;
            }
        }

        // Get campaign multiplier if active
        $today = now()->toDateString();
        $campaign = DB::table('membership_campaigns')
            ->where('is_active', true)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->orderBy('multiplier', 'desc')
            ->first();

        if ($campaign) {
            $multiplier *= (float) $campaign->multiplier;
        }

        return (int) floor($basePoints * $multiplier);
    }

    /**
     * Earn points for a customer
     */
    public function earnPoints(Customer $customer, float $grandTotal, string $referenceType = 'Sale', string $referenceId = null, string $description = 'Poin dari transaksi POS', int $createdById = null): PointTransaction
    {
        return DB::transaction(function () use ($customer, $grandTotal, $referenceType, $referenceId, $description, $createdById) {
            // Idempotency check: don't earn twice for same reference
            if ($referenceId) {
                $existing = PointTransaction::where('customer_id', $customer->id)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('transaction_type', 'PURCHASE')
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $pointsEarned = $this->calculatePotentialPoints($customer, $grandTotal);
            if ($pointsEarned <= 0) {
                $pointsEarned = 0;
            }

            $balanceBefore = $customer->points;
            $balanceAfter = $balanceBefore + $pointsEarned;

            $expiryMonths = (int) (DB::table('membership_rules')->where('key_name', 'point_expiry_months')->value('value') ?? 12);
            $expiredAt = now()->addMonths($expiryMonths)->toDateString();

            // 1. Create Point Transaction
            $transaction = PointTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => 'PURCHASE',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'points_in' => $pointsEarned,
                'points_out' => 0,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'expired_at' => $expiredAt,
                'description' => $description,
                'created_by' => $createdById,
            ]);

            // 2. Create Point Lot for FIFO tracking
            if ($pointsEarned > 0) {
                PointLot::create([
                    'customer_id' => $customer->id,
                    'point_transaction_id' => $transaction->id,
                    'original_points' => $pointsEarned,
                    'remaining_points' => $pointsEarned,
                    'earned_at' => now()->toDateString(),
                    'expired_at' => $expiredAt,
                    'status' => 'ACTIVE',
                ]);
            }

            // 3. Update Customer Points
            $customer->increment('points', $pointsEarned);

            return $transaction;
        });
    }

    /**
     * Redeem points for a customer using FIFO (First-In First-Out)
     */
    public function redeemPoints(Customer $customer, int $pointsToRedeem, string $referenceType = 'POS_Discount', string $referenceId = null, string $description = 'Penukaran poin untuk diskon', int $createdById = null): PointTransaction
    {
        if ($pointsToRedeem <= 0) {
            throw new Exception("Jumlah poin yang di-redeem harus lebih besar dari 0.");
        }

        if ($customer->points < $pointsToRedeem) {
            throw new Exception("Poin tidak mencukupi. Saldo tersedia: {$customer->points} Pts, dibutuhkan: {$pointsToRedeem} Pts.");
        }

        return DB::transaction(function () use ($customer, $pointsToRedeem, $referenceType, $referenceId, $description, $createdById) {
            $balanceBefore = $customer->points;
            $balanceAfter = $balanceBefore - $pointsToRedeem;

            // 1. Deduct from FIFO Point Lots
            $needed = $pointsToRedeem;
            $lots = PointLot::where('customer_id', $customer->id)
                ->where('status', 'ACTIVE')
                ->where('remaining_points', '>', 0)
                ->orderBy('expired_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            foreach ($lots as $lot) {
                if ($needed <= 0) break;

                if ($lot->remaining_points <= $needed) {
                    $needed -= $lot->remaining_points;
                    $lot->remaining_points = 0;
                    $lot->status = 'FULLY_REDEEMED';
                    $lot->save();
                } else {
                    $lot->remaining_points -= $needed;
                    $needed = 0;
                    $lot->save();
                }
            }

            // 2. Create Point Transaction Log
            $transaction = PointTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => 'REDEEM',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'points_in' => 0,
                'points_out' => $pointsToRedeem,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'created_by' => $createdById,
            ]);

            // 3. Update Customer Points
            $customer->decrement('points', $pointsToRedeem);

            return $transaction;
        });
    }

    /**
     * Reverse points due to Refund / Return
     */
    public function reversePoints(Customer $customer, int $pointsToReverse, string $reason = 'Pembatalan/Retur Transaksi', string $referenceId = null): PointTransaction
    {
        return DB::transaction(function () use ($customer, $pointsToReverse, $reason, $referenceId) {
            $balanceBefore = $customer->points;
            $actualDeduction = min($balanceBefore, $pointsToReverse);
            $balanceAfter = $balanceBefore - $actualDeduction;

            $transaction = PointTransaction::create([
                'customer_id' => $customer->id,
                'transaction_type' => 'REFUND_REVERSAL',
                'reference_type' => 'Sale',
                'reference_id' => $referenceId,
                'points_in' => 0,
                'points_out' => $actualDeduction,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $reason,
            ]);

            if ($actualDeduction > 0) {
                $customer->decrement('points', $actualDeduction);
            }

            return $transaction;
        });
    }
}
