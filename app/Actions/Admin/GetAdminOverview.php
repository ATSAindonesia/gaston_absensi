<?php

namespace App\Actions\Admin;

use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GetAdminOverview
{
    /**
     * Get admin overview statistics.
     *
     * @return array{total_sessions: int, total_users: int, total_participants: int}
     */
    public function stats(): array
    {
        return [
            'total_sessions' => AttendanceSession::count(),
            'total_users' => User::count(),
            'total_participants' => DB::table('attendance_session_user')->count(),
        ];
    }

    /**
     * Get the currently active session, if any.
     */
    public function activeSession(): ?AttendanceSession
    {
        return AttendanceSession::with(['creator', 'sport', 'court', 'participants'])
            ->withCount('participants')
            ->active()
            ->latest()
            ->first();
    }

    /**
     * Get upcoming (scheduled) sessions.
     *
     * @return Collection<int, AttendanceSession>
     */
    public function upcomingSessions(): Collection
    {
        return AttendanceSession::with(['creator', 'sport', 'court'])
            ->withCount('participants')
            ->where('status', SessionStatus::Soon)
            ->orderBy('starts_at')
            ->limit(5)
            ->get();
    }
}
