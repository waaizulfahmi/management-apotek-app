<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MembershipLoyaltySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Tiers
        $tiers = [
            [
                'name' => 'BRONZE',
                'slug' => 'bronze',
                'min_spending' => 0,
                'max_spending' => 999999.99,
                'point_multiplier' => 1.00,
                'discount_percentage' => 0.00,
                'badge_color' => '#94a3b8',
                'description' => 'Member regular baru terdaftar',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'SILVER',
                'slug' => 'silver',
                'min_spending' => 1000000,
                'max_spending' => 4999999.99,
                'point_multiplier' => 1.25,
                'discount_percentage' => 5.00,
                'badge_color' => '#38bdf8',
                'description' => 'Member langganan aktif apotek',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'GOLD',
                'slug' => 'gold',
                'min_spending' => 5000000,
                'max_spending' => 9999999.99,
                'point_multiplier' => 1.50,
                'discount_percentage' => 10.00,
                'badge_color' => '#f59e0b',
                'description' => 'Member VIP apresiasi tinggi',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'PLATINUM',
                'slug' => 'platinum',
                'min_spending' => 10000000,
                'max_spending' => null,
                'point_multiplier' => 2.00,
                'discount_percentage' => 15.00,
                'badge_color' => '#a855f7',
                'description' => 'Member prioritas utama apotek',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($tiers as $tier) {
            DB::table('membership_tiers')->updateOrInsert(
                ['slug' => $tier['slug']],
                $tier
            );
        }

        // 2. Seed Membership Rules
        $rules = [
            ['key_name' => 'point_rate', 'display_name' => 'Nilai Belanja per 1 Poin (Rp)', 'value' => '10000', 'description' => 'Setiap kelipatan Rp X belanja berhak mendapat 1 poin'],
            ['key_name' => 'point_conversion', 'display_name' => 'Nilai Tukar Poin (100 Pts = Rp X)', 'value' => '10000', 'description' => 'Nilai nominal diskon yang didapat dari 100 Pts'],
            ['key_name' => 'min_redeem_points', 'display_name' => 'Minimum Poin Tukar', 'value' => '100', 'description' => 'Batas minimal saldo poin untuk bisa di-redeem'],
            ['key_name' => 'max_redeem_percentage', 'display_name' => 'Maksimum Pemotongan Poin (%)', 'value' => '50', 'description' => 'Persentase maksimal diskon poin dari total belanja'],
            ['key_name' => 'point_expiry_months', 'display_name' => 'Masa Kadaluarsa Poin (Bulan)', 'value' => '12', 'description' => 'Jumlah bulan sebelum poin hangus'],
            ['key_name' => 'welcome_bonus', 'display_name' => 'Bonus Poin Pendaftaran Member Baru', 'value' => '100', 'description' => 'Poin gratis saat member pertama kali mendaftar'],
            ['key_name' => 'birthday_bonus', 'display_name' => 'Bonus Poin Ulang Tahun Member', 'value' => '100', 'description' => 'Bonus poin spesial di hari ulang tahun member'],
            ['key_name' => 'referral_referrer_bonus', 'display_name' => 'Bonus Poin Pengajak (Referrer)', 'value' => '100', 'description' => 'Bonus poin untuk member lama yang mengajak teman'],
            ['key_name' => 'referral_referred_bonus', 'display_name' => 'Bonus Poin Diajak (Referred Member)', 'value' => '50', 'description' => 'Bonus poin untuk member baru dari kode referral'],
        ];

        foreach ($rules as $rule) {
            DB::table('membership_rules')->updateOrInsert(
                ['key_name' => $rule['key_name']],
                array_merge($rule, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // 3. Seed Sample Rewards & Vouchers
        DB::table('rewards')->updateOrInsert(
            ['name' => 'Voucher Diskon Rp 10.000'],
            [
                'description' => 'Tukarkan 100 Poin untuk potongan langsung Rp 10.000 di Kasir POS',
                'required_points' => 100,
                'reward_type' => 'DISCOUNT_AMOUNT',
                'reward_value' => 10000,
                'stock' => 500,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('rewards')->updateOrInsert(
            ['name' => 'Voucher Diskon Rp 50.000'],
            [
                'description' => 'Tukarkan 500 Poin untuk potongan langsung Rp 50.000 di Kasir POS',
                'required_points' => 500,
                'reward_type' => 'DISCOUNT_AMOUNT',
                'reward_value' => 50000,
                'stock' => 200,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addYear()->toDateString(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('vouchers')->updateOrInsert(
            ['code' => 'MEMBER50K'],
            [
                'name' => 'Voucher Promo Member Rp 50.000',
                'type' => 'DISCOUNT_AMOUNT',
                'value' => 50000,
                'min_purchase' => 200000,
                'max_discount' => 50000,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'usage_limit_total' => 1000,
                'usage_limit_per_member' => 2,
                'applicable_tier' => 'ALL',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Assign default Bronze tier_id to existing customers
        $bronze = DB::table('membership_tiers')->where('slug', 'bronze')->first();
        if ($bronze) {
            DB::table('customers')->whereNull('tier_id')->update(['tier_id' => $bronze->id]);
        }
    }
}
