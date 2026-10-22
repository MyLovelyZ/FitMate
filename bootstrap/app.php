<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\CustomerServiceMiddleware;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\SellerMiddleware;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\UserMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserIsNotBanned::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'cs' => CustomerServiceMiddleware::class,
            'seller' => SellerMiddleware::class,
            'superadmin' => SuperAdminMiddleware::class,
            'user' => UserMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
