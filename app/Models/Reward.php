<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'required_points',
        'reward_type',
        'reward_value',
        'stock',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'required_points' => 'integer',
        'reward_value' => 'decimal:2',
        'stock' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function redemptions()
    {
        return $this->hasMany(RewardRedemption::class, 'reward_id');
    }
}
