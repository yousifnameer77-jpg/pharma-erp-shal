<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single scoped role assignment: user X has role Y, optionally limited to one
 * branch and/or one warehouse. NULL branch_id/warehouse_id means the role applies
 * company-wide / branch-wide respectively. Modelled as a plain Eloquent model
 * (not a bare pivot) because it carries its own id and is queried directly by
 * User::hasPermission().
 */
class UserRole extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'role_id', 'branch_id', 'warehouse_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
