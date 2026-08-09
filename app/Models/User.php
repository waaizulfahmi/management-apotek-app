<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'profil',
        'no_hp',
        'role',
        'status',
        'outlet_id',
        'last_login_at',
        'last_login_ip',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'failed_login_attempts' => 'integer',
        ];
    }

    public function transaksis()
    {
        return $this->hasMany(Transaksi::class, 'id_kasir');
    }

    public function primaryOutlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function outlets()
    {
        return $this->belongsToMany(Outlet::class, 'user_outlets');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class, 'user_id')->orderBy('created_at', 'desc');
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }
}
