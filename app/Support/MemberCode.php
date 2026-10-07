<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Generate sequential unique member codes (GSTN-000001, GSTN-000002, ...).
 */
class MemberCode
{
    public const PREFIX = 'GSTN-';

    /**
     * Generate the next available member code.
     */
    public static function generate(): string
    {
        return DB::transaction(function (): string {
            $last = DB::table('users')
                ->whereNotNull('member_code')
                ->orderByDesc('member_code')
                ->lockForUpdate()
                ->value('member_code');

            $next = $last !== null ? ((int) substr($last, strlen(self::PREFIX))) + 1 : 1;

            return sprintf('%s%06d', self::PREFIX, $next);
        });
    }
}
