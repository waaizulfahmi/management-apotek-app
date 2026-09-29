<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'obat_id',
        'outlet_id',
        'stock',
        'min_stock',
        'rack_location',
    ];

    protected $casts = [
        'stock' => 'float',
        'min_stock' => 'float',
    ];

    public function obat()
    {
        return $this->belongsTo(Obat::class, 'obat_id', 'kode');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
