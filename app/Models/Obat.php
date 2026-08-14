<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Obat extends Model
{
    use SoftDeletes;

    protected $primaryKey = 'kode';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama',
        'gambar',
        'stok',
        'jenis_obat',
        'kategori',
        'harga',
        'min_stok',
        'max_stok',
        'supplier_id',
        'supplier_name',
        'merk',
        'deleted_by'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function transaksis()
    {
        return $this->hasMany(Transaksi::class, 'kode_produk', 'kode');
    }
}
