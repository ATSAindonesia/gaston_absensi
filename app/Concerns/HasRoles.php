<?php

namespace App\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    /**
     * Get the roles assigned to the user.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Determine if the user has any of the given roles.
     *
     * @param  string|list<string>  $roles
     */
    public function hasRole(string|array $roles): bool
    {
        $roleNames = (array) $roles;

        return $this->roles->pluck('name')->intersect($roleNames)->isNotEmpty();
    }

    /**
     * Determine if the user has any of the given roles.
     *
     * @param  list<string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->hasRole($roles);
    }

    /**
     * Determine if the user has all of the given roles.
     *
     * @param  list<string>  $roles
     */
    public function hasAllRoles(array $roles): bool
    {
        return collect($roles)->diff($this->roles->pluck('name'))->isEmpty();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role|string $role): static
    {
        if (is_string($role)) {
            $role = Role::firstOrCreate(
                ['name' => $role],
                ['label' => ucwords(str_replace(['-', '_'], ' ', $role))]
            );
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(Role|string $role): static
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->first();
            if (! $role) {
                return $this;
            }
        }

        $this->roles()->detach($role->id);
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Synchronize the roles assigned to the user.
     *
     * @param  list<Role|string>  $roles
     */
    public function syncRoles(array $roles): static
    {
        $roleIds = [];

        foreach ($roles as $role) {
            if (is_string($role)) {
                $roleModel = Role::firstOrCreate(
                    ['name' => $role],
                    ['label' => ucwords(str_replace(['-', '_'], ' ', $role))]
                );
                $roleIds[] = $roleModel->id;
            } elseif ($role instanceof Role) {
                $roleIds[] = $role->id;
            }
        }

        $this->roles()->sync($roleIds);
        $this->unsetRelation('roles');

        return $this;
    }

    /**
     * Determine if the user has a specific permission via their roles.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roles->flatMap->permissions->pluck('name')->contains($permission);
    }

    /**
     * Determine if the user is a superadmin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Determine if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Determine if the user is an anggota.
     */
    public function isAnggota(): bool
    {
        return $this->hasRole('anggota');
    }

    /**
     * Get the primary role name for display and authorization logic.
     */
    public function primaryRoleName(): string
    {
        if ($this->isSuperAdmin()) {
            return 'superadmin';
        }

        if ($this->isAdmin()) {
            return 'admin';
        }

        if ($this->isAnggota()) {
            return 'anggota';
        }

        return $this->roles->first()?->name ?? 'anggota';
    }

    /**
     * Get the primary role label for UI display.
     */
    public function primaryRoleLabel(): string
    {
        if ($this->isSuperAdmin()) {
            return 'Super Admin';
        }

        if ($this->isAdmin()) {
            return 'Admin';
        }

        if ($this->isAnggota()) {
            return 'Anggota';
        }

        return $this->roles->first()?->label ?? 'Anggota';
    }
}
