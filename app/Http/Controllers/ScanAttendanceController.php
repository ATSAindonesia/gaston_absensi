<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Support\SessionQr;
use Illuminate\Http\Request;

class ScanAttendanceController extends Controller
{
    /**
     * Record attendance from a scanned dynamic QR token.
     */
    public function __invoke(Request $request, string $token)
    {
        $session = SessionQr::resolveActiveSession($token);

        if (! $session) {
            return response()->view('attendance.expired', status: 410);
        }

        $user = $request->user();

        if (! $session->participants()->where('users.id', $user->getKey())->exists()) {
            return response()->view('attendance.forbidden', ['session' => $session], 403);
        }

        $attendance = Attendance::firstOrCreate(
            [
                'attendance_session_id' => $session->getKey(),
                'user_id' => $user->getKey(),
            ],
            ['scanned_at' => now()]
        );

        return view('attendance.success', [
            'session' => $session->load('creator'),
            'attendance' => $attendance,
            'already' => ! $attendance->wasRecentlyCreated,
        ]);
    }
}
