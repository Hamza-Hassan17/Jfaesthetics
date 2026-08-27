<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_active',
        'doctor_id',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // belongs to role
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function doctor()
    {
        return $this->belongsTo(doctor::class);
    }

    /**
     * The single source of truth for permission checks. Never compare
     * $user->role->name === 'admin' anywhere else in the app — always go
     * through this (or the `permission` route middleware, which uses it).
     */
    public function hasPermission(string $module, string $action = 'view'): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->hasPermission($module, $action);
    }

    public function hasAnyPermissionFor(string $module): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions->contains(fn ($permission) => $permission->module === $module);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role && $this->role->rank === 0;
    }

    /**
     * The rank-based hierarchy rule from the RBAC spec: this user can
     * manage (edit permissions of, assign, or delete) the given role only
     * if their own role strictly outranks it.
     */
    public function canManageRole(Role $role): bool
    {
        if (!$this->role) {
            return false;
        }

        // Super Admin has unrestricted access (spec Section 3) — including
        // assigning the Super Admin role to another account, which the
        // plain "strictly outranks" rule can't express since two Super
        // Admins are equal rank, not one outranking the other.
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->role->outranks($role);
    }
}
