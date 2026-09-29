<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MembershipTier;
use App\Models\PointTransaction;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Voucher;
use App\Services\MembershipService;
use App\Services\PointService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Exception;

class MembershipController extends Controller
{
    protected $membershipService;
    protected $pointService;

    public function __construct(MembershipService $membershipService, PointService $pointService)
    {
        $this->membershipService = $membershipService;
        $this->pointService = $pointService;
    }

    /**
     * Dashboard Membership Page
     */
    public function dashboard()
    {
        $stats = $this->membershipService->getDashboardStats();

        // Top Member Spenders (Nominal Belanja Terbesar)
        $topMembers = Customer::with('tier')
            ->orderBy('total_spending', 'desc')
            ->limit(5)
            ->get();

        // Member Paling Aktif (Frekuensi Belanja / Transaksi Terbanyak)
        $activeMembers = Customer::with('tier')
            ->withCount('sales')
            ->orderBy('sales_count', 'desc')
            ->orderBy('total_spending', 'desc')
            ->limit(5)
            ->get();

        // Recent Point Transactions
        $recentTransactions = PointTransaction::with('customer')
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get();

        return Inertia::render('Membership/Dashboard', [
            'stats' => $stats,
            'topMembers' => $topMembers,
            'activeMembers' => $activeMembers,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    /**
     * Data Member List Page
     */
    public function members(Request $request)
    {
        $query = Customer::with('tier');

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->tier_id) {
            $query->where('tier_id', $request->tier_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();
        $tiers = MembershipTier::where('is_active', true)->get();

        return Inertia::render('Membership/Members/Index', [
            'customers' => $customers,
            'tiers' => $tiers,
            'filters' => $request->only(['search', 'tier_id', 'status']),
        ]);
    }

    /**
     * Store New Member
     */
    public function storeMember(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30|unique:customers,phone',
            'email' => 'nullable|email|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:L,P',
            'address' => 'nullable|string',
            'allergies' => 'nullable|string',
        ]);

        // Auto Generate Unique Member ID: MEM-2026-XXXXX
        $latestId = Customer::max('id') + 1;
        $memberCode = 'MEM-' . date('Y') . '-' . str_pad($latestId, 5, '0', STR_PAD_LEFT);

        $bronzeTier = MembershipTier::where('slug', 'bronze')->first();

        $customer = Customer::create([
            'code' => $memberCode,
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'address' => $request->address,
            'allergies' => $request->allergies,
            'tier_id' => $bronzeTier ? $bronzeTier->id : null,
            'membership_level' => 'regular',
            'points' => 0,
            'total_spending' => 0,
            'status' => 'ACTIVE',
            'referral_code' => strtoupper(substr(md5(uniqid()), 0, 6)),
        ]);

        // Grant Welcome Bonus if configured
        $welcomeBonus = (int) (DB::table('membership_rules')->where('key_name', 'welcome_bonus')->value('value') ?? 100);
        if ($welcomeBonus > 0) {
            $this->pointService->earnPoints($customer, 0, 'WelcomeBonus', null, 'Bonus Poin Pendaftaran Member Baru');
        }

        return redirect()->back()->with('success', "Member {$customer->name} ({$customer->code}) berhasil terdaftar!");
    }

    /**
     * Update Existing Member & Drug Allergy Info
     */
    public function updateMember(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30|unique:customers,phone,' . $customer->id,
            'email' => 'nullable|email|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:L,P',
            'address' => 'nullable|string',
            'allergies' => 'nullable|string',
            'status' => 'nullable|in:ACTIVE,INACTIVE',
        ]);

        $customer->update([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'address' => $request->address,
            'allergies' => $request->allergies,
            'status' => $request->status ?: $customer->status,
        ]);

        return redirect()->back()->with('success', "Data Member & Riwayat Alergi {$customer->name} berhasil diperbarui!");
    }

    /**
     * Delete Member
     */
    public function destroyMember($id)
    {
        $customer = Customer::findOrFail($id);
        $name = $customer->name;
        $customer->delete();

        return redirect()->back()->with('success', "Member {$name} berhasil dihapus.");
    }

    /**
     * Member Detail Profile Page
     */
    public function memberShow($id)
    {
        $customer = Customer::with(['tier', 'pointTransactions', 'rewardRedemptions.reward', 'voucherUsages.voucher', 'sales'])->findOrFail($id);

        $nextTier = MembershipTier::where('min_spending', '>', $customer->total_spending)
            ->orderBy('min_spending', 'asc')
            ->first();

        return Inertia::render('Membership/Members/Show', [
            'customer' => $customer,
            'nextTier' => $nextTier,
        ]);
    }

    /**
     * Riwayat Point Page
     */
    public function points(Request $request)
    {
        $query = PointTransaction::with(['customer.tier', 'creator']);

        if ($request->search) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->type) {
            $query->where('transaction_type', $request->type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->paginate(12)->withQueryString();

        return Inertia::render('Membership/Points/History', [
            'transactions' => $transactions,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    /**
     * Point & Reward Page
     */
    public function rewards()
    {
        $rewards = Reward::orderBy('required_points', 'asc')->get();
        $redemptions = RewardRedemption::with(['customer', 'reward'])->orderBy('created_at', 'desc')->limit(20)->get();

        return Inertia::render('Membership/Rewards/Index', [
            'rewards' => $rewards,
            'redemptions' => $redemptions,
        ]);
    }

    /**
     * Redeem Reward Action
     */
    public function redeemReward(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'reward_id' => 'required|exists:rewards,id',
        ]);

        $customer = Customer::findOrFail($request->customer_id);
        $reward = Reward::findOrFail($request->reward_id);

        try {
            $redemptionCode = 'RW-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 5));

            // Deduct Points
            $this->pointService->redeemPoints($customer, $reward->required_points, 'RewardClaim', $redemptionCode, "Tukar Reward: {$reward->name}");

            // Create Redemption Record
            RewardRedemption::create([
                'customer_id' => $customer->id,
                'reward_id' => $reward->id,
                'redemption_code' => $redemptionCode,
                'points_used' => $reward->required_points,
                'status' => 'AVAILABLE',
                'redeemed_at' => now(),
                'expired_at' => now()->addDays(30),
            ]);

            return redirect()->back()->with('success', "Berhasil menukarkan reward {$reward->name}! Kode Klaim: {$redemptionCode}");
        } catch (Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Vouchers Page
     */
    public function vouchers()
    {
        $vouchers = Voucher::orderBy('created_at', 'desc')->get();
        return Inertia::render('Membership/Vouchers/Index', [
            'vouchers' => $vouchers,
        ]);
    }

    /**
     * Promos & Campaigns Page
     */
    public function promos()
    {
        $campaigns = DB::table('membership_campaigns')->orderBy('created_at', 'desc')->get();
        return Inertia::render('Membership/Promos/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Tiers Page
     */
    public function tiers()
    {
        $tiers = MembershipTier::withCount('customers')->orderBy('min_spending', 'asc')->get();
        return Inertia::render('Membership/Tiers/Index', [
            'tiers' => $tiers,
        ]);
    }

    /**
     * Purchase History Page
     */
    public function purchases(Request $request)
    {
        $query = DB::table('sales')
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->select('sales.*', 'customers.name as customer_name', 'customers.code as customer_code', 'customers.phone as customer_phone')
            ->whereNotNull('sales.customer_id');

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('sales.invoice_number', 'like', "%{$search}%")
                  ->orWhere('customers.name', 'like', "%{$search}%")
                  ->orWhere('customers.phone', 'like', "%{$search}%");
            });
        }

        $purchases = $query->orderBy('sales.created_at', 'desc')->paginate(12);

        return Inertia::render('Membership/Purchases/History', [
            'purchases' => $purchases,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Settings Page
     */
    public function settings()
    {
        $rules = DB::table('membership_rules')->get();
        return Inertia::render('Membership/Settings/Index', [
            'rules' => $rules,
        ]);
    }

    /**
     * Update Setting Rules Action
     */
    public function updateSettings(Request $request)
    {
        foreach ($request->rules as $key => $value) {
            DB::table('membership_rules')->where('key_name', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        }
        return redirect()->back()->with('success', 'Pengaturan Membership berhasil diperbarui!');
    }
}
