<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'reward_id',
        'redemption_code',
        'points_used',
        'status',
        'redeemed_at',
        'used_at',
        'expired_at',
    ];

    protected $casts = [
        'points_used' => 'integer',
        'redeemed_at' => 'datetime',
        'used_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function reward()
    {
        return $this->belongsTo(Reward::class, 'reward_id');
    }
}
