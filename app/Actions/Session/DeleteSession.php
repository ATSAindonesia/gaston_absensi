<?php

namespace App\Actions\Session;

use App\Exceptions\DomainException;
use App\Models\AttendanceSession;

class DeleteSession
{
    /**
     * Delete a session. Active sessions must be finished/cancelled first.
     */
    public function handle(AttendanceSession $session): void
    {
        if ($session->isActive()) {
            throw new DomainException(__('Sesi aktif tidak dapat dihapus. Selesaikan atau batalkan terlebih dahulu.'));
        }

        $session->delete();
    }
}
