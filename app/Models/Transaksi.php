<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $fillable = [
        'id_kasir', 'kode_produk', 'jumlah', 'total_harga', 'metode_pembayaran', 'waktu'
    ];

    public function kasir()
    {
        return $this->belongsTo(User::class, 'id_kasir');
    }

    public function obat()
    {
        return $this->belongsTo(Obat::class, 'kode_produk', 'kode');
    }
}
