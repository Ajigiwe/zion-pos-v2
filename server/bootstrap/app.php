<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// helpers are ALSO required here on purpose: production deploys carry the
// composer-generated autoload over unchanged, so a deploy that only ADDS
// helper functions would otherwise hit "undefined function" 500s until a
// composer dump-autoload ran on the host. function_exists guards make the
// double load harmless.
require dirname(__DIR__).'/app/Support/helpers.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);

        // multi-shop context: runs after StartSession/auth for every web request
        $middleware->web([\App\Http\Middleware\BootShopContext::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
