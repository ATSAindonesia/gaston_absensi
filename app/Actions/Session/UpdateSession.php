<?php

namespace App\Actions\Session;

use App\Exceptions\DomainException;
use App\Models\AttendanceSession;
use Illuminate\Support\Facades\DB;

class UpdateSession
{
    /**
     * Update a non-terminal session.
     *
     * @param  array{name: string, sport_id: int, court_id: int, starts_at: string, ends_at: string}  $data
     */
    public function handle(AttendanceSession $session, array $data): AttendanceSession
    {
        return DB::transaction(function () use ($session, $data): AttendanceSession {
            $locked = AttendanceSession::lockForUpdate()->findOrFail($session->getKey());

            if ($locked->status->isTerminal()) {
                throw new DomainException(__('Sesi yang sudah selesai atau dibatalkan tidak dapat diubah.'));
            }

            $locked->update([
                'name' => $data['name'],
                'sport_id' => $data['sport_id'],
                'court_id' => $data['court_id'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
            ]);

            return $locked;
        });
    }
}
