<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'key_name',
        'display_name',
        'value',
        'description',
    ];
}
