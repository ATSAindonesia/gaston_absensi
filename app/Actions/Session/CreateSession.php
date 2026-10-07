<?php

namespace App\Actions\Session;

use App\Enums\SessionStatus;
use App\Exceptions\DomainException;
use App\Models\AttendanceSession;
use App\Models\User;

class CreateSession
{
    /**
     * Create a new session as "soon".
     *
     * @param  array{name: string, sport_id: int, court_id: int, starts_at: string, ends_at: string}  $data
     */
    public function handle(array $data, User $creator): AttendanceSession
    {
        if (AttendanceSession::active()->exists()) {
            throw new DomainException(__('Masih ada sesi aktif. Selesaikan atau batalkan sesi yang berjalan sebelum membuat sesi baru.'));
        }

        return AttendanceSession::create([
            'name' => $data['name'],
            'sport_id' => $data['sport_id'],
            'court_id' => $data['court_id'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'status' => SessionStatus::Soon,
            'created_by' => $creator->getKey(),
        ]);
    }
}
