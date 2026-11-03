<?php

namespace App\Models\UserAccess;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    protected $fillable = ['permission_group_id', 'name', 'guard_name', 'label', 'description'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'permission_group_id');
    }

}
