<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipTierHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'previous_tier_id',
        'new_tier_id',
        'reason',
        'created_by',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function previousTier()
    {
        return $this->belongsTo(MembershipTier::class, 'previous_tier_id');
    }

    public function newTier()
    {
        return $this->belongsTo(MembershipTier::class, 'new_tier_id');
    }
}
