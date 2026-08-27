<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'rank',
        'is_system_role',
    ];

    protected $casts = [
        'is_system_role' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function hasPermission(string $module, string $action): bool
    {
        return $this->permissions->contains(function ($permission) use ($module, $action) {
            return $permission->module === $module && $permission->action === $action;
        });
    }

    /**
     * The rank-based hierarchy rule: a role can only manage (edit
     * permissions of, assign, or delete) roles strictly below it in rank
     * (i.e. a strictly larger rank number = lower authority).
     */
    public function outranks(Role $other): bool
    {
        return $this->rank < $other->rank;
    }
}
