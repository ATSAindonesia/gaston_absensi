<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;

class SessionPolicy
{
    /**
     * Determine if the user can manage the given room.
     * Superadmin can manage all rooms; admins only their own room.
     */
    public function manage(User $user, AttendanceSession $session): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $session->isOwnedBy($user);
    }
}
