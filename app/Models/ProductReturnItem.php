<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReturnItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public function productReturn()
    {
        return $this->belongsTo(ProductReturn::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Obat::class, 'medicine_id', 'kode');
    }

    public function batch()
    {
        return $this->belongsTo(MedicineBatch::class, 'batch_id');
    }
}
