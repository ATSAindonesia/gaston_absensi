<?php

namespace App\Actions\Session;

use App\Enums\SessionStatus;
use App\Exceptions\DomainException;
use App\Models\AttendanceSession;

class FinishSession
{
    /**
     * Finish the active session.
     */
    public function handle(AttendanceSession $session): AttendanceSession
    {
        if (! $session->canBeFinished()) {
            throw new DomainException(__('Hanya sesi aktif yang dapat diselesaikan.'));
        }

        $session->update([
            'status' => SessionStatus::Finished,
            'finished_at' => now(),
            'qr_secret' => null,
        ]);

        return $session;
    }
}
