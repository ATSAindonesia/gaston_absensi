<?php

namespace App\Actions\Session;

use App\Exceptions\DomainException;
use App\Models\AttendanceSession;

class AddSessionParticipants
{
    /**
     * Attach users to a session, skipping existing and busy ones.
     *
     * @param  list<int>  $userIds
     * @return array{added: list<int>, already: int}
     */
    public function handle(AttendanceSession $session, array $userIds): array
    {
        $existing = $session->participants()->pluck('users.id')->all();
        $fresh = array_values(array_diff(array_map('intval', $userIds), array_map('intval', $existing)));

        $busy = array_intersect($fresh, $session->busyParticipantIds());

        if ($busy !== []) {
            throw new DomainException(__(':count pengguna terpilih bentrok jam dengan sesi lain dan tidak didaftarkan.', ['count' => count($busy)]));
        }

        if ($fresh === []) {
            throw new DomainException(__('Semua pengguna yang dipilih sudah terdaftar di sesi ini.'));
        }

        $session->participants()->syncWithoutDetaching($fresh);

        return [
            'added' => $fresh,
            'already' => count($userIds) - count($fresh),
        ];
    }
}
