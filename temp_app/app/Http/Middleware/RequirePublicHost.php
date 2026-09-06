<?php

namespace App\Http\Middleware;

use App\Support\PublicPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePublicHost
{
    public function __construct(private readonly PublicPortal $portal) {}

    public function handle(Request $request, Closure $next): Response
    {
        $publicHost = $this->portal->host();

        abort_if($publicHost && strcasecmp($request->getHost(), $publicHost) !== 0, 404);

        return $next($request);
    }
}
