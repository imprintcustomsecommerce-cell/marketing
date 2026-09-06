<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RequirePasswordChange {
    public function handle(Request $request, Closure $next): Response {
        if ($request->user()?->must_change_password && ! $request->routeIs('admin.account.*', 'admin.logout')) {
            return redirect()->route('admin.account.edit')->with('warning', 'Change the default password before continuing.');
        }
        return $next($request);
    }
}
