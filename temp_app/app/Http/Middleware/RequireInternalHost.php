<?php

namespace App\Http\Middleware;

use App\Support\PublicPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireInternalHost
{
    public function __construct(private readonly PublicPortal $portal) {}

    public function handle(Request $request, Closure $next): Response
    {
        $publicHost = $this->portal->host();

        abort_if($publicHost && strcasecmp($request->getHost(), $publicHost) === 0, 404);

        $internalHosts = config('imprint.internal_hosts', []);
        abort_if($internalHosts && ! in_array(strtolower($request->getHost()), array_map('strtolower', $internalHosts), true), 404);

        return $next($request);
    }
}
