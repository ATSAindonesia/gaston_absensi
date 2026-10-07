<?php

namespace App\Actions\Session;

use App\Enums\SessionStatus;
use App\Exceptions\DomainException;
use App\Models\AttendanceSession;

class CancelSession
{
    /**
     * Cancel a scheduled/active session.
     */
    public function handle(AttendanceSession $session, ?string $reason): AttendanceSession
    {
        if (! $session->canBeCancelled()) {
            throw new DomainException(__('Sesi ini tidak dapat dibatalkan.'));
        }

        $session->update([
            'status' => SessionStatus::Cancelled,
            'cancel_reason' => $reason,
            'cancelled_at' => now(),
            'qr_secret' => null,
        ]);

        return $session;
    }
}
