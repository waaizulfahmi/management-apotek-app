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
        'satuan_dasar_id',
        'satuan_pembelian_id',
        'satuan_penjualan_id',
        'deleted_by'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function productOutlets()
    {
        return $this->hasMany(ProductOutlet::class, 'obat_id', 'kode');
    }

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'product_outlets', 'obat_id', 'outlet_id')->whereNull('product_outlets.deleted_at');
    }

    public function productStocks()
    {
        return $this->hasMany(ProductStock::class, 'obat_id', 'kode');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function transaksis()
    {
        return $this->hasMany(Transaksi::class, 'kode_produk', 'kode');
    }

    public function satuanDasar()
    {
        return $this->belongsTo(Unit::class, 'satuan_dasar_id');
    }

    public function satuanPembelian()
    {
        return $this->belongsTo(Unit::class, 'satuan_pembelian_id');
    }

    public function satuanPenjualan()
    {
        return $this->belongsTo(Unit::class, 'satuan_penjualan_id');
    }

    public function productUnits()
    {
        return $this->hasMany(ProductUnit::class, 'product_id', 'kode');
    }

    public function conversions()
    {
        return $this->hasMany(UnitConversion::class, 'product_id', 'kode');
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class, 'product_id', 'kode');
    }

    public function priceHistories()
    {
        return $this->hasMany(PriceHistory::class, 'product_id', 'kode');
    }
}
