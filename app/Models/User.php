<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

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
        'access_all_outlets',
        'last_login_at',
        'last_login_ip',
        'failed_login_attempts',
        'locked_until',
        'deleted_by',
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
            'access_all_outlets' => 'boolean',
        ];
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
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
        return $this->belongsToMany(Outlet::class, 'user_outlets')
            ->withPivot(['is_primary'])
            ->whereNull('user_outlets.deleted_at');
    }

    public function hasAccessToAllOutlets(): bool
    {
        if ($this->access_all_outlets) {
            return true;
        }

        $roleStr = strtolower($this->role ?? '');
        if (in_array($roleStr, ['admin', 'owner', 'superadmin', 'super admin', 'manager'])) {
            return true;
        }

        try {
            if (method_exists($this, 'hasAnyRole') && $this->hasAnyRole(['admin', 'owner', 'superadmin', 'super admin', 'Super Admin', 'Admin', 'Manager'])) {
                return true;
            }
        } catch (\Throwable $e) {}

        return false;
    }

    public function hasOutletAccess(int $outletId): bool
    {
        if ($this->hasAccessToAllOutlets()) {
            return true;
        }

        return $this->outlets()->where('outlets.id', $outletId)->exists();
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
