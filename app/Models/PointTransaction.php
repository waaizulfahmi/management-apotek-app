<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'transaction_type',
        'reference_type',
        'reference_id',
        'points_in',
        'points_out',
        'balance_before',
        'balance_after',
        'expired_at',
        'description',
        'created_by',
    ];

    protected $casts = [
        'points_in' => 'integer',
        'points_out' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'expired_at' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
