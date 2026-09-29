<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'unit_id',
        'price_type',
        'price',
        'is_default',
        'effective_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_default' => 'boolean',
        'effective_date' => 'date',
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
