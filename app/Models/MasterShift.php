<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class MasterShift extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'master_shifts';

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_minutes',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'grace_minutes' => 'integer',
    ];

    /**
     * Check if a given Carbon/string time (or now) is within shift operating hours
     */
    public function isWithinSchedule($time = null): bool
    {
        $now = $time ? Carbon::parse($time) : Carbon::now();
        $currentTimeStr = $now->format('H:i:s');

        $start = Carbon::parse($this->start_time)->format('H:i:s');
        $end = Carbon::parse($this->end_time)->format('H:i:s');

        if ($start <= $end) {
            // Normal daytime shift (e.g., 07:00 - 15:00)
            return $currentTimeStr >= $start && $currentTimeStr <= $end;
        } else {
            // Overnight shift (e.g., 22:00 - 07:00)
            return $currentTimeStr >= $start || $currentTimeStr <= $end;
        }
    }

    /**
     * Get formatted shift schedule string e.g. "07:00 – 15:00 WIB"
     */
    public function getFormattedScheduleAttribute(): string
    {
        $start = Carbon::parse($this->start_time)->format('H:i');
        $end = Carbon::parse($this->end_time)->format('H:i');
        return "{$start} – {$end} WIB";
    }
}
