<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Core\Department;
use Database\Factories\UserFactory;
use Iankibet\Shbackend\App\Repositories\RoleRepository;
use Iankibet\Shbackend\App\Traits\HasShPermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'role', 'department_id', 'password', 'google_id', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'google_id'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasShPermission, Notifiable, PasskeyAuthenticatable;

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function withResolvedPermissions(): static
    {
        $permissions = RoleRepository::isDepartmentScopedUser($this)
            ? RoleRepository::getDepartmentPermissions($this->department_id)
            : RoleRepository::getRolePermissions($this->role);

        $this->setAttribute('permissions', array_values(array_unique($permissions)));

        return $this;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
