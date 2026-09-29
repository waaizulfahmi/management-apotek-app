<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'address',
        'province',
        'city',
        'district',
        'postal_code',
        'phone',
        'email',
        'pic_name',
        'is_main',
        'status',
        'is_active',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_outlets');
    }

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class, 'outlet_id');
    }

    public function transfersFrom()
    {
        return $this->hasMany(StockTransfer::class, 'from_outlet_id');
    }

    public function transfersTo()
    {
        return $this->hasMany(StockTransfer::class, 'to_outlet_id');
    }
}
