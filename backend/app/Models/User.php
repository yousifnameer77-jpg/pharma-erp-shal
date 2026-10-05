<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'branch_id', 'username', 'email', 'password', 'full_name', 'phone', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Convenience read relation: the distinct roles a user holds, regardless of
     * scope. Use userRoles() instead when the branch/warehouse scope matters.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['id', 'branch_id', 'warehouse_id'])
            ->withTimestamps();
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Whether this user holds $permissionCode through any role assignment that
     * covers the given branch/warehouse (an assignment with a NULL scope column
     * covers every branch/warehouse). Super Admin always passes.
     */
    public function hasPermission(string $permissionCode, ?string $branchId = null, ?string $warehouseId = null): bool
    {
        if ($this->hasRole('Super Admin')) {
            return true;
        }

        $roleIds = $this->userRoles()
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where(fn ($q) => $q->whereNull('warehouse_id')->orWhere('warehouse_id', $warehouseId))
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return false;
        }

        return Role::whereIn('id', $roleIds)
            ->whereHas('permissions', fn ($q) => $q->where('code', $permissionCode))
            ->exists();
    }
}
