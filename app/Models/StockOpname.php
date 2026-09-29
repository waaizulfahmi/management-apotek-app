<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'opname_date' => 'date',
        'completed_at' => 'datetime',
        'system_total_value' => 'decimal:2',
        'physical_total_value' => 'decimal:2',
        'difference_value' => 'decimal:2',
    ];

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id')->withTrashed();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class, 'stock_opname_id');
    }
}
