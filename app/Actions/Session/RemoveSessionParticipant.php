<?php

namespace App\Actions\Session;

use App\Models\AttendanceSession;

class RemoveSessionParticipant
{
    /**
     * Detach a participant from a session (the user record is kept).
     */
    public function handle(AttendanceSession $session, int $userId): void
    {
        $session->participants()->detach($userId);
    }
}
