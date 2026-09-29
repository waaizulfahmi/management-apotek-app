<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'obat_id',
        'qty_requested',
        'qty_sent',
        'qty_received',
        'unit',
        'notes',
    ];

    protected $casts = [
        'qty_requested' => 'float',
        'qty_sent' => 'float',
        'qty_received' => 'float',
    ];

    public function transfer()
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function obat()
    {
        return $this->belongsTo(Obat::class, 'obat_id', 'kode');
    }
}
