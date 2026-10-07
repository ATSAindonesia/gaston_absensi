<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminsToAdminArea
{
    /**
     * Keep admins and superadmins inside the admin area.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasAnyRole(['admin', 'superadmin'])) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
