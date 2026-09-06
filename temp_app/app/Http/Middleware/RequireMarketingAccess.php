<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketing screens are for marketing and the administrator. The multimedia
 * crew are turned away at the route, not merely hidden from the menu.
 */
class RequireMarketingAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canSeeMarketing(), 403);

        return $next($request);
    }
}
