<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseAdminLayout
{
    /**
     * Use the admin layout for Livewire pages that do not declare
     * their own layout (e.g. settings pages under the admin scope).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        config(['livewire.component_layout' => 'layouts::admin']);

        return $next($request);
    }
}
