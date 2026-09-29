<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'unit_id',
        'is_base_unit',
        'is_purchase_unit',
        'is_selling_unit',
        'conversion_factor',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_base_unit' => 'boolean',
        'is_purchase_unit' => 'boolean',
        'is_selling_unit' => 'boolean',
        'conversion_factor' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Obat::class, 'product_id', 'kode');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
