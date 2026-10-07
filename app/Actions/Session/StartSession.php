<?php

namespace App\Actions\Session;

use App\Enums\SessionStatus;
use App\Exceptions\DomainException;
use App\Models\AttendanceSession;
use App\Support\SessionQr;
use Illuminate\Support\Facades\DB;

class StartSession
{
    /**
     * Start a scheduled session. Only one active session is allowed.
     */
    public function handle(AttendanceSession $session): AttendanceSession
    {
        return DB::transaction(function () use ($session): AttendanceSession {
            $locked = AttendanceSession::lockForUpdate()->findOrFail($session->getKey());

            if (! $locked->canBeStarted()) {
                throw new DomainException(__('Hanya sesi berstatus Segera yang dapat dimulai.'));
            }

            if (AttendanceSession::where('id', '!=', $locked->getKey())->active()->lockForUpdate()->exists()) {
                throw new DomainException(__('Masih ada sesi aktif lain. Selesaikan atau batalkan sesi tersebut terlebih dahulu.'));
            }

            $locked->update([
                'status' => SessionStatus::Active,
                'started_at' => now(),
                'qr_secret' => SessionQr::newSecret(),
            ]);

            return $locked;
        });
    }
}
