<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Support\SessionQr;
use Illuminate\Http\Request;

class SessionQrController extends Controller
{
    /**
     * Show the standalone QR page (embedded via iframe in the preview modal).
     */
    public function show(Request $request, AttendanceSession $session)
    {
        $user = $request->user();

        if (! $session->isActive() || (! $user->isSuperAdmin() && ! $session->isOwnedBy($user))) {
            abort(404);
        }

        $url = SessionQr::scanUrl($session);

        if ($url === null) {
            abort(404);
        }

        return view('attendance.qr', [
            'svg' => SessionQr::svg($url),
        ]);
    }
}
