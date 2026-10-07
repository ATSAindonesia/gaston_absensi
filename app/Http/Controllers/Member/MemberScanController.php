<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Support\SessionQr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberScanController extends Controller
{
    /**
     * Record attendance from a QR payload scanned by a member.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['nullable', 'string', 'max:255', 'required_without:scan_url'],
            'scan_url' => ['nullable', 'string', 'max:2048', 'required_without:token'],
        ]);

        $token = $this->extractToken(
            $validated['token'] ?? null,
            $validated['scan_url'] ?? null
        );

        if ($token === null || $token === '') {
            return response()->json([
                'ok' => false,
                'reason' => 'invalid',
                'message' => 'QR tidak valid.',
            ], 422);
        }

        $session = SessionQr::resolveActiveSession($token);

        if (! $session) {
            return response()->json([
                'ok' => false,
                'reason' => 'expired',
                'message' => 'QR kedaluwarsa. Minta QR terbaru.',
            ], 410);
        }

        $user = $request->user();

        if (! $session->participants()->where('users.id', $user->getKey())->exists()) {
            return response()->json([
                'ok' => false,
                'reason' => 'forbidden',
                'message' => 'Anda tidak terdaftar pada sesi ini.',
            ], 403);
        }

        $attendance = Attendance::firstOrCreate(
            [
                'attendance_session_id' => $session->getKey(),
                'user_id' => $user->getKey(),
            ],
            ['scanned_at' => now()]
        );

        return response()->json([
            'ok' => true,
            'already' => ! $attendance->wasRecentlyCreated,
            'session' => [
                'id' => $session->getKey(),
                'name' => $session->name,
            ],
            'scanned_at' => $attendance->scanned_at?->toISOString(),
        ]);
    }

    /**
     * Extract the raw token from a token string or a full scan URL.
     */
    private function extractToken(?string $token, ?string $scanUrl): ?string
    {
        if (is_string($token) && trim($token) !== '') {
            // Allow pasting a full URL into the token field as well.
            if (str_contains($token, '/')) {
                return $this->extractToken(null, $token);
            }

            return trim($token);
        }

        if (! is_string($scanUrl) || trim($scanUrl) === '') {
            return null;
        }

        $path = parse_url(trim($scanUrl), PHP_URL_PATH) ?? trim($scanUrl);
        $segments = array_values(array_filter(explode('/', (string) $path)));

        $candidate = end($segments);

        return $candidate === false ? null : trim((string) $candidate);
    }
}
