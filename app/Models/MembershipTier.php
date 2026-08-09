<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'min_spending',
        'max_spending',
        'point_multiplier',
        'discount_percentage',
        'badge_color',
        'description',
        'is_active',
    ];

    protected $casts = [
        'min_spending' => 'decimal:2',
        'max_spending' => 'decimal:2',
        'point_multiplier' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'tier_id');
    }
}
