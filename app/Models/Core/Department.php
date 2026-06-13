<?php

namespace App\Models\Core;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description'])]
class Department extends Model
{
    /** @return HasMany<DepartmentPermission, $this> */
    public function permissions(): HasMany
    {
        return $this->departmentPermissions();
    }

    /** @return HasMany<DepartmentPermission, $this> */
    public function departmentPermissions(): HasMany
    {
        return $this->hasMany(DepartmentPermission::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
