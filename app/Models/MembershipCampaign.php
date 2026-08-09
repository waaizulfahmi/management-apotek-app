<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'multiplier',
        'bonus_points',
        'start_date',
        'end_date',
        'target_tier',
        'is_active',
    ];

    protected $casts = [
        'multiplier' => 'decimal:2',
        'bonus_points' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];
}
