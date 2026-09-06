<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Multimedia screens are for the crew and the administrator. Marketing staff
 * are turned away at the route, not merely hidden from the menu.
 */
class RequireMultimediaAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canSeeMultimedia(), 403);

        return $next($request);
    }
}
