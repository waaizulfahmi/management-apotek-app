<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'start_date',
        'end_date',
        'usage_limit_total',
        'usage_limit_per_member',
        'applicable_tier',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'usage_limit_total' => 'integer',
        'usage_limit_per_member' => 'integer',
        'is_active' => 'boolean',
    ];

    public function usages()
    {
        return $this->hasMany(VoucherUsage::class, 'voucher_id');
    }
}
