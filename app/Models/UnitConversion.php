<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitConversion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'parent_unit_id',
        'child_unit_id',
        'conversion_rate',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'conversion_rate' => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Obat::class, 'product_id', 'kode');
    }

    public function parentUnit()
    {
        return $this->belongsTo(Unit::class, 'parent_unit_id');
    }

    public function childUnit()
    {
        return $this->belongsTo(Unit::class, 'child_unit_id');
    }
}
