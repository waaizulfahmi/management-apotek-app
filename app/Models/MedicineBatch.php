<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicineBatch extends Model
{
    use HasFactory;

    protected $table = 'medicine_batches';

    protected $guarded = ['id'];

    public function medicine()
    {
        return $this->belongsTo(Obat::class, 'medicine_id', 'kode');
    }
}
