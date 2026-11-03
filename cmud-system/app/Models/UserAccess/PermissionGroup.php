<?php

namespace App\Models\UserAccess;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PermissionGroup extends Model
{
    protected $fillable = ['name', 'description'];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'permission_group_id');
    }
}
