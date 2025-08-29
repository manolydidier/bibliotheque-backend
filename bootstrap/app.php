<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\CustomAuthenticate;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // ✅ Active le CORS globalement
        $middleware->append(HandleCors::class);

        // ❗ Ne PAS écraser l’alias "auth" par défaut.
        // Si tu as un middleware perso, donne-lui un alias séparé :
        $middleware->alias([
            'custom.auth' => CustomAuthenticate::class,
        ]);

        // Si tu DOIS remplacer "auth", assure-toi que ton CustomAuthenticate
        // étend \Illuminate\Auth\Middleware\Authenticate et supporte les gardes.
        // $middleware->alias(['auth' => CustomAuthenticate::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
