<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'point_transaction_id',
        'original_points',
        'remaining_points',
        'earned_at',
        'expired_at',
        'status',
    ];

    protected $casts = [
        'original_points' => 'integer',
        'remaining_points' => 'integer',
        'earned_at' => 'date',
        'expired_at' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
