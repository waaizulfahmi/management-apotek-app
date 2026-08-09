<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'address',
        'medical_notes',
        'allergies',
        'membership_level',
        'points',
        'total_spending',
        'tier_id',
        'referral_code',
        'referred_by',
        'status',
        'is_active',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'points' => 'integer',
        'total_spending' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function tier()
    {
        return $this->belongsTo(MembershipTier::class, 'tier_id');
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class, 'customer_id')->orderBy('created_at', 'desc');
    }

    public function pointLots()
    {
        return $this->hasMany(PointLot::class, 'customer_id');
    }

    public function rewardRedemptions()
    {
        return $this->hasMany(RewardRedemption::class, 'customer_id')->orderBy('created_at', 'desc');
    }

    public function voucherUsages()
    {
        return $this->hasMany(VoucherUsage::class, 'customer_id')->orderBy('created_at', 'desc');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id')->orderBy('created_at', 'desc');
    }
}
