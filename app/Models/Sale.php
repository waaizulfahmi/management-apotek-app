<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'cashier_id',
        'user_id',
        'shift_id',
        'outlet_id',
        'sale_date',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
        'paid_amount',
        'change_amount',
        'payment_method',
        'notes',
        'status',
        'deleted_by',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'change_amount' => 'decimal:2',
    ];

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'cashier_id')->withTrashed();
    }

    public function shift()
    {
        return $this->belongsTo(CashierShift::class, 'shift_id')->withTrashed();
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id')->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function productReturns()
    {
        return $this->hasMany(ProductReturn::class, 'reference_id')->where('type', 'sale');
    }
}
