<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Obat extends Model
{
    protected $primaryKey = 'kode';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'kode', 'nama', 'gambar', 'stok', 'jenis_obat', 'kategori', 'harga'
    ];

    public function transaksis()
    {
        return $this->hasMany(Transaksi::class, 'kode_produk', 'kode');
    }
}
