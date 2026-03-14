<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            \App\Http\Middleware\SetLocale::class,
            HandleInertiaRequests::class,
            \App\Http\Middleware\SetTeamContext::class,
            \App\Http\Middleware\RedirectIfEmployeePortalUser::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        
        // Exempt webhooks and ZKTeco device routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'webhook/fp',
            'webhook/fp/*',
            'iclock/cdata',
        ]);
        
        $middleware->alias([
            'team.access' => \App\Http\Middleware\EnsureTeamAccess::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'employee.portal' => \App\Http\Middleware\RedirectIfEmployeePortalUser::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
