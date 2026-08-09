<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\MembershipTier;
use App\Models\MembershipTierHistory;
use Illuminate\Support\Facades\DB;

class MembershipService
{
    /**
     * Evaluate & auto-upgrade/downgrade customer membership tier based on total spending
     */
    public function evaluateCustomerTier(Customer $customer, string $reason = 'Total spending mencapai threshold baru'): ?MembershipTierHistory
    {
        $totalSpending = (float) $customer->total_spending;

        // Find tier matching spending
        $matchedTier = MembershipTier::where('is_active', true)
            ->where('min_spending', '<=', $totalSpending)
            ->orderBy('min_spending', 'desc')
            ->first();

        if (!$matchedTier) {
            return null;
        }

        // If tier changed, update and log history
        if ($customer->tier_id != $matchedTier->id) {
            $previousTierId = $customer->tier_id;
            
            $customer->tier_id = $matchedTier->id;
            $customer->membership_level = strtolower($matchedTier->name);
            $customer->save();

            $history = MembershipTierHistory::create([
                'customer_id' => $customer->id,
                'previous_tier_id' => $previousTierId,
                'new_tier_id' => $matchedTier->id,
                'reason' => $reason,
                'created_by' => auth()->id(),
            ]);

            // Create notification
            DB::table('member_notifications')->insert([
                'customer_id' => $customer->id,
                'title' => "🎉 Selamat! Anda naik ke Tier {$matchedTier->name}",
                'message' => "Nikmati benefit poin {$matchedTier->point_multiplier}x dan diskon member {$matchedTier->discount_percentage}%.",
                'channel' => 'IN_APP',
                'status' => 'SENT',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $history;
        }

        return null;
    }

    /**
     * Get Membership Dashboard Statistics
     */
    public function getDashboardStats(): array
    {
        $totalMembers = Customer::count();
        $activeMembers = Customer::where('is_active', true)->count();
        $newMembersThisMonth = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $inactiveMembers = Customer::where('is_active', false)->count();

        $totalPointsCirculating = (int) Customer::sum('points');
        $totalPointsEarned = (int) DB::table('point_transactions')->sum('points_in');
        $totalPointsRedeemed = (int) DB::table('point_transactions')->sum('points_out');

        $totalRewardsClaimed = DB::table('reward_redemptions')->count();
        $totalVouchersUsed = DB::table('voucher_usages')->count();

        $memberSalesQuery = DB::table('sales')->whereNotNull('customer_id')->where('status', 'completed');
        $totalMemberTransactions = $memberSalesQuery->count();
        $totalMemberSales = (float) $memberSalesQuery->sum('grand_total');

        $allSalesTotal = (float) DB::table('sales')->where('status', 'completed')->sum('grand_total');
        $memberContributionPercent = $allSalesTotal > 0 ? round(($totalMemberSales / $allSalesTotal) * 100, 1) : 0;

        return [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'new_members_this_month' => $newMembersThisMonth,
            'inactive_members' => $inactiveMembers,
            'total_points_circulating' => $totalPointsCirculating,
            'total_points_earned' => $totalPointsEarned,
            'total_points_redeemed' => $totalPointsRedeemed,
            'total_rewards_claimed' => $totalRewardsClaimed,
            'total_vouchers_used' => $totalVouchersUsed,
            'total_member_transactions' => $totalMemberTransactions,
            'total_member_sales' => $totalMemberSales,
            'member_contribution_percent' => $memberContributionPercent,
        ];
    }
}
