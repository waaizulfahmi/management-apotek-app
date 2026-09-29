<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductOutlet extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_outlets';

    protected $fillable = [
        'obat_id',
        'outlet_id',
        'is_active',
        'price',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'price' => 'float',
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
