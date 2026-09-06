<?php

use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireInternalHost;
use App\Http\Middleware\RequireMarketingAccess;
use App\Http\Middleware\RequireMultimediaAccess;
use App\Http\Middleware\RequirePublicHost;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'internal.host' => RequireInternalHost::class,
            'public.host' => RequirePublicHost::class,
            'admin.only' => RequireAdmin::class,
            'marketing.access' => RequireMarketingAccess::class,
            'multimedia.access' => RequireMultimediaAccess::class,
            'password.changed' => RequirePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
