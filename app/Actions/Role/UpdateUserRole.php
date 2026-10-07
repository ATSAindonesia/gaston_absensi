<?php

namespace App\Actions\Role;

use App\Exceptions\DomainException;
use App\Models\User;

class UpdateUserRole
{
    /**
     * Update a user's role. Only superadmin may grant the admin role.
     */
    public function handle(User $actor, User $target, string $newRole): void
    {
        if (! in_array($newRole, ['superadmin', 'admin', 'anggota'], true)) {
            throw new DomainException(__('Pilihan role tidak valid.'));
        }

        if ($target->is($actor) && $newRole !== 'superadmin') {
            throw new DomainException(__('Anda tidak dapat mencabut role Superadmin dari akun Anda sendiri.'));
        }

        $target->syncRoles([$newRole]);
    }
}
