<?php

namespace App\Support;

use App\Models\AttendanceSession;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

/**
 * Dynamic QR tokens for session attendance.
 *
 * The token is an HMAC bound to a 10-second time window, so a displayed
 * QR dies automatically shortly after the window rolls over. The resolver
 * accepts the current window plus one previous window (10s grace) so a
 * scan started just before rotation — e.g. a late-added participant —
 * is not rejected as expired.
 */
class SessionQr
{
    /**
     * Token lifetime in seconds (one rotation window).
     */
    public const WINDOW_SECONDS = 10;

    /**
     * How many previous windows are still accepted (grace for rotation race).
     */
    public const GRACE_WINDOWS = 1;

    /**
     * Generate a fresh per-start secret for a session.
     */
    public static function newSecret(): string
    {
        return Str::random(32);
    }

    /**
     * Get the token for a session at the given moment.
     */
    public static function tokenFor(AttendanceSession $session, ?int $at = null): ?string
    {
        if (empty($session->qr_secret)) {
            return null;
        }

        $window = intdiv($at ?? now()->timestamp, self::WINDOW_SECONDS);

        return hash_hmac('sha256', $session->qr_secret.'|'.$session->getKey().'|'.$window, (string) config('app.key'));
    }

    /**
     * Get the absolute scan URL encoded in the QR.
     */
    public static function scanUrl(AttendanceSession $session, ?int $at = null): ?string
    {
        $token = self::tokenFor($session, $at);

        if ($token === null) {
            return null;
        }

        return route('attendance.scan', ['token' => $token], absolute: true);
    }

    /**
     * Resolve the active session owning a token for the current window.
     * Accepts the current window plus GRACE_WINDOWS previous windows.
     * Returns null for expired, finished, or unknown tokens.
     */
    public static function resolveActiveSession(string $token): ?AttendanceSession
    {
        $now = now()->timestamp;
        $currentWindow = intdiv($now, self::WINDOW_SECONDS);

        $sessions = AttendanceSession::active()
            ->whereNotNull('qr_secret')
            ->get(['id', 'qr_secret']);

        foreach ($sessions as $session) {
            for ($ago = 0; $ago <= self::GRACE_WINDOWS; $ago++) {
                $windowIndex = $currentWindow - $ago;

                if ($windowIndex < 0) {
                    continue;
                }

                if (hash_equals((string) self::tokenFor($session, $windowIndex * self::WINDOW_SECONDS), $token)) {
                    return AttendanceSession::find($session->getKey());
                }
            }
        }

        return null;
    }

    /**
     * Render a QR code SVG for the given content.
     */
    public static function svg(string $content, int $size = 400): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd);
        $writer = new Writer($renderer);

        return $writer->writeString($content);
    }
}
